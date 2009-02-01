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

    var $note;
    var $notes;

    /**
     * Constructor
     */
    function helper_plugin_refnotes() {
        $this->note = array();
        $this->notes = 0;
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
     * Don't publish any methods (it's not a public helper)
     */
    function getMethods() {
        return array();
    }

    /**
     * Sets text of a given note
     */
    function setNoteText($id, $text) {
        if (array_key_exists($id, $this->note)) {
            $this->note[$id]->text = $text;
        }
    }

    /**
     * Adds a reference to the notes array. Returns a note identifier
     */
    function addReference($name, $hidden) {
        $id = 0;
        if ($name != '') {
            $id = $this->_findNote($name);
        }
        if ($id == 0) {
            $id = ++$this->notes;
            $this->note[$id] = new refnotes_note($id, $name);
        }
        if (!$hidden) {
            $this->note[$id]->addReference();
        }
        return $id;
    }

    /**
     *
     */
    function renderReference($id) {
        if (array_key_exists($id, $this->note)) {
            $noteId = $this->note[$id]->getAnchorId();
            $refId = $this->note[$id]->getAnchorId($this->note[$id]->count);

            $html = '<sup><a href="#' . $noteId . '" name="' . $refId . '" class="fn_top">';
            $html .= $id . ')';
            $html .= '</a></sup>';
        }
        return $html;
    }

    /**
     *
     */
    function renderNotes() {
        $html = '';
        foreach ($this->note as &$note) {
            if ($note->isReadyForRendering()) {
                $html .= $note->render();
            }
        }
        return $html;
    }

    /**
     * Finds a note identifier given it's name
     */
    function _findNote($name) {
        for ($id = $this->notes; $id > 0; $id--) {
            if ($this->note[$id]->name == $name) {
                break;
            }
        }
        return $id;
    }
}

class refnotes_note {

    var $id;
    var $name;
    var $count;
    var $text;
    var $rendered;

    /**
     * Constructor
     */
    function refnotes_note($id, $name) {
        $this->id = $id;
        $this->name = $name;
        $this->count = 0;
        $this->text = '';
        $this->rendered = false;
    }

    /**
     *
     */
    function addReference() {
        ++$this->count;
    }

    /**
     *
     */
    function getAnchorId($count = 0) {
        $result = 'refnote-' . $this->id;
        if ($count > 0) {
            $result .= '-' . $count;
        }
        return $result;
    }

    /**
     * Checks if the note should be rendered
     */
    function isReadyForRendering() {
        return !$this->rendered && ($this->count > 0) && ($this->text != '');
    }

    /**
     *
     */
    function render() {
        $noteId = $this->getAnchorId();
        $html = '<div class="fn"><sup>' . DOKU_LF;

        for ($c = 1; $c <= $this->count; $c++) {
            $refId = $this->getAnchorId($c);
            $html .= '<a href="#' . $refId . '" name="' . $noteId .'" class="fn_bot">';
            $html .= $this->id . ')';
            $html .= '</a>';
            if ($c < $this->count) {
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