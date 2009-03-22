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
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');

class action_plugin_refnotes extends DokuWiki_Action_Plugin {

    var $create;
    var $render;
    var $style;

    /**
     * Constructor
     */
    function action_plugin_refnotes() {
        $this->create = array();
        $this->render = array();
        $this->style = array();
    }

    /**
     * Return some info
     */
    function getInfo() {
        return refnotes_getinfo('default notes renderer');
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
        $this->_extractStyles($event);
        if (count($this->style) > 0) {
            $this->_insertStyles($event);
        }
        $this->_renderLeftovers($event);
    }

    /**
     * Extract style data and replace "split" instructions by "render"
     */
    function _extractStyles(&$event) {
        $count = count($event->data->calls);
        for ($i = 0; $i < $count; $i++) {
            $call =& $event->data->calls[$i];
            if ($call[0] == 'plugin') {
                switch ($call[1][0]) {
                    case 'refnotes_references':
                        $this->_handleReference($call[1][1]);
                        break;

                    case 'refnotes_notes':
                        $this->_handleNotes($i, $call[1][1]);
                        break;
                }
            }
        }
    }

    /**
     * Mark namespace creation instructions
     */
    function _handleReference(&$callData) {
        if ($callData[0] == DOKU_LEXER_ENTER) {
            list($namespace, $note) = refnotes_parseName($callData[1]['name']);
            if (!array_key_exists($namespace, $this->create)) {
                $this->_markNamespaceCreation($namespace);
            }
        }
    }

    /**
     * Mark namespace creation instructions
     */
    function _markNamespaceCreation($namespace) {
        if ($namespace == ':') {
            $this->create[$namespace] = 0;
        }
        else {
            $parent = refnotes_getParentNamespace($namespace);
            $this->create[$namespace] = $this->_getScopeStart($parent);
        }
    }

    /**
     * Extract style data and replace "split" instructions with "render"
     */
    function _handleNotes($callIndex, &$callData) {
        $namespace = refnotes_canonizeNamespace($callData[1]['ns']);
        if ($callData[0] == 'split') {
            $index = $this->_getScopeStart($namespace);
            $this->style[] = array('pos' => $index, 'ns' => $namespace, 'data' => $callData[2]);
            $callData[0] = 'render';
            unset($callData[2]);
        }
        $this->render[$namespace] = $callIndex;
    }

    /**
     * Returns index of instruction that starts current scope of the specified namespace
     */
    function _getScopeStart($namespace) {
        if (array_key_exists($namespace, $this->render)) {
            $index = $this->render[$namespace] + 1;
        }
        else {
            if (!array_key_exists($namespace, $this->create)) {
                $this->_markNamespaceCreation($namespace);
            }
            $index = $this->create[$namespace];
        }
        return $index;
    }

    /**
     * Insert style instructions
     */
    function _insertStyles(&$event) {
        $calls = count($event->data->calls);
        $styles = count($this->style);
        $call = array();
        for ($c = 0, $s = 0; $c < $calls; $c++) {
            while (($s < $styles) && ($this->style[$s]['pos'] == $c)) {
                $attribute['ns'] = $this->style[$s]['ns'];
                $data[0] = 'style';
                $data[1] = $attribute;
                $data[2] = $this->style[$s]['data'];
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
}
