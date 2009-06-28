<?php

/**
 * Plugin RefNotes: Configuration interface
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'admin.php');
require_once(DOKU_PLUGIN . 'refnotes/info.php');

class admin_plugin_refnotes extends DokuWiki_Admin_Plugin {

    var $html;
    var $locale;

    /**
     * Constructor
     */
    function admin_plugin_refnotes() {
        $this->html = new refnotes_html_sink();
        $this->locale = new refnotes_localization($this);
    }

    /**
     * Return some info
     */
    function getInfo() {
        return refnotes_getInfo('configuration interface');
    }

    /**
     * Handle user request
     */
    function handle() {
        // All handling is done using AJAX
    }

    /**
     * Output appropriate html
     */
    function html() {
        $this->html->ptln('<!-- refnotes -->');
        $this->html->ptln('<div id="refnotes-config"><div id="config__manager">');
        $this->html->ptln('<form action="" method="post">');
        $this->html->indent();

        $this->_printGeneral();
        $this->_printNamespaces();
        $this->_printNotes();

        $this->html->ptln($this->_getButton('save'));

        $this->html->unindent();
        $this->html->ptln('</form></div></div>');
        $this->html->ptln('<!-- /refnotes -->');
    }

    /**
     *
     */
    function _printGeneral() {
        $section = new refnotes_config_general();
        $section->printHtml($this->html, $this->locale);
    }

    /**
     *
     */
    function _printNamespaces() {
        $section = new refnotes_config_namespaces();
        $section->printHtml($this->html, $this->locale);
    }

    /**
     *
     */
    function _printNotes() {
        $section = new refnotes_config_notes();
        $section->printHtml($this->html, $this->locale);
    }

    /**
     *
     */
    function _getButton($action) {
        $html = '<input type="button" class="button"';
        $id = $action . '-config';
        $html .= ' id="' . $id . '"';
        $html .= ' name="' . $id . '"';
        $html .= ' value="' . $this->locale->getLang('btn_' . $action) . '"';
        $html .= ' />';

        return $html;
    }
}

class refnotes_config_section {

    var $html;
    var $locale;
    var $id;
    var $title;

    /**
     * Constructor
     */
    function refnotes_config_section($id) {
        $this->html = NULL;
        $this->locale = NULL;
        $this->id = $id;
        $this->title = 'sec_' . $id;
    }

    /**
     *
     */
    function printHtml($html, $locale) {
        $this->html = $html;
        $this->locale = $locale;
        $this->_open();
        $this->_printFields();
        $this->_close();
    }

    /**
     *
     */
    function _open() {
        $this->html->ptln('<fieldset id="' . $this->id . '">');
        $this->html->ptln('<legend>' . $this->locale->getLang($this->title) . '</legend>');
        $this->html->ptln('<table class="inline">');
        $this->html->indent();
    }

    /**
     *
     */
    function _close() {
        $this->html->unindent();
        $this->html->ptln('</table>');
        $this->html->ptln('</fieldset>');
    }

    /**
     *
     */
    function _printFields() {
        $field = $this->_getFields();
        foreach ($field as $f) {
            $this->_printFieldRow($f);
        }
    }

    /**
     *
     */
    function _getFields() {
        $fieldData = $this->_getFieldDefinitions();
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
    function _printFieldRow($field, $startRow = true) {
        if ($startRow) {
            $this->html->ptln('<tr>');
            $this->html->indent();
        }

        if (get_class($field) != 'refnotes_config_textarea') {
            $settingName = $field->getSettingName();
            if ($settingName != '') {
                $this->html->ptln('<td class="label">');
                $this->html->ptln($settingName);
            }
            else {
                $this->html->ptln('<td class="lean-label">');
            }

            $this->html->ptln($field->getLabel($this->locale));
            $this->html->ptln('</td><td class="value">');
        }
        else {
            $this->html->ptln('<td class="value" colspan="2">');
        }

        $this->html->ptln($field->getControl($this->locale));
        $this->html->ptln('</td>');

        $this->html->unindent();
        $this->html->ptln('</tr>');
    }
}

class refnotes_config_list_section extends refnotes_config_section {

    var $listRows;

    /**
     * Constructor
     */
    function refnotes_config_list_section($id, $listRows) {
        parent::refnotes_config_section($id);

        $this->listRows = $listRows;
    }

    /**
     *
     */
    function _close() {
        $this->html->unindent();
        $this->html->ptln('</table>');
        $this->_printListControls();
        $this->html->ptln('</fieldset>');
    }

    /**
     *
     */
    function _printListControls() {
        $this->html->ptln('<div class="list-controls">');
        $this->html->indent();

        $this->html->ptln($this->_getEdit());
        $this->html->ptln($this->_getButton('add'));
        $this->html->ptln($this->_getButton('delete'));

        $this->html->unindent();
        $this->html->ptln('</div>');
    }

