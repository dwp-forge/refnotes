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

    var $scopeStart;
    var $scopeEnd;
    var $style;

    /**
     * Constructor
     */
    function action_plugin_refnotes() {
        $this->scopeStart = array();
        $this->scopeEnd = array();
        $this->style = array();
    }

    /**
     * Return some info
     */
    function getInfo() {
        return refnotes_getInfo('default notes renderer');
    }

    /**
     * Register callbacks
     */
    function register(&$controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'addAdminScript');
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'processCallList');
    }

    /**
     *
     */
    function addAdminScript(&$event, $param) {
        if (($_REQUEST['do'] == 'admin') && !empty($_REQUEST['page']) && ($_REQUEST['page'] == 'refnotes')) {
            $event->data['script'][] = array (
                'type' => 'text/javascript',
                'charset' => 'utf-8',
                'src' => DOKU_BASE . 'lib/plugins/refnotes/admin.js',
                '_data' => ''
            );
            error_log(print_r($event->data, true));
        }
    }

    /**
     *
     */
    function processCallList(&$event, $param) {
        $this->_extractStyles($event);

        if (count($this->style) > 0) {
            $this->_sortStyles();
            $this->_insertStyles($event);
        }

        if (count($this->scopeStart) > 0) {
            $this->_renderLeftovers($event);
        }
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
                        $this->_handleReference($i, $call[1][1]);
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
    function _handleReference($callIndex, &$callData) {
        if ($callData[0] == DOKU_LEXER_ENTER) {
            $this->_markScopeStart($callData[1]['ns'], $callIndex);
        }
    }

    /**
     * Mark start of a scope instruction
     */
    function _markScopeStart($namespace, $callIndex) {
        if (array_key_exists($namespace, $this->scopeStart)) {
            if (count($this->scopeStart[$namespace]) < count($this->scopeEnd[$namespace])) {
                $this->scopeStart[$namespace][] = $callIndex;
            }
        }
        else {
            $this->_markScopeEnd($namespace, -1);
            $this->scopeStart[$namespace][] = $callIndex;
        }
    }

    /**
     * Mark end of a scope instruction
     */
    function _markScopeEnd($namespace, $callIndex) {
        $this->scopeEnd[$namespace][] = $callIndex;
    }

    /**
     * Extract style data and replace "split" instructions with "render"
     */
    function _handleNotes($callIndex, &$callData) {
        $namespace = $callData[1]['ns'];
        if ($callData[0] == 'split') {
            if (array_key_exists('inherit', $callData[2])) {
                $index = $this->_getStyleIndex($namespace, $callData[2]['inherit']);
            }
            else {
                $index = $this->_getStyleIndex($namespace);
            }

            $this->style[] = array('idx' => $index, 'ns' => $namespace, 'data' => $callData[2]);
            $callData[0] = 'render';
            unset($callData[2]);
        }

        $this->_markScopeEnd($namespace, $callIndex);
    }

    /**
     * Returns instruction index where the style instruction has to be inserted
     */
    function _getStyleIndex($namespace, $parent = '') {
        if (($parent == '') && (count($this->scopeStart[$namespace]) == 1)) {
            /* Default inheritance for the first scope */
            $parent = refnotes_getParentNamespace($namespace);
        }

        $index = end($this->scopeEnd[$namespace]) + 1;

        if ($parent != '') {
            $start = end($this->scopeStart[$namespace]);
            $end = end($this->scopeEnd[$namespace]);

            while ($parent != '') {
                if (array_key_exists($parent, $this->scopeEnd)) {
                    for ($i = count($this->scopeEnd[$parent]) - 1; $i >= 0; $i--) {
                        $parentEnd = $this->scopeEnd[$parent][$i];
                        if (($parentEnd >= $end) && ($parentEnd < $start)) {
                            $index = $parentEnd + 1;
                            break 2;
                        }
                    }
                }

                $parent = refnotes_getParentNamespace($parent);
            }
        }

        return $index;
    }

    /**
     * Sort the style blocks so that the namespaces with inherited style go after
     * the namespaces they inherit from
     */
    function _sortStyles() {
        /* Sort in ascending order to ensure the default enheritance */
        foreach ($this->style as $key => $style) {
            $index[$key] = $style['idx'];
            $namespace[$key] = $style['ns'];
        }
        array_multisort($index, SORT_ASC, $namespace, SORT_ASC, $this->style);

        /* Sort to ensure explicit enheritance */
        foreach ($this->style as $style) {
            $bucket[$style['idx']][] = $style;
        }

        $this->style = array();

        foreach ($bucket as $b) {
            $inherit = array();
            foreach ($b as $style) {
                if (array_key_exists('inherit', $style['data'])) {
                    $inherit[] = $style;
                }
                else {
                    $this->style[] = $style;
                }
            }

            $inherits = count($inherit);
            if ($inherits > 0) {
                if ($inherits > 1) {
                    /* Perform simplified topological sorting */
                    $target = array();
                    $source = array();

                    for ($i = 0; $i < $inherits; $i++) {
                        $target[$i] = $inherit[$i]['ns'];
                        $source[$i] = $inherit[$i]['data']['inherit'];
                    }

                    for ($i = 0; $i < $inherits; $i++) {
                        foreach ($source as $index => $s) {
                            if (!in_array($s, $target)) {
                                break;
                            }
                        }
                        $this->style[] = $inherit[$index];
                        unset($target[$index]);
                        unset($source[$index]);
                    }
                }
                else {
                    $this->style[] = $inherit[0];
                }
            }
        }
    }

    /**
     * Insert style instructions
     */
    function _insertStyles(&$event) {
        $calls = count($event->data->calls);
        $styles = count($this->style);
        $call = array();

        for ($c = 0, $s = 0; $c < $calls; $c++) {
            while (($s < $styles) && ($this->style[$s]['idx'] == $c)) {
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
