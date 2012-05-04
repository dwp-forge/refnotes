<?php

/**
 * Plugin RefNotes: BibTeX parser
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

require_once(DOKU_INC . 'inc/parser/parser.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_parser extends Doku_Parser {

    private static $instance = NULL;

    private $handler;
    private $lexer;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_bibtex_parser();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->Handler = new refnotes_bibtex_handler();

        $this->addBibtexMode(new refnotes_bibtex_outside_mode());
        $this->addBibtexMode(new refnotes_bibtex_entry_mode('parented'));
        $this->addBibtexMode(new refnotes_bibtex_entry_mode('braced'));
        $this->addBibtexMode(new refnotes_bibtex_field_mode());
        $this->addBibtexMode(new refnotes_bibtex_integer_value_mode());
        $this->addBibtexMode(new refnotes_bibtex_string_value_mode('quoted'));
        $this->addBibtexMode(new refnotes_bibtex_string_value_mode('braced'));
        $this->addBibtexMode(new refnotes_bibtex_nested_braces_mode());
    }

    /**
     *
     */
    private function addBibtexMode($mode) {
        $this->addMode($mode->getName(), $mode);
    }

    /**
     *
     */
    public function connectModes() {
        if (!$this->connected) {
            $this->modes['outside']->connectTo('base');
            $this->modes['entry_parented']->connectTo('base');
            $this->modes['entry_braced']->connectTo('base');

            parent::connectModes();
        }
    }

    /**
     *
     */
    public function parse($text) {
        $this->connectModes();

        $this->Handler->reset();
        $this->Lexer->parse(str_replace("\r\n", "\n", $text));

        return $this->Handler->finalize();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_mode extends Doku_Parser_Mode {

    protected $name;
    protected $handler;
    protected $specialPattern;
    protected $entryPattern;
    protected $exitPattern;

    /**
     * Constructor
     */
    public function __construct() {
        $this->name = preg_replace('/refnotes_bibtex_(\w+)_mode/', '$1', get_class($this));
        $this->handler = '';

        $this->specialPattern = array();
        $this->entryPattern = array();
        $this->exitPattern = array();
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
    public function connectTo($mode) {
        foreach ($this->specialPattern as $pattern) {
            $this->Lexer->addSpecialPattern($pattern, $mode, $this->name);
        }

        foreach ($this->entryPattern as $pattern) {
            $this->Lexer->addEntryPattern($pattern, $mode, $this->name);
        }

        if ($this->handler != '') {
            $this->Lexer->mapHandler($this->name, $this->handler);
        }
    }

    /**
     *
     */
    public function postConnect() {
        foreach ($this->exitPattern as $pattern) {
            $this->Lexer->addExitPattern($pattern, $this->name);
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_outside_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->specialPattern[] = '(?:^[ \t]*)?\n';
    }

    /**
     *
     */
    public function connectTo($mode) {
        parent::connectTo($mode);

        $this->Lexer->mapHandler('base', $this->name);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_entry_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct($type) {
        parent::__construct();

        $this->handler = $this->name;
        $this->name .= '_' . $type;

        list($open, $close) = ($type == 'parented') ? array('\(', '\)') : array('{', '}');

        $this->entryPattern[] = '@\w+' . $open . '(?=.*' . $close . ')';
        $this->exitPattern[] = '\s*' . $close;

        $this->allowedModes = array('field');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_field_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->entryPattern[] = '^\s*\w+\s*=\s*';
        $this->exitPattern[] = '\s*(?:,|(?=[\)}]))';

        $this->allowedModes = array('integer_value', 'string_value_quoted', 'string_value_braced');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_integer_value_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->specialPattern[] = '^\d+';
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_string_value_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct($type) {
        parent::__construct();

        $this->handler = $this->name;
        $this->name .= '_' . $type;

        list($open, $close) = ($type == 'quoted') ? array('"', '"') : array('{', '}');

        $this->entryPattern[] = '^' . $open . '(?=.*' . $close . ')';
        $this->exitPattern[] = $close;

        $this->allowedModes = array('nested_braces');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_nested_braces_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->entryPattern[] = '{(?=.*})';
        $this->exitPattern[] = '}';

        $this->allowedModes = array('nested_braces');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_handler {

    private $entry;
    private $currentEntry;
    private $currentField;

    /**
     * Constructor
     */
    public function __construct() {
        $this->reset();
    }

    /**
     *
     */
    public function reset() {
        $this->entry = array();
        $this->currentEntry = NULL;
        $this->currentField = NULL;
    }

    /**
     *
     */
    public function finalize() {
        error_log(print_r($this->entry, true));

        return $this->entry;
    }

    /**
     *
     */
    private function finalizeEntry() {
        if ($this->currentField != NULL) {
            $this->finalizeField();
        }

        $this->entry[] = $this->currentEntry;
        $this->currentEntry = NULL;
    }

    /**
     *
     */
    private function finalizeField() {
        $this->currentField->finalize();
        $this->currentEntry->addField($this->currentField);
        $this->currentField = NULL;
    }

    /**
     *
     */
    public function outside($match, $state, $pos) {
        /* Ignore everything outside the entries */
        return true;
    }

    /**
     *
     */
    public function entry($match, $state, $pos) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $this->currentEntry = new refnotes_bibtex_entry(preg_replace('/@(\w+)\W/', '$1', $match));
                break;

            case DOKU_LEXER_UNMATCHED:
                $this->currentEntry->handleUnmatched($match);
                break;

            case DOKU_LEXER_EXIT:
                $this->finalizeEntry();
                break;
        }

        return true;
    }

    /**
     *
     */
    public function field($match, $state, $pos) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $this->currentField = new refnotes_bibtex_field(preg_replace('/\W*(\w+)\W*/', '$1', $match));
                break;

            case DOKU_LEXER_UNMATCHED:
                $this->currentField->handleUnmatched($match);
                break;

            case DOKU_LEXER_EXIT:
                $this->finalizeField();
                break;
        }

        return true;
    }

    /**
     *
     */
    public function integer_value($match, $state, $pos) {
        $this->currentField->handleIntegerValue($match, $state);

        return true;
    }

    /**
     *
     */
    public function string_value($match, $state, $pos) {
        $this->currentField->handleStringValue($match, $state);

        return true;
    }

    /**
     *
     */
    public function nested_braces($match, $state, $pos) {
        $this->currentField->handleNestedBraces($match, $state);

        return true;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_entry {

    private $type;
    private $name;
    private $data;

    /**
     * Constructor
     */
    public function __construct($type) {
        $this->type = strtolower($type);
        $this->name = '';
        $this->data = array();
    }

    /**
     *
     */
    public function getType() {
        return $this->type;
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
    public function getData() {
        return $this->data;
    }

    /**
     *
     */
    public function handleUnmatched($token) {
        if (($this->name == '') && (preg_match('/\s*(\w+)\s*,/', $token, $match) == 1)) {
            $this->name = $match[1];
        }
    }

    /**
     *
     */
    public function addField($field) {
        $this->data[$field->getName()] = $field->getValue();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_field {

    private $name;
    private $value;

    /**
     * Constructor
     */
    public function __construct($name) {
        $this->name = strtolower($name);
        $this->value = '';
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
    public function getValue() {
        return $this->value;
    }

    /**
     *
     */
    public function finalize() {
        $this->value = preg_replace('/\s+/', ' ', trim($this->value));
    }

    /**
     *
     */
    public function handleUnmatched($token) {
        $this->value .= $token;
    }

    /**
     *
     */
    public function handleIntegerValue($token, $state) {
        $this->value = $token;
    }

    /**
     *
     */
    public function handleStringValue($token, $state) {
        if ($state == DOKU_LEXER_UNMATCHED) {
            $this->handleUnmatched($token);
        }
    }

    /**
     *
     */
    public function handleNestedBraces($token, $state) {
        if ($state == DOKU_LEXER_UNMATCHED) {
            $this->handleUnmatched($token);
        }
    }
}
