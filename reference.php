<?php

/**
 * Plugin RefNotes: Reference
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference {

    private $namespace;
    private $name;
    private $inline;
    private $hidden;
    private $data;
    private $scope;
    private $note;
    private $id;

    /**
     * Constructor
     */
    public function __construct($info) {
        $this->namespace = $info['ns'];
        $this->name = $info['name'];
        $this->inline = isset($info['inline']) ? $info['inline'] : false;
        $this->hidden = isset($info['hidden']) ? $info['hidden'] : false;
        $this->data = $info;
        $this->scope = NULL;
        $this->note = NULL;
        $this->id = 0;

        if (preg_match('/(?:@@FNT|#)(\d+)/', $this->name, $match) == 1) {
            $this->name = intval($match[1]);
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
    public function getData() {
        return $this->data;
    }

    /**
     *
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->scope->getName();
        $result .= ':ref' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function getNote() {
        $result = $this->note;

        if ($result == NULL) {
            $result = new refnotes_note_mock();
        }

        return $result;
    }

    /**
     *
     */
    public function joinScope($scope) {
        $note = $scope->findNote($this->name);

        if (($note == NULL) && !is_int($this->name)) {
            $note = new refnotes_note($scope, $this->name, $this->inline);

            $scope->addNote($note);
        }

        if (($note != NULL) && !$this->hidden && !$this->inline) {
            $this->id = $scope->getReferenceId();

            $note->addReference($this);
        }

        $this->scope = $scope;
        $this->note = $note;
    }

    /**
     *
     */
    public function render() {
        $html = '';

        if (($this->note != NULL) && !$this->hidden) {
            if ($this->inline) {
                $html = '<sup>' . $this->note->getText() . '</sup>';
            }
            else {
                $html = $this->scope->getRenderer()->renderReference($this);
            }
        }

        return $html;
    }
}
