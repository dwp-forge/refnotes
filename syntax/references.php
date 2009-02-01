<?php

/**
 * Plugin FootRefs: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_footrefs extends DokuWiki_Syntax_Plugin {

    var $mode;
    var $core;
    var $currentNote;
    var $docBackup;

    /**
     * Constructor
     */
    function syntax_plugin_footrefs() {
        $this->mode = substr(get_class($this), 7);
        $this->core = NULL;
        $this->currentNote = 0;
        $this->docBackup = '';
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-01-31',
            'name'   => 'FootRefs Plugin',
            'desc'   => 'Extended syntax for footnotes and references.',
            'url'    => 'http://code.google.com/p/dwp-forge/',
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType() {
        return 'formatting';
    }

    /**
     * What modes are allowed within our mode?
     */
    function getAllowedTypes() {
        return array (
            'formatting',
            'substition',
            'protected',
            'disabled'
        );
    }

    /**
     * Where to sort in?
     */
    function getSort() {
        return 150;
    }

    function connectTo($mode) {
        /*TODO: $this->Lexer->addEntryPattern('<footrefs.*?>(?=.*?</footrefs>)', $mode, $this->mode);*/
        $this->Lexer->addEntryPattern('\[\((?:\w+>)?(?=.*?\)\])', $mode, $this->mode);
    }

    function postConnect() {
        //TODO: $this->Lexer->addExitPattern('</footrefs>', $this->mode);
        $this->Lexer->addExitPattern('\)\]', $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        try {
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    return $this->_handleEnter($match);
    
                case DOKU_LEXER_UNMATCHED:
                    return array($state, $match);
    
                case DOKU_LEXER_EXIT:
                    return $this->_handleExit();
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
                switch ($data[0]) {
                    case DOKU_LEXER_ENTER:
                        $this->_renderEnter($renderer, $data[1], $data[2]);
                        break;
    
                    case DOKU_LEXER_UNMATCHED:
                        $renderer->doc .= $renderer->_xmlEntities($data[1]);
                        break;
    
                    case DOKU_LEXER_EXIT:
                        $this->_renderExit($renderer, $data[1]);
                        break;
                }
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
    function _handleEnter($match) {
        if ($this->currentNote == 0) {
            $core = $this->_getCore();
            $id = $core->addReference($match);
            $count = $core->getReferenceCount($id);
            $this->currentNote = $id;

            return array(DOKU_LEXER_ENTER, $id, $count);
        }
        else {
            //TODO: Check if it's possible to prevent nesting on accepts() level
            return false; //TODO: return the match as unmatched?
        }
    }

    /**
     *
     */
    function _handleExit() {
        if ($this->currentNote != 0) {
            $id = $this->currentNote;
            $this->currentNote = 0;

            return array(DOKU_LEXER_EXIT, $id);
        }
        else {
            return false; //TODO: return the match as unmatched?
        }
    }

    /**
     * Renders reference link and starts renderer output capture
     */
    function _renderEnter(&$renderer, $id, $count) {
        $noteId = 'footref-' . $id;
        $refId = $noteId . '-' . $count;

        $renderer->doc .= '<sup><a href="#' . $noteId . '" name="' . $refId . '" class="fn_top">';
        $renderer->doc .= $id . ')';
        $renderer->doc .= '</a></sup>';

        $this->_startCapture($renderer);
    }

    /**
     * Stops renderer output capture
     */
    function _renderExit(&$renderer, $id) {
        $this->_stopCapture($renderer, $id);
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

    /**
     * Starts renderer output capture
     */
    function _startCapture(&$renderer) {
        $this->docBackup = $renderer->doc;
        $renderer->doc = '';
    }

    /**
     * Stops renderer output capture
     */
    function _stopCapture(&$renderer, $id) {
        $this->_getCore()->setNoteText($id, $renderer->doc);
        $renderer->doc = $this->docBackup;
        $this->docBackup = '';
    }
}
