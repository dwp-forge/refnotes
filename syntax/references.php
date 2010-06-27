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
require_once(DOKU_PLUGIN . 'refnotes/helper.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    private static $instance = NULL;

    private $mode;
    private $entryPattern;
    private $exitPattern;
    private $handlePattern;
    private $locale;
    private $noteRenderer;
    private $database;
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
        $this->mode = substr(get_class($this), 7);
        $this->locale = NULL;
        $this->noteRenderer = NULL;
        $this->database = NULL;
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
     *
     */
    private function getLocale() {
        if ($this->locale == NULL) {
            $this->locale = new refnotes_localization($this);
        }

        return $this->locale;
    }

    /**
     *
     */
    private function getNoteRenderer() {
        if ($this->noteRenderer == NULL) {
            $this->noteRenderer = new refnotes_note_renderer($this->getLocale());
        }

        return $this->noteRenderer;
    }

    /**
     *
     */
    private function getDatabase() {
        if ($this->database == NULL) {
            $this->database = new refnotes_reference_database_mock();
            $this->database = new refnotes_reference_database($this->getLocale());
        }

        return $this->database;
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
        $result = $this->parsingContext->canHandle($state);

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

        $this->parsingContext->enterReference($match[1], $data, $exitPos);

        return array(DOKU_LEXER_ENTER);
    }

    /**
     *
     */
    private function handleExit($pos, $handler) {
        $textDefined = $this->parsingContext->isTextDefined($pos);
        $reference = $this->parsingContext->getReferenceInfo();
        $note = $this->parsingContext->getNoteData();

        if (!$textDefined && $reference->isNamed()) {
            $database = $this->getDatabase();
            $name = $reference->getFullName();

            if ($database->isDefined($name)) {
                $reference->updateInfo($database->getNoteInfo($name));

                $note = $database->getNoteData($name)->updateData($note);
            }
        }

        if (!$note->isEmpty()) {
            $text = $this->getNoteRenderer()->render($note->getData());

            if ($text != '') {
                $this->parseNestedText($text, $pos, $handler);
            }
        }

        $this->parsingContext->exitReference();

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
        $hidden = isset($info['hidden']) ? $info['hidden'] : false;
        $inline = isset($info['inline']) ? $info['inline'] : false;
        $core = helper_plugin_refnotes::getInstance();
        $note = $core->addReference($info['ns'], $info['name'], $hidden, $inline);
        $text = $this->noteCapture->stop();

        if ($note != NULL) {
            if ($text != '') {
                $note->setText($text);
            }

            if (!$hidden) {
                $renderer->doc .= $note->renderReference();
            }
        }
    }

    /**
     *
     */
    public function renderMetadata($renderer, $data) {
        if ($data[0] == DOKU_LEXER_EXIT) {
            $source = '';

            if ( array_key_exists('source', $data[1])) {
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
    public function canHandle($state) {
        return end($this->context)->canHandle($state);
    }

    /**
     *
     */
    public function enterReference($name, $data, $exitPos) {
        end($this->context)->enterReference($name, $data, $exitPos);
    }

    /**
     *
     */
    public function exitReference() {
        end($this->context)->exitReference();
    }

    /**
     *
     */
    public function isTextDefined($pos) {
        return end($this->context)->isTextDefined($pos);
    }

    /**
     *
     */
    public function getReferenceInfo() {
        return end($this->context)->getReferenceInfo();
    }

    /**
     *
     */
    public function getNoteData() {
        return end($this->context)->getNoteData();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parsing_context {

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
        $this->handling = false;
        $this->exitPos = -1;
        $this->info = NULL;
        $this->data = NULL;
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

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_renderer {

    private $renderer;

    /**
     * Constructor
     */
    public function __construct($locale) {
        $this->renderer['basic'] = new refnotes_basic_note_renderer();
        $this->renderer['harvard'] = new refnotes_harvard_note_renderer($locale);
    }

    /**
     *
     */
    public function render($field) {
        $renderer = '';

        if (array_key_exists('note-text', $field)) {
            $renderer = 'basic';
        }
        elseif (array_key_exists('title', $field)) {
            $renderer = 'harvard';
        }

        if ($renderer != '') {
            $text = $this->renderer[$renderer]->render($field);
        }
        else {
            $text = '';
        }

        return $text;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_basic_note_renderer {

    /**
     *
     */
    public function render($field) {
        $text = $field['note-text'];

        if (array_key_exists('url', $field)) {
            $text = '[[' . $field['url'] . '|' . $text . ']]';
        }

        return $text;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_harvard_note_renderer {

    private $locale;

    /**
     * Constructor
     */
    public function __construct($locale) {
        $this->locale = $locale;
    }

    /**
     *
     */
    public function render($field) {
        // authors, published. //[[url|title.]]// edition. publisher, pages, isbn.
        // authors, published. chapter In //[[url|title.]]// edition. publisher, pages, isbn.
        // authors, published. [[url|title.]] //journal//, volume, publisher, pages, issn.

        $title = $this->renderTitle($field);

        // authors, published. //$title// edition. publisher, pages, isbn.
        // authors, published. chapter In //$title// edition. publisher, pages, isbn.
        // authors, published. $title //journal//, volume, publisher, pages, issn.

        $authors = $this->renderAuthors($field);

        // $authors? //$title// edition. publisher, pages, isbn.
        // $authors? chapter In //$title// edition. publisher, pages, isbn.
        // $authors? $title //journal//, volume, publisher, pages, issn.

        $publication = $this->renderPublication($field, $authors != '');

        if (array_key_exists('journal', $field)) {
            // $authors? $title //journal//, volume, $publication?

            $text = $title . ' ' . $this->renderJournal($field);

            // $authors? $text, $publication?

            $text .= ($publication != '') ? ',' : '.';
        }
        else {
            // $authors? //$title// edition. $publication?
            // $authors? chapter In //$title// edition. $publication?

            $text = $this->renderBook($field, $title);
        }

        // $authors? $text $publication?

        if ($authors != '') {
            $text = $authors . ' ' . $text;
        }

        if ($publication != '') {
            $text .= ' ' . $publication;
        }

        return $text;
    }

    /**
     *
     */
    private function renderTitle($field) {
        $text = $field['title'] . '.';

        if (array_key_exists('url', $field)) {
            $text = '[[' . $field['url'] . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    private function renderAuthors($field) {
        $text = '';

        if (array_key_exists('authors', $field)) {
            $text = $field['authors'];

            if (array_key_exists('published', $field)) {
                $text .= ', ' . $field['published'];
            }

            $text .= '.';
        }

        return $text;
    }

    /**
     *
     */
    private function renderPublication($field, $authors) {
        $part = array();

        if (array_key_exists('publisher', $field)) {
            $part[] = $field['publisher'];
        }

        if (!$authors && array_key_exists('published', $field)) {
            $part[] = $field['published'];
        }

        if (array_key_exists('pages', $field)) {
            $part[] = $field['pages'];
        }

        if (array_key_exists('isbn', $field)) {
            $part[] = 'ISBN ' . $field['isbn'];
        }
        elseif (array_key_exists('issn', $field)) {
            $part[] = 'ISSN ' . $field['issn'];
        }

        $text = implode(', ', $part);

        if ($text != '') {
            $text = rtrim($text, '.') . '.';
        }

        return $text;
    }

    /**
     *
     */
    private function renderJournal($field) {
        $text = '//' . $field['journal'] . '//';

        if (array_key_exists('volume', $field)) {
            $text .= ', ' . $field['volume'];
        }

        return $text;
    }

    /**
     *
     */
    private function renderBook($field, $title) {
        $text = '//' . $title . '//';

        if (array_key_exists('chapter', $field)) {
            $text = $field['chapter'] . '. ' . $this->locale->getLang('txt_in_cap') . ' ' . $text;
        }

        if (array_key_exists('edition', $field)) {
            $text .= ' ' . $field['edition'] . '.';
        }

        return $text;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_database_mock {

    /**
     *
     */
    public function isDefined($name) {
        return false;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_database {

    private $note;
    private $key;
    private $page;
    private $namespace;

    /**
     * Constructor
     */
    public function __construct($locale) {
        $this->page = array();
        $this->namespace = array();

        $this->loadNotesFromConfiguration();

        if (refnotes_configuration::getSetting('reference-db-enable')) {
            $this->loadKeys($locale);
            $this->loadPages();
            $this->loadNamespaces();
        }
    }

    /**
     *
     */
    private function loadNotesFromConfiguration() {
        $note = refnotes_configuration::load('notes');

        foreach ($note as $name => $info) {
            $this->note[$name] = new refnotes_reference_database_note('{configuration}', $info);
        }
    }

    /**
     *
     */
    private function loadKeys($locale) {
        foreach ($locale->getByPrefix('dbk') as $key => $text) {
            $this->key[$this->normalizeKeyText($text)] = $key;
        }
    }

    /**
     *
     */
    public function getKey($text) {
        $result = '';
        $text = $this->normalizeKeyText($text);

        if (array_key_exists($text, $this->key)) {
            $result = $this->key[$text];
        }

        return $result;
    }

    /**
     *
     */
    private function normalizeKeyText($text) {
        return preg_replace('/\s+/', ' ', utf8_strtolower(trim($text)));
    }

    /**
     *
     */
    private function loadPages() {
        global $conf;

        if (file_exists($conf['indexdir'] . '/page.idx')) {
            require_once(DOKU_INC . 'inc/indexer.php');

            $pageIndex = idx_getIndex('page', '');
            $namespace = refnotes_configuration::getSetting('reference-db-namespace');
            $namespacePattern = '/^' . trim($namespace, ':') . ':/';
            $cache = new refnotes_reference_database_cache();

            foreach ($pageIndex as $pageId) {
                $pageId = trim($pageId);

                if ((preg_match($namespacePattern, $pageId) == 1) && file_exists(wikiFN($pageId))) {
                    $this->page[$pageId] = new refnotes_reference_database_page($this, $cache, $pageId);
                }
            }

            $cache->save();
        }
    }

    /**
     *
     */
    private function loadNamespaces() {
        foreach ($this->page as $pageId => $page) {
            foreach ($page->getNamespaces() as $ns) {
                $this->namespace[$ns][] = $pageId;
            }
        }
    }

    /**
     *
     */
    public function isDefined($name) {
        $result = array_key_exists($name, $this->note);

        if (!$result) {
            list($namespace, $temp) = refnotes_parseName($name);

            if (array_key_exists($namespace, $this->namespace)) {
                $this->loadNamespaceNotes($namespace);

                $result = array_key_exists($name, $this->note);
            }
        }

        return $result;
    }

    /**
     *
     */
    private function loadNamespaceNotes($namespace) {
        foreach ($this->namespace[$namespace] as $pageId) {
            if (array_key_exists($pageId, $this->page)) {
                $this->note = array_merge($this->note, $this->page[$pageId]->getNotes());

                unset($this->page[$pageId]);
            }
        }

        unset($this->namespace[$namespace]);
    }

    /**
     *
     */
    public function getNoteInfo($name) {
        return $this->note[$name]->getInfo();
    }

    /**
     *
     */
    public function getNoteData($name) {
        return $this->note[$name]->getData();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_database_page {

    private $database;
    private $id;
    private $fileName;
    private $namespace;
    private $note;

    /**
     * Constructor
     */
    public function __construct($database, $cache, $id) {
        $this->database = $database;
        $this->id = $id;
        $this->fileName = wikiFN($id);
        $this->namespace = array();
        $this->note = array();

        if ($cache->isCached($this->fileName)) {
            $this->namespace = $cache->getNamespaces($this->fileName);
        }
        else {
            $this->parse();

            $cache->update($this->fileName, $this->namespace);
        }
    }

    /**
     *
     */
    private function parse() {
        $text = io_readWikiPage($this->fileName, $this->id);
        $call = p_cached_instructions($this->fileName);
        $calls = count($call);

        for ($c = 0; $c < $calls; $c++) {
            if ($call[$c][0] == 'table_open') {
                $c = $this->parseTable($call, $calls, $c, $text);
            }
        }
    }

    /**
     *
     */
    private function parseTable($call, $calls, $c, $text) {
        $row = 0;
        $column = 0;
        $columns = 0;
        $valid = true;

        for ( ; $c < $calls; $c++) {
            switch ($call[$c][0]) {
                case 'tablerow_open':
                    $column = 0;
                    break;

                case 'tablerow_close':
                    if ($row == 0) {
                        $columns = $column;
                    }
                    else {
                        if ($column != $columns) {
                            $valid = false;
                            break 2;
                        }
                    }
                    $row++;
                    break;

                case 'tablecell_open':
                case 'tableheader_open':
                    $cellOpen = $call[$c][2];
                    break;

                case 'tablecell_close':
                case 'tableheader_close':
                    $table[$row][$column] = trim(substr($text, $cellOpen, $call[$c][2] - $cellOpen), "^| ");
                    $column++;
                    break;

                case 'table_close':
                    break 2;
            }
        }

        if ($valid && ($row > 1) && ($columns > 1)) {
            $this->handleTable($table, $columns, $row);
        }

        return $c;
    }

    /**
     *
     */
    private function handleTable($table, $columns, $rows) {
        $key = array();
        for ($c = 0; $c < $columns; $c++) {
            $key[$c] = $this->database->getKey($table[0][$c]);
        }

        if (!in_array('', $key)) {
            $this->handleDataSheet($table, $columns, $rows, $key);
        }
        else {
            if ($columns == 2) {
                $key = array();
                for ($r = 0; $r < $rows; $r++) {
                    $key[$r] = $this->database->getKey($table[$r][0]);
                }

                if (!in_array('', $key)) {
                    $this->handleDataCard($table, $rows, $key);
                }
            }
        }
    }

    /**
     * The data is organized in rows, one note per row. The first row contains the caption.
     */
    private function handleDataSheet($table, $columns, $rows, $key) {
        for ($r = 1; $r < $rows; $r++) {
            $field = array();

            for ($c = 0; $c < $columns; $c++) {
                $field[$key[$c]] = $table[$r][$c];
            }

            $this->handleNote($field);
        }
    }

    /**
     * Every note is stored in a separate table. The first column of the table contains
     * the caption, the second one contains the data.
     */
    private function handleDataCard($table, $rows, $key) {
        $field = array();

        for ($r = 0; $r < $rows; $r++) {
            $field[$key[$r]] = $table[$r][1];
        }

        $this->handleNote($field);
    }

    /**
     *
     */
    private function handleNote($field) {
        $note = new refnotes_reference_database_note($this->id, $field);

        list($namespace, $name) = $note->getNameParts();

        if ($name != '') {
            if (!in_array($namespace, $this->namespace)) {
                $this->namespace[] = $namespace;
            }

            $this->note[$namespace . $name] = $note;
        }
    }

    /**
     *
     */
    public function getNamespaces() {
        return $this->namespace;
    }

    /**
     *
     */
    public function getNotes() {
        if (empty($this->note)) {
            $this->parse();
        }

        return $this->note;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_database_note {

    private $nameParts;
    private $info;
    private $data;

    /**
     * Constructor
     */
    public function __construct($source, $data) {
        $this->nameParts = array('', '');

        if ($source == '{configuration}') {
            $this->initializeConfigNote($data);
        }
        else {
            $this->initializePageNote($data);
        }

        $this->info['source'] = $source;
    }

    /**
     *
     */
    public function initializeConfigNote($info) {
        $this->info = $info;
        $this->data = new refnotes_note_data(array('note-text' => $info['text']));

        unset($this->info['text']);
    }


    /**
     *
     */
    public function initializePageNote($data) {
        if (isset($data['note-name'])) {
            if (preg_match('/(?:(?:[[:alpha:]]\w*)?:)*[[:alpha:]]\w*/', $data['note-name']) == 1) {
                $this->nameParts = refnotes_parseName($data['note-name']);
            }

            unset($data['note-name']);
        }

        $this->info = array();
        $this->data = new refnotes_note_data($data);
    }

    /**
     *
     */
    public function getNameParts() {
        return $this->nameParts;
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
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_database_cache {

    private $fileName;
    private $cache;
    private $requested;
    private $updated;

    /**
     * Constructor
     */
    public function __construct() {
        $this->fileName = DOKU_PLUGIN . 'refnotes/database.dat';

        $this->load();
    }

    /**
     *
     */
    private function load() {
        $this->cache = array();
        $this->requested = array();

        if (file_exists($this->fileName)) {
            $this->cache = unserialize(io_readFile($this->fileName, false));
        }

        foreach (array_keys($this->cache) as $fileName) {
            $this->requested[$fileName] = false;
        }

        $this->updated = false;
    }

    /**
     *
     */
    public function isCached($fileName) {
        $result = false;

        if (array_key_exists($fileName, $this->cache)) {
            if ($this->cache[$fileName]['time'] == @filemtime($fileName)) {
                $result = true;
            }
        }

        $this->requested[$fileName] = true;

        return $result;
    }

    /**
     *
     */
    public function getNamespaces($fileName) {
        return $this->cache[$fileName]['ns'];
    }

    /**
     *
     */
    public function update($fileName, $namespace) {
        $this->cache[$fileName] = array('ns' => $namespace, 'time' => @filemtime($fileName));
        $this->updated = true;
    }

    /**
     *
     */
    public function save() {
        $this->removeOldPages();

        if ($this->updated) {
            io_saveFile($this->fileName, serialize($this->cache));
        }
    }

    /**
     *
     */
    private function removeOldPages() {
        foreach ($this->requested as $fileName => $requested) {
            if (!$requested && array_key_exists($fileName, $this->cache)) {
                unset($this->cache[$fileName]);

                $this->updated = true;
            }
        }
    }
}
