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
        $this->Lexer->addSpecialPattern('~~(?:QNA|FAQ)(?:\s[^\n]*?)?~~', $mode, $this->mode);
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

            $toc = $this->buildToc($data);

            if (!empty($toc)) {
                $this->renderToc($renderer, $toc);
            }

            return true;
        }

        return false;
    }

    /**
     * Assemble questions from all pages into a single TOC
     */
    private function buildToc($pageId) {
        $toc = array();

        foreach ($pageId as $id) {
            $pageToc = p_get_metadata($id, 'description tableofquestions');

            if (!empty($pageToc)) {
                foreach ($pageToc as $item) {
                    $item['link'] = $id . '#' . $item['id'];
                    $toc[] = $item;
                }
            }
        }

        return $toc;
    }

    /**
     *
     */
    private function renderToc($renderer, $toc) {
        $renderer->doc .= '<div class="qna-toc">' . DOKU_LF;
        $this->renderList($renderer, $toc, 0);
        $renderer->doc .= '</div>' . DOKU_LF;
    }

    /**
     *
     */
    private function renderList($renderer, $toc, $index) {
        $items = count( $toc );
        $level = 1;

        $renderer->listu_open();

        for ($i = $index; $i < $items; $i++) {
            $renderer->listitem_open($level);
            $renderer->listcontent_open();
            $renderer->internallink($toc[ $i ]['link'], $toc[ $i ]['title']);
            $renderer->listcontent_close();
            $renderer->listitem_close();
        }

        $renderer->listu_close();
    }
}
