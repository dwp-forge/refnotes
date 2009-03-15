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
require_once(DOKU_PLUGIN . 'refnotes/info.php');

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
        return refnotes_getinfo('notes syntax');
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
        $this->Lexer->addSpecialPattern('<refnotes.*?\/>', $mode, $this->mode);
        $this->Lexer->addSpecialPattern('<refnotes.*?[^/]>.*?<\/refnotes>', $mode, $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        switch ($match{0}) {
            case '~':
                return $this->_handleBasic($match);

            case '<':
                return $this->_handleExtended($match);
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
                    case 'style':
                        $this->_style($renderer, $data[1], $data[2]);
                        break;

                    case 'render':
                        $this->_render($renderer, $data[1]);
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
    function _handleBasic($syntax) {
        preg_match('/~~REFNOTES(.*?)~~/', $syntax, $match);
        return array('render', $this->_parseAttributes($match[1]));
    }

    /**
     *
     */
    function _handleExtended($syntax) {
        preg_match('/<refnotes(.*?)(?:\/>|>(.*?)<\/refnotes>)/s', $syntax, $match);
        $attribute = $this->_parseAttributes($match[1]);
        $style = array();
        if ($match[2] != '') {
            $style = $this->_parseStyles($match[2]);
        }
        if (count($style) > 0) {
            return array('split', $attribute, $style);
        }
        else {
            return array('render', $attribute);
        }
    }

    /**
     *
     */
    function _parseAttributes($syntax) {
        static $propertyMatch = array(
            'ns' => '/^(:|:*([[:alpha:]]\w*:+)*?[[:alpha:]]\w*:*)$/',
            'limit' => '/^\/?\d+$/'
        );
        $attribute = array('ns' => ':');
        $token = preg_split('/\s+/', $syntax);
        foreach ($token as $t) {
            foreach ($propertyMatch as $name => $pattern) {
                if (preg_match($pattern, $t) == 1) {
                    $attribute[$name] = $t;
                    break;
                }
            }
        }
        return $attribute;
    }

    /**
     *
     */
    function _parseStyles($syntax) {
        $style = array();
        preg_match_all('/([-\w]+)\s*:\s*(.+?)\s*?[\n;]/', $syntax, $match, PREG_SET_ORDER);
        foreach ($match as $m) {
            $style[$m[1]] = $m[2];
        }
        /* Validate direct-to-html styles */
        if (array_key_exists('notes-separator', $style)) {
            if (preg_match('/(?:\d+\.?|\d*\.\d+)(?:%|em|px)|none/', $style['notes-separator'], $match) == 1) {
                $style['notes-separator'] = $match[0];
            }
            else {
                $style['notes-separator'] = '';
            }
        }
        return $style;
    }

    /**
     *
     */
    function _style(&$renderer, $attribute, $style) {
        $this->_getCore()->styleNotes($attribute['ns'], $style);
    }

    /**
     *
     */
    function _render(&$renderer, $attribute) {
        $limit = array_key_exists('limit', $attribute) ? $attribute['limit'] : '';
        $html = $this->_getCore()->renderNotes($attribute['ns'], $limit);
        if ($html != '') {
            $renderer->doc .= '<div class="refnotes">' . DOKU_LF;
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
