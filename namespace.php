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
class refnotes_namespace_mock {

    /**
     *
     */
    public function findScopeEnd($start, $end) {
        return -1;
    }
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
        $this->scope = array(new refnotes_scope($this, 0, -1, -1));
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
    public function getScopesCount() {
        /* Remove dummy [-1,-1] scope from the count */
        return count($this->scope) - 1;
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
    public function setStyle($style) {
        foreach ($style as $property => $value) {
            $this->style[$property] = $value;
        }
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
    private function getPreviousScope() {
        return $this->scope[count($this->scope) - 2];
    }

    /**
     *
     */
    public function getCurrentScope($create = true) {
        if ($create && $this->newScope) {
            $this->scope[] = new refnotes_scope($this, count($this->scope));
            $this->newScope = false;
        }

        return end($this->scope);
    }

    /**
     *
     */
    public function markScopeStart($callIndex) {
        if (!$this->getCurrentScope(false)->isOpen()) {
            $this->scope[] = new refnotes_scope(NULL, 0, $callIndex);
        }
    }

    /**
     *
     */
    public function markScopeEnd($callIndex) {
        /* Create an empty scope if there is no open one */
        $this->markScopeStart($callIndex - 1);
        $this->getCurrentScope(false)->getLimits()->end = $callIndex;
    }


    /**
     * Find last scope end within specified range
     */
    private function findScopeEnd($start, $end) {
        for ($i = count($this->scope) - 1; $i > 0; $i--) {
            $scopeEnd = $this->scope[$i]->getLimits()->end;

            if (($scopeEnd > $start) && ($scopeEnd < $end)) {
                return $scopeEnd;
            }
        }

        return -1;
    }

    /**
     *
     */
    public function getStyleIndex($parent) {
        $previousEnd = $this->getPreviousScope()->getLimits()->end;
        $currentStart = $this->getCurrentScope(false)->getLimits()->start;
        $parentEnd = $parent->findScopeEnd($previousEnd, $currentStart);

        return max($parentEnd, $previousEnd) + 1;
    }

    /**
     *
     */
    public function renderNotes($limit = '') {
        $this->resetScope();
        $html = '';

        if (count($this->scope) > 1) {
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
