<?php

/**
 * Plugin RefNotes: Note
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_block_iterator extends FilterIterator {
    private $note;
    private $limit;
    private $count;

    /**
    * Constructor
    */
    public function __construct($note, $limit) {
        $this->note = new ArrayObject($note);
        $this->limit = $this->getRenderLimit($limit);
        $this->count = 0;

        parent::__construct($this->note->getIterator());
    }

    /**
     *
     */
    function accept() {
        $result = $this->current()->isRenderable();

        if ($result) {
            ++$this->count;
        }

        return $result;
    }

    /**
     *
     */
    function valid() {
        return parent::valid() && (($this->limit == 0) || ($this->count <= $this->limit));
    }

    /**
     *
     */
    private function getRenderLimit($limit) {
        if (preg_match('/(\/?)(\d+)/', $limit, $match) == 1) {
            if ($match[1] != '') {
                $devider = intval($match[2]);
                $result = ceil($this->getRenderableCount() / $devider);
            }
            else {
                $result = intval($match[2]);
            }
        }
        else {
            $result = 0;
        }

        return $result;
    }

    /**
     *
     */
    private function getRenderableCount() {
        $result = 0;

        foreach ($this->note as $note) {
            if ($note->isRenderable()) {
                ++$result;
            }
        }

        return $result;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_mock {

    /**
     *
     */
    public function setText($text) {
    }

    /**
     *
     */
    public function addReference($attributes, $data) {
        return new refnotes_reference_mock($this);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note extends refnotes_refnote {

    private $scope;
    private $id;
    private $name;
    private $inline;
    private $reference;
    private $text;
    private $rendered;

    /**
     * Constructor
     */
    public function __construct($scope, $name, $useDatabase = false) {
        parent::__construct();

        $this->scope = $scope;
        $this->id = -1;
        $this->name = $name;
        $this->inline = false;
        $this->reference = array();
        $this->text = '';
        $this->rendered = false;

        if ($useDatabase) {
            $this->loadDatabaseDefinition();
        }
    }

    /**
     *
     */
    private function initId() {
        $this->id = $this->scope->getNoteId();

        if ($this->name == '') {
            $this->name = '#' . $this->id;
        }
    }

    /**
     *
     */
    private function loadDatabaseDefinition() {
        $name = $this->scope->getNamespaceName() . $this->name;
        $note = refnotes_reference_database::getInstance()->findNote($name);

        if ($note != NULL) {
            $this->attributes = $note->getAttributes();
            $this->data = $note->getData();
        }
    }

    /**
     *
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->scope->getName();
        $result .= ':note' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function setText($text) {
        if (($this->text == '') || !$this->inline) {
            $this->text = $text;
        }
    }

    /**
     *
     */
    public function getText() {
        return $this->text;
    }

    /**
     *
     */
    public function addReference($attributes, $data) {
        $reference = new refnotes_renderer_reference($this->scope, $this, $attributes, $data);

        if ($this->id == -1 && !$this->inline) {
            $this->inline = $reference->isInline();

            if (!$this->inline) {
                $this->initId();
            }
        }

        if ($reference->isBackReferenced()) {
            $this->reference[] = $reference;
        }

        return $reference;
    }

    /**
     * Checks if the note should be rendered
     */
    public function isRenderable() {
        return !$this->rendered && (count($this->reference) > 0) && ($this->text != '');
    }

    /**
     *
     */
    public function render() {
        $html = $this->scope->getRenderer()->renderNote($this, $this->reference);

        $this->rendered = true;

        return $html;
    }
}
