<?php

/**
 * Plugin RefNotes: Reference
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parser_reference extends refnotes_refnote {

    /**
     * Constructor
     */
    public function __construct($name, $data) {
        list($namespace, $name) = refnotes_namespace::parseName($name);

        if (preg_match('/(?:@@FNT|#)(\d+)/', $name, $match) == 1) {
            $name = intval($match[1]);
        }

        parent::__construct(array('ns' => $namespace, 'name' => $name));

        if ($data != '') {
            $this->parseStructuredData($data);
        }
    }

    /**
     *
     */
    private function parseStructuredData($data) {
        if (preg_match('/^\s*\|/', $data) == 1) {
            preg_match_all('/\|\s*([-\w]+)\s*=\s*([^|]+)/', $data, $match, PREG_SET_ORDER);

            foreach ($match as $m) {
                $this->data[$m[1]] = preg_replace('/\s+/', ' ', trim($m[2]));
            }
        }
        else {
            preg_match_all('/([-\w]+)\s*:\s*(.+?)\s*?(?:(?<!\\\\);|\n|$)/', $data, $match, PREG_SET_ORDER);

            foreach ($match as $m) {
                $this->data[$m[1]] = str_replace('\\;', ';', $m[2]);
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference extends refnotes_refnote {

    protected $inline;
    protected $hidden;
    protected $note;
    protected $id;

    /**
     * Constructor
     */
    public function __construct($note, $attributes, $data) {
        parent::__construct($attributes, $data);

        $this->inline = $this->getAttribute('inline', false);
        $this->hidden = $this->getAttribute('hidden', false);
        $this->note = $note;
        $this->id = -1;

        if ($this->isBackReferenced()) {
            $this->id = $this->note->getScope()->getReferenceId();
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
    public function getNote() {
        return $this->note;
    }

    /**
     *
     */
    public function isInline() {
        return $this->inline;
    }

    /**
     *
     */
    public function isBackReferenced() {
        return !$this->inline && !$this->hidden;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer_reference extends refnotes_reference {

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->note->getScope()->getName();
        $result .= ':ref' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function render($mode) {
        $doc = '';

        if (!$this->hidden) {
            $doc = $this->note->getScope()->getRenderer()->renderReference($mode, $this);
        }

        return $doc;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_action_reference extends refnotes_reference {

    private $call;

    /**
     * Constructor
     */
    public function __construct($note, $attributes, $data, $call) {
        parent::__construct($note, $attributes, $data);

        $this->call = $call;
    }

    /**
     *
     */
    private function updateAttributes($attributes) {
        static $key = array('inline', 'use-reference-base', 'use-reference-font-weight', 'use-reference-font-style', 'use-reference-format', 'source');

        foreach ($key as $k) {
            if (array_key_exists($k, $attributes)) {
                $this->attributes[$k] = $attributes[$k];
            }
        }
    }

    /**
     *
     */
    private function updateData($data) {
        $include = $this->note->getScope()->getRenderer()->getReferenceSharedDataSet();
        $data = array_intersect_key($data, array_flip($include));
        $this->data = array_merge($data, $this->data);
    }

    /**
     *
     */
    public function rewrite($attributes, $data) {
        $this->updateAttributes($attributes);
        $this->updateData($data);

        $this->call->setPluginData(1, $this->attributes);

        if ($this->hasData()) {
            $this->call->setPluginData(2, $this->data);
        }
    }

    /**
     *
     */
    public function setNoteText($text) {
        if ($text != '') {
            $calls = refnotes_parser_core::getInstance()->getInstructions($text);

            $this->call->insertBefore(new refnotes_nest_instruction($calls));
        }
    }
}
