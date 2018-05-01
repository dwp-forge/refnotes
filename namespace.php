<?php

/**
 * Plugin RefNotes: Namespace heplers
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
abstract class refnotes_namespace_data_stash {

    protected $index;

    /**
     * Constructor
     */
    public function __construct() {
        $this->index = array();
    }

    /**
     *
     */
    abstract public function add($namespace, $data);

    /**
     *
     */
    public function getCount() {
        return count($this->index);
    }

    /**
     *
     */
    public function getIndex() {
        return array_keys($this->index);
    }

    /**
     *
     */
    public function getAt($index) {
        return array_key_exists($index, $this->index) ? $this->index[$index] : array();
    }

    /**
     *
     */
    public function sort() {
        ksort($this->index);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_namespace_data {

    protected $namespace;
    protected $data;

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
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_namespace_style_stash extends refnotes_namespace_data_stash {

    private $page;

    /**
     * Constructor
     */
    public function __construct($page) {
        parent::__construct();

        $this->page = $page;
    }

    /**
     *
     */
    public function add($namespace, $data) {
        $style = new refnotes_namespace_style_info($namespace, $data);
        $parent = $style->getInheritedNamespace();

        if (($parent == '') && ($namespace->getScopesCount() == 1)) {
            /* Default inheritance for the first scope */
            $parent = refnotes_namespace::getParentName($namespace->getName());
        }

        $index = $namespace->getStyleIndex($this->page->findParentNamespace($parent));

        $this->index[$index][] = $style;
    }
    /**
     * Sort the style blocks so that the namespaces with inherited style go after
     * the namespaces they inherit from.
     */
    public function sort() {
        parent::sort();

        $this->sortByDefaultInheritance();
        $this->sortByExplicitInheritance();
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
class refnotes_namespace_style_info extends refnotes_namespace_data {

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
class refnotes_namespace_mapping_stash extends refnotes_namespace_data_stash {

    /**
     *
     */
    public function add($namespace, $data) {
        $this->index[$namespace->getMappingIndex()][] = new refnotes_namespace_data($namespace, $data);
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
     *
     */
    public static function getNamePattern($type) {
        $result = '(?:(?:' . refnotes_note::getNamePattern('strict') . ')?:)*';

        if ($type == 'required') {
            $result .= refnotes_note::getNamePattern('strict') . ':*';
        }

        return $result;
    }

    /**
     * Returns canonic name for a namespace
     */
    public static function canonizeName($name) {
        return preg_replace('/:{2,}/', ':', ':' . $name . ':');
    }

    /**
     * Returns name of the parent namespace
     */
    public static function getParentName($name) {
        return preg_replace('/\w*:$/', '', $name);
    }

    /**
     * Splits full note name into namespace and name components
     */
    public static function parseName($name) {
        $pos = strrpos($name, ':');
        if ($pos !== false) {
            $namespace = self::canonizeName(substr($name, 0, $pos));
            $name = substr($name, $pos + 1);
        }
        else {
            $namespace = ':';
        }

        return array($namespace, $name);
    }

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
        $this->renderer = NULL;
    }

    /**
     *
     */
    public function setStyle($style) {
        $this->style = array_merge($this->style, $style);
        $this->renderer = NULL;
    }

    /**
     *
     */
    public function getStyle($name) {
        return array_key_exists($name, $this->style) ? $this->style[$name] : '';
    }

    /**
     * Defer creation of renderer until namespace style is set.
     */
    public function getRenderer() {
        if ($this->renderer == NULL) {
            $this->renderer = new refnotes_renderer($this);
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
    public function getMappingIndex() {
        return $this->getPreviousScope()->getLimits()->end + 1;
    }

    /**
     *
     */
    public function rewriteReferences($limit = '') {
        $this->resetScope();

        if (count($this->scope) > 0) {
            $html = $this->getCurrentScope()->rewriteReferences($limit);
        }
    }

    /**
     *
     */
    public function renderNotes($mode, $limit = '') {
        $this->resetScope();
        $doc = '';

        if (count($this->scope) > 0) {
            $doc = $this->getCurrentScope()->renderNotes($mode, $limit);
        }

        return $doc;
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
