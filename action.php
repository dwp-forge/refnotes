<?php

/**
 * Plugin RefNotes: Event handler
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

require_once(DOKU_PLUGIN . 'refnotes/core.php');
require_once(DOKU_PLUGIN . 'refnotes/instructions.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class action_plugin_refnotes extends DokuWiki_Action_Plugin {
    use refnotes_localization_plugin;

    private $afterParserHandlerDone;
    private $beforeAjaxCallUnknown;
    private $beforeParserCacheUse;
    private $beforeParserWikitextPreprocess;
    private $beforeTplMetaheaderOutput;

    /**
     * Constructor
     */
    public function __construct() {
        refnotes_localization::initialize($this);

        $this->afterParserHandlerDone = new refnotes_after_parser_handler_done();
        $this->beforeAjaxCallUnknown = new refnotes_before_ajax_call_unknown();
        $this->beforeParserCacheUse = new refnotes_before_parser_cache_use();
        $this->beforeParserWikitextPreprocess = new refnotes_before_parser_wikitext_preprocess();
        $this->beforeTplMetaheaderOutput = new refnotes_before_tpl_metaheader_output();
    }

    /**
     * Register callbacks
     */
    public function register(Doku_Event_Handler $controller) {
        $this->afterParserHandlerDone->register($controller);
        $this->beforeAjaxCallUnknown->register($controller);
        $this->beforeParserCacheUse->register($controller);
        $this->beforeParserWikitextPreprocess->register($controller);
        $this->beforeTplMetaheaderOutput->register($controller);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_after_parser_handler_done {

    /**
     * Register callback
     */
    public function register($controller) {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'handle');
    }

    /**
     *
     */
    public function handle($event, $param) {
        refnotes_parser_core::getInstance()->exitParsingContext($event->data);

        /* We need a new instance of mangler for each event because we can trigger it recursively
         * by loading reference database or by parsing structured notes.
         */
        $mangler = new refnotes_instruction_mangler($event);

        $mangler->process();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_instruction_mangler {

    private $core;
    private $calls;
    private $paragraphReferences;
    private $referenceGroup;
    private $hidden;
    private $inReference;
    private $includedPages;

    /**
     * Constructor
     */
    public function __construct($event) {
        $this->core = new refnotes_action_core();
        $this->calls = new refnotes_instruction_list($event);
        $this->paragraphReferences = array();
        $this->referenceGroup = array();
        $this->hidden = true;
        $this->inReference = false;
        $this->includedPages = array();
    }

    /**
     *
     */
    public function process() {
        $this->scanInstructions();

        /* If there are some includes on the current page, the implicit rendering of leftover notes
         * has to be disabled inside the included pages. Instead the notes referred by the included
         * pages have to be rendered on the current page. So even in case when the current page has
         * no references, there has to be leftovers rendering at the end, just to ensure that any
         * possible references on the included pages are taken care of.
         */
        if ($this->core->getNamespaceCount() > 0 || count($this->includedPages) > 0) {
            $this->renderLeftovers();

            $this->calls->applyChanges();
        }

        if ($this->core->getNamespaceCount() > 0) {
            $this->insertNotesInstructions($this->core->getStyles(), 'refnotes_notes_style_instruction');
            $this->insertNotesInstructions($this->core->getMappings(), 'refnotes_notes_map_instruction');

            $this->calls->applyChanges();

            $this->renderStructuredNotes();

            $this->calls->applyChanges();
        }
    }

    /**
     *
     */
    private function scanInstructions() {
        foreach ($this->calls as $call) {
            $this->markHiddenReferences($call);
            $this->markReferenceGroups($call);
            $this->markScopeLimits($call);
            $this->extractStyles($call);
            $this->extractMappings($call);
            $this->collectIncludedPages($call);
        }
    }

    /**
     *
     */
    private function markHiddenReferences($call) {
        switch ($call->getName()) {
            case 'p_open':
                $this->paragraphReferences = array();
                $this->hidden = true;
                break;

            case 'p_close':
                if ($this->hidden) {
                    foreach ($this->paragraphReferences as $call) {
                        $call->setRefnotesAttribute('hidden', true);
                    }
                }
                break;

            case 'cdata':
                if (!$this->inReference && !empty(trim($call->getData(0)))) {
                    $this->hidden = false;
                }
                break;

            case 'plugin_refnotes_references':
                switch ($call->getPluginData(0)) {
                    case 'start':
                        $this->inReference = true;
                        break;

                    case 'render':
                        $this->inReference = false;
                        $this->paragraphReferences[] = $call;
                        break;
                }
                break;

            default:
                if (!$this->inReference) {
                    $this->hidden = false;
                }
                break;
        }
    }

    /**
     *
     */
    private function markReferenceGroups($call) {
        if (($call->getName() == 'plugin_refnotes_references') && ($call->getPluginData(0) == 'render')) {
            if (!empty($this->referenceGroup)) {
                $groupNamespace = $this->referenceGroup[0]->getRefnotesAttribute('ns');

                if ($call->getRefnotesAttribute('ns') != $groupNamespace) {
                    $this->closeReferenceGroup();
                }
            }

            $this->referenceGroup[] = $call;
        }
        elseif (!$this->inReference && !empty($this->referenceGroup)) {
            // Allow whitespace "cdata" istructions between references in a group
            if ($call->getName() == 'cdata' && empty(trim($call->getData(0)))) {
                return;
            }

            $this->closeReferenceGroup();
        }
    }

    /**
     *
     */
    private function closeReferenceGroup() {
        $count = count($this->referenceGroup);

        if ($count > 1) {
            $this->referenceGroup[0]->setRefnotesAttribute('group', 'open');

            for ($i = 1; $i < $count - 1; $i++) {
                $this->referenceGroup[$i]->setRefnotesAttribute('group', 'hold');
            }

            $this->referenceGroup[$count - 1]->setRefnotesAttribute('group', 'close');
        }

        $this->referenceGroup = array();
    }

    /**
     *
     */
    private function markScopeLimits($call) {
        switch ($call->getName()) {
            case 'plugin_refnotes_references':
                if ($call->getPluginData(0) == 'render') {
                    $this->core->markScopeStart($call->getRefnotesAttribute('ns'), $call->getIndex());
                }
                break;

            case 'plugin_refnotes_notes':
                $this->core->markScopeEnd($call->getRefnotesAttribute('ns'), $call->getIndex());
                break;
        }
    }

    /**
     * Extract style data and replace "split" instructions with "render"
     */
    private function extractStyles($call) {
        if (($call->getName() == 'plugin_refnotes_notes') && ($call->getPluginData(0) == 'split')) {
            $this->core->addStyle($call->getRefnotesAttribute('ns'), $call->getPluginData(2));

            $call->setPluginData(0, 'render');
            $call->unsetPluginData(2);
        }
    }

    /**
     * Extract namespace mapping info
     */
    private function extractMappings($call) {
        if ($call->getName() == 'plugin_refnotes_notes') {
            $map = $call->getRefnotesAttribute('map');

            if (!empty($map)) {
                $this->core->addMapping($call->getRefnotesAttribute('ns'), $map);
                $call->unsetRefnotesAttribute('map');
            }
        }
    }

    /**
     *
     */
    private function collectIncludedPages($call) {
        if ($call->getName() == 'plugin_include_include') {
            $this->includedPages[] = $call;
        }
    }

    /**
     *
     */
    private function insertNotesInstructions($stash, $instruction) {
        if ($stash->getCount() == 0) {
            return;
        }

        $stash->sort();

        foreach ($stash->getIndex() as $index) {
            foreach ($stash->getAt($index) as $data) {
                $this->calls->insert($index, new $instruction($data->getNamespace(), $data->getData()));
            }
        }
    }

    /**
     * Insert render call at the very bottom of the page
     */
    private function renderLeftovers() {
        /* Block leftovers rendering on the included pages */
        foreach ($this->includedPages as $call) {
            $call->insertBefore(new refnotes_notes_render_block_instruction('enter'));
            $call->insertAfter(new refnotes_notes_render_block_instruction('exit'));
        }

        $this->calls->append(new refnotes_notes_render_instruction('*'));
    }

    /**
     *
     */
    private function renderStructuredNotes() {
        $this->core->reset();

        foreach ($this->calls as $call) {
            $this->styleNamespaces($call);
            $this->setNamespaceMappings($call);
            $this->addReferences($call);
            $this->rewriteReferences($call);
        }
    }

    /**
     *
     */
    private function styleNamespaces($call) {
        if (($call->getName() == 'plugin_refnotes_notes') && ($call->getPluginData(0) == 'style')) {
            $this->core->styleNamespace($call->getRefnotesAttribute('ns'), $call->getPluginData(2));
        }
    }

    /**
     *
     */
    private function setNamespaceMappings($call) {
        if (($call->getName() == 'plugin_refnotes_notes') && ($call->getPluginData(0) == 'map')) {
            $this->core->setNamespaceMapping($call->getRefnotesAttribute('ns'), $call->getPluginData(2));
        }
    }

    /**
     *
     */
    private function addReferences($call) {
        if (($call->getName() == 'plugin_refnotes_references') && ($call->getPluginData(0) == 'render')) {
            $attributes = $call->getPluginData(1);
            $data = (count($call->getData(1)) > 2) ? $call->getPluginData(2) : array();
            $reference = $this->core->addReference($attributes, $data, $call);

            if ($call->getPrevious()->getName() != 'plugin_refnotes_references') {
                $reference->getNote()->setText('defined');
            }
        }
    }

    /**
     *
     */
    private function rewriteReferences($call) {
        if (($call->getName() == 'plugin_refnotes_notes') && ($call->getPluginData(0) == 'render')) {
            $this->core->rewriteReferences($call->getRefnotesAttribute('ns'), $call->getRefnotesAttribute('limit'));
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_before_ajax_call_unknown {

    /**
     * Register callback
     */
    public function register($controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle');
    }

    /**
     *
     */
    public function handle($event, $param) {
        global $conf;

        if ($event->data == 'refnotes-admin') {
            $event->preventDefault();
            $event->stopPropagation();

            /* Check admin rights */
            if (auth_quickaclcheck($conf['start']) < AUTH_ADMIN) {
                die('access denied');
            }

            switch ($_POST['action']) {
                case 'load-settings':
                    $this->sendConfig();
                    break;

                case 'save-settings':
                    $this->saveConfig($_POST['settings']);
                    break;
            }
        }
    }

    /**
     *
     */
    private function sendResponse($contentType, $data) {
        static $cookie = '{B27067E9-3DDA-4E31-9768-E66F23D18F4A}';

        header('Content-Type: ' . $contentType);
        print($cookie . $data . $cookie);
    }

    /**
     *
     */
    private function sendConfig() {
        $namespace = refnotes_configuration::load('namespaces');
        $namespace = $this->translateStyles($namespace, 'dw', 'js');

        $config['general'] = refnotes_configuration::load('general');
        $config['namespaces'] = $namespace;
        $config['notes'] = refnotes_configuration::load('notes');

        $this->sendResponse('application/x-suggestions+json', json_encode($config));
    }

    /**
     *
     */
    private function saveConfig($config) {
        global $config_cascade;

        $config = json_decode($config, true);

        $namespace = $config['namespaces'];
        $namespace = $this->translateStyles($namespace, 'js', 'dw');

        $saved = refnotes_configuration::save('general', $config['general']);
        $saved = $saved && refnotes_configuration::save('namespaces', $namespace);
        $saved = $saved && refnotes_configuration::save('notes', $config['notes']);

        if ($config['general']['reference-db-enable']) {
            $saved = $saved && $this->setupReferenceDatabase($config['general']['reference-db-namespace']);
        }

        /* Touch local config file to expire the cache */
        $saved = $saved && touch(reset($config_cascade['main']['local']));

        $this->sendResponse('text/plain', $saved ? 'saved' : 'failed');
    }

    /**
     *
     */
    private function translateStyles($namespace, $from, $to) {
        foreach ($namespace as &$ns) {
            foreach ($ns as $styleName => &$style) {
                $style = $this->translateStyle($styleName, $style, $from, $to);
            }
        }

        return $namespace;
    }

    /**
     *
     */
    private function translateStyle($styleName, $style, $from, $to) {
        static $dictionary = array(
            'refnote-id' => array(
                'dw' => array('1'      , 'a'          , 'A'          , 'i'          , 'I'          , '*'    , 'name'     ),
                'js' => array('numeric', 'latin-lower', 'latin-upper', 'roman-lower', 'roman-upper', 'stars', 'note-name')
            ),
            'reference-base' => array(
                'dw' => array('sup'  , 'text'       ),
                'js' => array('super', 'normal-text')
            ),
            'reference-format' => array(
                'dw' => array(')'           , '()'     , ']'            , '[]'      ),
                'js' => array('right-parent', 'parents', 'right-bracket', 'brackets')
            ),
            'reference-group' => array(
                'dw' => array('none'      , ','          , 's'              ),
                'js' => array('group-none', 'group-comma', 'group-semicolon')
            ),
            'multi-ref-id' => array(
                'dw' => array('ref'        , 'note'   ),
                'js' => array('ref-counter', 'note-counter')
            ),
            'note-id-base' => array(
                'dw' => array('sup'  , 'text'       ),
                'js' => array('super', 'normal-text')
            ),
            'note-id-format' => array(
                'dw' => array(')'           , '()'     , ']'            , '[]'      , '.'  ),
                'js' => array('right-parent', 'parents', 'right-bracket', 'brackets', 'dot')
            ),
            'back-ref-base' => array(
                'dw' => array('sup'  , 'text'       ),
                'js' => array('super', 'normal-text')
            ),
            'back-ref-format' => array(
                'dw' => array('1'      , 'a'    , 'note'   ),
                'js' => array('numeric', 'latin', 'note-id')
            ),
            'back-ref-separator' => array(
                'dw' => array(','    ),
                'js' => array('comma')
            ),
            'struct-refs' => array(
                'dw' => array('off'    , 'on'    ),
                'js' => array('disable', 'enable')
            )
        );

        if (array_key_exists($styleName, $dictionary)) {
            $key = array_search($style, $dictionary[$styleName][$from]);

            if ($key !== false) {
                $style = $dictionary[$styleName][$to][$key];
            }
        }

        return $style;
    }

    /**
     *
     */
    private function setupReferenceDatabase($namespace) {
        $success = true;
        $source = refnotes_localization::getInstance()->getFileName('__template');
        $destination = wikiFN(cleanID($namespace . ':template'));
        $destination = preg_replace('/template.txt$/', '__template.txt', $destination);

        if (@filemtime($destination) < @filemtime($source)) {
            if (!file_exists(dirname($destination))) {
                @mkdir(dirname($destination), 0755, true);
            }

            $success = copy($source, $destination);

            touch($destination, filemtime($source));
        }

        return $success;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_before_parser_cache_use {

    /**
     * Register callback
     */
    public function register($controller) {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle');
    }

    /**
     *
     */
    public function handle($event, $param) {
        global $ID;

        $cache = $event->data;

        if (isset($cache->page) && ($cache->page == $ID)) {
            if (isset($cache->mode) && (($cache->mode == 'xhtml') || ($cache->mode == 'i'))) {
                $meta = p_get_metadata($ID, 'plugin refnotes');

                if (!empty($meta) && isset($meta['dbref'])) {
                    $this->addDependencies($cache, array_keys($meta['dbref']));
                }
            }
        }
    }

    /**
     * Add extra dependencies to the cache
     */
    private function addDependencies($cache, $depends) {
        foreach ($depends as $file) {
            if (!in_array($file, $cache->depends['files']) && file_exists($file)) {
                $cache->depends['files'][] = $file;
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_before_parser_wikitext_preprocess {

    /**
     * Register callback
     */
    public function register($controller) {
        $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'BEFORE', $this, 'handle');
    }

    /**
     *
     */
    public function handle($event, $param) {
        refnotes_parser_core::getInstance()->enterParsingContext();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_before_tpl_metaheader_output {

    /**
     * Register callback
     */
    public function register($controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle');
    }

    /**
     *
     */
    public function handle($event, $param) {
        if (!empty($_REQUEST['do']) && $_REQUEST['do'] == 'admin' &&
                !empty($_REQUEST['page']) && $_REQUEST['page'] == 'refnotes') {
            $this->addAdminIncludes($event);
        }
    }

    /**
     *
     */
    private function addAdminIncludes($event) {
        $this->addTemplateHeaderInclude($event, 'admin.js');
        $this->addTemplateHeaderInclude($event, 'admin.css');
    }

    /**
     *
     */
    private function addTemplateHeaderInclude($event, $fileName) {
        $type = '';
        $fileName = DOKU_BASE . 'lib/plugins/refnotes/' . $fileName;

        switch (pathinfo($fileName, PATHINFO_EXTENSION)) {
            case 'js':
                $type = 'script';
                $data = array('type' => 'text/javascript', 'charset' => 'utf-8', 'src' => $fileName, '_data' => '', 'defer' => 'defer');
                break;

            case 'css':
                $type = 'link';
                $data = array('type' => 'text/css', 'rel' => 'stylesheet', 'href' => $fileName);
                break;
        }

        if ($type != '') {
            $event->data[$type][] = $data;
        }
    }
}
