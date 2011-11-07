<?php

/**
 * Plugin RefNotes: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if (!defined('DOKU_INC') || !defined('DOKU_PLUGIN')) die();

require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/locale.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');
require_once(DOKU_PLUGIN . 'refnotes/core.php');
require_once(DOKU_PLUGIN . 'refnotes/database.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    private static $instance = NULL;

    private $mode;
    private $entryPattern;
    private $exitPattern;
    private $handlePattern;
    private $parsingContext;
    private $noteCapture;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = plugin_load('syntax', 'refnotes_references');
            if (self::$instance == NULL) {
                throw new Exception('Syntax plugin "refnotes_references" is not available or invalid.');
            }
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        refnotes_localization::initialize($this);

        $this->mode = substr(get_class($this), 7);
        $this->parsingContext = new refnotes_parsing_context_stack();
        $this->noteCapture = new refnotes_note_capture();

        $this->initializePatterns();
    }

    /**
     *
     */
    private function initializePatterns() {
        if (refnotes_configuration::getSetting('replace-footnotes')) {
            $entry = '(?:\(\(|\[\()';
            $exit = '(?:\)\)|\)\])';
            $name ='(?:@@FNT\d+|#\d+|[[:alpha:]]\w*)';
        }
        else {
            $entry = '\[\(';
            $exit = '\)\]';
            $name ='(?:#\d+|[[:alpha:]]\w*)';
        }

        $optionalNamespace ='(?:(?:[[:alpha:]]\w*)?:)*';
        $text = '.*?';

        $fullName = '\s*' . $optionalNamespace . $name .'\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $fullName . $lookaheadExit;

        $optionalFullName = $optionalNamespace . $name .'?';
        $structuredEntry = '\s*' . $optionalFullName . '\s*>>' . $text  . $lookaheadExit;

        $define = '\s*' . $optionalFullName . '\s*>\s*';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->entryPattern = $entry . '(?:' . $nameEntry . '|' . $structuredEntry . '|' . $defineEntry . ')';
        $this->exitPattern = $exit;
        $this->handlePattern = '/' . $entry . '\s*(' . $optionalFullName . ')\s*(>>)?(.*)/s';
    }

    /**
     * Return some info
     */
    public function getInfo() {
        return refnotes_getInfo('references syntax');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'formatting';
    }

    public function accepts($mode) {
        if ($mode == $this->mode) {
            return true;
        }

        return parent::accepts($mode);
    }

    /**
     * What modes are allowed within our mode?
     */
    public function getAllowedTypes() {
        return array (
            'formatting',
            'substition',
            'protected',
            'disabled'
        );
    }

    /**
     * Where to sort in?
     */
    public function getSort() {
        return 145;
    }

    public function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->entryPattern, $mode, $this->mode);
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->exitPattern, $this->mode);
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, $handler) {
        $result = $this->parsingContext->getCurrent()->canHandle($state);

        if ($result) {
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    $result = $this->handleEnter($match, $pos, $handler);
                    break;

                case DOKU_LEXER_EXIT:
                    $result = $this->handleExit($pos, $handler);
                    break;
            }
        }

        if ($result === false) {
            $handler->_addCall('cdata', array($match), $pos);
        }

        return $result;
    }

    /**
     * Create output
     */
    public function render($mode, $renderer, $data) {
        $result = false;

        try {
            switch ($mode) {
                case 'xhtml':
                    $result = $this->renderXhtml($renderer, $data);
                    break;

                case 'metadata':
                    $result = $this->renderMetadata($renderer, $data);
                    break;
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }

        return $result;
    }

    /**
     *
     */
    private function handleEnter($syntax, $pos, $handler) {
        if (preg_match($this->handlePattern, $syntax, $match) == 0) {
            return false;
        }

        $data = ($match[2] == '>>') ? $match[3] : '';
        $exitPos = $pos + strlen($syntax);

        $this->parsingContext->getCurrent()->enterReference($match[1], $data, $exitPos);

        return array(DOKU_LEXER_ENTER);
    }

    /**
     *
     */
    private function handleExit($pos, $handler) {
        $parsingContext = $this->parsingContext->getCurrent();

        $textDefined = $parsingContext->isTextDefined($pos);
        $reference = $parsingContext->getReferenceInfo();
        $note = $parsingContext->getNoteData();

        if (!$textDefined && $reference->isNamed()) {
            $database = refnotes_reference_database::getInstance();
            $name = $reference->getFullName();

            if ($database->isDefined($name)) {
                $reference->updateInfo($database->getNoteInfo($name));

                $note = $database->getNoteData($name)->updateData($note);
            }
        }

        if (!$note->isEmpty()) {
            $text = $parsingContext->getCore()->renderNoteText($reference->getNamespace(), $note->getData());

            if ($text != '') {
                $this->parseNestedText($text, $pos, $handler);
            }

            $reference->setData($note->getData());
        }

        $parsingContext->exitReference();

        return array(DOKU_LEXER_EXIT, $reference->getInfo());
    }

    /**
     *
     */
    private function parseNestedText($text, $pos, $handler) {
        $nestedWriter = new refnotes_nested_call_writer($handler->CallWriter);
        $callWriterBackup = $handler->CallWriter;
        $handler->CallWriter = $nestedWriter;

        /*
            HACK: If doku.php parses a number of pages during one call (it's common after the cache
            clean-up) $this->Lexer can be a different instance from the one used in the current parser
            pass. Here we ensure that $handler is linked to $this->Lexer while parsing the nested text.
        */
        $handlerBackup = $this->Lexer->_parser;
        $this->Lexer->_parser = $handler;

        $this->Lexer->parse($text);

        $this->Lexer->_parser = $handlerBackup;
        $handler->CallWriter = $callWriterBackup;

        $nestedWriter->process($pos);
    }

    /**
     *
     */
    public function renderXhtml($renderer, $data) {
        switch ($data[0]) {
            case DOKU_LEXER_ENTER:
                $this->renderXhtmlEnter($renderer);
                break;

            case DOKU_LEXER_EXIT:
                $this->renderXhtmlExit($renderer, $data[1]);
                break;
        }

        return true;
    }

    /**
     * Starts renderer output capture
     */
    private function renderXhtmlEnter($renderer) {
        $this->noteCapture->start($renderer);
    }

    /**
     * Stops renderer output capture and renders the reference link
     */
    private function renderXhtmlExit($renderer, $info) {
        $reference = refnotes_syntax_core::getInstance()->addReference($info);
        $text = $this->noteCapture->stop();

        if ($text != '') {
            $reference->getNote()->setText($text);
        }

        $renderer->doc .= $reference->render();
    }

    /**
     *
     */
    public function renderMetadata($renderer, $data) {
        if ($data[0] == DOKU_LEXER_EXIT) {
            $source = '';

            if (array_key_exists('source', $data[1])) {
                $source = $data[1]['source'];
            }

            if (($source != '') && ($source != '{configuration}')) {
                $renderer->meta['plugin']['refnotes']['dbref'][wikiFN($source)] = true;
            }
        }

        return true;
    }

    /**
     *
     */
    public function enterParsingContext() {
        $this->parsingContext->enterContext();
    }

    /**
     *
     */
    public function exitParsingContext() {
        $this->parsingContext->exitContext();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parsing_context_stack {

    private $context;

    /**
     * Constructor
     */
    public function __construct() {
        /* Default context. Should never be used, but just in case... */
        $this->context = array(new refnotes_parsing_context());
    }

    /**
     *
     */
    public function enterContext() {
        $this->context[] = new refnotes_parsing_context();
    }

    /**
     *
     */
    public function exitContext() {
        unset($this->context[count($this->context) - 1]);
    }

    /**
     *
     */
    public function getCurrent() {
        return end($this->context);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parsing_context {

    private $core;
    private $handling;
    private $exitPos;
    private $info;
    private $data;

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize();
    }

    /**
     *
     */
    private function initialize() {
        $this->core = new refnotes_action_core();
        $this->handling = false;
        $this->exitPos = -1;
        $this->info = NULL;
        $this->data = NULL;
    }

    /**
     *
     */
    public function getCore() {
        return $this->core;
    }

    /**
     *
     */
    public function canHandle($state) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $result = !$this->handling;
                break;

            case DOKU_LEXER_EXIT:
                $result = $this->handling;
                break;

            default:
                $result = false;
                break;
        }

        return $result;
    }

    /**
     *
     */
    public function enterReference($name, $data, $exitPos) {
        $this->handling = true;
        $this->exitPos = $exitPos;
        $this->info = new refnotes_reference_info($name);
        $this->data = new refnotes_note_data($data);
    }

    /**
     *
     */
    public function exitReference() {
        $this->initialize();
    }

    /**
     *
     */
    public function isTextDefined($pos) {
        return $pos > $this->exitPos;
    }

    /**
     *
     */
    public function getReferenceInfo() {
        return $this->info;
    }

    /**
     *
     */
    public function getNoteData() {
        return $this->data;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_info {

    private $info;

    /**
     * Constructor
     */
    public function __construct($name) {
        list($namespace, $name) = refnotes_parseName($name);

        $this->info = array('ns' => $namespace, 'name' => $name);
    }

    /**
     *
     */
    public function isNamed() {
        return ($this->info['name'] != '') && ($this->info['name']{0} != '#');
    }

    /**
     *
     */
    public function getNamespace() {
        return $this->info['ns'];
    }

    /**
     *
     */
    public function getFullName() {
        return $this->info['ns'] . $this->info['name'];
    }

    /**
     *
     */
    public function updateInfo($info) {
        static $key = array('inline', 'source');

        foreach ($key as $k) {
            if (isset($info[$k])) {
                $this->info[$k] = $info[$k];
            }
        }
    }


    /**
     *
     */
    public function setData($data) {
        static $key = array('authors', 'page');

        foreach ($key as $k) {
            if (isset($data[$k])) {
                $this->info[$k] = $data[$k];
            }
        }
    }

    /**
     *
     */
    public function getInfo() {
        return $this->info;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_data {

    private $data;

    /**
     * Constructor
     */
    public function __construct($data) {
        if (is_array($data)) {
            $this->data = $data;
        }
        elseif ($data != '') {
            $this->data = $this->parseStructuredData($data);
        }
        else {
            $this->data = array();
        }
    }

    /**
     *
     */
    private function parseStructuredData($syntax) {
        preg_match_all('/([-\w]+)\s*[:=]\s*(.+?)\s*?(:?[\n|;]|$)/', $syntax, $match, PREG_SET_ORDER);

        $data = array();

        foreach ($match as $m) {
            $data[$m[1]] = $m[2];
        }

        return $data;
    }

    /**
     *
     */
    public function updateData($data) {
        $this->data = array_merge($this->data, $data->getData());

        return $this;
    }

    /**
     *
     */
    public function isEmpty() {
        return empty($this->data);
    }

    /**
     *
     */
    public function getData() {
        return $this->data;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_nested_call_writer extends Doku_Handler_Nest {

    /**
     *
     */
    public function process($pos) {
        $this->CallWriter->writeCall(array("nest", array($this->calls), $pos));
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_capture {

    private $renderer;
    private $note;
    private $doc;

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize();
    }

    /**
     *
     */
    private function initialize() {
        $this->renderer = NULL;
        $this->doc = '';
    }

    /**
     *
     */
    private function resetCapture() {
        $this->renderer->doc = '';
    }

    /**
     *
     */
    public function start($renderer) {
        $this->renderer = $renderer;
        $this->doc = $renderer->doc;

        $this->resetCapture();
    }

    /**
     *
     */
    public function restart() {
        $text = trim($this->renderer->doc);

        $this->resetCapture();

        return $text;
    }

    /**
     *
     */
    public function stop() {
        $text = trim($this->renderer->doc);

        $this->renderer->doc = $this->doc;

        $this->initialize();

        return $text;
    }
}
