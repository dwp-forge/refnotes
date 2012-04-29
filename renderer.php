<?php

/**
 * Plugin RefNotes: Renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
abstract class refnotes_renderer_base {

    protected $namespace;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
    }

    /**
     *
     */
    protected function getStyle($name) {
        return $this->namespace->getStyle($name);
    }

    /**
     * Returns an array of keys for data that is shared between references and notes.
     */
    abstract public function getReferenceSharedDataSet();

    /**
     * Returns an array of keys for data that is private to references.
     */
    abstract public function getReferencePrivateDataSet();

    /**
     *
     */
    abstract public function renderReference($reference);

    /**
     *
     */
    abstract public function renderNoteText($note);

    /**
     *
     */
    abstract public function renderNote($note, $reference);
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer extends refnotes_renderer_base {

    private $referenceRenderer;
    private $noteRenderer;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        parent::__construct($namespace);

        $this->referenceRenderer = $this->createRenderer($this->getStyle('reference-render'));
        $this->noteRenderer = $this->createRenderer($this->getStyle('note-render'));
    }

    /**
     *
     */
    private function createRenderer($style) {
        switch ($style) {
            case 'harvard':
                $renderer = new refnotes_harvard_renderer($this->namespace);
                break;

            default:
                $renderer = new refnotes_basic_renderer($this->namespace);
                break;
        }

        return $renderer;
    }

    /**
     *
     */
    public function getReferenceSharedDataSet() {
        return $this->referenceRenderer->getReferenceSharedDataSet();
    }

    /**
     *
     */
    public function getReferencePrivateDataSet() {
        return $this->referenceRenderer->getReferencePrivateDataSet();
    }

    /**
     *
     */
    public function renderReference($reference) {
        return $this->referenceRenderer->renderReference($reference);
    }

    /**
     *
     */
    public function renderNotesSeparator() {
        $html = '';
        $style = $this->getStyle('notes-separator');
        if ($style != 'none') {
            if ($style != '') {
                $style = ' style="width: '. $style . '"';
            }
            $html = '<hr' . $style . '>' . DOKU_LF;
        }

        return $html;
    }

    /**
     *
     */
    public function renderNoteText($note) {
        return $this->noteRenderer->renderNoteText($note);
    }

    /**
     *
     */
    public function renderNote($note, $reference) {
        return $this->noteRenderer->renderNote($note, $reference);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_basic_renderer extends refnotes_renderer_base {

    /**
     *
     */
    public function getReferenceSharedDataSet() {
        return array();
    }

    /**
     *
     */
    public function getReferencePrivateDataSet() {
        return array();
    }

    /**
     *
     */
    public function renderReference($reference) {
        $html = '';

        $noteName = $reference->getNote()->getAnchorName();
        $referenceName = $reference->getAnchorName();
        $class = $this->renderReferenceClass();

        list($baseOpen, $baseClose) = $this->renderReferenceBase();
        list($fontOpen, $fontClose) = $this->renderReferenceFont();
        list($formatOpen, $formatClose) = $this->renderReferenceFormat();

        $html = $baseOpen . $fontOpen;
        $html .= '<a href="#' . $noteName . '" name="' . $referenceName . '" class="' . $class . '">';
        $html .= $formatOpen . $this->renderReferenceId($reference) . $formatClose;
        $html .= '</a>';
        $html .= $fontClose . $baseClose;

        return $html;
    }

    /**
     *
     */
    public function renderNoteText($note) {
        $data = $note->getData();

        if (array_key_exists('note-text', $data)) {
            $text = $data['note-text'];
        }
        elseif (array_key_exists('title', $data)) {
            $text = $data['title'];
        }
        else {
            $text = '';
            foreach($data as $value) {
                if (strlen($text) < strlen($value)) {
                    $text = $value;
                }
            }
        }

        if (array_key_exists('url', $data)) {
            $text = '[[' . $data['url'] . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    public function renderNote($note, $reference) {
        $html = '<div class="' . $this->renderNoteClass() . '">' . DOKU_LF;
        $html .= $this->renderBackReferences($note, $reference);
        $html .= '<span id="' . $note->getAnchorName() . ':text">' . DOKU_LF;
        $html .= $note->getText() . DOKU_LF;
        $html .= '</span></div>' . DOKU_LF;

        $this->rendered = true;

        return $html;
    }

    /**
     *
     */
    protected function renderBackReferences($note, $reference) {
        $references = count($reference);
        $singleReference = ($references == 1);
        $nameAttribute = ' name="' . $note->getAnchorName() .'"';
        $backRefFormat = $this->getStyle('back-ref-format');
        $backRefCaret = '';

        list($formatOpen, $formatClose) = $this->renderNoteIdFormat();

        if (($backRefFormat != 'note') && ($backRefFormat != '')) {
            list($baseOpen, $baseClose) = $this->renderNoteIdBase();
            list($fontOpen, $fontClose) = $this->renderNoteIdFont();

            $html .= $baseOpen . $fontOpen;
            $html .= '<a' . $nameAttribute .' class="nolink">';
            $html .= $formatOpen . $this->renderNoteId($note) . $formatClose;
            $html .= '</a>';
            $html .= $fontClose . $baseClose . DOKU_LF;

            $nameAttribute = '';
            $formatOpen = '';
            $formatClose = '';
            $backRefCaret = $this->renderBackRefCaret($singleReference);
        }

        if ($backRefFormat != 'none') {
            $separator = $this->renderBackRefSeparator();

            list($baseOpen, $baseClose) = $this->renderBackRefBase();
            list($fontOpen, $fontClose) = $this->renderBackRefFont();

            $html .= $baseOpen . $backRefCaret;

            for ($r = 0; $r < $references; $r++) {
                $referenceName = $reference[$r]->getAnchorName();

                if ($r > 0) {
                    $html .= $separator . DOKU_LF;
                }

                $html .= $fontOpen;
                $html .= '<a href="#' . $referenceName . '"' . $nameAttribute .' class="backref">';
                $html .= $formatOpen . $this->renderBackRefId($reference[$r], $r, $singleReference) . $formatClose;
                $html .= '</a>';
                $html .= $fontClose;

                $nameAttribute = '';
            }

            $html .= $baseClose . DOKU_LF;
        }

        return $html;
    }

    /**
     *
     */
    protected function renderReferenceClass() {
        switch ($this->getStyle('note-preview')) {
            case 'tooltip':
                $result = 'refnotes-ref note-tooltip';
                break;

            case 'none':
                $result = 'refnotes-ref';
                break;

            default:
                $result = 'refnotes-ref note-popup';
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderReferenceBase() {
        return $this->renderBase($this->getStyle('reference-base'));
    }

    /**
     *
     */
    protected function renderReferenceFont() {
        return $this->renderFont('reference-font-weight', 'normal', 'reference-font-style');
    }

    /**
     *
     */
    protected function renderReferenceFormat() {
        return $this->renderFormat($this->getStyle('reference-format'));
    }

    /**
     *
     */
    protected function renderReferenceId($reference) {
        $idStyle = $this->getStyle('refnote-id');
        if ($idStyle == 'name') {
            $html = $reference->getNote()->getName();
        }
        else {
            switch ($this->getStyle('multi-ref-id')) {
                case 'note':
                    $id = $reference->getNote()->getId();
                    break;

                default:
                    $id = $reference->getId();
                    break;
            }

            $html = $this->convertToStyle($id, $idStyle);
        }

        return $html;
    }

    /**
     *
     */
    protected function renderNoteClass() {
        $result = 'note';

        switch ($this->getStyle('note-font-size')) {
            case 'small':
                $result .= ' small';
                break;
        }

        switch ($this->getStyle('note-text-align')) {
            case 'left':
                $result .= ' left';
                break;

            default:
                $result .= ' justify';
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderNoteIdBase() {
        return $this->renderBase($this->getStyle('note-id-base'));
    }

    /**
     *
     */
    protected function renderNoteIdFont() {
        return $this->renderFont('note-id-font-weight', 'normal', 'note-id-font-style');
    }

    /**
     *
     */
    protected function renderNoteIdFormat() {
        $style = $this->getStyle('note-id-format');
        switch ($style) {
            case '.':
                $result = array('', '.');
                break;

            default:
                $result = $this->renderFormat($style);
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderNoteId($note) {
        $idStyle = $this->getStyle('refnote-id');
        if ($idStyle == 'name') {
            $html = $note->getName();
        }
        else {
            $html = $this->convertToStyle($note->getId(), $idStyle);
        }

        return $html;
    }

    /**
     *
     */
    protected function renderBackRefCaret($singleReference) {
        switch ($this->getStyle('back-ref-caret')) {
            case 'prefix':
                $result = '^ ';
                break;

            case 'merge':
                $result = $singleReference ? '' : '^ ';
                break;

            default:
                $result = '';
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderBackRefBase() {
        return $this->renderBase($this->getStyle('back-ref-base'));
    }

    /**
     *
     */
    protected function renderBackRefFont() {
        return $this->renderFont('back-ref-font-weight', 'bold', 'back-ref-font-style');
    }

    /**
     *
     */
    protected function renderBackRefSeparator() {
        static $html = array('' => ',', 'none' => '');

        $style = $this->getStyle('back-ref-separator');
        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderBackRefId($reference, $index, $singleReference) {
        $style = $this->getStyle('back-ref-format');
        switch ($style) {
            case 'a':
                $result = $this->convertToLatin($index + 1, $style);
                break;

            case '1':
                $result = $index + 1;
                break;

            case 'caret':
                $result = '^';
                break;

            case 'arrow':
                $result = '&uarr;';
                break;

            default:
                $result = $this->renderReferenceId($reference);
                break;
        }

        if ($singleReference && ($this->getStyle('back-ref-caret') == 'merge')) {
            $result = '^';
        }

        return $result;
    }

    /**
     *
     */
    protected function renderBase($style) {
        static $html = array(
            '' => array('<sup>', '</sup>'),
            'text' => array('', '')
        );

        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderFont($weight, $defaultWeight, $style) {
        list($weightOpen, $weightClose) = $this->renderFontWeight($this->getStyle($weight), $defaultWeight);
        list($styleOpen, $styleClose) = $this->renderFontStyle($this->getStyle($style));

        return array($weightOpen . $styleOpen, $styleClose . $weightClose);
    }

    /**
     *
     */
    protected function renderFontWeight($style, $default) {
        static $html = array(
            'normal' => array('', ''),
            'bold' => array('<b>', '</b>')
        );

        if (!array_key_exists($style, $html)) {
            $style = $default;
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderFontStyle($style) {
        static $html = array(
            '' => array('', ''),
            'italic' => array('<i>', '</i>')
        );

        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderFormat($style) {
        static $html = array(
            '' => array('', ')'),
            '()' => array('(', ')'),
            ']' => array('', ']'),
            '[]' => array('[', ']'),
            'none' => array('', '')
        );

        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function convertToStyle($id, $style) {
        switch ($style) {
            case 'a':
            case 'A':
                $result = $this->convertToLatin($id, $style);
                break;

            case 'i':
            case 'I':
                $result = $this->convertToRoman($id, $style);
                break;

            case '*':
                $result = str_repeat('*', $id);
                break;

            default:
                $result = $id;
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function convertToLatin($number, $case)
    {
        static $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $result = '';
        while ($number > 0) {
            --$number;
            $digit = $number % 26;
            $result = $alpha{$digit} . $result;
            $number = intval($number / 26);
        }

        if ($case == 'a') {
            $result = strtolower($result);
        }

        return $result;
    }

    /**
     *
     */
    protected function convertToRoman($number, $case)
    {
        static $lookup = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        );

        $result = '';
        foreach ($lookup as $roman => $value) {
            $matches = intval($number / $value);
            if ($matches > 0) {
                $result .= str_repeat($roman, $matches);
                $number = $number % $value;
            }
        }

        if ($case == 'i') {
            $result = strtolower($result);
        }

        return $result;
    }

    /**
     *
     */
    protected function isPositive($data)
    {
        static $lookup = array('y', 'yes', 'on', 'true', '1');

        return in_array(strtolower($data), $lookup);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_harvard_renderer extends refnotes_basic_renderer {

    /**
     * Constructor
     */
    public function __construct($namespace) {
        parent::__construct($namespace);
    }

    /**
     *
     */
    public function getReferenceSharedDataSet() {
        static $key = array('authors', 'authors-short', 'published');

        return $key;
    }

    /**
     *
     */
    public function getReferencePrivateDataSet() {
        static $key = array('inline');

        return $key;
    }

    /**
     *
     */
    public function renderNoteText($note) {
        $data = $note->getData();

        if (!array_key_exists('title', $data)) {
            return parent::renderNoteText($note);
        }

        // authors, published. //[[url|title.]]// edition. publisher, pages, isbn.
        // authors, published. chapter In //[[url|title.]]// edition. publisher, pages, isbn.
        // authors, published. [[url|title.]] //journal//, volume, publisher, pages, issn.

        $title = $this->renderTitle($data);

        // authors, published. //$title// edition. publisher, pages, isbn.
        // authors, published. chapter In //$title// edition. publisher, pages, isbn.
        // authors, published. $title //journal//, volume, publisher, pages, issn.

        $authors = $this->renderNoteAuthors($data);

        // $authors? //$title// edition. publisher, pages, isbn.
        // $authors? chapter In //$title// edition. publisher, pages, isbn.
        // $authors? $title //journal//, volume, publisher, pages, issn.

        $publication = $this->renderPublication($data, $authors != '');

        if (array_key_exists('journal', $data)) {
            // $authors? $title //journal//, volume, $publication?

            $text = $title . ' ' . $this->renderJournal($data);

            // $authors? $text, $publication?

            $text .= ($publication != '') ? ',' : '.';
        }
        else {
            // $authors? //$title// edition. $publication?
            // $authors? chapter In //$title// edition. $publication?

            $text = $this->renderBook($data, $title);
        }

        // $authors? $text $publication?

        if ($authors != '') {
            $text = $authors . ' ' . $text;
        }

        if ($publication != '') {
            $text .= ' ' . $publication;
        }

        return $text;
    }

    /**
     *
     */
    protected function renderTitle($data) {
        $text = $data['title'] . '.';

        if (array_key_exists('url', $data)) {
            $text = '[[' . $data['url'] . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderNoteAuthors($data) {
        $text = '';

        if (array_key_exists('authors', $data)) {
            $text = $data['authors'];

            if (array_key_exists('published', $data)) {
                $text .= ', ' . $data['published'];
            }

            $text .= '.';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderPublication($data, $authors) {
        $part = array();

        if (array_key_exists('publisher', $data)) {
            $part[] = $data['publisher'];
        }

        if (!$authors && array_key_exists('published', $data)) {
            $part[] = $data['published'];
        }

        $pages = $this->renderPages($data, array('note-pages', 'note-page', 'pages', 'page'));

        if ($pages != '') {
            $part[] = $pages;
        }

        if (array_key_exists('isbn', $data)) {
            $part[] = 'ISBN ' . $data['isbn'];
        }
        elseif (array_key_exists('issn', $data)) {
            $part[] = 'ISSN ' . $data['issn'];
        }

        $text = implode(', ', $part);

        if ($text != '') {
            $text = rtrim($text, '.') . '.';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderPages($data, $key) {
        $text = '';

        foreach ($key as $k) {
            if (array_key_exists($k, $data)) {
                $text = $data[$k];

                if (preg_match("/^[0-9]/", $text)) {
                    $abbr_key = (substr($k, -1) == 's') ? 'txt_pages_abbr' : 'txt_page_abbr';
                    $text = refnotes_localization::getInstance()->getLang($abbr_key) . $text;
                }
                break;
            }
        }

        return $text;
    }

    /**
     *
     */
    protected function renderJournal($data) {
        $text = '//' . $data['journal'] . '//';

        if (array_key_exists('volume', $data)) {
            $text .= ', ' . $data['volume'];
        }

        return $text;
    }

    /**
     *
     */
    protected function renderBook($data, $title) {
        $text = '//' . $title . '//';

        if (array_key_exists('chapter', $data)) {
            $text = $data['chapter'] . '. ' . refnotes_localization::getInstance()->getLang('txt_in_cap') . ' ' . $text;
        }

        if (array_key_exists('edition', $data)) {
            $text .= ' ' . $data['edition'] . '.';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderReferenceId($reference) {
        $data = $reference->getData();

        if (!$this->checkReferenceData($data)) {
            return $this->renderBasicReferenceId($reference);
        }

        $authors = $this->renderReferenceAuthors($data);
        $html = $this->renderReferenceExtra($data);

        list($formatOpen, $formatClose) = $this->renderReferenceParentheses();

        if (array_key_exists('inline', $data) && $this->isPositive($data['inline'])) {
            $html = $authors . ' ' . $formatOpen . $html . $formatClose;
        }
        else {
            $html = $formatOpen . $authors . ', ' . $html . $formatClose;
        }

        return htmlspecialchars($html);
    }

    /**
     *
     */
    protected function renderBasicReferenceId($reference) {
        list($formatOpen, $formatClose) = parent::renderReferenceFormat();

        return $formatOpen . parent::renderReferenceId($reference) . $formatClose;
    }

    /**
     *
     */
    protected function renderReferenceAuthors($data) {
        if (array_key_exists('authors-short', $data)) {
            $html = $data['authors-short'];
        }
        else {
            $html = $data['authors'];
        }

        return $html;
    }

    /**
     *
     */
    protected function renderReferenceExtra($data) {
        $html = '';

        if (array_key_exists('published', $data)) {
            $html .= $data['published'];
        }

        $pages = $this->renderPages($data, array('page', 'pages'));

        if ($pages != '') {
            if ($html != '') {
                $html .= ', ';
            }

            $html .= $pages;
        }

        return $html;
    }

    /**
     *
     */
    protected function renderReferenceParentheses() {
        $style = $this->getStyle('reference-format');
        $style = (($style == '[]') || ($style == ']')) ? '[]' : '()';

        return $this->renderFormat($style);
    }

    /**
     *
     */
    protected function renderReferenceFormat() {
        return array('', '');
    }

    /**
     *
     */
    protected function checkReferenceData($data) {
        $authors = array_key_exists('authors', $data) || array_key_exists('authors-short', $data);
        $year = array_key_exists('published', $data);
        $page = array_key_exists('page', $data) || array_key_exists('pages', $data);

        return $authors && ($year || $page);
    }
}
