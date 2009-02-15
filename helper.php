<?php

/**
 * Plugin RefNotes: Notes list
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_INC . 'inc/plugin.php');

class helper_plugin_refnotes extends DokuWiki_Plugin {

    var $namespace;

    /**
     * Constructor
     */
    function helper_plugin_refnotes() {
        $this->namespace = array();
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-15',
            'name'   => 'RefNotes Plugin',
            'desc'   => 'Extended syntax for footnotes and references.',
            'url'    => 'http://code.google.com/p/dwp-forge/',
        );
    }

    /**
     * Don't publish any methods (it's not a public helper)
     */
    function getMethods() {
        return array();
    }

    /**
     * Adds a reference to the notes array. Returns a note
     */
    function addReference($name, $hidden) {
        list($namespaceName, $noteName) = $this->_parseName($name);
        $namespace = $this->_findNamespace($namespaceName);
        if ($namespace == NULL) {
            $this->namespace[] = new refnotes_namespace($namespaceName);
            $namespace = end($this->namespace);
        }
        return $namespace->addReference($noteName, $hidden);
    }

    /**
     *
     */
    function renderNotes($name) {
        $namespaceName = $this->_canonizeNamespace($name);
        $namespace = $this->_findNamespace($namespaceName);
        $html = '';
        if ($namespace != NULL) {
            $html = $namespace->renderNotes();
        }
        return $html;
    }

    /**
     * Splits full note name into namespace and name components
     */
    function _parseName($name) {
        $pos = strrpos($name, ':');
        if ($pos !== false) {
            $namespace = $this->_canonizeNamespace(substr($name, 0, $pos));
            $name = substr($name, $pos + 1);
        }
        else {
            $namespace = ':';
        }
        return array($namespace, $name);
    }

    /**
     * Returns canonic name for a namespace
     */
    function _canonizeNamespace($name) {
        return preg_replace('/:{2,}/', ':', ':' . $name . ':');
    }

    /**
     * Finds a namespace given it's name
     */
    function _findNamespace($name) {
        $result = NULL;
        foreach ($this->namespace as $namespace) {
            if ($namespace->name == $name) {
                $result = $namespace;
                break;
            }
        }
        return $result;
    }
}

class refnotes_namespace {

    var $name;
    var $style;
    var $scope;
    var $newScope;

    /**
     * Constructor
     */
    function refnotes_namespace($name) {
        $this->name = $name;
        $this->style = array();
        $this->scope = array();
        $this->newScope = true;
    }

    /**
     *
     */
    function getName() {
        return $this->name;
    }

    /**
     *
     */
    function getStyle($property) {
        $result = '';
        if (array_key_exists($property, $this->style)) {
            $result = $this->style[$property];
        }
        return $result;
    }

    /**
     * Adds a reference to the current scope. Returns a note
     */
    function addReference($name, $hidden) {
        if ($this->newScope) {
            $id = count($this->scope) + 1;
            $this->scope[] = new refnotes_scope($this, $id);
            $this->newScope = false;
        }
        $scope = end($this->scope);
        return $scope->addReference($name, $hidden);
    }

    /**
     *
     */
    function renderNotes($limit = '') {
        $scope = end($this->scope);
        if (preg_match('/(\/?)(\d+)/', $limit, $match) == 1) {
            if ($match[1] != '') {
                $devider = intval($match[2]);
                $limit = ceil($scope->getRenderableCount() / $devider);
            }
            else {
                $limit = intval($match[2]);
            }
        }
        else {
            $limit = 0;
        }
        return $scope->renderNotes($limit);
    }
}

class refnotes_scope {

    var $namespace;
    var $id;
    var $note;
    var $notes;
    var $references;

    /**
     * Constructor
     */
    function refnotes_scope($namespace, $id) {
        $this->namespace = $namespace;
        $this->id = $id;
        $this->note = array();
        $this->notes = 0;
        $this->references = 0;
    }

    /**
     *
     */
    function getName() {
        return $this->namespace->getName() . $this->id;
    }

    /**
     * Returns the number of renderable notes in the scope
     */
    function getRenderableCount() {
        $result = 0;
        foreach ($this->note as $note) {
            if ($note->isRenderable()) {
                ++$result;
            }
        }
        return $result;
    }

