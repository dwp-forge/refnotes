<?php

/**
 * Plugin FootRefs: Notes list
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_INC . 'inc/plugin.php');

class helper_plugin_footrefs extends DokuWiki_Plugin {

    var $note;
    var $notes;

    /**
     * Constructor
     */
    function helper_plugin_footrefs() {
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
            'date'   => '2009-01-31',
            'name'   => 'FootRefs Plugin',
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
     * Returns number of references to a given note
     */
    function getReferenceCount($id) {
        if (array_key_exists($id, $this->note)) {
            return $this->note[$id]->count;
        }
        else {
            return 0;
        }
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
    function addReference($match) {
        if (preg_match('/\[\((\w+)>/', $match, $match) == 1) {
            $name = $match[1];
            $id = $this->_findNote($name);
            if ($id != 0) {
                ++$this->note[$id]->count;
            }
            else {
                $id = ++$this->notes;
                $this->note[$id] = new footrefs_note($name);
            }
        }
        else {
            $id = ++$this->notes;
            $this->note[$id] = new footrefs_note();
        }
        return $id;
    }

    /**
     * Renders notes
     */
    function render() {
        $html = '';
        foreach ($this->note as $id => &$note) {
            if ($note->isReadyForRendering()) {
                $html .= $note->render($id);
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

class footrefs_note {

    var $name;
    var $count;
    var $text;
    var $rendered;

    /**
     * Constructor
     */
    function footrefs_note($name = '') {
        $this->name = $name;
        $this->count = 1;
        $this->text = '';
        $this->rendered = false;
    }

    /**
     * Checks if the note should be rendered
     */
    function isReadyForRendering() {
        return !$this->rendered && ($this->text != '');
    }

    function render($id) {
        $noteId = 'footref-' . $id;
        $html = '<div class="fn"><sup>' . DOKU_LF;

        for ($c = 1; $c <= $this->count; $c++) {
            $refId = $noteId . '-' . $c;
            $html .= '<a href="#' . $refId . '" name="' . $noteId .'" class="fn_bot">';
            $html .= $id . ')';
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