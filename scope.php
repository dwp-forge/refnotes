<?php

/**
 * Plugin RefNotes: Scope
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope {

    private $namespace;
    private $id;
    private $note;
    private $notes;
    private $references;

    /**
     * Constructor
     */
    public function __construct($namespace, $id) {
        $this->namespace = $namespace;
        $this->id = $id;
        $this->note = array();
        $this->notes = 0;
        $this->references = 0;
    }

    /**
     *
     */
    public function getName() {
        return $this->namespace->getName() . $this->id;
    }

    /**
     *
     */
    public function getRenderer() {
        return $this->namespace->getRenderer();
    }

    /**
     *
     */
    public function getNoteId() {
        return ++$this->notes;
    }

    /**
     *
     */
    public function getReferenceId() {
        return ++$this->references;
    }

    /**
     * Returns the number of renderable notes in the scope
     */
    public function getRenderableCount() {
        $result = 0;
        foreach ($this->note as $note) {
            if ($note->isRenderable()) {
                ++$result;
            }
        }

        return $result;
    }

    /**
     *
     */
    public function addNote($note) {
        $this->note[] = $note;
    }

    /**
     *
     */
    public function renderNotes($limit) {
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

        if ($html != '') {
            $open = $this->getRenderer()->renderNotesSeparator() . '<div class="notes">' . DOKU_LF;
            $close = '</div>' . DOKU_LF;
            $html = $open . $html . $close;
        }

        return $html;
    }

    /**
     * Finds a note given it's name or id
     */
    public function getNote($name) {
        $result = NULL;

        if ($name != '') {
            if (preg_match('/(?:@@FNT|#)(\d+)/', $name, $match) == 1) {
                $name = intval($match[1]);
                $getter = 'getId';
            }
            else {
                $getter = 'getName';
            }

            foreach ($this->note as $note) {
                if ($note->$getter() == $name) {
                    $result = $note;
                    break;
                }
            }
        }

        if (($result == NULL) && !is_int($name)) {
            $this->note[] = new refnotes_note($this, $name);

            $result = end($this->note);
        }

        return $result;
    }
}
