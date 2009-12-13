<?php

/**
 * Plugin QnA: Layout parser
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');
require_once(DOKU_PLUGIN . 'qna/info.php');

class action_plugin_qna extends DokuWiki_Action_Plugin {

    const STATE_CLOSED   = 0;
    const STATE_QUESTION = 1;
    const STATE_ANSWER   = 2;

    private $rewriter;
    private $blockState;
    private $headerIndex;
    private $headerTitle;
    private $headerLevel;
    private $headerId;

    /**
     * Return some info
     */
    public function getInfo() {
        return qna_getInfo('layout parser');
    }

    /**
     * Register callbacks
     */
    public function register($controller) {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'afterParserHandlerDone');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'beforeParserCacheUse');
    }

    /**
     *
     */
    public function afterParserHandlerDone($event, $param) {
        $this->reset();
        $this->fixLayout($event);
    }

    /**
     * Reset internal state
     */
    private function reset() {
        $this->rewriter = new qna_instruction_rewriter();
        $this->blockState = self::STATE_CLOSED;
        $this->headerIndex = -1;
        $this->headerTitle = '';
        $this->headerLevel = 0;
        $this->headerId = array();
    }

    /**
     * Insert implicit instructions
     */
    private function fixLayout($event) {
        $instructions = count($event->data->calls);
        for ($i = 0; $i < $instructions; $i++) {
            $instruction = $event->data->calls[$i];

            switch ($instruction[0]) {
                case 'header':
                    $this->headerIndex = $i;
                    $this->headerTitle = $instruction[1][0];
                    $this->headerLevel = $instruction[1][1];
                    sectionID($instruction[1][0], $this->headerId);
                    /* Fall through */

                case 'section_close':
                case 'section_edit':
                case 'section_open':
                    if ($this->blockState != self::STATE_CLOSED) {
                        $this->rewriter->insertBlockCall($i, 'close_block', 2);
                        $this->blockState = self::STATE_CLOSED;
                    }
                    break;

                case 'plugin':
                    switch ($instruction[1][0]) {
                        case 'qna_block':
                            $this->handlePluginQnaBlock($i, $instruction[1][1]);
                            break;

                        case 'qna_header':
                            $this->handlePluginQnaHeader($i);
                            break;
                    }
                    break;
            }
        }

        if ($this->blockState != self::STATE_CLOSED) {
            $this->rewriter->appendBlockCall('close_block', 2);
        }

        $this->rewriter->apply($event->data->calls);
    }

    /**
     * Insert implicit instructions
     */
    private function handlePluginQnaBlock($index, $data) {
        switch ($data[0]) {
            case 'open_question':
                if ($this->blockState != self::STATE_CLOSED) {
                    $this->rewriter->insertBlockCall($index, 'close_block', 2);
                }

                $this->rewriter->insertBlockCall($index, 'open_block');
                $this->rewriter->setQuestionLevel($index, $this->headerLevel + 1);
                $this->blockState = self::STATE_QUESTION;
                break;

            case 'open_answer':
                switch ($this->blockState) {
                    case self::STATE_CLOSED:
                        $this->rewriter->delete($index);
                        break;

                    case self::STATE_QUESTION:
                    case self::STATE_ANSWER:
                        $this->rewriter->insertBlockCall($index, 'close_block');
                        $this->blockState = self::STATE_ANSWER;
                        break;
                }
                break;

            case 'close_block':
                switch ($this->blockState) {
                    case self::STATE_CLOSED:
                        $this->rewriter->delete($index);
                        break;

                    case self::STATE_QUESTION:
                    case self::STATE_ANSWER:
                        $this->rewriter->insertBlockCall($index, 'close_block');
                        $this->blockState = self::STATE_CLOSED;
                        break;
                }
                break;
        }
    }

    /**
     * Wrap the last header
     */
    private function handlePluginQnaHeader($index) {
        /* On a clean install the distance between the header instruction and qna_header dummy
           sould be 2 (one section_open in between). Allowing distance to be in the range from
           1 to 3 gives some flexibility for better compatibility with other plugins that might
           rearrange instructions around the header. */
        if (($index - $this->headerIndex) < 4) {
            $data[0] ='open';
            $data[1] = $this->headerTitle;
            $data[2] = end($this->headerId);
            $data[3] = $this->headerLevel;

            $this->rewriter->insertHeaderCall($this->headerIndex, $data);
            $this->rewriter->insertHeaderCall($this->headerIndex + 1, 'close');
        }

        $this->rewriter->delete($index);
    }

    /**
     *
     */
    public function beforeParserCacheUse($event, $param) {
        global $ID;

        $cache = $event->data;

        if (isset($cache->mode) && ($cache->mode == 'xhtml')) {
            $depends = p_get_metadata($ID, 'relation depends');

            if (!empty($depends) && isset($depends['rendering'])) {
                $this->addDependencies($cache, array_keys($depends['rendering']));
            }
        }
    }

    /**
     * Add extra dependencies to the cache
     */
    private function addDependencies($cache, $depends) {
        foreach ($depends as $file) {
            if (!in_array($file, $cache->depends['files']) && file_exists($file)) {
                $cache->depends['files'][] = $file;
            }
        }
    }
}

