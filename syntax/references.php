<?php

/**
 * Plugin RefNotes: Reference collector/renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');

class syntax_plugin_refnotes_references extends DokuWiki_Syntax_Plugin {

    private $mode;
    private $entrySyntax;
    private $exitSyntax;
    private $entryPattern;
    private $exitPattern;
    private $handlePattern;
    private $core;
    private $note;
    private $handling;
    private $embedding;
    private $lastHiddenExit;
    private $capturedNote;
    private $docBackup;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mode = substr(get_class($this), 7);
        $this->entrySyntax = '[(';
        $this->exitSyntax = ')]';
        $this->core = NULL;
        $this->note = refnotes_loadConfigFile('notes');
        $this->handling = false;
        $this->embedding = false;
        $this->lastHiddenExit = -1;
        $this->capturedNote = NULL;
        $this->docBackup = '';

        $this->initializePatterns();
    }

    /**
     *
     */
    private function initializePatterns() {
        $useFootnoteSyntax = false;
        $config = refnotes_loadConfigFile('general');

        if (array_key_exists('replace-footnotes', $config)) {
            $useFootnoteSyntax = $config['replace-footnotes'];
        }

        if ($useFootnoteSyntax) {
            $entry = '(?:\(\(|\[\()';
            $exit = '(?:\)\)|\)\])';
            $name ='(?:@@FNT\d+|#\d+|[[:alpha:]]\w*)';
        }
        else {
            $entry = '\[\(';
            $exit = '\)\]';
            $name ='(?:#\d+|[[:alpha:]]\w*)';
        }

        $newLine = '(?:\n?[ \t]*\n)?';
        $namespace ='(?:(?:[[:alpha:]]\w*)?:)*';
        $text = '.*?';

        $nameMatch = '\s*' . $namespace . $name .'\s*';
        $lookaheadExit = '(?=' . $exit . ')';
        $nameEntry = $nameMatch . $lookaheadExit;

        $optionalName = $name .'?';
        $define = '\s*' . $namespace . $optionalName .'\s*>';
        $optionalDefine = '(?:' . $define . ')?';
        $lookaheadExit = '(?=' . $text . $exit . ')';
        $defineEntry = $optionalDefine . $lookaheadExit;

        $this->entryPattern = $newLine . $entry . '(?:' . $nameEntry . '|' . $defineEntry . ')';
        $this->exitPattern = $exit;
        $this->handlePattern = '/(\s*)' . $entry . '\s*(' . $namespace . $optionalName . ').*/';
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

    public function accepts($mode) {
        if ($mode == $this->mode) {
            return true;
        }

        return parent::accepts($mode);
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
        switch ($state) {
            case DOKU_LEXER_ENTER:
                if (!$this->handling) {
                    return $this->handleEnter($match, $pos, $handler);
                }
                break;

            case DOKU_LEXER_EXIT:
                if ($this->handling) {
                    return $this->handleExit($match, $pos);
                }
                break;
        }

        $handler->_addCall('cdata', array($match), $pos);

        return false;
    }

    /**
     * Create output
     */
    public function render($mode, $renderer, $data) {
        try {
            if($mode == 'xhtml') {
                switch ($data[0]) {
                    case DOKU_LEXER_ENTER:
                        $this->renderEnter($renderer, $data[1]);
                        break;

                    case DOKU_LEXER_EXIT:
                        $this->renderExit($renderer);
                        break;
                }

                return true;
            }
        }
        catch (Exception $error) {
            msg($error->getMessage(), -1);
        }

        return false;
    }

    /**
     *
     */
    private function handleEnter($syntax, $pos, $handler) {
        if (preg_match($this->handlePattern, $syntax, $match) == 0) {
            return false;
        }

        list($namespace, $name) = refnotes_parseName($match[2]);

        if (!$this->embedding) {
            $fullName = $namespace . $name;
            if (array_key_exists($fullName, $this->note)) {
                $this->embedPredefinedNote($fullName, $pos, $handler);
            }
        }

        $this->handling = true;

        $info['ns'] = $namespace;
        $info['name'] = $name;
        $info['hidden'] = $this->isHiddenReference($match[1], $pos, $handler);

        return array(DOKU_LEXER_ENTER, $info);
    }

    /**
     *
     */
    private function embedPredefinedNote($name, $pos, $handler) {
        $text = $this->entrySyntax . $name . '>' . $this->note[$name]['text'] . $this->exitSyntax;

        $lastHiddenExit = $this->lastHiddenExit;
        $this->lastHiddenExit = 0;
        $this->embedding = true;

        $this->parseNestedText($text, $pos, $handler);

        $this->embedding = false;
        $this->lastHiddenExit = $lastHiddenExit;

        if ($this->note[$name]['inline']) {
            $handler->calls[count($handler->calls) - 1][1][0][0][1][1][1]['inline'] = true;
        }
    }

    /**
     *
     */
    private function parseNestedText($text, $pos, $handler) {
        $nestedWriter = new Doku_Handler_Nest($handler->CallWriter);
        $handler->CallWriter =& $nestedWriter;

        $this->Lexer->parse($text);

        $nestedWriter->process();
        $handler->CallWriter =& $nestedWriter->CallWriter;

        $handler->calls[count($handler->calls) - 1][2] = $pos;
    }

    /**
     *
     */
    private function handleExit($syntax, $pos) {
        $this->handling = false;

        if ($this->lastHiddenExit >= 0) {
            $this->lastHiddenExit = $pos + strlen($syntax);
        }

        return array(DOKU_LEXER_EXIT);
    }

    /**
     *
     */
    private function isHiddenReference($space, $pos, $handler) {
        $newLines = substr_count($space, "\n");
        $lastCall = end($handler->calls);
        $lastCall = $lastCall[0];

        if (($newLines == 2) || ($lastCall == 'table_close')) {
            $this->lastHiddenExit = $pos;
        }
        else {
            if (($this->lastHiddenExit >= 0) && ($this->lastHiddenExit < $pos)) {
                $this->lastHiddenExit = -1;
            }
        }

        return $this->lastHiddenExit >= 0;
    }

    /**
     * Renders reference link and starts renderer output capture
     */
    private function renderEnter($renderer, $info) {
        $core = $this->getCore();

        $inline = false;
        if (array_key_exists('inline', $info)) {
            $inline = $info['inline'];
        }

        $note = $core->addReference($info['ns'], $info['name'], $info['hidden'], $inline);
        if (($note != NULL) && !$info['hidden']) {
            $renderer->doc .= $note->renderReference();
        }

        $this->startCapture($renderer, $note);
    }

    /**
     * Stops renderer output capture
     */
    private function renderExit($renderer) {
        $this->stopCapture($renderer);
    }

    /**
     *
     */
    private function getCore() {
        if ($this->core == NULL) {
            $this->core = plugin_load('helper', 'refnotes');
            if ($this->core == NULL) {
                throw new Exception('Helper plugin "refnotes" is not available or invalid.');
            }
        }

        return $this->core;
    }

    /**
     * Starts renderer output capture
     */
    private function startCapture($renderer, $note) {
        $this->capturedNote = $note;
        $this->docBackup = $renderer->doc;
        $renderer->doc = '';
    }

    /**
     * Stops renderer output capture
     */
    private function stopCapture($renderer) {
        $text = trim($renderer->doc);
        if ($text != '') {
            $this->capturedNote->setText($text);
        }

        $renderer->doc = $this->docBackup;
        $this->capturedNote = NULL;
        $this->docBackup = '';
    }
}
