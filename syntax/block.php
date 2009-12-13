<?php

/**
 * Plugin QnA: Block syntax
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'qna/info.php');

class syntax_plugin_qna_block extends DokuWiki_Syntax_Plugin {

    private $mode;
    private $questionId;
    private $maxIdLength;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
        $this->questionId = array();
        $this->maxIdLength = 30;
    }

    /**
     *
     */
    public function getInfo() {
        return qna_getInfo('block syntax');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'container';
    }

    public function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort() {
        return 55;
    }

    /**
     *
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\n\?{3}.*?(?=\n)', $mode, $this->mode);
        $this->Lexer->addSpecialPattern('\n!{3}', $mode, $this->mode);
    }

    /**
     *
     */
    public function handle($match, $state, $pos, $handler) {
        if ($state == DOKU_LEXER_SPECIAL) {
            if ($match{1} == '?') {
                $question = trim(substr($match, 4));

                if ($question != '') {
                    $identifier = $this->questionToIdentifier($question);

                    $data = array('open_question', $question, $identifier);
                }
                else {
                    $data = array('close_block');
                }
            }
            else {
                $data = array('open_answer');
            }
        }
        else {
            $data = false;
        }

        return $data;
    }

    /**
     *
     */
    public function render($mode, $renderer, $data) {
        if ($mode == 'xhtml') {
            list($tag, $style) = explode('_', $data[0]);

            if ($tag == 'open') {
                $renderer->doc .= '<div class="qna-' . $style . '">' . DOKU_LF;

                if ($style == 'question') {
                    $renderer->doc .= '<div class="qna-title">';
                    $renderer->doc .= '<a name="' . $data[2] . '">';
                    $renderer->doc .= $data[1] . '</a></div>' . DOKU_LF;
                }
            }
            else {
                $renderer->doc .= '</div>' . DOKU_LF;
            }

            return true;
        }
        elseif ($mode == 'metadata') {
            if ($data[0] == 'open_question') {
                $meta['title'] = $data[1];
                $meta['id'] = $data[2];
                $meta['level'] = $data[3];
                $meta['class'] = 'question';

                $renderer->meta['description']['tableofquestions'][] = $meta;
            }

            return true;
        }

        return false;
    }

    /**
     * Convert a question title to unique identifier
     */
    private function questionToIdentifier($title) {
        $identifier = str_replace(':', '', cleanID($title));
        $identifier = ltrim($identifier, '0123456789._-');

        if (strlen($identifier) > $this->maxIdLength) {
            $identifier = substr($identifier, 0, $this->maxIdLength);
        }

        if (isset($this->questionId[$identifier])) {
            $identifier .= '_' . ++$this->questionId[$identifier];
        }
        else {
            $this->questionId[$identifier] = 1;
        }

        return $identifier;
    }
}
