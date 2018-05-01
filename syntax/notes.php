<?php

/**
 * Plugin RefNotes: Note renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

/* Must be run within Dokuwiki */
if (!defined('DOKU_INC') || !defined('DOKU_PLUGIN')) die();

require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'refnotes/core.php');

class syntax_plugin_refnotes_notes extends DokuWiki_Syntax_Plugin {

    private $mode;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort() {
        return 150;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~REFNOTES.*?~~', $mode, $this->mode);
        $this->Lexer->addSpecialPattern('<refnotes[^>]*?\/>', $mode, $this->mode);
        $this->Lexer->addSpecialPattern('<refnotes(?:[^>]*?[^/>])?>.*?<\/refnotes>', $mode, $this->mode);
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        switch ($match{0}) {
            case '~':
                return $this->handleBasic($match);

            case '<':
                return $this->handleExtended($match);
        }

        return false;
    }

    /**
     * Create output
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        try {
            if($mode == 'xhtml') {
                switch ($data[0]) {
                    case 'style':
                        refnotes_renderer_core::getInstance()->styleNamespace($data[1]['ns'], $data[2]);
                        break;

                    case 'map':
                        refnotes_renderer_core::getInstance()->setNamespaceMapping($data[1]['ns'], $data[2]);
                        break;

                    case 'render':
                        $this->renderNotes($mode, $renderer, $data[1]);
                        break;
                }

                return true;
            }
            elseif ($mode == 'odt') {
                switch ($data[0]) {
                    case 'render':
                        $this->renderNotes($mode, $renderer, $data[1]);
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
    private function handleBasic($syntax) {
        preg_match('/~~REFNOTES(.*?)~~/', $syntax, $match);

        return array('render', $this->parseAttributes($match[1]));
    }

    /**
     *
     */
    private function handleExtended($syntax) {
        preg_match('/<refnotes(.*?)(?:\/>|>(.*?)<\/refnotes>)/s', $syntax, $match);
        $attribute = $this->parseAttributes($match[1]);
        $style = array();

        if ($match[2] != '') {
            $style = $this->parseStyles($match[2]);
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
    private function parseAttributes($syntax) {
        $propertyMatch = array(
            'ns' => '/^' . refnotes_namespace::getNamePattern('required') . '$/',
            'limit' => '/^\/?\d+$/'
        );

        $attribute = array();
        $token = preg_split('/\s+/', $syntax, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($token as $t) {
            foreach ($propertyMatch as $name => $pattern) {
                if (preg_match($pattern, $t) == 1) {
                    $attribute[$name][] = $t;
                    break;
                }
            }
        }

        if (array_key_exists('ns', $attribute)) {
            /* Ensure that namespaces are in canonic form */
            $attribute['ns'] = array_map('refnotes_namespace::canonizeName', $attribute['ns']);

            if (count($attribute['ns']) > 1) {
                $attribute['map'] = array_slice($attribute['ns'], 1);
            }

            $attribute['ns'] = $attribute['ns'][0];
        }
        else {
            $attribute['ns'] = ':';
        }

        if (array_key_exists('limit', $attribute)) {
            $attribute['limit'] = end($attribute['limit']);
        }

        return $attribute;
    }

    /**
     *
     */
    private function parseStyles($syntax) {
        $style = array();
        preg_match_all('/([-\w]+)\s*:\s*(.+?)\s*?(:?[\n;]|$)/', $syntax, $match, PREG_SET_ORDER);
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

        /* Ensure that namespaces are in canonic form */
        if (array_key_exists('inherit', $style)) {
            $style['inherit'] = refnotes_namespace::canonizeName($style['inherit']);
        }

        return $style;
    }

    /**
     *
     */
    private function renderNotes($mode, $renderer, $attribute) {
        $limit = array_key_exists('limit', $attribute) ? $attribute['limit'] : '';
        $doc = refnotes_renderer_core::getInstance()->renderNotes($mode, $attribute['ns'], $limit);

        if ($doc != '') {
            if ($mode == 'xhtml') {
                $open = '<div class="refnotes">' . DOKU_LF;
                $close = '</div>' . DOKU_LF;
            }
            else {
                $open = '';
                $close = '';
            }

            $renderer->doc .= $open;
            $renderer->doc .= $doc;
            $renderer->doc .= $close;
        }
    }
}
