<?php

/**
 * Plugin RefNotes: Information
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

function refnotes_getinfo($component = '') {
    $info = array(
        'author' => 'Mykola Ostrovskyy',
        'email'  => 'spambox03@mail.ru',
        'date'   => '2009-03-15',
        'name'   => 'RefNotes Plugin',
        'desc'   => 'Extended syntax for footnotes and references.',
        'url'    => 'http://code.google.com/p/dwp-forge/',
    );
    if ($component != '') {
        $trace = debug_backtrace(false);
        $infoPlugin = (array_key_exists('class', $trace[2]) &&
                       ($trace[2]['class'] == 'syntax_plugin_info') &&
                       ($trace[2]['function'] == '_plugins_xhtml'));
        if (!$infoPlugin) {
            $info['name'] .= ' (' . $component . ')';
        }
    }
    return $info;
}