class qna_instruction_rewriter {

    const DELETE = 1;
    const INSERT = 2;
    const SET_LEVEL = 3;

    private $correction;

    /**
     * Constructor
     */
    public function __construct() {
        $this->correction = array();
    }

    /**
     * Remove instruction at $index
     */
    public function delete($index) {
        $this->correction[$index][] = array(self::DELETE);
    }

    /**
     * Insert a plugin call in front of instruction at $index
     */
    public function insertPluginCall($index, $name, $data, $state, $text = '') {
        $this->correction[$index][] = array(self::INSERT, array('plugin', array($name, $data, $state, $text)));
    }

    /**
     * Insert qna_block plugin call in front of instruction at $index
     */
    public function insertBlockCall($index, $data, $repeat = 1) {
        for ($i = 0; $i < $repeat; $i++) {
            $this->insertPluginCall($index, 'qna_block', array($data), DOKU_LEXER_SPECIAL);
        }
    }

    /**
     * Insert qna_header plugin call in front of instruction at $index
     */
    public function insertHeaderCall($index, $data) {
        if (!is_array($data)) {
            $data = array($data);
        }

        $this->insertPluginCall($index, 'qna_header', $data, DOKU_LEXER_SPECIAL);
    }

    /**
     * Append a plugin call at the end of the instruction list
     */
    public function appendPluginCall($name, $data, $state, $text = '') {
        $this->correction[-1][] = array(self::INSERT, array('plugin', array($name, $data, $state, $text)));
    }

    /**
     * Append qna_block plugin call at the end of the instruction list
     */
    public function appendBlockCall($data, $repeat = 1) {
        for ($i = 0; $i < $repeat; $i++) {
            $this->appendPluginCall('qna_block', array($data), DOKU_LEXER_SPECIAL);
        }
    }

    /**
     * Set open_question list level for TOC
     */
    public function setQuestionLevel($index, $level) {
        $this->correction[$index][] = array(self::SET_LEVEL, $level);
    }

    /**
     * Apply the corrections
     */
    public function apply(&$instruction) {
        if (count($this->correction) > 0) {
            $index = $this->getCorrectionIndex();
            $corrections = count($index);
            $instructions = count($instruction);
            $output = array();

            for ($c = 0, $i = 0; $c < $corrections; $c++, $i++) {
                /* Copy all instructions, which are ahead of the next correction */
                for ( ; $i < $index[$c]; $i++) {
                    $output[] = $instruction[$i];
                }

                $this->applyCorrections($i, $instruction, $output);
            }

            /* Copy the rest of instructions after the last correction */
            for ( ; $i < $instructions; $i++) {
                $output[] = $instruction[$i];
            }

            /* Handle appends */
            if (array_key_exists(-1, $this->correction)) {
                $this->applyAppend($output);
            }

            $instruction = $output;
        }
    }

    /**
     * Sort corrections on instruction index, remove appends
     */
    private function getCorrectionIndex() {
        $result = array_keys($this->correction);
        asort($result);
        $result = array_values($result);

        /* Remove appends */
        if ($result[0] == -1) {
            array_shift($result);
        }

        return $result;
    }

    /**
     * Apply corrections at $index
     */
    private function applyCorrections($index, $input, &$output) {
        $delete = false;
        $position = $input[$index][2];

        foreach ($this->correction[$index] as $correction) {
            switch ($correction[0]) {
                case self::DELETE:
                    $delete = true;
                    break;

                case self::INSERT:
                    $output[] = array($correction[1][0], $correction[1][1], $position);
                    break;

                case self::SET_LEVEL:
                    if (($input[$index][0] == 'plugin') && ($input[$index][1][0] == 'qna_block') && ($input[$index][1][1][0] == 'open_question')) {
                        $input[$index][1][1][3] = $correction[1];
                    }
                    break;
            }
        }

        if (!$delete) {
            $output[] = $input[$index];
        }
    }

    /**
     *
     */
    private function applyAppend(&$output) {
        $lastCall = end($output);
        $position = $lastCall[2];

        foreach ($this->correction[-1] as $correction) {
            switch ($correction[0]) {
                case self::INSERT:
                    $output[] = array($correction[1][0], $correction[1][1], $position);
                    break;
            }
        }
    }
}