<?php

/**
 * Plugin RefNotes: Note renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_refnotes_notes extends DokuWiki_Syntax_Plugin {

    var $mode;
    var $core;

    /**
     * Constructor
     */
    function syntax_plugin_refnotes_notes() {
        $this->mode = substr(get_class($this), 7);
        $this->core = NULL;
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-07',
            'name'   => 'RefNotes Plugin',
            'desc'   => 'Extended syntax for footnotes and references.',
            'url'    => 'http://code.google.com/p/dwp-forge/',
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort() {
        return 150;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~REFNOTES.*?~~', $mode, $this->mode);
        /*TODO: $this->Lexer->addSpecialPattern('<refnotes.*?>.*?</refnotes>', $mode, $this->mode);*/
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        try {
            switch ($match{0}) {
                case '~':
                    return $this->_handleBasic($match);
                    break;

                case '<':
                    return $this->_handleExtended($match);
                    break;
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }
        return false;
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        try {
            if($mode == 'xhtml') {
                $this->_render($renderer, $data);
                return true;
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }
        return false;
    }

    /**
     *
     */
    function _handleBasic($syntax) {
        if (preg_match('/~~REFNOTES\s*(.+?)\s*~~/', $syntax, $match) == 1) {
            return false;
        }
        else {
            return array('ns' => ':');
        }
    }

    /**
     *
     */
    function _handleExtended() {
        return false;
    }

    /**
     * Stops renderer output capture
     */
    function _render(&$renderer, $config) {
        $html = $this->_getCore()->renderNotes($config['ns']);
        if ($html != '') {
            $renderer->doc .= '<div class="footnotes">' . DOKU_LF;
            $renderer->doc .= $html;
            $renderer->doc .= '</div>' . DOKU_LF;
        }
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