    /**
     *
     */
    function _getEdit() {
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
    function _getButton($action) {
        $html = '<input type="button" class="button"';
        $id = $action . '-' . $this->id;
        $html .= ' id="' . $id . '"';
        $html .= ' name="' . $id . '"';
        $html .= ' value="' . $this->locale->getLang('btn_' . $action) . '"';
        $html .= ' />';

        return $html;
    }

    /**
     *
     */
    function _printFields() {
        $field = $this->_getFields();
        $fields = count($field);

        $this->html->ptln('<tr>');
        $this->html->indent();
        $this->html->ptln('<td class="list" rowspan="' . $fields . '">');
        $this->html->ptln('<select class="list" id="select-' . $this->id . '" size="' . $this->listRows . '"></select>');
        $this->html->ptln('</td>');

        $this->_printFieldRow($field[0], false);

        for ($f = 1; $f < $fields; $f++) {
            $this->_printFieldRow($field[$f]);
        }
    }
}

class refnotes_config_general extends refnotes_config_section {

    /**
     * Constructor
     */
    function refnotes_config_general() {
        parent::refnotes_config_section('general');
    }

    /**
     *
     */
    function _getFieldDefinitions() {
        static $field = array(
            'replace-footnotes' => array(
                'class' => 'checkbox',
                'lean' => true
            )
        );

        return $field;
    }
}

class refnotes_config_namespaces extends refnotes_config_list_section {

    /**
     * Constructor
     */
    function refnotes_config_namespaces() {
        parent::refnotes_config_list_section('namespaces', 10);
    }

    /**
     *
     */
    function _getFieldDefinitions() {
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
                'option' => array('right-parent', 'parents', 'right-bracket', 'brackes', 'none', 'inherit')
            )
        );

        return $field;
    }
}

class refnotes_config_notes extends refnotes_config_list_section {

    /**
     * Constructor
     */
    function refnotes_config_notes() {
        parent::refnotes_config_list_section('notes', 7);
    }

    /**
     *
     */
    function _getFieldDefinitions() {
        static $field = array(
            'note-text' => array(
                'class' => 'textarea',
                'rows' => '4',
                'lean' => true
            ),
            'inline' => array(
                'class' => 'checkbox',
                'lean' => true
            )
        );

        return $field;
    }
}

class refnotes_config_field {

    var $id;
    var $settingName;
    var $label;

    /**
     * Constructor
     */
    function refnotes_config_field($id, $data) {
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
    function getSettingName() {
        $html = '';

        if ($this->settingName != '') {
            $html = '<span class="outkey">' . $this->settingName . '</span>';
        }

        return $html;
    }

    /**
     *
     */
    function getLabel($locale) {
        return '<label for="' . $this->id . '">' . $locale->getLang($this->label) . '</label>';
    }
}

class refnotes_config_checkbox extends refnotes_config_field {

    /**
     * Constructor
     */
    function refnotes_config_checkbox($id, $data) {
        parent::refnotes_config_field($id, $data);
    }

    /**
     *
     */
    function getControl($locale) {
        $html = '<div class="input">';
        $html .= '<input type="checkbox" class="checkbox"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '" value="1"';
        $html .= '/></div>';

        return $html;
    }
}

class refnotes_config_select extends refnotes_config_field {

    var $option;

    /**
     * Constructor
     */
    function refnotes_config_select($id, $data) {
        parent::refnotes_config_field($id, $data);

        $this->option = $data['option'];
    }

    /**
     *
     */
    function getControl($locale) {
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

class refnotes_config_textarea extends refnotes_config_field {

    var $rows;

    /**
     * Constructor
     */
    function refnotes_config_textarea($id, $data) {
        parent::refnotes_config_field($id, $data);

        $this->rows = $data['rows'];
    }

    /**
     *
     */
    function getControl($locale) {
        $html = '<div class="input">';
        $html .= '<textarea class="edit"';
        $html .= ' id="' . $this->id . '"';
        $html .= ' name="' . $this->id . '"';
        $html .= ' cols="40" rows="' . $this->rows . '">';
        $html .= '</textarea></div>';

        return $html;
    }
}

class refnotes_html_sink {

    var $indentIncrement;
    var $indent;

    /**
     * Constructor
     */
    function refnotes_html_sink() {
        $this->indentIncrement = 2;
        $this->indent = 0;
    }

    /**
     *
     */
    function indent() {
        $this->indent += $this->indentIncrement;
    }

    /**
     *
     */
    function unindent() {
        if ($this->indent >= $this->indentIncrement) {
            $this->indent -= $this->indentIncrement;
        }
    }

    /**
     *
     */
    function ptln($string, $indentDelta = 0) {
        if ($indentDelta < 0) {
            $this->indent += $this->indentIncrement * $indentDelta;
        }

        $text = explode(DOKU_LF, $string);
        foreach ($text as $string) {
            ptln($string, $this->indent);
        }

        if ($indentDelta > 0) {
            $this->indent += $this->indentIncrement * $indentDelta;
        }
    }
}

class refnotes_localization {

    var $plugin;

    /**
     * Constructor
     */
    function refnotes_localization($plugin) {
        $this->plugin = $plugin;
    }

    /**
     *
     */
    function getLang($id) {
        return $this->plugin->getLang($id);
    }
}
