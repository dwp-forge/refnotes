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

    private static $instance = NULL;

    private $namespaceStyle;
    private $namespace;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_core();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->namespaceStyle = refnotes_configuration::load('namespaces');
        $this->namespace = array();
    }

    /**
     * Adds a reference to the notes array
     */
    public function addReference($info) {
        $reference = new refnotes_reference($info);

        $this->getNamespace($reference->getNamespace())->addReference($reference);

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
    private function findNamespace($name) {
        $result = NULL;
        if (array_key_exists($name, $this->namespace)) {
            $result = $this->namespace[$name];
        }

        return $result;
    }

    /**
     *
     */
    private function createNamespace($name) {
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
}
