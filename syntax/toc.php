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

class syntax_plugin_qna_toc extends DokuWiki_Syntax_Plugin {

    private $mode;

    /**
     * Construct
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
    }

    /**
     *
     */
    public function getInfo() {
        return qna_getInfo('TOC syntax');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
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

    /**
     *
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~(?:QNA|FAQ)[^\n]*?~~', $mode, $this->mode);
    }

    /**
     *
     */
    public function handle($match, $state, $pos, $handler) {
        global $ID;

        if ($state == DOKU_LEXER_SPECIAL) {
            preg_match('/~~(?:QNA|FAQ)(.*?)~~/', $match, $match);
            $data = preg_split('/\s+/', $match[1], -1, PREG_SPLIT_NO_EMPTY);
            $ns = getNS($ID);

            foreach ($data as &$pageId) {
                resolve_pageid($ns, $pageId, $exists);
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
        global $ID;

        if ($mode == 'xhtml') {
            if (empty($data)) {
                /* If no page is specified, render the questions from the current one */
                $data[0] = $ID;
            }

            $this->renderToc($renderer, $data);

            return true;
        }

        return false;
    }

    /**
     *
     */
    private function renderToc($renderer, $pageId) {
        $empty = true;

        foreach ($pageId as $id) {
            $toq = p_get_metadata($id, 'description tableofquestions');

            if (!empty($toq)) {
                if ($empty) {
                    $renderer->doc .= '<div class="qna-toc">' . DOKU_LF;
                    $empty = false;
                }

                $this->renderList($renderer, $id, $toq);
            }
        }

        if (!$empty) {
            $renderer->doc .= '</div>' . DOKU_LF;
        }
    }

    /**
     *
     */
    private function renderList($renderer, $pageId, $toq) {
        $renderer->listu_open();

        foreach ($toq as $question) {
            $renderer->listitem_open(1);
            $renderer->listcontent_open();
            $renderer->internallink($pageId . '#' . $question['id'], $question['title']);
            $renderer->listcontent_close();
            $renderer->listitem_close();
        }

        $renderer->listu_close();
    }
}
