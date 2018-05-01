<?php

/**
 * Plugin RefNotes: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

/* Must be run within Dokuwiki */
if (!defined('DOKU_INC') || !defined('DOKU_PLUGIN')) die();

require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'refnotes/core.php');
require_once(DOKU_PLUGIN . 'refnotes/bibtex.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {
    use refnotes_localization_plugin;

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
            $id = '@@FNT\d+|#\d+';
        }
        else {
            $entry = '\[\(';
            $exit = '\)\]';
            $id = '#\d+';
        }

        $strictName = refnotes_note::getNamePattern('strict');
        $extendedName = refnotes_note::getNamePattern('extended');
        $namespace = refnotes_namespace::getNamePattern('optional');

        $text = '.*?';

        $strictName = '(?:' . $id . '|' . $strictName . ')';
        $fullName = '\s*(?:' . $namespace . $strictName . '|:' . $namespace . $extendedName . ')\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $fullName . $lookaheadExit;

        $extendedName = '(?:' . $id . '|' . $extendedName . ')';
        $optionalFullName = $namespace . $extendedName . '?';
        $structuredEntry = '\s*' . $optionalFullName . '\s*>>' . $text  . $lookaheadExit;

        $define = '\s*' . $optionalFullName . '\s*>\s*';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->entryPattern = $entry . '(?:' . $nameEntry . '|' . $structuredEntry . '|' . $defineEntry . ')';
        $this->exitPattern = $exit;
        $this->handlePattern = '/' . $entry . '\s*(' . $optionalFullName . ')\s*(?:>>(.*))?(.*)/s';
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
        refnotes_parser_core::getInstance()->registerLexer($this->Lexer);

        $this->Lexer->addEntryPattern($this->entryPattern, $mode, $this->mode);
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->exitPattern, $this->mode);
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $result = refnotes_parser_core::getInstance()->canHandle($state);

        if ($result) {
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    $result = $this->handleEnter($match);
                    break;

                case DOKU_LEXER_EXIT:
                    $result = $this->handleExit();
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
    public function render($mode, Doku_Renderer $renderer, $data) {
        $result = false;

        try {
            switch ($mode) {
                case 'xhtml':
                case 'odt':
                    $result = $this->renderReferences($mode, $renderer, $data);
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
    private function handleEnter($syntax) {
        if (preg_match($this->handlePattern, $syntax, $match) == 0) {
            return false;
        }

        refnotes_parser_core::getInstance()->enterReference($match[1], $match[2]);

        return array('start');
    }

    /**
     *
     */
    private function handleExit() {
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
    public function renderReferences($mode, $renderer, $data) {
        switch ($data[0]) {
            case 'start':
                $this->noteCapture->start($renderer);
                break;

            case 'render':
                $this->renderReference($mode, $renderer, $data[1], (count($data) > 2) ? $data[2] : array());
                break;
        }

        return true;
    }

    /**
     * Stops renderer output capture and renders the reference link
     */
    private function renderReference($mode, $renderer, $attributes, $data) {
        $reference = refnotes_renderer_core::getInstance()->addReference($attributes, $data);
        $text = $this->noteCapture->stop();

        if ($text != '') {
            $reference->getNote()->setText($text);
        }

        $renderer->doc .= $reference->render($mode);
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

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_nested_call_writer extends Doku_Handler_Nest {

    private $handler;
    private $callWriterBackup;

    /**
     * Constructor
     */
    public function __construct($handler) {
        $this->handler = $handler;

        parent::__construct($this->handler->CallWriter);
    }

    /**
     *
     */
    public function connect() {
        $this->callWriterBackup = $this->handler->CallWriter;
        $this->handler->CallWriter = $this;
    }

    /**
     *
     */
    public function disconnect() {
        $this->handler->CallWriter = $this->callWriterBackup;
    }
}
