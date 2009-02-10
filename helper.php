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
            'date'   => '2009-02-07',
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
        $this->references = 0;
        $this->text = '';
        $this->rendered = false;
    }

    /**
     *
     */
    function addReference($referenceId) {
        ++$this->references;
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
    function getAnchorName($reference = 0) {
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
    function renderReference() {
        $noteName = $this->getAnchorName();
        $refName = $this->getAnchorName($this->references);

        $html = '<sup><a href="#' . $noteName . '" name="' . $refName . '" class="fn_top">';
        $html .= $this->id . ')';
        $html .= '</a></sup>';

        return $html;
    }

    /**
     *
     */
    function render() {
        $noteName = $this->getAnchorName();
        $html = '<div class="fn"><sup>' . DOKU_LF;

        for ($c = 1; $c <= $this->references; $c++) {
            $refName = $this->getAnchorName($c);

            $html .= '<a href="#' . $refName . '" name="' . $noteName .'" class="fn_bot">';
            $html .= $this->id . ')';
            $html .= '</a>';

            if ($c < $this->references) {
                $html .= ',';
            }
            $html .= DOKU_LF;
        }

        $html .= '</sup> ' . $this->text . DOKU_LF;
        $html .= '</div>' . DOKU_LF;

        $this->rendered = true;

        return $html;
    }
}