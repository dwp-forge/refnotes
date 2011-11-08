<?php

/**
 * Plugin RefNotes: Namespace heplers
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/**
 * Returns canonic name for a namespace
 */
function refnotes_canonizeNamespace($name) {
    return preg_replace('/:{2,}/', ':', ':' . $name . ':');
}

/**
 * Returns name of the parent namespace
 */
function refnotes_getParentNamespace($name) {
    return preg_replace('/\w*:$/', '', $name);
}

/**
 * Splits full note name into namespace and name components
 */
function refnotes_parseName($name) {
    $pos = strrpos($name, ':');
    if ($pos !== false) {
        $namespace = refnotes_canonizeNamespace(substr($name, 0, $pos));
        $name = substr($name, $pos + 1);
    }
    else {
        $namespace = ':';
    }

    return array($namespace, $name);
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_namespace {

    private $name;
    private $style;
    private $renderer;
    private $scope;
    private $newScope;

    /**
     * Constructor
     */
    public function __construct($name, $parent = NULL) {
        $this->name = $name;
        $this->style = array();
        $this->renderer = NULL;
        $this->scope = array();
        $this->newScope = true;

        if ($parent != NULL) {
            $this->style = $parent->style;
        }
    }

    /**
     *
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     */
    public function style($style) {
        foreach ($style as $property => $value) {
            $this->style[$property] = $value;
        }
    }

    /**
     *
     */
    public function inheritStyle($source) {
        $this->style = $source->style;
    }

    /**
     *
     */
    public function getStyle($property) {
        $result = '';

        if (array_key_exists($property, $this->style)) {
            $result = $this->style[$property];
        }

        return $result;
    }

    /**
     *
     */
    public function getRenderer() {
        if ($this->renderer == NULL) {
            switch ($this->getStyle('struct-render')) {
                case 'harvard':
                    $this->renderer = new refnotes_harvard_renderer($this);
                    break;

                default:
                    $this->renderer = new refnotes_basic_renderer($this);
                break;
            }
        }

        return $this->renderer;
    }

    /**
     *
     */
    public function getCurrentScope() {
        if ($this->newScope) {
            $id = count($this->scope) + 1;
            $this->scope[] = new refnotes_scope($this, $id);
            $this->newScope = false;
        }

        return end($this->scope);
    }

    /**
     *
     */
    public function renderNotes($limit = '') {
        $this->resetScope();
        $html = '';
        if (count($this->scope) > 0) {
            $scope = end($this->scope);
            $limit = $this->getRenderLimit($limit, $scope);
            $html = $scope->renderNotes($limit);
        }

        return $html;
    }

    /**
     *
     */
    private function resetScope() {
        switch ($this->getStyle('scoping')) {
            case 'single':
                break;

            default:
                $this->newScope = true;
            break;
        }
    }

    /**
     *
     */
    private function getRenderLimit($limit, $scope) {
        if (preg_match('/(\/?)(\d+)/', $limit, $match) == 1) {
            if ($match[1] != '') {
                $devider = intval($match[2]);
                $result = ceil($scope->getRenderableCount() / $devider);
            }
            else {
                $result = intval($match[2]);
            }
        }
        else {
            $result = 0;
        }

        return $result;
    }
}
