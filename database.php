<?php

/**
 * Plugin RefNotes: Reference database
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

require_once(DOKU_PLUGIN . 'refnotes/locale.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');

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

    private static $instance = NULL;

    private $note;
    private $key;
    private $page;
    private $namespace;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            /* Loading of the database can trigger parsing of the database pages, which in turn can
             * result in the database access. To prevent infinite recursion, before loading the database
             * we first set the instance pointer to a database mock.
             */
            self::$instance = new refnotes_reference_database_mock();
            self::$instance = new refnotes_reference_database();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->page = array();
        $this->namespace = array();

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

        foreach ($note as $name => $info) {
            $this->note[$name] = new refnotes_reference_database_note('{configuration}', $info);
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
        $this->data = array('note-text' => $info['text']);

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
        $this->data = $data;
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
