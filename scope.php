<?php

/**
 * Plugin RefNotes: Scope
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope_limits {
    public $start;
    public $end;

    /**
     * Constructor
     */
    public function __construct($start, $end = -1000) {
        $this->start = $start;
        $this->end = $end;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope_mock {

    /**
     *
     */
    public function getLimits() {
        return new refnotes_scope_limits(-1, -1);
    }

    /**
     *
     */
    public function isOpen() {
        return false;
    }

    /**
     *
     */
    public function getRenderer() {
        return new refnotes_renderer_mock();
    }

    /**
     *
     */
    public function getReferenceId() {
        return 0;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope {

    private $namespace;
    private $id;
    private $limits;
    private $note;
    private $notes;
    private $references;

    /**
     * Constructor
     */
    public function __construct($namespace, $id, $start = -1, $end = -1000) {
        $this->namespace = $namespace;
        $this->id = $id;
        $this->limits = new refnotes_scope_limits($start, $end);
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
    public function getLimits() {
        return $this->limits;
    }

    /**
     *
     */
    public function isOpen() {
        return $this->limits->end == -1000;
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
     *
     */
    public function addNote($note) {
        $this->note[] = $note;
    }

    /**
     *
     */
    public function rewriteReferences($limit) {
        $block = new refnotes_note_block_iterator($this->note, $limit);

        foreach ($block as $note) {
            $note->rewriteReferences();
        }
    }

    /**
     *
     */
    public function renderNotes($mode, $limit) {
        $block = new refnotes_note_block_iterator($this->note, $limit);
        $doc = '';

        foreach ($block as $note) {
            $doc .= $note->render($mode);
        }

        if ($mode == 'xhtml' && $doc != '') {
            $open = $this->getRenderer()->renderNotesSeparator() . '<div class="notes">' . DOKU_LF;
            $close = '</div>' . DOKU_LF;
            $doc = $open . $doc . $close;
        }

        return $doc;
    }

    /**
     * Finds a note given it's name or id
     */
    public function findNote($namespaceName, $noteName) {
        $result = NULL;

        if ($noteName != '') {
            if (is_int($noteName)) {
                $getter = 'getId';
            }
            else {
                $getter = 'getName';
            }

            foreach ($this->note as $note) {
                if (($note->getNamespaceName() == $namespaceName) && ($note->$getter() == $noteName)) {
                    $result = $note;
                    break;
                }
            }
        }

        return $result;
    }
}
