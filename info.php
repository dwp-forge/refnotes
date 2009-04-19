<?php

/**
 * Plugin RefNotes: Information
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

function refnotes_getInfo($component = '') {
    $info = array(
        'author' => 'Mykola Ostrovskyy',
        'email'  => 'spambox03@mail.ru',
        'date'   => '2009-03-21',
        'name'   => 'RefNotes Plugin',
        'desc'   => 'Extended syntax for footnotes and references.',
        'url'    => 'http://code.google.com/p/dwp-forge/',
    );

    if ($component != '') {
        if (($_REQUEST['do'] == 'admin') && !empty($_REQUEST['page']) && ($_REQUEST['page'] == 'plugin')) {
            $info['name'] .= ' (' . $component . ')';
        }
    }

    return $info;
}
