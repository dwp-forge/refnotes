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
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');

class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    var $mode;
    var $entryPattern;
    var $exitPattern;
    var $handlePattern;
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

        $newLine = '(?:\n?[ \t]*\n)?';
        $entry = '\[\(';
        $exit = '\)\]';
        $namespace ='(?:(?:[[:alpha:]]\w*)?:)*';
        $name ='(?:#\d+|[[:alpha:]]\w*)';
        $text = '.*?';

        $nameMatch = '\s*' . $namespace . $name .'\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $nameMatch . $lookaheadExit;

        $optionalName = $name .'?';
        $define = '\s*' . $namespace . $optionalName .'\s*>';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->entryPattern = $newLine . $entry . '(?:' . $nameEntry . '|' . $defineEntry . ')';
        $this->exitPattern = $exit;
        $this->handlePattern = '/(\s*)' . $entry . '\s*(' . $namespace . $optionalName . ').*/';

        $this->core = NULL;
        $this->handling = false;
        $this->lastHiddenExit = -1;
        $this->capturedNote = NULL;
        $this->docBackup = '';
    }

    /**
     * Return some info
     */
    function getInfo() {
        return refnotes_getInfo('references syntax');
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
        $this->Lexer->addEntryPattern($this->entryPattern, $mode, $this->mode);
    }

    function postConnect() {
        $this->Lexer->addExitPattern($this->exitPattern, $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                if (!$this->handling) {
                    return $this->_handleEnter($match, $pos, $handler);
                }
                break;

            case DOKU_LEXER_EXIT:
                if ($this->handling) {
                    return $this->_handleExit($match, $pos);
                }
                break;
        }
        $handler->_addCall('cdata', array($match), $pos);
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
    function _handleEnter($syntax, $pos, &$handler) {
        if (preg_match($this->handlePattern, $syntax, $match) == 0) {
            return false;
        }
        $this->handling = true;
        list($namespace, $name) = refnotes_parseName($match[2]);
        $info['ns'] = $namespace;
        $info['name'] = $name;
        $info['hidden'] = $this->_isHiddenReference($match[1], $pos, $handler);

        return array(DOKU_LEXER_ENTER, $info);
    }

    /**
     *
     */
    function _handleExit($syntax, $pos) {
        $this->handling = false;

        if ($this->lastHiddenExit >= 0) {
            $this->lastHiddenExit = $pos + strlen($syntax);
        }
        return array(DOKU_LEXER_EXIT);
    }

    /**
     *
     */
    function _isHiddenReference($space, $pos, &$handler) {
        $newLines = substr_count($space, "\n");
        $lastCall = end($handler->calls);
        $lastCall = $lastCall[0];
        if (($newLines == 2) || ($lastCall == 'table_close')) {
            $this->lastHiddenExit = $pos;
        }
        else {
            if (($this->lastHiddenExit >= 0) && ($this->lastHiddenExit < $pos)) {
                $this->lastHiddenExit = -1;
            }
        }
        return $this->lastHiddenExit >= 0;
    }

    /**
     * Renders reference link and starts renderer output capture
     */
    function _renderEnter(&$renderer, $info) {
        $core = $this->_getCore();
        $note = $core->addReference($info['ns'], $info['name'], $info['hidden']);
        if (($note != NULL) && !$info['hidden']) {
            $renderer->doc .= $note->renderReference();
        }
        $this->_startCapture($renderer, $note);
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
    function _startCapture(&$renderer, $note) {
        $this->capturedNote = $note;
        $this->docBackup = $renderer->doc;
        $renderer->doc = '';
    }

    /**
     * Stops renderer output capture
     */
    function _stopCapture(&$renderer) {
        $text = trim($renderer->doc);
        if ($text != '') {
            $this->capturedNote->setText($text);
        }
        $renderer->doc = $this->docBackup;
        $this->capturedNote = NULL;
        $this->docBackup = '';
    }
}
