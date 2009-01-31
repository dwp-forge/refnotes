<?php

/**
 * Plugin FootRefs: Default renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_footrefs extends DokuWiki_Action_Plugin {
    
    var $core;

    /**
     * Constructor
     */
    function action_plugin_footrefs() {
        $this->core = NULL;
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-01-31',
            'name'   => 'FootRefs',
            'desc'   => 'Extended syntax for footnotes and references.',
            'url'    => 'http://code.google.com/p/dwp-forge/',
        );
    }

    /**
     * Register callbacks
     */
    function register(&$controller) {
        $controller->register_hook('RENDERER_CONTENT_POSTPROCESS', 'AFTER', $this, 'render');
    }
    
    /**
     * Renders notes that were not rendered yet at the very bottom of the page
     */
    function render(&$event, $param) {
        try {
            if ($event->data[0] == 'xhtml') {
                $html = $this->_getCore()->render();
                if ($html != '') {
                    $event->data[1] .= '<div class="footnotes">' . DOKU_LF;
                    $event->data[1] .= $html;
                    $event->data[1] .= '</div>' . DOKU_LF;
                }
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }
    }

    /**
     *
     */
    function _getCore() {
        if ($this->core == NULL) {
            $this->core =& plugin_load('helper', 'footrefs');
            if ($this->core == NULL) {
                throw new Exception('Helper plugin "footrefs" is not available or invalid.');
            }
        }
        return $this->core;
    }
}
