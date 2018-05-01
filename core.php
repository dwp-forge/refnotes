<?php

/**
 * Plugin RefNotes: Core functionality
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

require_once(DOKU_PLUGIN . 'refnotes/locale.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/refnote.php');
require_once(DOKU_PLUGIN . 'refnotes/reference.php');
require_once(DOKU_PLUGIN . 'refnotes/note.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');
require_once(DOKU_PLUGIN . 'refnotes/scope.php');
require_once(DOKU_PLUGIN . 'refnotes/rendering.php');
require_once(DOKU_PLUGIN . 'refnotes/database.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parser_core {

    private static $instance = NULL;

    private $context;
    private $lexer;
    private $handler;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_parser_core();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        /* Default context. Should never be used, but just in case... */
        $this->context = array(new refnotes_parsing_context());
        $this->lexer = NULL;
        $this->handler = NULL;
    }

    /**
     *
     */
    public function registerLexer($lexer) {
        $this->lexer = $lexer;
    }

    /**
     *
     */
    public function enterParsingContext() {
        $this->context[] = new refnotes_parsing_context();
    }

    /**
     *
     */
    public function exitParsingContext($handler) {
        $this->handler = $handler;

        unset($this->context[count($this->context) - 1]);
    }

    /**
     *
     */
    public function getInstructions($text) {
        $this->callWriter = new refnotes_nested_call_writer($this->handler);

        $this->callWriter->connect();
        $this->lexer->parse($text);
        $this->callWriter->disconnect();

        return $this->callWriter->calls;
    }

    /**
     *
     */
    private function getCurrentContext() {
        return end($this->context);
    }

    /**
     *
     */
    public function canHandle($state) {
        return $this->getCurrentContext()->canHandle($state);
    }

    /**
     *
     */
    public function enterReference($name, $data) {
        $this->getCurrentContext()->enterReference($name, $data);
    }

    /**
     *
     */
    public function exitReference() {
        return $this->getCurrentContext()->exitReference();
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parsing_context {

    private $handling;
    private $reference;

    /**
     * Constructor
     */
    public function __construct() {
        $this->reset();
    }

    /**
     *
     */
    private function reset() {
        $this->handling = false;
        $this->reference = NULL;
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
    public function enterReference($name, $data) {
        $this->handling = true;
        $this->reference = new refnotes_parser_reference($name, $data);
    }

    /**
     *
     */
    public function exitReference() {
        $reference = $this->reference;

        $this->reset();

        return $reference;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
abstract class refnotes_core {

    protected $presetStyle;
    protected $namespace;
    protected $mapping;

    /**
     * Constructor
     */
    public function __construct() {
        $this->presetStyle = refnotes_configuration::load('namespaces');
        $this->namespace = array();
        $this->mapping = array();
    }

    /**
     *
     */
    public function getNamespaceCount() {
        return count($this->namespace);
    }

    /**
     * Returns a namespace given it's name. The namespace is created if it doesn't exist yet.
     */
    public function getNamespace($name) {
        $result = $this->findNamespace($name);

        if ($result == NULL) {
            $result = $this->createNamespace($name);
        }

        return $result;
    }

    /**
     * Finds a namespace given it's name
     */
    protected function findNamespace($name) {
        $result = NULL;

        if (array_key_exists($name, $this->namespace)) {
            $result = $this->namespace[$name];
        }

        return $result;
    }

    /**
     *  Finds a namespace or it's parent
     */
    public function findParentNamespace($name) {
        while (($name != '') && !array_key_exists($name, $this->namespace)) {
            $name = refnotes_namespace::getParentName($name);
        }

        return ($name != '') ? $this->namespace[$name] : NULL;
    }

    /**
     *
     */
    public function styleNamespace($namespaceName, $style) {
        $namespace = $this->getNamespace($namespaceName);

        if (array_key_exists('inherit', $style)) {
            $source = $this->getNamespace($style['inherit']);
            $namespace->inheritStyle($source);
        }

        $namespace->setStyle($style);
    }

    /**
     *
     */
    public function setNamespaceMapping($namespaceName, $map) {
        foreach ($map as $ns) {
            $this->mapping[$ns] = $namespaceName;
        }
    }

    /**
     *
     */
    protected function clearNamespaceMapping($namespaceName) {
        $this->mapping = array_diff($this->mapping, array($namespaceName));
    }

    /**
     *
     */
    protected function createNamespace($name) {
        if ($name != ':') {
            $parentName = refnotes_namespace::getParentName($name);
            $parent = $this->getNamespace($parentName);
            $this->namespace[$name] = new refnotes_namespace($name, $parent);
        }
        else {
            $this->namespace[$name] = new refnotes_namespace($name);
        }

        if (array_key_exists($name, $this->presetStyle)) {
            $this->namespace[$name]->setStyle($this->presetStyle[$name]);
        }

        return $this->namespace[$name];
    }

    /**
     *
     */
    protected function getNote($namespaceName, $noteName) {
        $scope = $this->getNamespace($namespaceName)->getActiveScope();
        $note = $scope->findNote($namespaceName, $noteName);

        if (($note == NULL) && array_key_exists($namespaceName, $this->mapping)) {
            $scope = $this->getNamespace($this->mapping[$namespaceName])->getActiveScope();
            $note = $scope->findNote($namespaceName, $noteName);
        }

        if ($note == NULL) {
            if (!is_int($noteName)) {
                $note = $this->createNote($scope, $namespaceName, $noteName);

                $scope->addNote($note);
            }
            else {
                $note = new refnotes_note_mock();
            }
        }

        return $note;
    }

    /**
     *
     */
    abstract protected function createNote($scope, $namespaceName, $noteName);
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer_core extends refnotes_core {

    private static $instance = NULL;

    /**
     * Renderer core is used by both references and notes syntax plugins during the rendering
     * stage. The instance has to be shared between the plugins, and since there should be no
     * more than one rendering pass during a DW page request, a single instance of the syntax
     * core should be enough.
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_renderer_core();
        }

        return self::$instance;
    }

    /**
     *
     */
    public function addReference($attributes, $data) {
        $note = $this->getNote($attributes['ns'], $attributes['name']);
        $reference = new refnotes_renderer_reference($note, $attributes, $data);

        $note->addReference($reference);

        return $reference;
    }

    /**
     *
     */
    public function renderNotes($mode, $namespaceName, $limit) {
        $this->clearNamespaceMapping($namespaceName);

        $html = '';

        if ($namespaceName == '*') {
            foreach ($this->namespace as $namespace) {
                $html .= $namespace->renderNotes($mode);
            }
        }
        else {
            $namespace = $this->findNamespace($namespaceName);
            if ($namespace != NULL) {
                $html = $namespace->renderNotes($mode, $limit);
            }
        }

        return $html;
    }

    /**
     *
     */
    protected function createNote($scope, $namespaceName, $noteName) {
        return new refnotes_renderer_note($scope, $namespaceName, $noteName);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_action_core extends refnotes_core {

    private $styleStash;
    private $mappingStash;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->styleStash = new refnotes_namespace_style_stash($this);
        $this->mappingStash = new refnotes_namespace_mapping_stash();
    }

    /**
     *
     */
    public function markScopeStart($namespaceName, $callIndex) {
        $this->getNamespace($namespaceName)->markScopeStart($callIndex);
    }

    /**
     *
     */
    public function markScopeEnd($namespaceName, $callIndex) {
        $this->getNamespace($namespaceName)->markScopeEnd($callIndex);
    }

    /**
     * Collect styling information from the page
     */
    public function addStyle($namespaceName, $style) {
        $this->styleStash->add($this->getNamespace($namespaceName), $style);
    }

    /**
     *
     */
    public function getStyles() {
        return $this->styleStash;
    }

    /**
     * Collect mapping information from the page
     */
    public function addMapping($namespaceName, $map) {
        $this->mappingStash->add($this->getNamespace($namespaceName), $map);
    }

    /**
     *
     */
    public function getMappings() {
        return $this->mappingStash;
    }

    /**
     *
     */
    public function reset() {
        $this->namespace = array();
    }

    /**
     *
     */
    public function addReference($attributes, $data, $call) {
        $note = $this->getNote($attributes['ns'], $attributes['name']);
        $reference = new refnotes_action_reference($note, $attributes, $data, $call);

        $note->addReference($reference);

        return $reference;
    }

    /**
     *
     */
    public function rewriteReferences($namespaceName, $limit) {
        $this->clearNamespaceMapping($namespaceName);

        if ($namespaceName == '*') {
            foreach ($this->namespace as $namespace) {
                $namespace->rewriteReferences();
            }
        }
        else {
            $namespace = $this->findNamespace($namespaceName);
            if ($namespace != NULL) {
                $namespace->rewriteReferences($limit);
            }
        }
    }

    /**
     *
     */
    protected function createNote($scope, $namespaceName, $noteName) {
        return new refnotes_action_note($scope, $namespaceName, $noteName);
    }
}
