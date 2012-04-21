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
class refnotes_namespace_style_info {
    private $namespace;
    private $data;

    /**
     * Constructor
     */
    public function __construct($namespace, $data) {
        $this->namespace = $namespace;
        $this->data = $data;
    }

    /**
     *
     */
    public function getNamespace() {
        return $this->namespace->getName();
    }

    /**
     *
     */
    public function getData() {
        return $this->data;
    }

    /**
     *
     */
    public function isDerived() {
        return array_key_exists('inherit', $this->data);
    }

    /**
     *
     */
    public function getInheritedNamespace() {
        return $this->isDerived() ? $this->data['inherit'] : '';
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_namespace_style_stash {
    private $page;
    private $index;

    /**
     * Constructor
     */
    public function __construct($page) {
        $this->page = $page;
        $this->index = array();
    }

    /**
     *
     */
    public function add($namespace, $data) {
        $style = new refnotes_namespace_style_info($namespace, $data);
        $parent = $style->getInheritedNamespace();

        if (($parent == '') && ($namespace->getScopesCount() == 1)) {
            /* Default inheritance for the first scope */
            $parent = refnotes_getParentNamespace($namespace->getName());
        }

        $index = $namespace->getStyleIndex($this->page->findParentNamespace($parent));

        $this->index[$index][] = $style;
    }

    /**
     *
     */
    public function getCount() {
        return count($this->index);
    }

    public function getIndex() {
        return array_keys($this->index);
    }

    public function getAt($index) {
        return array_key_exists($index, $this->index) ? $this->index[$index] : array();
    }

    /**
     * Sort the style blocks so that the namespaces with inherited style go after
     * the namespaces they inherit from
     */
    public function sort() {
        $this->sortByIndex();
        $this->sortByDefaultInheritance();
        $this->sortByExplicitInheritance();
    }

    /**
     *
     */
    private function sortByIndex() {
        ksort($this->index);
    }

    /**
     *
     */
    private function sortByDefaultInheritance() {
        foreach ($this->index as &$index) {
            $namespace = array();

            foreach ($index as $style) {
                $namespace[] = $style->getNamespace();
            }

            array_multisort($namespace, SORT_ASC, $index);
        }
    }

    /**
     *
     */
    private function sortByExplicitInheritance() {
        foreach ($this->index as &$index) {
            $derived = array();
            $sorted = array();

            foreach ($index as $style) {
                if ($style->isDerived()) {
                    $derived[] = $style;
                }
                else {
                    $sorted[] = $style;
                }
            }

            $derivedCount = count($derived);

            if ($derivedCount > 0) {
                if ($derivedCount == 1) {
                    $sorted[] = $derived[0];
                }
                else {
                    /* Perform simplified topological sorting */
                    $target = array();
                    $source = array();

                    for ($i = 0; $i < $derivedCount; $i++) {
                        $target[$i] = $derived[$i]->getNamespace();
                        $source[$i] = $derived[$i]->getInheritedNamespace();
                    }

                    for ($j = 0; $j < $derivedCount; $j++) {
                        foreach ($source as $i => $s) {
                            if (!in_array($s, $target)) {
                                break;
                            }
                        }

                        $sorted[] = $derived[$i];

                        unset($target[$i]);
                        unset($source[$i]);
                    }
                }
            }

            $index = $sorted;
        }
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
    public function getScopesCount() {
        return count($this->scope);
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
        $this->style = array_merge($this->style, $style);
    }

    /**
     *
     */
    public function getStyle($name) {
        return array_key_exists($name, $this->style) ? $this->style[$name] : '';
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
    private function getScope($index) {
        $index = count($this->scope) + $index;

        return ($index >= 0) ? $this->scope[$index] : new refnotes_scope_mock();
    }

    /**
     *
     */
    private function getPreviousScope() {
        return $this->getScope(-2);
    }

    /**
     *
     */
    private function getCurrentScope() {
        return $this->getScope(-1);
    }

    /**
     *
     */
    public function getActiveScope() {
        if ($this->newScope) {
            $this->scope[] = new refnotes_scope($this, count($this->scope) + 1);
            $this->newScope = false;
        }

        return $this->getCurrentScope();
    }

    /**
     *
     */
    public function markScopeStart($callIndex) {
        if (!$this->getCurrentScope()->isOpen()) {
            $this->scope[] = new refnotes_scope(NULL, 0, $callIndex);
        }
    }

    /**
     *
     */
    public function markScopeEnd($callIndex) {
        /* Create an empty scope if there is no open one */
        $this->markScopeStart($callIndex - 1);
        $this->getCurrentScope()->getLimits()->end = $callIndex;
    }


    /**
     * Find last scope end within specified range
     */
    private function findScopeEnd($start, $end) {
        for ($i = count($this->scope) - 1; $i >= 0; $i--) {
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
        $currentStart = $this->getCurrentScope()->getLimits()->start;
        $parentEnd = ($parent != NULL) ? $parent->findScopeEnd($previousEnd, $currentStart) : -1;

        return max($parentEnd, $previousEnd) + 1;
    }

    /**
     *
     */
    public function renderNotes($limit = '') {
        $this->resetScope();
        $html = '';

        if (count($this->scope) > 0) {
            $html = $this->getCurrentScope()->renderNotes($limit);
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
}
