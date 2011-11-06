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
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note {

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
    public function __construct($scope, $name, $inline) {
        $this->scope = $scope;
        $this->id = $inline ? 0 : $scope->getNoteId();

        if ($name != '') {
            $this->name = $name;
        }
        else {
            $this->name = '#' . $id;
        }

        $this->inline = $inline;
        $this->reference = array();
        $this->text = '';
        $this->rendered = false;
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
    public function addReference($reference) {
        $this->reference[] = $reference;
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
