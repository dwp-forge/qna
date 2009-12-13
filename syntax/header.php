<?php

/**
 * Plugin QnA: Header syntax
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'qna/info.php');

class syntax_plugin_qna_header extends DokuWiki_Syntax_Plugin {

    private $mode;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
    }

    /**
     *
     */
    public function getInfo() {
        return qna_getInfo('header syntax');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'baseonly';
    }

    public function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort() {
        return 50;
    }

    /**
     *
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('[ \t]*=\?={1,4}[^=\n][^\n]+={3,}[ \t]*(?=\n)', $mode, $this->mode);
    }

    /**
     *
     */
    function handle($match, $state, $pos, &$handler) {
        if ($state == DOKU_LEXER_SPECIAL) {
            $match = preg_replace('/^(\s*=)\?/', '$1=', $match);

            $handler->header($match, $state, $pos);

            $data = array('dummy');
        }
        else {
            $data = false;
        }

        return $data;
    }

    /**
     *
     */
    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            switch ($data[0]) {
                case 'open':
                    $renderer->doc .= DOKU_LF . '<div class="qna-header">';
                    break;

                case 'close':
                    $renderer->doc .= '</div>' . DOKU_LF;
                    break;
            }

            return true;
        }
        elseif ($mode == 'metadata') {
            if ($data[0] == 'open') {
                $meta['title'] = $data[1];
                $meta['id'] = $data[2];
                $meta['level'] = $data[3];

                $renderer->meta['description']['tableofquestions'][] = $meta;
            }

            return true;
        }

        return false;
    }
}
