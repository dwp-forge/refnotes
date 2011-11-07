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
require_once(DOKU_PLUGIN . 'refnotes/scope.php');
require_once(DOKU_PLUGIN . 'refnotes/reference.php');
require_once(DOKU_PLUGIN . 'refnotes/note.php');
require_once(DOKU_PLUGIN . 'refnotes/renderer.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_core {

    protected $namespaceStyle;
    protected $namespace;

    /**
     * Constructor
     */
    public function __construct() {
        $this->namespaceStyle = refnotes_configuration::load('namespaces');
        $this->namespace = array();
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

        if (array_key_exists($name, $this->namespaceStyle)) {
            $this->namespace[$name]->style($this->namespaceStyle[$name]);
        }

        return $this->namespace[$name];
    }

    /**
     *
     */
    protected function getNote($namespaceName, $noteName) {
        $scope = $this->getNamespace($namespaceName)->getCurrentScope();
        $note = $scope->findNote($noteName);

        if (($note == NULL) && !is_int($noteName)) {
            $note = new refnotes_note($scope, $noteName);

            $scope->addNote($note);
        }

        return $note;
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
    public function addReference($info) {
        $note = $this->getNote($info['ns'], $info['name']);

        if ($note != NULL) {
            $reference = $note->addReference($info);
        }
        else {
            $reference = new refnotes_reference_mock();
        }

        return $reference;
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

        $namespace->style($style);
    }

    /**
     *
     */
    public function renderNotes($namespaceName, $limit = '') {
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

    /**
     * Returns wiki markup rendered according to the namespace style
     */
    public function renderNoteText($namespaceName, $data) {
        return $this->getNamespace($namespaceName)->getRenderer()->renderNoteText($data);
    }
}
