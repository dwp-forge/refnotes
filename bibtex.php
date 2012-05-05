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
        $this->Lexer = new refnotes_bibtex_lexer($this->Handler, 'base', true);

        $this->addBibtexMode(new refnotes_bibtex_outside_mode());
        $this->addBibtexMode(new refnotes_bibtex_entry_mode('parented'));
        $this->addBibtexMode(new refnotes_bibtex_entry_mode('braced'));
        $this->addBibtexMode(new refnotes_bibtex_field_mode());
        $this->addBibtexMode(new refnotes_bibtex_integer_value_mode());
        $this->addBibtexMode(new refnotes_bibtex_string_value_mode('quoted'));
        $this->addBibtexMode(new refnotes_bibtex_string_value_mode('braced'));
        $this->addBibtexMode(new refnotes_bibtex_nested_braces_mode('quoted'));
        $this->addBibtexMode(new refnotes_bibtex_nested_braces_mode('braced'));
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
class refnotes_bibtex_lexer extends Doku_Lexer {

    /**
     *
     */
    public function parse($text) {
        $lastMode = '';

        while (is_array($parsed = $this->_reduce($text))) {
            list($unmatched, $matched, $mode) = $parsed;

            if (!$this->_dispatchTokens($unmatched, $matched, $mode, 0, 0)) {
                return false;
            }

            if (empty($unmatched) && empty($matched) && ($lastMode == $this->_mode->getCurrent())) {
                return false;
            }

            $lastMode = $this->_mode->getCurrent();
        }

        if (!$parsed) {
            return false;
        }

        return $this->_invokeParser($text, DOKU_LEXER_UNMATCHED, 0);
    }

    /**
     *
     */
    function _invokeParser($text, $state, $pos) {
        if (($text == "") && ($state == DOKU_LEXER_UNMATCHED)) {
            return true;
        }

        $mode = $this->_mode->getCurrent();
        $handler = isset($this->_mode_handlers[$mode]) ? $this->_mode_handlers[$mode] : $mode;

        return $this->_parser->$handler($text, $state);
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

        $this->specialPattern[] = '[^@]+(?=@)';
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

        $this->entryPattern[] = '^@\w+\s*' . $open . '(?=.*' . $close . ')';
        $this->exitPattern[] = '\s*(?:' . $close . '|(?=@))';

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
        $this->exitPattern[] = '\s*(?:,|(?=[\)}@]))';

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

        list($open, $close, $exit) = ($type == 'quoted') ? array('"', '"', '"') : array('{', '}', '(?:}|(?=@))');

        $this->entryPattern[] = '^' . $open . '(?=.*' . $close . ')';
        $this->exitPattern[] = $exit;

        $this->allowedModes = array('nested_braces_' . $type);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_nested_braces_mode extends refnotes_bibtex_mode {

    /**
     * Constructor
     */
    public function __construct($type) {
        parent::__construct();

        $this->handler = $this->name;
        $this->name .= '_' . $type;

        $this->entryPattern[] = '{(?=.*})';
        $this->exitPattern[] = ($type == 'quoted') ? '}' : '(?:}|(?=@))';

        $this->allowedModes = array($this->name);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_handler {

    private $entries;
    private $entry;
    private $field;

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
        $this->entries = new refnotes_bibtex_entry_stash();
        $this->entry = NULL;
        $this->field = NULL;
    }

    /**
     *
     */
    public function finalize() {
        return $this->entries->getEntries();
    }

    /**
     *
     */
    public function outside($match, $state) {
        /* Ignore everything outside the entries */
        return true;
    }

    /**
     *
     */
    public function entry($match, $state) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $this->entry = new refnotes_bibtex_entry(preg_replace('/@(\w+)\W+/', '$1', $match));
                break;

            case DOKU_LEXER_UNMATCHED:
                $this->entry->handleUnmatched($match);
                break;

            case DOKU_LEXER_EXIT:
                $this->entries->add($this->entry);
                $this->entry = NULL;
                break;
        }

        return true;
    }

    /**
     *
     */
    public function field($match, $state) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $this->field = new refnotes_bibtex_field(preg_replace('/\W*(\w+)\W*/', '$1', $match));
                break;

            case DOKU_LEXER_UNMATCHED:
                $this->field->handleUnmatched($match);
                break;

            case DOKU_LEXER_EXIT:
                $this->entry->addField($this->field);
                $this->field = NULL;
                break;
        }

        return true;
    }

    /**
     *
     */
    public function integer_value($match, $state) {
        $this->field->handleIntegerValue($match, $state);

        return true;
    }

    /**
     *
     */
    public function string_value($match, $state) {
        $this->field->handleStringValue($match, $state);

        return true;
    }

    /**
     *
     */
    public function nested_braces($match, $state) {
        $this->field->handleNestedBraces($match, $state);

        return true;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_entry_stash {

    private $entry;
    private $namespace;

    /**
     * Constructor
     */
    public function __construct() {
        $this->entry = array();
        $this->namespace = ':';
    }

    /**
     *
     */
    public function getEntries() {
        return $this->entry;
    }

    /**
     *
     */
    public function add($entry) {
        $name = $entry->getName();

        if ($this->isValidName($name)) {
            if ($name{0} != ':') {
                $name = $this->namespace . $name;
            }

            $this->entry[] = array_merge(array('note-name' => $name), $entry->getData());
        }
    }

    /**
     *
     */
    private function isValidName($name) {
        return preg_match('/(?:(?:[[:alpha:]]\w*)?:)*[[:alpha:]]\w*/', $name) == 1;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_bibtex_entry {

    private $type;
    private $name;
    private $field;

    /**
     * Constructor
     */
    public function __construct($type) {
        $this->type = strtolower($type);
        $this->name = '';
        $this->field = array();
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
        $data = array();

        foreach ($this->field as $field) {
            $data[$field->getName()] = $field->getValue();
        }

        return $data;
    }

    /**
     *
     */
    public function handleUnmatched($token) {
        if (($this->name == '') && (preg_match('/\s*([\w:]+)\s*,/', $token, $match) == 1)) {
            $this->name = $match[1];
        }
    }

    /**
     *
     */
    public function addField($field) {
        $this->field[] = $field;
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
        return preg_replace('/\s+/', ' ', trim($this->value));
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
