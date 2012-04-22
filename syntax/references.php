<?php

/**
 * Plugin RefNotes: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if (!defined('DOKU_INC') || !defined('DOKU_PLUGIN')) die();

require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/locale.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');
require_once(DOKU_PLUGIN . 'refnotes/core.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    private $mode;
    private $entryPattern;
    private $exitPattern;
    private $handlePattern;
    private $noteCapture;

    /**
     * Constructor
     */
    public function __construct() {
        refnotes_localization::initialize($this);

        $this->mode = substr(get_class($this), 7);
        $this->noteCapture = new refnotes_note_capture();

        $this->initializePatterns();
    }

    /**
     *
     */
    private function initializePatterns() {
        if (refnotes_configuration::getSetting('replace-footnotes')) {
            $entry = '(?:\(\(|\[\()';
            $exit = '(?:\)\)|\)\])';
            $name ='(?:@@FNT\d+|#\d+|[[:alpha:]]\w*)';
        }
        else {
            $entry = '\[\(';
            $exit = '\)\]';
            $name ='(?:#\d+|[[:alpha:]]\w*)';
        }

        $optionalNamespace ='(?:(?:[[:alpha:]]\w*)?:)*';
        $text = '.*?';

        $fullName = '\s*' . $optionalNamespace . $name .'\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $fullName . $lookaheadExit;

        $optionalFullName = $optionalNamespace . $name .'?';
        $structuredEntry = '\s*' . $optionalFullName . '\s*>>' . $text  . $lookaheadExit;

        $define = '\s*' . $optionalFullName . '\s*>\s*';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->entryPattern = $entry . '(?:' . $nameEntry . '|' . $structuredEntry . '|' . $defineEntry . ')';
        $this->exitPattern = $exit;
        $this->handlePattern = '/' . $entry . '\s*(' . $optionalFullName . ')\s*(>>)?(.*)/s';
    }

    /**
     * Return some info
     */
    public function getInfo() {
        return refnotes_getInfo('references syntax');
    }

    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'formatting';
    }

    /**
     * What modes are allowed within our mode?
     */
    public function getAllowedTypes() {
        return array (
            'formatting',
            'substition',
            'protected',
            'disabled'
        );
    }

    /**
     * Where to sort in?
     */
    public function getSort() {
        return 145;
    }

    public function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->entryPattern, $mode, $this->mode);
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->exitPattern, $this->mode);
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, $handler) {
        $result = refnotes_parser_core::getInstance()->canHandle($state);

        if ($result) {
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    $result = $this->handleEnter($match, $pos, $handler);
                    break;

                case DOKU_LEXER_EXIT:
                    $result = $this->handleExit($pos, $handler);
                    break;
            }
        }

        if ($result === false) {
            $handler->_addCall('cdata', array($match), $pos);
        }

        return $result;
    }

    /**
     * Create output
     */
    public function render($mode, $renderer, $data) {
        $result = false;

        try {
            switch ($mode) {
                case 'xhtml':
                    $result = $this->renderXhtml($renderer, $data);
                    break;

                case 'metadata':
                    $result = $this->renderMetadata($renderer, $data);
                    break;
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }

        return $result;
    }

    /**
     *
     */
    private function handleEnter($syntax, $pos, $handler) {
        if (preg_match($this->handlePattern, $syntax, $match) == 0) {
            return false;
        }

        $data = ($match[2] == '>>') ? $match[3] : '';
        $exitPos = $pos + strlen($syntax);

        refnotes_parser_core::getInstance()->enterReference($match[1], $data);

        return array('start');
    }

    /**
     *
     */
    private function handleExit($pos, $handler) {
        $reference = refnotes_parser_core::getInstance()->exitReference();

        if ($reference->hasData()) {
            return array('render', $reference->getAttributes(), $reference->getData());
        }
        else {
            return array('render', $reference->getAttributes());
        }
    }

    /**
     *
     */
    public function renderXhtml($renderer, $data) {
        switch ($data[0]) {
            case 'start':
                $this->noteCapture->start($renderer);
                break;

            case 'render':
                $this->renderReference($renderer, $data[1], (count($data) > 2) ? $data[2] : array());
                break;
        }

        return true;
    }

    /**
     * Stops renderer output capture and renders the reference link
     */
    private function renderReference($renderer, $attributes, $data) {
        $reference = refnotes_renderer_core::getInstance()->addReference($attributes, $data);
        $text = $this->noteCapture->stop();

        if ($text != '') {
            $reference->getNote()->setText($text);
        }

        $renderer->doc .= $reference->render();
    }

    /**
     *
     */
    public function renderMetadata($renderer, $data) {
        if ($data[0] == 'render') {
            $source = '';

            if (array_key_exists('source', $data[1])) {
                $source = $data[1]['source'];
            }

            if (($source != '') && ($source != '{configuration}')) {
                $renderer->meta['plugin']['refnotes']['dbref'][wikiFN($source)] = true;
            }
        }

        return true;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_capture {

    private $renderer;
    private $note;
    private $doc;

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize();
    }

    /**
     *
     */
    private function initialize() {
        $this->renderer = NULL;
        $this->doc = '';
    }

    /**
     *
     */
    private function resetCapture() {
        $this->renderer->doc = '';
    }

    /**
     *
     */
    public function start($renderer) {
        $this->renderer = $renderer;
        $this->doc = $renderer->doc;

        $this->resetCapture();
    }

    /**
     *
     */
    public function restart() {
        $text = trim($this->renderer->doc);

        $this->resetCapture();

        return $text;
    }

    /**
     *
     */
    public function stop() {
        $text = trim($this->renderer->doc);

        $this->renderer->doc = $this->doc;

        $this->initialize();

        return $text;
    }
}
