<?php

/**
 * Plugin RefNotes: Common base class for references and notes
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_refnote {

    protected $attributes;
    protected $data;

    /**
     * Constructor
     */
    public function __construct($attributes = array(), $data = array()) {
        $this->attributes = $attributes;
        $this->data = $data;
    }

    /**
     *
     */
    public function getAttributes() {
        return $this->attributes;
    }

    /**
     *
     */
    public function getData() {
        return $this->data;
    }
}