    /**
     * Adds a reference to the notes array. Returns a note
     */
    function addReference($name, $hidden) {
        $note = NULL;
        if (preg_match('/#(\d+)/', $name, $match) == 1) {
            $id = intval($match[1]);
            if (array_key_exists($id, $this->note)) {
                $note = $this->note[$id];
            }
        }
        else {
            if ($name != '') {
                $note = $this->_findNote($name);
            }
            if ($note == NULL) {
                $id = ++$this->notes;
                $note = new refnotes_note($this, $id, $name);
                $this->note[$id] = $note;
            }
        }
        if (($note != NULL) && !$hidden) {
            $note->addReference(++$this->references);
        }
        return $note;
    }

    /**
     *
     */
    function renderNotes($limit) {
        $html = '';
        $count = 0;
        foreach ($this->note as $note) {
            if ($note->isRenderable()) {
                $html .= $note->render();
                if (($limit != 0) && (++$count == $limit)) {
                    break;
                }
            }
        }
        return $html;
    }

    /**
     * Finds a note given it's name
     */
    function _findNote($name) {
        $result = NULL;
        foreach ($this->note as $note) {
            if ($note->name == $name) {
                $result = $note;
                break;
            }
        }
        return $result;
    }
}

class refnotes_note {

    var $scope;
    var $id;
    var $name;
    var $reference;
    var $references;
    var $text;
    var $rendered;

    /**
     * Constructor
     */
    function refnotes_note($scope, $id, $name) {
        $this->scope = $scope;
        $this->id = $id;
        if ($name != '') {
            $this->name = $name;
        }
        else {
            $this->name = '#' . $id;
        }
        $this->reference = array();
        $this->references = 0;
        $this->text = '';
        $this->rendered = false;
    }

    /**
     *
     */
    function addReference($referenceId) {
        $this->reference[++$this->references] = $referenceId;
    }

    /**
     *
     */
    function setText($text) {
        $this->text = $text;
    }

    /**
     * Checks if the note should be rendered
     */
    function isRenderable() {
        return !$this->rendered && ($this->references > 0) && ($this->text != '');
    }

    /**
     *
     */
    function renderReference() {
        $noteName = $this->_renderAnchorName();
        $referenceName = $this->_renderAnchorName($this->references);
        list($baseOpen, $baseClose) = $this->_renderReferenceBase();
        list($formatOpen, $formatClose) = $this->_renderReferenceFormat();

        $html = $baseOpen . '<a href="#' . $noteName . '" name="' . $referenceName . '" class="fn_top">';
        $html .= $formatOpen . $this->_renderReferenceId() . $formatClose;
        $html .= '</a>' . $baseClose;

        return $html;
    }

    /**
     *
     */
    function render() {
        $noteName = $this->_renderAnchorName();
        $backRefFormat = $this->_getStyle('back-ref-format');
        list($formatOpen, $formatClose) = $this->_renderNoteIdFormat();

        $html = '<div class="' . $this->_renderNoteClass() . '">' . DOKU_LF;

        if (($backRefFormat != 'note') && ($backRefFormat != '')) {
            list($baseOpen, $baseClose) = $this->_renderNoteIdBase();

            $html .= $baseOpen . '<a name="' . $noteName .'" class="fn_bot">';
            $html .= $formatOpen . $this->_renderNoteId() . $formatClose;
            $html .= '</a>' . $baseClose . DOKU_LF;

            $formatOpen = '';
            $formatClose = '';
        }

        if ($backRefFormat != 'none') {
            $separator = $this->_renderBackRefSeparator();
            list($baseOpen, $baseClose) = $this->_renderBackRefBase();

            $html .= $baseOpen;

            for ($r = 1; $r <= $this->references; $r++) {
                $referenceName = $this->_renderAnchorName($r);

                $html .= '<a href="#' . $referenceName . '" name="' . $noteName .'" class="fn_bot">';
                $html .= $formatOpen . $this->_renderBackRefId($r, $backRefFormat) . $formatClose;
                $html .= '</a>';

                if ($r < $this->references) {
                    $html .= $separator . DOKU_LF;
                }
            }
            $html .= $baseClose . DOKU_LF;
        }

        $html .=  $this->text . DOKU_LF;
        $html .= '</div>' . DOKU_LF;

        $this->rendered = true;

        return $html;
    }

    /**
     *
     */
    function _renderAnchorName($reference = 0) {
        $result = 'refnotes';
        $result .= $this->scope->getName();
        $result .= ':note' . $this->id;
        if ($reference > 0) {
            $result .= ':ref' . $reference;
        }
        return $result;
    }

    /**
     *
     */
    function _renderReferenceBase() {
        return $this->_renderBase($this->_getStyle('reference-base'));
    }

