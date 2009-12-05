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

    public function __construct() {
        $this->mode = substr(get_class($this), 7);
    }

    public function getInfo() {
        return qna_getInfo('block syntax');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'substition';
    }

    /**
     * What modes are allowed within our mode?
     */
    public function getAllowedTypes() {
        return array ();
    }

    /**
     * Where to sort in?
     */
    public function getSort() {
        return 55;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('^\?{3}.*?\n', $mode, $this->mode);
        $this->Lexer->addSpecialPattern('^!{3}', $mode, $this->mode);
    }

    public function handle($match, $state, $pos, &$handler) {
        if ($state == DOKU_LEXER_SPECIAL) {
            if ($match{0} == '?') {
                $question = trim(substr($match, 3));

                if ($question != '') {
                    $data = array('open_question', $question);
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

    public function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            list($tag, $style) = explode('_', $data[0]);

            if ($tag == 'open') {
                $renderer->doc .= '<div style="qna-' . $style . '">' . DOKU_LF;

                if ($style == 'question') {
                    $renderer->doc .= '<div style="qna-title">' . $data[1] . '</div>' . DOKU_LF;
                }
            }
            else {
                $renderer->doc .= '</div>' . DOKU_LF;
            }

            return true;
        }

        return false;
    }
}
