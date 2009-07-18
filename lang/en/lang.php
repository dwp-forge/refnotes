<?php
/**
 * Plugin RefNotes: English language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';

// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'RefNotes Configuration';

$lang['sec_general'] = 'General settings';
$lang['sec_namespaces'] = 'Namespaces';
$lang['sec_notes'] = 'Notes';

$lang['lbl_replace-footnotes'] = 'Use footnotes syntax';
$lang['lbl_refnote-id'] = 'Reference/note identifier style';
$lang['lbl_reference-base'] = 'Reference baseline';
$lang['lbl_reference-font-weight'] = 'Reference font weight';
$lang['lbl_reference-font-style'] = 'Reference font style';
$lang['lbl_reference-format'] = 'Reference formatting';
$lang['lbl_notes-separator'] = 'Notes block separator';

$lang['lbl_inline'] = 'Inline';

$lang['opt_inherit'] = 'Inherit';
$lang['opt_none'] = 'None';
$lang['opt_numeric'] = 'Numeric';
$lang['opt_latin-lower'] = 'Latin lower case';
$lang['opt_latin-upper'] = 'Latin upper case';
$lang['opt_roman-lower'] = 'Roman lower case';
$lang['opt_roman-upper'] = 'Roman upper case';
$lang['opt_stars'] = 'Stars';
$lang['opt_note-name'] = 'Note name';
$lang['opt_normal'] = 'Normal';
$lang['opt_super'] = 'Superscript';
$lang['opt_normal-text'] = 'Normal text';
$lang['opt_bold'] = 'Bold';
$lang['opt_italic'] = 'Italic';
$lang['opt_right-parent'] = 'Right parenthesis';
$lang['opt_parents'] = 'Parentheses';
$lang['opt_right-bracket'] = 'Right bracket';
$lang['opt_brackets'] = 'Brackets';

$lang['btn_add'] = 'Add';
$lang['btn_delete'] = 'Delete';
$lang['btn_save'] = 'Save';

$lang['js_status'] = 'Server comunication status.';
$lang['js_loading'] = 'Loading configuration settings from the server...';
$lang['js_loaded'] = 'Configuration settings are successfully loaded from the server.';
$lang['js_loading_failed'] = 'Failed to load configuration settings from the server.';
$lang['js_saving'] = 'Saving configuration settings on the server...';
$lang['js_saved'] = 'Configuration settings are successfully saved on the server.';
$lang['js_saving_failed'] = 'Failed to save configuration settings on the server.';
$lang['js_invalid_ns_name'] = 'The specified namespace name is invalid.';
$lang['js_ns_name_exists'] = 'The {1} namespace already exists.';
$lang['js_delete_ns'] = 'Are you sure you want to delete {1} namespace?';
$lang['js_invalid_note_name'] = 'The specified note name is invalid.';
$lang['js_note_name_exists'] = 'The {1} note already exists.';
$lang['js_delete_note'] = 'Are you sure you want to delete {1} note?';