    /**
     *
     */
    function _renderReferenceFormat() {
        return $this->_renderFormat($this->_getStyle('reference-format'));
    }

    /**
     *
     */
    function _renderReferenceId() {
        $idStyle = $this->_getStyle('refnote-id');
        if ($idStyle == 'name') {
            $html = $this->name;
        }
        else {
            switch ($this->_getStyle('multi-ref-id')) {
                case 'note':
                    $id = $this->id;
                    break;
                
                default:
                    $id = end($this->reference);
                    break;
            }
            $html = $this->_convertToStyle($id, $idStyle);
        }
        return $html;
    }

    /**
     *
     */
    function _renderNoteClass() {
        switch ($this->_getStyle('note-font-size')) {
            case 'small':
                $result = 'rn_small';
                break;

            default:
                $result = 'fn';
                break;
        }
        return $result;
    }

    /**
     *
     */
    function _renderNoteIdBase() {
        return $this->_renderBase($this->_getStyle('note-id-base'));
    }

    /**
     *
     */
    function _renderNoteIdFormat() {
        $style = $this->_getStyle('note-id-format');
        switch ($style) {
            case '.':
                $result = array('', '.');
                break;

            default:
                $result = $this->_renderFormat($style);
                break;
        }
        return $result;
    }

    /**
     *
     */
    function _renderNoteId($reference = 0) {
        $idStyle = $this->_getStyle('refnote-id');
        if ($idStyle == 'name') {
            $html = $this->name;
        }
        else {
            if ($reference > 0) {
                $id = $this->reference[$reference];
            }
            else {
                $id = $this->id;
            }
            $html = $this->_convertToStyle($id, $idStyle);
        }
        return $html;
    }

    /**
     *
     */
    function _renderBackRefBase() {
        return $this->_renderBase($this->_getStyle('back-ref-base'));
    }

    /**
     *
     */
    function _renderBackRefSeparator() {
        static $html = array('' => ',', 'none' => '');
        if (!array_key_exists($style, $html)) {
            $style = '';
        }
        return $html[$style];
    }

    /**
     *
     */
    function _renderBackRefId($reference, $style) {
        switch ($style) {
            case 'a':
                $result = $this->_convertToLatin($reference, $style);
                break;

            case '1':
                $result = $reference;
                break;

            case 'caret':
                $result = '^';
                break;

            case 'arrow':
                $result = '&uarr;';
                break;

            default:
                $result = $this->_renderNoteId($reference);
                break;
        }
        return $result;
    }

    /**
     *
     */
    function _renderBase($style) {
        static $html = array(
            '' => array('<sup>', '</sup>'),
            'text' => array('', '')
        );
        if (!array_key_exists($style, $html)) {
            $style = '';
        }
        return $html[$style];
    }

    /**
     *
     */
    function _renderFormat($style) {
        static $html = array(
            '' => array('', ')'),
            '()' => array('(', ')'),
            ']' => array('', ']'),
            '[]' => array('[', ']'),
            'none' => array('', '')
        );
        if (!array_key_exists($style, $html)) {
            $style = '';
        }
        return $html[$style];
    }

    /**
     *
     */
    function _getStyle($property) {
        return $this->scope->namespace->getStyle($property);
    }

    /**
     *
     */
    function _convertToStyle($id, $style) {
        switch ($style) {
            case 'a':
            case 'A':
                $result = $this->_convertToLatin($id, $style);
                break;
            
            case 'i':
            case 'I':
                $result = $this->_convertToRoman($id, $style);
                break;

            case '*':
                $result = str_repeat('*', $id);
                break;

            default:
                $result = $id;
                break;
        }
        return $result;
    }

    /**
     *
     */
    function _convertToLatin($number, $case)
    {
        static $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $result = '';
        while ($number > 0) {
            --$number;
            $digit = $number % 26;
            $result = $alpha{$digit} . $result;
            $number = intval($number / 26);
        }
        if ($case == 'a') {
            $result = strtolower($result);
        }
        return $result;
    }

    /**
     *
     */
    function _convertToRoman($number, $case)
    {
        static $lookup = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);

        $result = '';
        foreach ($lookup as $roman => $value) {
            $matches = intval($number / $value);
            if ($matches > 0) {
                $result .= str_repeat($roman, $matches);
                $number = $number % $value;
            }
        }
        if ($case == 'i') {
            $result = strtolower($result);
        }
        return $result;
    }
}