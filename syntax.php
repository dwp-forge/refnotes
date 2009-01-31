<?php
/**
 * Plugin FootRefs
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_footrefs extends DokuWiki_Syntax_Plugin {

    var $mode;
    var $note;
    var $notes;
    var $currentNote;
    var $docBackup;

    /**
     * Constructor
     */
    function syntax_plugin_footrefs() {
        $this->mode = substr(get_class($this), 7);
        $this->note = array();
        $this->notes = 0;
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
            'url'    => 'FIXME!!!',
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
        switch ($state) {
            case DOKU_LEXER_ENTER:
                return $this->_handleEnter($match);

            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);

            case DOKU_LEXER_EXIT:
                return $this->_handleExit();
        }
        return false;
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
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
        return false;
    }

    /**
     *
     */
    function _handleEnter($match) {
        if ($this->currentNote == 0) {
            $id = $this->_addReference($match);
            $count = $this->note[$id]['count'];
            $this->currentNote = $id;
        }
        return array(DOKU_LEXER_ENTER, $id, $count);
    }

    /**
     *
     */
    function _handleExit() {
        if ($this->currentNote != 0) {
            $id = $this->currentNote;
            $this->currentNote = $id;
        }
        return array(DOKU_LEXER_EXIT);
    }

    /**
     * Adds a reference to the notes array. Returns a note identifier
     */
    function _addReference($match) {
        if (preg_match('/\[\((\w+)>/', $match, $match) == 1) {
            $name = $match[1];
            $id = $this->_findNote($name);
            if ($id != 0) {
                ++$this->note[$id]['count'];
            }
            else {
                $id = ++$this->note;
                $this->note[$id] = array('name' => $name, 'count' => 1, 'text' => '');
            }
        }
        else {
            $id = ++$this->notes;
            $this->note[$id] = array('name' => '', 'count' => 1, 'text' => '');
        }
        return $id;
    }

    /**
     * Finds a note identifier given it's name
     */
    function _findNote($name) {
        for ($id = $this->notes; $id > 0; $id--) {
            if ($this->note[$id]['name'] == $name) {
                break;
            }
        }
        return $id;
    }

    /**
     * Renders reference link and starts renderer output capture
     */
    function _renderEnter(&$renderer, $id, $count) {
        $noteId = 'footref-' . $id;
        $refId = $noteId . '-' . $count;

        $renderer->doc .= '<sup><a href="#' . $noteId . '" name="' . $refId . '" class="fn_top">' . $id . ')</a></sup>';

        $this->_startCapture($renderer);
    }

    /**
     * Stops renderer output capture
     */
    function _renderExit(&$renderer, $id) {
        $this->_stopCapture($renderer, $id);
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
        $this->note[$id]['text'] = $renderer->doc;
        $renderer->doc = $this->docBackup;
        $this->docBackup = '';
    }
}
