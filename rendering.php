<?php

/**
 * Plugin RefNotes: Renderer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer_mock {

    /**
     *
     */
    public function renderReference($reference) {
        return '';
    }
}

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
     * Returns an array of keys for data that is specific to references.
     */
    abstract public function getReferencePrivateDataSet();

    /**
     *
     */
    abstract public function renderReference($mode, $reference);

    /**
     *
     */
    abstract public function renderNoteText($note);

    /**
     *
     */
    abstract public function renderNote($mode, $note, $reference);
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
    public function renderReference($mode, $reference) {
        return $this->referenceRenderer->renderReference($mode, $reference);
    }

    /**
     *
     */
    public function renderNotesSeparator() {
        $html = '';
        $style = $this->getStyle('notes-separator');
        if ($style != 'none') {
            if (!empty($style)) {
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
    public function renderNote($mode, $note, $reference) {
        return $this->noteRenderer->renderNote($mode, $note, $reference);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer_data {

    private $data;

    /**
     * Constructor
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     *
     */
    public function has($key) {
        if (func_num_args() > 1) {
            $result = false;

            foreach (func_get_args() as $key) {
                if (array_key_exists($key, $this->data)) {
                    $result = true;
                    break;
                }
            }

            return $result;
        }
        else {
            return array_key_exists($key, $this->data);
        }
    }

    /**
     *
     */
    public function get($key) {
        if (func_num_args() > 1) {
            $result = '';

            foreach (func_get_args() as $key) {
                if (array_key_exists($key, $this->data)) {
                    $result = $this->data[$key];
                    break;
                }
            }

            return $result;
        }
        else {
            return array_key_exists($key, $this->data) ? $this->data[$key] : '';
        }
    }

    /**
     *
     */
    public function getLongest() {
        $result = '';

        if (func_num_args() > 0) {
            foreach (func_get_args() as $key) {
                if (array_key_exists($key, $this->data) && (strlen($result) < strlen($this->data[$key]))) {
                    $result = $this->data[$key];
                }
            }
        }
        else {
            foreach ($this->data as $value) {
                if (strlen($result) < strlen($value)) {
                    $result = $value;
                }
            }
        }

        return $result;
    }

    /**
     *
     */
    public function isPositive($key)
    {
        static $lookup = array('y', 'yes', 'on', 'true', '1');

        return in_array(strtolower($this->get($key)), $lookup);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_basic_renderer extends refnotes_renderer_base {

    protected $renderedNoteId = array();

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
    public function renderReference($mode, $reference) {
        if ($reference->isInline()) {
            $doc = $this->renderInlineReference($reference);
        }
        else {
            $doc = $this->renderRegularReference($mode, $reference);
        }

        return $doc;
    }

    /**
     *
     */
    public function renderNoteText($note) {
        $data = new refnotes_renderer_data($note->getData());

        $text = $data->get('note-text', 'title');

        if (empty($text)) {
            $text = $data->getLongest();
        }

        if ($url = $data->get('url')) {
            $text = '[[' . $url . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    public function renderNote($mode, $note, $reference) {
        $doc = '';

        switch ($mode) {
            case 'xhtml':
                $doc = $this->renderNoteXhtml($note, $reference);
                break;

            case 'odt':
                $doc = $this->renderNoteOdt($note, $reference);
                break;
        }

        return $doc;
    }

    /**
     *
     */
    protected function renderNoteXhtml($note, $reference) {
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
    protected function renderNoteOdt($note, $reference) {
        $this->rendered = true;

        return '';
    }

    /**
     *
     */
    protected function getInlineReferenceStyle($reference, $name, $default) {
        return ($reference->getAttribute('use-' . $name) === false) ? $default : $this->getStyle($name);
    }

    /**
     *
     */
    protected function renderInlineReference($reference) {
        $baseStyle = $this->getInlineReferenceStyle($reference, 'reference-base', 'text');
        $fontWeightStyle = $this->getInlineReferenceStyle($reference, 'reference-font-weight', 'normal');
        $fontStyle = $this->getInlineReferenceStyle($reference, 'reference-font-style', 'normal');
        $formatStyle = $this->getInlineReferenceStyle($reference, 'reference-format', 'none');

        list($baseOpen, $baseClose) = $this->renderBase($baseStyle);
        list($fontOpen, $fontClose) = $this->renderFont($fontWeightStyle, 'normal', $fontStyle);
        list($formatOpen, $formatClose) = $this->renderFormat($formatStyle);

        $html = $baseOpen . $fontOpen . $formatOpen;
        $html .= $reference->getNote()->getText();
        $html .= $formatClose . $fontClose . $baseClose;

        return $html;
    }

    /**
     *
     */
    protected function renderRegularReference($mode, $reference) {
        $doc = '';

        switch ($mode) {
            case 'xhtml':
                $doc = $this->renderRegularReferenceXhtml($reference);
                break;

            case 'odt':
                $doc = $this->renderRegularReferenceOdt($reference);
                break;
        }

        return $doc;
    }

    /**
     *
     */
    protected function renderRegularReferenceXhtml($reference) {
        $noteName = $reference->getNote()->getAnchorName();
        $referenceName = $reference->getAnchorName();
        $class = $this->renderReferenceClass();

        list($baseOpen, $baseClose) = $this->renderReferenceBase();
        list($fontOpen, $fontClose) = $this->renderReferenceFont();
        list($formatOpen, $formatClose) = $this->renderReferenceFormat($reference);

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
    protected function renderRegularReferenceOdt($reference) {
        $xmlOdt = '';
        $note = $reference->getNote();
        $noteId = $note->getId();
        $refId = $reference->getId();

        // Check to see if this note has been seen before

        if (array_search($noteId, $this->renderedNoteId) === false) {
            // new note, add it to the $renderedNoteId array
            $this->renderedNoteId[] = $noteId;

            $xmlOdt .= '<text:note text:id="refnote' . $refId . '" text:note-class="footnote">';
            $xmlOdt .= '<text:note-citation text:label="' . $refId . '">' . $refId . '</text:note-citation>';
            $xmlOdt .= '<text:note-body>';
            $xmlOdt .= '<text:p>' . $note->getText();
            $xmlOdt .= '</text:p>';
            $xmlOdt .= '</text:note-body>';
            $xmlOdt .= '</text:note>';
        }
        else {
            // Seen this one before - just reference it FIXME: style isn't correct
            $xmlOdt = '<text:note-ref text:note-class="footnote" text:ref-name="refnote' . $noteId . '">';
            $xmlOdt .= $refId;
            $xmlOdt .= '</text:note-ref>';
        }

        return $xmlOdt;
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
        $html = '';

        list($formatOpen, $formatClose) = $this->renderNoteIdFormat();

        if (($backRefFormat != 'note') && !empty($backRefFormat)) {
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
    protected function renderReferenceFormat($reference) {
        $result = $this->renderFormat($this->getStyle('reference-format'));

        if ($this->getReferenceGrouping($reference)) {
            switch ($reference->getAttribute('group')) {
                case 'open':
                    $result = array($result[0], $this->renderReferenceGroupSeparator());
                    break;

                case 'hold':
                    $result = array('', $this->renderReferenceGroupSeparator());
                    break;

                case 'close':
                    $result = array('', $result[1]);
                    break;
            }
        }

        return $result;
    }

    /**
     *
     */
    protected function getReferenceGrouping($reference) {
        $group = $reference->getAttribute('group');
        return !empty($group) && in_array($group, array('open', 'hold', 'close')) &&
                in_array($this->getStyle('reference-group'), array(',', 's'));
    }

    /**
     *
     */
    protected function renderReferenceGroupSeparator() {
        $style = $this->getStyle('reference-group');
        switch ($style) {
            case ',':
                $result = ',';
                break;

            case 's':
                $result = ';';
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
            case 'normal':
                break;

            case 'small':
                $result .= ' small';
                break;

            default:
                $result .= ' reduced';
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
    protected function convertToLatin($number, $case) {
        static $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $result = '';

        while ($number > 0) {
            --$number;
            $digit = $number % 26;
            $result = $alpha[$digit] . $result;
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
    protected function convertToRoman($number, $case) {
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
        static $key = array('ref-authors', 'ref-author', 'authors', 'author', 'published', 'month', 'year');

        return $key;
    }

    /**
     *
     */
    public function getReferencePrivateDataSet() {
        static $key = array('direct', 'pages', 'page');

        return $key;
    }

    /**
     *
     */
    public function renderNoteText($note) {
        $data = new refnotes_renderer_data($note->getData());

        if (!$data->has('title')) {
            return parent::renderNoteText($note);
        }

        // authors, published. //[[url|title.]]// edition. [format], publisher, pages, isbn. (accessed)
        // authors, published. chapter In //[[url|title.]]// edition. [format], publisher, pages, isbn. (accessed)
        // authors, published. [[url|title.]] //journal//, volume, [format], publisher, pages, issn. (accessed)
        // authors, published. [[url|title.]] //booktitle//, [format], publisher, pages, issn. (accessed)

        $title = $this->renderTitle($data);

        // authors, published. //$title// edition. [format], publisher, pages, isbn. (accessed)
        // authors, published. chapter In //$title// edition. [format], publisher, pages, isbn. (accessed)
        // authors, published. $title //journal//, volume, [format], publisher, pages, issn. (accessed)
        // authors, published. $title //booktitle//, [format], publisher, pages, issn. (accessed)

        $authors = $this->renderAuthors($data);

        // $authors? //$title// edition. [format], publisher, pages, isbn. (accessed)
        // $authors? chapter In //$title// edition. [format], publisher, pages, isbn. (accessed)
        // $authors? $title //journal//, volume, [format], publisher, pages, issn. (accessed)
        // $authors? $title //booktitle//, [format], publisher, pages, issn. (accessed)

        $publication = $this->renderPublication($data, !empty($authors));

        if ($data->has('journal')) {
            // $authors? $title //journal//, volume, $publication?

            $subtitle = $this->renderJournal($data);
        }
        elseif ($data->has('booktitle')) {
            // $authors? $title //booktitle//, $publication?

            $subtitle = $this->renderBookTitle($data);
        }

        if (!empty($subtitle)) {
            // $authors? $title $subtitle?, $publication?

            $text = $title . ' ' . $subtitle;

            // $authors? $text, $publication?

            $text .= !empty($publication) ? ',' : '.';
        }
        else {
            // $authors? //$title// edition. $publication?
            // $authors? chapter In //$title// edition. $publication?

            $text = $this->renderBook($data, $title);
        }

        // $authors? $text $publication?

        if (!empty($authors)) {
            $text = $authors . ' ' . $text;
        }

        if (!empty($publication)) {
            $text .= ' ' . $publication;
        }

        return $text;
    }

    /**
     *
     */
    protected function renderTitle($data) {
        $text = $data->get('title') . '.';

        if ($url = $data->get('url')) {
            $text = '[[' . $url . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderAuthors($data) {
        $text = $data->get('authors', 'author');

        if (!empty($text)) {
            if ($published = $this->renderPublished($data)) {
                $text .= ', ' . $published;
            }

            $text .= '.';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderPublished($data, $useMonth = true) {
        $text = $data->get('published');

        if (empty($text)) {
            if ($text = $data->get('year')) {
                if ($useMonth && $month = $data->get('month')) {
                    $text = $month . ' ' . $text;
                }
            }
        }

        return $text;
    }

    /**
     *
     */
    protected function renderPublication($data, $authors) {
        $part = array();

        if ($format = $data->get('format')) {
            $part[] = '[' . $format . ']';
        }

        $address = $data->get('address');
        $publisher = $data->get('publisher');

        if ($address && $publisher) {
            $part[] = $address . ': ' . $publisher;
        }
        else {
            if ($address || $publisher) {
                $part[] = $address . $publisher;
            }
        }

        if (!$authors && ($published = $this->renderPublished($data))) {
            $part[] = $published;
        }

        if ($pages = $this->renderPages($data, array('note-pages', 'note-page', 'pages', 'page'))) {
            $part[] = $pages;
        }

        if ($isbn = $data->get('isbn')) {
            $part[] = 'ISBN ' . $isbn;
        }
        elseif ($issn = $data->get('issn')) {
            $part[] = 'ISSN ' . $issn;
        }

        $text = implode(', ', $part);

        if (!empty($text)) {
            $text = rtrim($text, '.') . '.';
        }

        if ($accessed = $data->get('accessed')) {
            $accessed = '(' . refnotes_localization::getInstance()->getLang('txt_accessed_cap') . ': ' . $accessed . ')';

            if (!empty($text)) {
                $text .= ' ' . $accessed;
            }
            else {
                $text = $accessed;
            }
        }

        return $text;
    }

    /**
     *
     */
    protected function renderPages($data, $key) {
        $text = '';

        foreach ($key as $k) {
            if ($text = $data->get($k)) {
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
        $text = '//' . $data->get('journal') . '//';

        if ($volume = $data->get('volume')) {
            $text .= ', ' . $volume;
        }

        return $text;
    }

    /**
     *
     */
    protected function renderBook($data, $title) {
        $text = '//' . $title . '//';

        if ($chapter = $data->get('chapter')) {
            $text = $chapter . '. ' . refnotes_localization::getInstance()->getLang('txt_in_cap') . ' ' . $text;
        }

        if ($edition = $data->get('edition')) {
            $text .= ' ' . $edition . '.';
        }

        return $text;
    }

    /**
     *
     */
    protected function renderBookTitle($data) {
        return '//' . $data->get('booktitle') . '//';
    }

    /**
     *
     */
    protected function renderReferenceId($reference) {
        $data = new refnotes_renderer_data($reference->getData());

        if (!$this->checkReferenceData($data)) {
            return $this->renderBasicReferenceId($reference);
        }

        $authors = $data->get('ref-authors', 'ref-author');

        if (empty($authors)) {
            $authors = $data->get('authors', 'author');
            $authorList = explode(',', $authors, 4);

            if (count($authorList) > 3) {
                $authors = $authorList[0] . refnotes_localization::getInstance()->getLang('txt_et_al');
            }
        }

        $html = $this->renderReferenceExtra($data);

        list($formatOpen, $formatClose) = $this->renderReferenceParentheses();

        if ($data->isPositive('direct')) {
            $html = $authors . ' ' . $formatOpen . $html . $formatClose;

            if ($this->getReferenceGrouping($reference)) {
                switch ($reference->getAttribute('group')) {
                    case 'open':
                    case 'hold':
                        $html .= $this->renderReferenceGroupSeparator();
                        break;
                }
            }
        }
        else {
            if ($this->getReferenceGrouping($reference)) {
                switch ($reference->getAttribute('group')) {
                    case 'open':
                        $formatClose = $this->renderReferenceGroupSeparator();
                        break;

                    case 'hold':
                        $formatOpen = '';
                        $formatClose = $this->renderReferenceGroupSeparator();
                        break;

                    case 'close':
                        $formatOpen = '';
                        break;
                }
            }

            $html = $formatOpen . $authors . ', ' . $html . $formatClose;
        }

        return htmlspecialchars($html);
    }

    /**
     *
     */
    protected function renderBasicReferenceId($reference) {
        list($formatOpen, $formatClose) = parent::renderReferenceFormat($reference);

        return $formatOpen . parent::renderReferenceId($reference) . $formatClose;
    }

    /**
     *
     */
    protected function renderReferenceExtra($data) {
        $html = '';

        if ($published = $this->renderPublished($data, false)) {
            $html .= $published;
        }

        if ($pages = $this->renderPages($data, array('page', 'pages'))) {
            if (!empty($html)) {
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
    protected function renderReferenceFormat($reference) {
        return array('', '');
    }

    /**
     *
     */
    protected function checkReferenceData($data) {
        $authors = $data->has('ref-authors', 'ref-author', 'authors', 'author');
        $year = $data->has('published', 'year');
        $page = $data->has('page', 'pages');

        return $authors && ($year || $page);
    }
}
