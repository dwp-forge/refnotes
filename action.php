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

    var $core;

    /**
     * Constructor
     */
    function action_plugin_refnotes() {
        $this->core = NULL;
    }

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
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'handle');
    }

    /**
     *
     */
    function handle(&$event, $param) {
        $style = $this->_extractStyles($event);
        if (count($style) > 0) {
            $this->_insertStyles($event, $style);
        }
        $this->_renderLeftovers($event);
    }

    /**
     * Extract style data and replace "split" instructions by "render"
     */
    function _extractStyles(&$event) {
        $count = count($event->data->calls);
        $render = array();
        $style = array();
        for ($i = 0; $i < $count; $i++) {
            $call =& $event->data->calls[$i];
            if (($call[0] == 'plugin') && ($call[1][0] == 'refnotes_notes')) {
                $namespace = $this->_getCore()->canonizeNamespace($call[1][1][1]['ns']);
                switch ($call[1][1][0]) {
                    case 'render':
                        $render[$namespace] = $i;
                        break;

                    case 'split':
                        $pos = array_key_exists($namespace, $render) ? $render[$namespace] + 1: 0;
                        $style[] = array('pos' => $pos, 'ns' => $namespace, 'data' => $call[1][1][2]);
                        $call[1][1][0] = 'render';
                        unset($call[1][1][2]);
                        $render[$namespace] = $i;
                        break;
                }
            }
        }
        return $style;
    }

    /**
     * Insert style instructions
     */
    function _insertStyles(&$event, $style) {
        $next = $style[0]['pos'];
        $calls = count($event->data->calls);
        $styles = count($style);
        $call = array();
        for ($c = 0, $s = 0; $c < $calls; $c++) {
            while (($s < $styles) && ($style[$s]['pos'] == $c)) {
                $attribute['ns'] = $style[$s]['ns'];
                $data[0] = 'style';
                $data[1] = $attribute;
                $data[2] = $style[$s]['data'];
                $call[] = $this->_getInstruction($data, $event->data->calls[$c][2]);
                $s++;
            }
            $call[] = $event->data->calls[$c];
        }
        $event->data->calls = $call;
    }

    /**
     * Insert render call at the very bottom of the page
     */
    function _renderLeftovers(&$event) {
        $attribute['ns'] = '*';
        $data[0] = 'render';
        $data[1] = $attribute;
        $lastCall = end($event->data->calls);
        $call = $this->_getInstruction($data, $lastCall[2]);
        $event->data->calls[] = $call;
    }

    /**
     * Format data into plugin instruction
     */
    function _getInstruction($data, $offset) {
        $parameters = array('refnotes_notes', $data, 5, 'refnotes_action');
        return array('plugin', $parameters, $offset);
    }

    /**
     *
     */
    function _getCore() {
        if ($this->core == NULL) {
            $this->core =& plugin_load('helper', 'refnotes');
            if ($this->core == NULL) {
                throw new Exception('Helper plugin "refnotes" is not available or invalid.');
            }
        }
        return $this->core;
    }
}
