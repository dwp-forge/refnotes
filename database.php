<?php

/**
 * Plugin RefNotes: Reference database
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_database {

    private static $instance = NULL;

    private $note;
    private $key;
    private $page;
    private $namespace;
    private $enabled;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_reference_database();

            /* Loading has to be separated from construction to prevent infinite recursion */
            self::$instance->load();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->page = array();
        $this->namespace = array();
        $this->enabled = true;
    }

    /**
     *
     */
    private function load() {
        $this->loadNotesFromConfiguration();

        if (refnotes_configuration::getSetting('reference-db-enable')) {
            $this->loadKeys();
            $this->loadPages();
            $this->loadNamespaces();
        }
    }

    /**
     *
     */
    private function loadNotesFromConfiguration() {
        $note = refnotes_configuration::load('notes');

        foreach ($note as $name => $data) {
            $this->note[$name] = new refnotes_reference_database_note('{configuration}', $data);
        }
    }

    /**
     *
     */
    private function loadKeys() {
        $locale = refnotes_localization::getInstance();
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

        if (in_array($text, $this->key)) {
            $result = $text;
        }
        elseif (array_key_exists($text, $this->key)) {
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
                    $this->enabled = false;
                    $this->page[$pageId] = new refnotes_reference_database_page($this, $cache, $pageId);
                    $this->enabled = true;
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
    public function findNote($name) {
        if (!$this->enabled) {
            return NULL;
        }

        $found = array_key_exists($name, $this->note);

        if (!$found) {
            list($namespace, $temp) = refnotes_namespace::parseName($name);

            if (array_key_exists($namespace, $this->namespace)) {
                $this->loadNamespaceNotes($namespace);

                $found = array_key_exists($name, $this->note);
            }
        }

        return $found ? $this->note[$name] : NULL;
    }

    /**
     *
     */
    private function loadNamespaceNotes($namespace) {
        foreach ($this->namespace[$namespace] as $pageId) {
            if (array_key_exists($pageId, $this->page)) {
                $this->enabled = false;
                $this->note = array_merge($this->note, $this->page[$pageId]->getNotes());
                $this->enabled = true;

                unset($this->page[$pageId]);
            }
        }

        unset($this->namespace[$namespace]);
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
            elseif ($call[$c][0] == 'code') {
                $this->parseCode($call[$c]);
            }
            elseif (($call[$c][0] == 'plugin') && ($call[$c][1][0] == 'data_entry')) {
                $this->parseDataEntry($call[$c][1][1]);
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
            $data = array();

            for ($c = 0; $c < $columns; $c++) {
                $data[$key[$c]] = $table[$r][$c];
            }

            $this->handleNote($data);
        }
    }

    /**
     * Every note is stored in a separate table. The first column of the table contains
     * the caption, the second one contains the data.
     */
    private function handleDataCard($table, $rows, $key) {
        $data = array();

        for ($r = 0; $r < $rows; $r++) {
            $data[$key[$r]] = $table[$r][1];
        }

        $this->handleNote($data);
    }

    /**
     *
     */
    private function parseCode($call) {
        switch ($call[1][1]) {
            case 'bibtex':
                $this->parseBibtex($call[1][0]);
                break;
        }
    }

    /**
     *
     */
    private function parseBibtex($text) {
        foreach (refnotes_bibtex_parser::getInstance()->parse($text) as $data) {
            $this->handleNote($data);
        }
    }

    /**
     *
     */
    private function parseDataEntry($pluginData) {
        if (preg_match('/\brefnotes\b/', $pluginData['classes'])) {
            $data = array();

            foreach ($pluginData['data'] as $key => $value) {
                if (is_array($value)) {
                    $data[$key . 's'] = implode(', ', $value);
                }
                else {
                    $data[$key] = $value;
                }
            }

            $this->handleNote($data);
        }
    }

    /**
     *
     */
    private function handleNote($data) {
        $note = new refnotes_reference_database_note($this->id, $data);

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
class refnotes_reference_database_note extends refnotes_refnote {

    private $nameParts;

    /**
     * Constructor
     */
    public function __construct($source, $data) {
        parent::__construct();

        $this->nameParts = array('', '');

        if ($source == '{configuration}') {
            $this->initializeConfigNote($data);
        }
        else {
            $this->initializePageNote($data);
        }

        $this->attributes['source'] = $source;
    }

    /**
     *
     */
    public function initializeConfigNote($data) {
        $this->data['note-text'] = $data['text'];

        unset($data['text']);

        $this->attributes = $data;
    }

    /**
     *
     */
    public function initializePageNote($data) {
        if (isset($data['note-name'])) {
            if (preg_match('/^' . refnotes_note::getNamePattern('full-extended') . '$/', $data['note-name']) == 1) {
                $this->nameParts = refnotes_namespace::parseName($data['note-name']);
            }

            unset($data['note-name']);
        }

        $this->data = $data;
    }

    /**
     *
     */
    public function getNameParts() {
        return $this->nameParts;
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
        global $conf;

        $this->fileName = $conf['cachedir'] . '/refnotes.database.dat';

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
