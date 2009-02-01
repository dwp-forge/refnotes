<?php

/**
 * Plugin RefNotes: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    var $mode;
    var $syntaxEntry;
    var $syntaxExit;
    var $syntaxParse;
    var $core;
    var $handling;
    var $lastHiddenExit;
    var $capturedNote;
    var $docBackup;

    /**
     * Constructor
     */
    function syntax_plugin_refnotes_references() {
        $this->mode = substr(get_class($this), 7);

        $entry = '\[\(';
        $exit = '\)\]';
        $name ='(?:#\d+|[[:alpha:]]\w+)';
        $text = '.*?';

        $nameMatch = '\s*' . $name .'\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $nameMatch . $lookaheadExit;

        $optionalName = $name .'?';
        $define = '\s*' . $optionalName .'\s*>';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->syntaxEntry = '\n?[ \t]*\n?' . $entry . '(?:' . $nameEntry . '|' . $defineEntry . ')';
        $this->syntaxExit = $exit;
        $this->syntaxParse = '/(\s*)' . $entry . '\s*(' . $optionalName . ').*/';

        $this->core = NULL;
        $this->handling = false;
        $this->lastHiddenExit = 0;
        $this->capturedNote = 0;
        $this->docBackup = '';
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-01',
            'name'   => 'RefNotes Plugin',
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
        $this->Lexer->addEntryPattern($this->syntaxEntry, $mode, $this->mode);
    }

    function postConnect() {
        $this->Lexer->addExitPattern($this->syntaxExit, $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                return $this->_handleEnter($match, $pos);

            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);

            case DOKU_LEXER_EXIT:
                return $this->_handleExit($pos);
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
                        $this->_renderEnter($renderer, $data[1]);
                        break;

                    case DOKU_LEXER_UNMATCHED:
                        $renderer->doc .= $renderer->_xmlEntities($data[1]);
                        break;

                    case DOKU_LEXER_EXIT:
                        $this->_renderExit($renderer);
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
    function _handleEnter($syntax, $pos) {
        if (!$this->handling) {
            if (preg_match($this->syntaxParse, $syntax, $match) == 0) {
                return false;
            }
            $this->handling = true;
            $info['name'] = $match[2];
            $info['hidden'] = $this->_isHiddenReference($match[1], $pos);

            return array(DOKU_LEXER_ENTER, $info);
        }
        else {
            //TODO: Check if it's possible to prevent nesting on accepts() level
            return false; //TODO: return the match as unmatched?
        }
    }

    /**
     *
     */
    function _handleExit($pos) {
        if ($this->handling) {
            $this->handling = false;

            if ($this->lastHiddenExit > 0) {
                $this->lastHiddenExit = $pos;
            }
            return array(DOKU_LEXER_EXIT);
        }
        else {
            return false; //TODO: return the match as unmatched?
        }
    }

    /**
     *
     */
    function _isHiddenReference($space, $pos) {
        $newLines = substr_count($space, "\n");
        switch ($newLines) {
            case 0:
            case 1:
                $entry = $this->lastHiddenExit + strlen($this->syntaxExit);
                if ($entry < $pos) {
                    $this->lastHiddenExit = 0;
                }
                break;

            case 2:
                $this->lastHiddenExit = $pos;
                break;
        }
        return $this->lastHiddenExit > 0;
    }

    /**
     * Renders reference link and starts renderer output capture
     */
    function _renderEnter(&$renderer, $info) {
        $core = $this->_getCore();
        $id = $core->addReference($info['name'], $info['hidden']);
        if (!$info['hidden']) {
            $renderer->doc .= $core->renderReference($id);
        }
        $this->_startCapture($renderer, $id);
    }

    /**
     * Stops renderer output capture
     */
    function _renderExit(&$renderer) {
        $this->_stopCapture($renderer);
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

    /**
     * Starts renderer output capture
     */
    function _startCapture(&$renderer, $id) {
        $this->capturedNote = $id;
        $this->docBackup = $renderer->doc;
        $renderer->doc = '';
    }

    /**
     * Stops renderer output capture
     */
    function _stopCapture(&$renderer) {
        if (trim($renderer->doc) != '') {
            $this->_getCore()->setNoteText($this->capturedNote, $renderer->doc);
        }
        $renderer->doc = $this->docBackup;
        $this->capturedNote = 0;
        $this->docBackup = '';
    }
}
