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

class syntax_plugin_qna_toc extends DokuWiki_Syntax_Plugin {

    private $mode;

    /**
     * Construct
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
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
    public function handle($match, $state, $pos, Doku_Handler $handler) {
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
    public function render($mode, Doku_Renderer $renderer, $data) {
        global $ID;

        if ($mode == 'xhtml') {
            if (empty($data)) {
                /* If no page is specified, render the questions from the current one */
                $data[0] = $ID;
            }

            $toc = $this->buildToc($data);

            if (!empty($toc)) {
                $this->compressToc($toc);
                $this->normalizeToc($toc);
                $this->renderToc($renderer, $toc);
            }

            return true;
        }
        elseif ($mode == 'metadata') {
            if (!empty($data)) {
                $this->addCacheDependencies($renderer, $data);
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
            if (auth_quickaclcheck($id) < AUTH_READ) {
                continue;
            }

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
     * Remove not used list levels
     */
    private function compressToc(&$toc) {
        $maxLevel = 0;

        foreach ($toc as $item) {
            if ($maxLevel < $item['level']) {
                $maxLevel = $item['level'];
            }
        }

        /* Build list level usage histogram */
        $level = array_fill(1, $maxLevel, 0);

        foreach ($toc as $item) {
            $level[$item['level']]++;
        }

        /* Determine how many unused list levels have to be skipped for each used one */
        $skipCount = 0;

        for ($l = 1; $l <= $maxLevel; $l++) {
            if ($level[$l] == 0) {
                $skipCount++;
            }
            else {
                $level[$l] = $skipCount;
            }
        }

        /* Remove unused list levels */
        foreach ($toc as &$item) {
            $item['level'] -= $level[$item['level']];
        }
    }

    /**
     * Make sure that list starts with a first level item
     */
    private function normalizeToc(&$toc) {
        $offset = 9;

        for ($i = 0; $toc[$i]['level'] > 1; $i++) {
            if (($toc[$i]['level'] - $offset) < 1) {
                $offset = $toc[$i]['level'] - 1;
            }

            $toc[$i]['level'] -= $offset;
        }
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
        $level = $toc[$index]['level'];

        $renderer->listu_open();

        for ($i = $index; ($i < $items) && ($toc[$i]['level'] == $level); $i++) {
            $renderer->listitem_open($level);
            $renderer->listcontent_open();
            $renderer->doc .= '<span class="qna-toc-' . $toc[$i]['class'] . '">';
            $renderer->internallink($toc[$i]['link'], $toc[$i]['title']);
            $renderer->doc .= '</span>';
            $renderer->listcontent_close();

            if ((($i + 1) < $items) && ($toc[$i + 1]['level'] > $level)) {
                $i = $this->renderList($renderer, $toc, $i + 1);
            }

            $renderer->listitem_close();
        }

        $renderer->listu_close();

        return $i - 1;
    }

    /**
     *
     */
    private function addCacheDependencies($renderer, $pageId) {
        foreach ($pageId as $id) {
            $metafile = metaFN($id, '.meta');

            $renderer->meta['relation']['depends']['rendering'][$metafile] = true;
        }
    }
}
