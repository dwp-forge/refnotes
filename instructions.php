<?php

/**
 * Plugin RefNotes: Handling of instruction array
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if (!defined('DOKU_INC') || !defined('DOKU_PLUGIN')) die();

//////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_instruction {
    protected $call;

    /**
     * Constructor
     */
    public function __construct($name, $data, $offset = -1) {
        $this->call = array($name, $data, $offset);
    }

    /**
     *
     */
    public function getCall() {
        return $this->call;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_plugin_instruction extends refnotes_instruction {

    /**
     * Constructor
     */
    public function __construct($name, $data, $type, $text, $offset = -1) {
        parent::__construct('plugin', array($name, $data, $type, $text), $offset);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_notes_instruction extends refnotes_plugin_instruction {

    /**
     * Constructor
     */
    public function __construct($type, $attributes, $data = NULL) {
        $pluginData[0] = $type;
        $pluginData[1] = $attributes;

        if (!empty($data)) {
            $pluginData[2] = $data;
        }

        parent::__construct('refnotes_notes', $pluginData, DOKU_LEXER_SPECIAL, '');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_notes_style_instruction extends refnotes_notes_instruction {

    /**
     * Constructor
     */
    public function __construct($namespace, $data) {
        parent::__construct('style', array('ns' => $namespace), $data);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_notes_render_instruction extends refnotes_notes_instruction {

    /**
     * Constructor
     */
    public function __construct($namespace) {
        parent::__construct('render', array('ns' => $namespace));
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_instruction_reference {
    private $call;
    private $index;
    private $name;

    /**
     * Constructor
     */
    public function __construct(&$call, $index) {
        $this->call =& $call;
        $this->index = $index;
        $this->name = ($call[0] == 'plugin') ? 'plugin_' . $call[1][0] : $call[0];
    }

    /**
     *
     */
    public function getIndex() {
        return $this->index;
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
    public function getData($index) {
        return $this->call[1][$index];
    }

    /**
     *
     */
    public function getPluginData($index) {
        return $this->call[1][1][$index];
    }

    /**
     *
     */
    public function setPluginData($index, $data) {
        $this->call[1][1][$index] = $data;
    }

    /**
     *
     */
    public function unsetPluginData($index) {
        unset($this->call[1][1][$index]);
    }

    /**
     *
     */
    public function getRefnotesAttribute($name) {
        return $this->call[1][1][1][$name];
    }

    /**
     *
     */
    public function setRefnotesAttribute($name, $value) {
        $this->call[1][1][1][$name] = $value;
    }

    /**
     *
     */
    public function getPrevious() {
        return $this->list->getAt($this->index - 1);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_instruction_list implements Iterator {
    private $event;
    private $index;
    private $extraCalls;

    /**
    * Constructor
    */
    public function __construct($event) {
        $this->event = $event;
        $this->index = 0;
        $this->extraCalls = array();
    }

    /**
     * Implementation of Iterator interface
     */
    function rewind() {
        $this->index = 0;
    }

    /**
     * Implementation of Iterator interface
     */
    function current() {
        return new refnotes_instruction_reference($this->event->data->calls[$this->index], $this->index);
    }

    /**
     * Implementation of Iterator interface
     */
    function key() {
        return $this->index;
    }

    /**
     * Implementation of Iterator interface
     */
    function next() {
        ++$this->index;
    }

    /**
     * Implementation of Iterator interface
     */
    function valid() {
        return isset($this->event->data->calls[$this->index]);
    }

    /**
     *
     */
    function getAt($index) {
        return $this->valid($index) ? new refnotes_instruction_reference($this->event->data->calls[$index], $index) : NULL;
    }

    /**
     *
     */
    function insert($index, $call) {
        $this->extraCalls[$index][] = $call->getCall();
    }

    /**
     *
     */
    function append($call) {
        $this->extraCalls[count($this->event->data->calls)][] = $call->getCall();
    }

    /**
     *
     */
    function applyChanges() {
        if (empty($this->extraCalls)) {
            return;
        }

        $calls = array();
        $prevIndex = 0;

        foreach ($this->extraCalls as $index => $extraCalls) {
            if ($prevIndex < $index) {
                $slice = array_slice($this->event->data->calls, $prevIndex, $index - $prevIndex);
                $calls = array_merge($calls, $slice);
            }

            $calls = array_merge($calls, $extraCalls);

            $prevIndex = $index;
        }

        $callCount = count($this->event->data->calls);

        if ($prevIndex < $callCount) {
            $slice = array_slice($this->event->data->calls, $prevIndex, $callCount - $prevIndex);
            $calls = array_merge($calls, $slice);
        }

        $offset = $this->event->data->calls[$callCount - 1][2];

        for ($i = count($calls) - 1; $i >= 0; --$i) {
            if ($calls[$i][2] == -1) {
                $calls[$i][2] = $offset;
            }
            else {
                $offset = $calls[$i][2];
            }
        }

        $this->event->data->calls = $calls;
        $this->extraCalls = array();
    }
}
