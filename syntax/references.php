<?php

/**
 * Plugin RefNotes: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');

class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    private $mode;
    private $entrySyntax;
    private $exitSyntax;
    private $entryPattern;
    private $exitPattern;
    private $handlePattern;
    private $core;
    private $database;
    private $handling;
    private $embedding;
    private $lastHiddenExit;
    private $capturedNote;
    private $docBackup;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
        $this->entrySyntax = '[(';
        $this->exitSyntax = ')]';
        $this->core = NULL;
        $this->database = NULL;
        $this->handling = false;
        $this->embedding = false;
        $this->lastHiddenExit = -1;
        $this->capturedNote = NULL;
        $this->docBackup = '';

        $this->initializePatterns();
    }

    /**
     *
     */
    private function initializePatterns() {
        $useFootnoteSyntax = false;
        $config = refnotes_loadConfigFile('general');

        if (array_key_exists('replace-footnotes', $config)) {
            $useFootnoteSyntax = $config['replace-footnotes'];
        }

        if ($useFootnoteSyntax) {
            $entry = '(?:\(\(|\[\()';
            $exit = '(?:\)\)|\)\])';
            $name ='(?:@@FNT\d+|#\d+|[[:alpha:]]\w*)';
        }
        else {
            $entry = '\[\(';
            $exit = '\)\]';
            $name ='(?:#\d+|[[:alpha:]]\w*)';
        }

        $newLine = '(?:\n?[ \t]*\n)?';
        $namespace ='(?:(?:[[:alpha:]]\w*)?:)*';
        $text = '.*?';

        $nameMatch = '\s*' . $namespace . $name .'\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $nameMatch . $lookaheadExit;

        $optionalName = $name .'?';
        $define = '\s*' . $namespace . $optionalName .'\s*>';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->entryPattern = $newLine . $entry . '(?:' . $nameEntry . '|' . $defineEntry . ')';
        $this->exitPattern = $exit;
        $this->handlePattern = '/(\s*)' . $entry . '\s*(' . $namespace . $optionalName . ').*/';
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
        switch ($state) {
            case DOKU_LEXER_ENTER:
                if (!$this->handling) {
                    return $this->handleEnter($match, $pos, $handler);
                }
                break;

            case DOKU_LEXER_EXIT:
                if ($this->handling) {
                    return $this->handleExit($match, $pos);
                }
                break;
        }

        $handler->_addCall('cdata', array($match), $pos);

        return false;
    }

    /**
     * Create output
     */
    public function render($mode, $renderer, $data) {
        try {
            if($mode == 'xhtml') {
                switch ($data[0]) {
                    case DOKU_LEXER_ENTER:
                        $this->renderEnter($renderer, $data[1]);
                        break;

                    case DOKU_LEXER_EXIT:
                        $this->renderExit($renderer);
                        break;
                }

                return true;
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }

        return false;
    }

    /**
     *
     */
    private function handleEnter($syntax, $pos, $handler) {
        if (preg_match($this->handlePattern, $syntax, $match) == 0) {
            return false;
        }

        list($namespace, $name) = refnotes_parseName($match[2]);

        if (!$this->embedding && ($name != '')) {
            $fullName = $namespace . $name;
            $database = $this->getDatabase();

            if ($database->isDefined($fullName)) {
                $this->embedPredefinedNote($database->getNote($fullName), $pos, $handler);
            }
        }

        $this->handling = true;

        $info['ns'] = $namespace;
        $info['name'] = $name;
        $info['hidden'] = $this->isHiddenReference($match[1], $pos, $handler);

        return array(DOKU_LEXER_ENTER, $info);
    }

    /**
     *
     */
    private function getDatabase() {
        if ($this->database == NULL) {
            $this->database = new refnotes_reference_database();
        }

        return $this->database;
    }

    /**
     *
     */
    private function embedPredefinedNote($note, $pos, $handler) {
        $text = $this->entrySyntax . $note['name'] . '>' . $note['text'] . $this->exitSyntax;

        $lastHiddenExit = $this->lastHiddenExit;
        $this->lastHiddenExit = 0;
        $this->embedding = true;

        $this->parseNestedText($text, $pos, $handler);

        $this->embedding = false;
        $this->lastHiddenExit = $lastHiddenExit;

        if ($note['inline']) {
            $handler->calls[count($handler->calls) - 1][1][0][0][1][1][1]['inline'] = true;
        }
    }

    /**
     *
     */
    private function parseNestedText($text, $pos, $handler) {
        $nestedWriter = new Doku_Handler_Nest($handler->CallWriter);
        $handler->CallWriter =& $nestedWriter;

        /*
            HACK: If doku.php parses a number of pages during one call (it's common after the cache
            clean-up) $this->Lexer can be a different instance form the one used in the current parser
            pass. Here we ensure that $handler is linked to $this->Lexer while parsing the nested text.
        */
        $handlerBackup = $this->Lexer->_parser;
        $this->Lexer->_parser = $handler;

        $this->Lexer->parse($text);

        $this->Lexer->_parser = $handlerBackup;

        $nestedWriter->process();
        $handler->CallWriter =& $nestedWriter->CallWriter;

        $handler->calls[count($handler->calls) - 1][2] = $pos;
    }

    /**
     *
     */
    private function handleExit($syntax, $pos) {
        $this->handling = false;

        if ($this->lastHiddenExit >= 0) {
            $this->lastHiddenExit = $pos + strlen($syntax);
        }

        return array(DOKU_LEXER_EXIT);
    }

    /**
     *
     */
    private function isHiddenReference($space, $pos, $handler) {
        $newLines = substr_count($space, "\n");
        $lastCall = end($handler->calls);
        $lastCall = $lastCall[0];

        if (($newLines == 2) || ($lastCall == 'table_close')) {
            $this->lastHiddenExit = $pos;
        }
        else {
            if (($this->lastHiddenExit >= 0) && ($this->lastHiddenExit < $pos)) {
                $this->lastHiddenExit = -1;
            }
        }

        return $this->lastHiddenExit >= 0;
    }

    /**
     * Renders reference link and starts renderer output capture
     */
    private function renderEnter($renderer, $info) {
        $core = $this->getCore();

        $inline = false;
        if (array_key_exists('inline', $info)) {
            $inline = $info['inline'];
        }

        $note = $core->addReference($info['ns'], $info['name'], $info['hidden'], $inline);
        if (($note != NULL) && !$info['hidden']) {
            $renderer->doc .= $note->renderReference();
        }

        $this->startCapture($renderer, $note);
    }

    /**
     * Stops renderer output capture
     */
    private function renderExit($renderer) {
        $this->stopCapture($renderer);
    }

    /**
     *
     */
    private function getCore() {
        if ($this->core == NULL) {
            $this->core = plugin_load('helper', 'refnotes');
            if ($this->core == NULL) {
                throw new Exception('Helper plugin "refnotes" is not available or invalid.');
            }
        }

        return $this->core;
    }

    /**
     * Starts renderer output capture
     */
    private function startCapture($renderer, $note) {
        $this->capturedNote = $note;
        $this->docBackup = $renderer->doc;
        $renderer->doc = '';
    }

    /**
     * Stops renderer output capture
     */
    private function stopCapture($renderer) {
        $text = trim($renderer->doc);
        if ($text != '') {
            $this->capturedNote->setText($text);
        }

        $renderer->doc = $this->docBackup;
        $this->capturedNote = NULL;
        $this->docBackup = '';
    }
}

