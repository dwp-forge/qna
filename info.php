<?php

/**
 * Plugin QnA: Information
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

function qna_getInfo($component = '') {
    $info = array(
        'author' => 'Mykola Ostrovskyy',
        'email'  => 'spambox03@mail.ru',
        'date'   => '2009-12-13',
        'name'   => 'QnA Plugin',
        'desc'   => 'Custom formatting for Q&A (FAQ) sections.',
        'url'    => 'http://www.dokuwiki.org/plugin:qna'
    );

    if ($component != '') {
        if (($_REQUEST['do'] == 'admin') && !empty($_REQUEST['page']) && ($_REQUEST['page'] == 'plugin')) {
            $info['name'] .= ' (' . $component . ')';
        }
    }

    return $info;
}
