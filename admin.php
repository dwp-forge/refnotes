<?php

/**
 * Plugin RefNotes: Configuration interface
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class admin_plugin_refnotes extends DokuWiki_Admin_Plugin {
    use refnotes_localization_plugin;

    private $locale;

    /**
     * Constructor
     */
    public function __construct() {
        refnotes_localization::initialize($this);

        $this->locale = refnotes_localization::getInstance();
    }

    /**
     * Handle user request
     */
    public function handle() {
        /* All handling is done using AJAX */
    }

    /**
     * Output appropriate html
     */
    public function html() {
        print($this->locale_xhtml('intro'));

        print('<!-- refnotes -->');

        $this->printLanguageStrings();

        print('<div id="refnotes-config"><div id="config__manager">');
        print('<noscript><div class="error">' . $this->locale->getLang('noscript') . '</div></noscript>');
        print('<div id="server-status" class="info" style="display: none;">&nbsp;</div>');
        print('<form action="" method="post">');

        $this->printGeneral();
        $this->printNamespaces();
        $this->printNotes();

        print($this->getButton('save'));

        print('</form></div></div>');
        print('<!-- /refnotes -->');
    }

    /**
     * Built-in JS localization stores all language strings in the common script (produced by js.php).
     * The strings used by administration plugin seem to be unnecessary in that script. Instead we print
     * them as part of the page and then load them into the LANG array on the client side.
     */
    private function printLanguageStrings() {
        $lang = $this->locale->getByPrefix('js');

        print('<div id="refnotes-lang" style="display: none;">');

        foreach ($lang as $key => $value) {
            print($key . ' : ' . $value . ':eos:');
        }

        print('</div>');
    }

    /**
     *
     */
    private function printGeneral() {
        $section = new refnotes_config_general();
        $section->printHtml();
    }

    /**
     *
     */
    private function printNamespaces() {
        $section = new refnotes_config_namespaces();
        $section->printHtml();
    }

    /**
     *
     */
    private function printNotes() {
        $section = new refnotes_config_notes();
        $section->printHtml();
    }

    /**
     *
     */
    private function getButton($action) {
        $html = '<input type="button" class="button"';
        $id = $action . '-config';
        $html .= ' id="' . $id . '"';
        $html .= ' name="' . $id . '"';
        $html .= ' value="' . $this->locale->getLang('btn_' . $action) . '"';
        $html .= ' />';

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_section {

    protected $id;
    protected $title;

    /**
     * Constructor
     */
    public function __construct($id) {
        $this->id = $id;
        $this->title = 'sec_' . $id;
    }

    /**
     *
     */
    public function printHtml() {
        $this->open();
        $this->printFields();
        $this->close();
    }

    /**
     *
     */
    protected function open() {
        $title = refnotes_localization::getInstance()->getLang($this->title);

        print('<fieldset id="' . $this->id . '">');
        print('<legend>' . $title . '</legend>');
        print('<table class="inline" cols="3">');
    }

    /**
     *
     */
    protected function close() {
        print('</table>');
        print('</fieldset>');
    }

    /**
     *
     */
    protected function printFields() {
        $field = $this->getFields();
        foreach ($field as $f) {
            $this->printFieldRow($f);
        }
    }

    /**
     *
     */
    protected function getFields() {
        $fieldData = $this->getFieldDefinitions();
        $field = array();

        foreach ($fieldData as $id => $fd) {
            $class = 'refnotes_config_' . $fd['class'];
            $field[] = new $class($id, $fd);
        }

        return $field;
    }

    /**
     *
     */
    protected function printFieldRow($field, $startRow = true) {
        if ($startRow) {
            print('<tr>');
        }

        if (get_class($field) != 'refnotes_config_textarea') {
            $settingName = $field->getSettingName();

            if ($settingName != '') {
                print('<td class="label">');
                print($settingName);
            }
            else {
                print('<td class="lean-label">');
            }

            print($field->getLabel());
            print('</td><td class="value">');
        }
        else {
            print('<td class="value" colspan="2">');
        }

        print($field->getControl());
        print('</td>');

        print('</tr>');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_list_section extends refnotes_config_section {

    private $listRows;

    /**
     * Constructor
     */
    public function __construct($id, $listRows) {
        parent::__construct($id);

        $this->listRows = $listRows;
    }

    /**
     *
     */
    protected function close() {
        print('</table>');

        $this->printListControls();

        print('</fieldset>');
    }

    /**
     *
     */
    private function printListControls() {
        print('<div class="list-controls">');

        print($this->getEdit());
        print($this->getButton('add'));
        print($this->getButton('rename'));
        print($this->getButton('delete'));

        print('</div>');
    }

    /**
     *
     */
    private function getEdit() {
        $html = '<input type="text" class="edit"';
        $id = 'name-' . $this->id;
        $html .= ' id="' . $id . '"';
        $html .= ' name="' . $id . '"';
        $html .= ' value=""';
        $html .= ' />';

        return $html;
    }

    /**
     *
     */
    private function getButton($action) {
        $label = refnotes_localization::getInstance()->getLang('btn_' . $action);

        $id = $action . '-' . $this->id;
        $html = '<input type="button" class="button"';
        $html .= ' id="' . $id . '"';
        $html .= ' name="' . $id . '"';
        $html .= ' value="' . $label . '"';
        $html .= ' />';

        return $html;
    }

    /**
     *
     */
    protected function printFields() {
        $field = $this->getFields();
        $fields = count($field);

        print('<tr>');
        print('<td class="list" rowspan="' . $fields . '">');
        print('<select class="list" id="select-' . $this->id . '" size="' . $this->listRows . '"></select>');
        print('</td>');

        $this->printFieldRow($field[0], false);

        for ($f = 1; $f < $fields; $f++) {
            $this->printFieldRow($field[$f]);
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_general extends refnotes_config_section {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('general');
    }

    /**
     *
     */
    protected function getFieldDefinitions() {
        static $field = array(
            'replace-footnotes' => array(
                'class' => 'checkbox',
                'lean' => true
            ),
            'reference-db-enable' => array(
                'class' => 'checkbox',
                'lean' => true
            ),
            'reference-db-namespace' => array(
                'class' => 'edit',
                'lean' => true
            )
        );

        return $field;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_namespaces extends refnotes_config_list_section {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('namespaces', 48);
    }

    /**
     *
     */
    protected function getFieldDefinitions() {
        static $field = array(
            'refnote-id' => array(
                'class' => 'select',
                'option' => array('numeric', 'latin-lower', 'latin-upper', 'roman-lower', 'roman-upper', 'stars', 'note-name', 'inherit')
            ),
            'reference-base' => array(
                'class' => 'select',
                'option' => array('super', 'normal-text', 'inherit')
            ),
            'reference-font-weight' => array(
                'class' => 'select',
                'option' => array('normal', 'bold', 'inherit')
            ),
            'reference-font-style' => array(
                'class' => 'select',
                'option' => array('normal', 'italic', 'inherit')
            ),
            'reference-format' => array(
                'class' => 'select',
                'option' => array('right-parent', 'parents', 'right-bracket', 'brackets', 'none', 'inherit')
            ),
            'reference-group' => array(
                'class' => 'select',
                'option' => array('group-none', 'group-comma', 'group-semicolon', 'inherit')
            ),
            'reference-render' => array(
                'class' => 'select',
                'option' => array('basic', 'harvard', 'inherit')
            ),
            'multi-ref-id' => array(
                'class' => 'select',
                'option' => array('ref-counter', 'note-counter', 'inherit')
            ),
            'note-preview' => array(
                'class' => 'select',
                'option' => array('popup', 'tooltip', 'none', 'inherit')
            ),
            'notes-separator' => array(
                'class' => 'edit_inherit'
            ),
            'note-text-align' => array(
                'class' => 'select',
                'option' => array('justify', 'left', 'inherit')
            ),
            'note-font-size' => array(
                'class' => 'select',
                'option' => array('normal', 'reduced', 'small', 'inherit')
            ),
            'note-render' => array(
                'class' => 'select',
                'option' => array('basic', 'harvard', 'inherit')
            ),
            'note-id-base' => array(
                'class' => 'select',
                'option' => array('super', 'normal-text', 'inherit')
            ),
            'note-id-font-weight' => array(
                'class' => 'select',
                'option' => array('normal', 'bold', 'inherit')
            ),
            'note-id-font-style' => array(
                'class' => 'select',
                'option' => array('normal', 'italic', 'inherit')
            ),
            'note-id-format' => array(
                'class' => 'select',
                'option' => array('right-parent', 'parents', 'right-bracket', 'brackets', 'dot', 'none', 'inherit')
            ),
            'back-ref-caret' => array(
                'class' => 'select',
                'option' => array('prefix', 'merge', 'none', 'inherit')
            ),
            'back-ref-base' => array(
                'class' => 'select',
                'option' => array('super', 'normal-text', 'inherit')
            ),
            'back-ref-font-weight' => array(
                'class' => 'select',
                'option' => array('normal', 'bold', 'inherit')
            ),
            'back-ref-font-style' => array(
                'class' => 'select',
                'option' => array('normal', 'italic', 'inherit')
            ),
            'back-ref-format' => array(
                'class' => 'select',
                'option' => array('note-id', 'latin', 'numeric', 'caret', 'arrow', 'none', 'inherit')
            ),
            'back-ref-separator' => array(
                'class' => 'select',
                'option' => array('comma', 'none', 'inherit')
            ),
            'scoping' => array(
                'class' => 'select',
                'option' => array('reset', 'single')
            )
        );

        return $field;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_notes extends refnotes_config_list_section {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('notes', 14);
    }

    /**
     *
     */
    protected function getFieldDefinitions() {
        static $field = array(
            'note-text' => array(
                'class' => 'textarea',
                'rows' => '4',
                'lean' => true
            ),
            'inline' => array(
                'class' => 'checkbox',
                'lean' => true
            ),
            'use-reference-base' => array(
                'class' => 'checkbox',
                'lean' => true
            ),
            'use-reference-font-weight' => array(
                'class' => 'checkbox',
                'lean' => true
            ),
            'use-reference-font-style' => array(
                'class' => 'checkbox',
                'lean' => true
            ),
            'use-reference-format' => array(
                'class' => 'checkbox',
                'lean' => true
            )
        );

        return $field;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_field {

    protected $id;
    protected $settingName;
    protected $label;

    /**
     * Constructor
     */
    public function __construct($id, $data) {
        $this->id = 'field-' . $id;
        $this->label = 'lbl_' . $id;

        if (array_key_exists('lean', $data) && $data['lean']) {
            $this->settingName = '';
        }
        else {
            $this->settingName = $id;
        }
    }

    /**
     *
     */
    public function getSettingName() {
        $html = '';

        if ($this->settingName != '') {
            $html = '<span class="outkey">' . $this->settingName . '</span>';
        }

        return $html;
    }

    /**
     *
     */
    public function getLabel() {
        $label = refnotes_localization::getInstance()->getLang($this->label);

        return '<label for="' . $this->id . '">' . $label . '</label>';
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_checkbox extends refnotes_config_field {

    /**
     * Constructor
     */
    public function __construct($id, $data) {
        parent::__construct($id, $data);
    }

    /**
     *
     */
    public function getControl() {
        $html = '<div class="input">';
        $html .= '<input type="checkbox" class="checkbox"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '" value="1"';
        $html .= '/></div>';

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_select extends refnotes_config_field {

    private $option;

    /**
     * Constructor
     */
    public function __construct($id, $data) {
        parent::__construct($id, $data);

        $this->option = $data['option'];
    }

    /**
     *
     */
    public function getControl() {
        $locale = refnotes_localization::getInstance();

        $html = '<div class="input">';

        $html .= '<select class="edit"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '">' . DOKU_LF;

        foreach ($this->option as $option) {
            $html .= '<option value="' . $option . '">' . $locale->getLang('opt_' . $option) . '</option>' . DOKU_LF;
        }

        $html .= '</select>';
        $html .= '</div>';

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_edit extends refnotes_config_field {

    /**
     * Constructor
     */
    public function __construct($id, $data) {
        parent::__construct($id, $data);
    }

    /**
     *
     */
    public function getControl() {
        $html = '<div class="input">';

        $html .= '<input type="text" class="edit"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '" />' . DOKU_LF;

        $html .= '</div>';

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_edit_inherit extends refnotes_config_field {

    /**
     * Constructor
     */
    public function __construct($id, $data) {
        parent::__construct($id, $data);
    }

    /**
     *
     */
    public function getControl() {
        $buttonLabel = refnotes_localization::getInstance()->getLang('opt_inherit');

        $html = '<div class="input">';

        $html .= '<input type="text" class="edit"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '" />' . DOKU_LF;

        $html .= '<input type="button" class="button"';
        $html .= ' id="' . $this->id . '-inherit"';
        $html .= ' name="' . $this->id . '-inherit"';
        $html .= ' value="' . $buttonLabel . '"';
        $html .= ' />';

        $html .= '</div>';

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_config_textarea extends refnotes_config_field {

    private $rows;

    /**
     * Constructor
     */
    public function __construct($id, $data) {
        parent::__construct($id, $data);

        $this->rows = $data['rows'];
    }

    /**
     *
     */
    public function getControl() {
        $html = '<div class="input">';
        $html .= '<textarea class="edit"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '"';
        $html .= ' cols="40" rows="' . $this->rows . '">';
        $html .= '</textarea></div>';

        return $html;
    }
}