class refnotes_reference_database {

    private $note;
    private $page;
    private $namespace;

    /**
     * Constructor
     */
    public function __construct() {
        $this->note = refnotes_loadConfigFile('notes');

        $this->loadPages();
        $this->loadNamespaces();
    }

    /**
     *
     */
    private function loadPages() {
        global $conf;

        $this->page = array();

        if (file_exists($conf['indexdir'] . '/page.idx')) {
            require_once(DOKU_INC . 'inc/indexer.php');

            $pageIndex = idx_getIndex('page', '');
            $namespacePattern = '/^' . preg_quote($this->getDatabaseNamespace()) . '/';

            foreach ($pageIndex as $page) {
                $page = trim($page);

                if (preg_match($namespacePattern, $page) == 1) {
                    $this->page[$page] = new refnotes_reference_database_page($page);
                }
            }
        }
    }

    /**
     *
     */
    private function getDatabaseNamespace() {
        $result = ':refnotes:';
        $config = refnotes_loadConfigFile('general');

        if (array_key_exists('reference-database', $config)) {
            if (trim($config['reference-database']) != '') {
                $result = $config['reference-database'];
            }
        }
        $result = trim($result, ':') . ':';

        return $result;
    }

    /**
     *
     */
    private function loadNamespaces() {
        $this->namespace = array();

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
    public function getNote($name) {
        $result['name'] = $name;
        $result['text'] = $this->note[$name]['text'];
        $result['inline'] = $this->note[$name]['inline'];

        return $result;
    }
}

class refnotes_reference_database_page {

    private $id;
    private $fileName;
    private $namespace;
    private $note;

    /**
     * Constructor
     */
    public function __construct($id) {
        $this->id = $id;
        $this->fileName = wikiFN($id);
        $this->namespace = array();
        $this->note = array();

        $this->parse();
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
                    if ($columns < $column) {
                    }
                    break;

                case 'table_close':
                    break 2;
            }
        }

        if ($valid && ($row > 1) && ($columns > 1)) {
            $this->handleTable($table);
        }

        return $c;
    }

    /**
     *
     */
    private function handleTable($table) {
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
        if (count($this->note) == 0) {
            $this->parse();
        }

        return $this->note;
    }
}
