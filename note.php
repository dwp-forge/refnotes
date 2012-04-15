<?php

/**
 * Plugin RefNotes: Note
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

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
    public function addReference($info) {
        return new refnotes_reference_mock($this);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note {

    private $scope;
    private $id;
    private $name;
    private $inline;
    private $reference;
    private $info;
    private $data;
    private $text;
    private $rendered;

    /**
     * Constructor
     */
    public function __construct($scope, $name, $useDatabase = false) {
        $this->scope = $scope;
        $this->id = -1;
        $this->name = $name;
        $this->inline = false;
        $this->reference = array();
        $this->info = array();
        $this->data = array();
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
        $database = refnotes_reference_database::getInstance();
        $name = $this->scope->getNamespaceName() . $this->name;

        if ($database->isDefined($name)) {
            $this->info = $database->getNoteInfo($name);
            $this->data = $database->getNoteData($name);
        }

        return $note;
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
    public function getInfo() {
        return $this->info;
    }

    /**
     *
     */
    public function getData() {
        return $this->data;
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
    public function addReference($info) {
        $reference = new refnotes_reference($this->scope, $this, $info);

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
