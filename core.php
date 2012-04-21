<?php

/**
 * Plugin RefNotes: Core functionality
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

require_once(DOKU_PLUGIN . 'refnotes/locale.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');
require_once(DOKU_PLUGIN . 'refnotes/database.php');
require_once(DOKU_PLUGIN . 'refnotes/scope.php');
require_once(DOKU_PLUGIN . 'refnotes/refnote.php');
require_once(DOKU_PLUGIN . 'refnotes/reference.php');
require_once(DOKU_PLUGIN . 'refnotes/note.php');
require_once(DOKU_PLUGIN . 'refnotes/renderer.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_core {

    protected $presetStyle;
    protected $namespace;

    /**
     * Constructor
     */
    public function __construct() {
        $this->presetStyle = refnotes_configuration::load('namespaces');
        $this->namespace = array();
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
            $name = refnotes_getParentNamespace($name);
        }

        return ($name != '') ? $this->namespace[$name] : NULL;
    }

    /**
     *
     */
    protected function createNamespace($name) {
        if ($name != ':') {
            $parentName = refnotes_getParentNamespace($name);
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
        $note = $scope->findNote($noteName);

        if ($note == NULL) {
            if (!is_int($noteName)) {
                $note = $this->createNote($scope, $noteName);

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
    protected function createNote($scope, $name) {
        return new refnotes_note($scope, $name);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_syntax_core extends refnotes_core {

    private static $instance = NULL;

    /**
     * The syntax core is used by both references and notes syntax plugins during the rendering
     * stage. The instance has to be shared between the plugins, and since there should be no
     * more than one rendering pass during a DW page request, a single instance of the syntax
     * core should be enough.
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_syntax_core();
        }

        return self::$instance;
    }

    /**
     *
     */
    public function addReference($attributes, $data) {
        $note = $this->getNote($attributes['ns'], $attributes['name']);

        return $note->addReference($attributes, $data);
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
    public function renderNotes($namespaceName, $limit) {
        $html = '';
        if ($namespaceName == '*') {
            foreach ($this->namespace as $namespace) {
                $html .= $namespace->renderNotes();
            }
        }
        else {
            $namespace = $this->findNamespace($namespaceName);
            if ($namespace != NULL) {
                $html = $namespace->renderNotes($limit);
            }
        }

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_action_core extends refnotes_core {
    private $pageStyles;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->pageStyles = new refnotes_namespace_style_stash($this);
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
        $this->pageStyles->add($this->getNamespace($namespaceName), $style);
    }

    /**
     *
     */
    public function getStyles() {
        return $this->pageStyles;
    }

    /**
     *
     */
    public function getDatabaseNote($attributes) {
        return parent::getNote($attributes['ns'], $attributes['name']);
    }

    /**
     * Returns wiki markup rendered according to the namespace style
     */
    public function renderNoteText($namespaceName, $data) {
        return $this->getNamespace($namespaceName)->getRenderer()->renderNoteText($data);
    }

    /**
     *
     */
    protected function createNote($scope, $name) {
        return new refnotes_note($scope, $name, true);
    }
}
