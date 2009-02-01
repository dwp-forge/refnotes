<?php

/**
 * Plugin RefNotes: Default renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_refnotes extends DokuWiki_Action_Plugin {

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-01',
            'name'   => 'RefNotes',
            'desc'   => 'Extended syntax for footnotes and references.',
            'url'    => 'http://code.google.com/p/dwp-forge/',
        );
    }

    /**
     * Register callbacks
     */
    function register(&$controller) {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'renderLeftovers');
    }

    /**
     * Inserts render call at the very bottom of the page
     */
    function renderLeftovers(&$event, $param) {
        $config['ns'] = '*';
        $parameters = array('refnotes_notes', $config, 5, '~~REFNOTES~~');
        $lastCall = end($event->data->calls);
        $pluginCall = array('plugin', $parameters, $last_call[2]);

        array_push($event->data->calls, $pluginCall);
    }
}
