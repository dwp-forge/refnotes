<?php
/**
 * Plugin RefNotes: English language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

// Settings must be present and set appropriately for the language
$lang['encoding'] = 'utf-8';
$lang['direction'] = 'ltr';

// Reference database keys
$lang['dbk_author'] = 'Author';
$lang['dbk_authors'] = 'Authors';
$lang['dbk_chapter'] = 'Chapter';
$lang['dbk_edition'] = 'Edition';
$lang['dbk_isbn'] = 'ISBN';
$lang['dbk_issn'] = 'ISSN';
$lang['dbk_journal'] = 'Journal';
$lang['dbk_month'] = 'Month';
$lang['dbk_note-name'] = 'Note name';
$lang['dbk_note-page'] = 'Note page';
$lang['dbk_note-pages'] = 'Note pages';
$lang['dbk_note-text'] = 'Note text';
$lang['dbk_page'] = 'Page';
$lang['dbk_pages'] = 'Pages';
$lang['dbk_published'] = 'Published';
$lang['dbk_publisher'] = 'Publisher';
$lang['dbk_ref-author'] = 'Reference author';
$lang['dbk_ref-authors'] = 'Reference authors';
$lang['dbk_title'] = 'Title';
$lang['dbk_url'] = 'URL';
$lang['dbk_volume'] = 'Volume';
$lang['dbk_year'] = 'Year';

$lang['txt_in_cap'] = 'In';
$lang['txt_page_abbr'] = 'p.';
$lang['txt_pages_abbr'] = 'pp.';

// For admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'RefNotes Configuration';

$lang['noscript'] = 'Sorry, this page requires JavaScript to function properly. Please enable it or get a decent browser.';

$lang['sec_general'] = 'General settings';
$lang['sec_namespaces'] = 'Namespaces';
$lang['sec_notes'] = 'Notes';

$lang['lbl_replace-footnotes'] = 'Use footnotes syntax';
$lang['lbl_reference-db-enable'] = 'Enable reference database';
$lang['lbl_reference-db-namespace'] = 'Reference database namespace';

$lang['lbl_refnote-id'] = 'Reference/note identifier style';
$lang['lbl_reference-base'] = 'Reference baseline';
$lang['lbl_reference-font-weight'] = 'Reference font weight';
$lang['lbl_reference-font-style'] = 'Reference font style';
$lang['lbl_reference-format'] = 'Reference formatting';
$lang['lbl_reference-render'] = 'Reference rendering';
$lang['lbl_multi-ref-id'] = 'Multi-reference identifier';
$lang['lbl_note-preview'] = 'Note preview';
$lang['lbl_notes-separator'] = 'Notes block separator';
$lang['lbl_note-text-align'] = 'Note text alignment';
$lang['lbl_note-font-size'] = 'Note font size';
$lang['lbl_note-render'] = 'Note rendering';
$lang['lbl_note-id-base'] = 'Note identifier baseline';
$lang['lbl_note-id-font-weight'] = 'Note identifier font weight';
$lang['lbl_note-id-font-style'] = 'Note identifier font style';
$lang['lbl_note-id-format'] = 'Note identifier formatting';
$lang['lbl_back-ref-caret'] = 'Back reference circumflex';
$lang['lbl_back-ref-base'] = 'Back reference base line';
$lang['lbl_back-ref-font-weight'] = 'Back reference font weight';
$lang['lbl_back-ref-font-style'] = 'Back reference font style';
$lang['lbl_back-ref-format'] = 'Back reference formatting';
$lang['lbl_back-ref-separator'] = 'Back reference separator';
$lang['lbl_scoping'] = 'Scoping behavior';

$lang['lbl_inline'] = 'Inline';
$lang['lbl_use-reference-base'] = 'Apply reference baseline';
$lang['lbl_use-reference-font-weight'] = 'Apply reference font weight';
$lang['lbl_use-reference-font-style'] = 'Apply reference font style';
$lang['lbl_use-reference-format'] = 'Apply reference formatting';

$lang['opt_arrow'] = 'Up arrow';
$lang['opt_basic'] = 'Plain text';
$lang['opt_bold'] = 'Bold';
$lang['opt_brackets'] = 'Brackets';
$lang['opt_caret'] = 'Circumflex';
$lang['opt_comma'] = 'Comma';
$lang['opt_dot'] = 'Dot';
$lang['opt_harvard'] = 'Harvard system of referencing';
$lang['opt_inherit'] = 'Inherit';
$lang['opt_italic'] = 'Italic';
$lang['opt_justify'] = 'Justify';
$lang['opt_latin'] = 'Latin';
$lang['opt_latin-lower'] = 'Latin lower case';
$lang['opt_latin-upper'] = 'Latin upper case';
$lang['opt_left'] = 'Left';
$lang['opt_merge'] = 'Merge with back references';
$lang['opt_none'] = 'None';
$lang['opt_normal'] = 'Normal';
$lang['opt_normal-text'] = 'Normal text';
$lang['opt_note-counter'] = 'Note counter';
$lang['opt_note-id'] = 'Note identifier';
$lang['opt_note-name'] = 'Note name';
$lang['opt_numeric'] = 'Numeric';
$lang['opt_parents'] = 'Parentheses';
$lang['opt_popup'] = 'Static pop-up';
$lang['opt_prefix'] = 'Prefix back references';
$lang['opt_ref-counter'] = 'Reference counter';
$lang['opt_reset'] = 'New scope after each notes block';
$lang['opt_right-bracket'] = 'Right bracket';
$lang['opt_right-parent'] = 'Right parenthesis';
$lang['opt_roman-lower'] = 'Roman lower case';
$lang['opt_roman-upper'] = 'Roman upper case';
$lang['opt_single'] = 'Use single scope';
$lang['opt_small'] = 'Small';
$lang['opt_stars'] = 'Stars';
$lang['opt_super'] = 'Superscript';
$lang['opt_tooltip'] = 'Tooltip';

$lang['btn_add'] = 'Add';
$lang['btn_rename'] = 'Rename';
$lang['btn_delete'] = 'Delete';
$lang['btn_save'] = 'Save';

$lang['js_status'] = 'Server communication status.';
$lang['js_loading'] = 'Loading configuration settings from the server...';
$lang['js_loaded'] = 'Configuration settings are successfully loaded from the server.';
$lang['js_loading_failed'] = 'Failed to load configuration settings from the server ({1}).';
$lang['js_invalid_data'] = 'Configuration settings loaded from the server are invalid or corrupted ({1}).';
$lang['js_saving'] = 'Saving configuration settings on the server...';
$lang['js_saved'] = 'Configuration settings are successfully saved on the server.';
$lang['js_saving_failed'] = 'Failed to save configuration settings on the server ({1}).';
$lang['js_invalid_ns_name'] = 'The specified namespace name is invalid.';
$lang['js_ns_name_exists'] = 'The {1} namespace already exists.';
$lang['js_delete_ns'] = 'Are you sure you want to delete {1} namespace?';
$lang['js_invalid_note_name'] = 'The specified note name is invalid.';
$lang['js_note_name_exists'] = 'The {1} note already exists.';
$lang['js_delete_note'] = 'Are you sure you want to delete {1} note?';
$lang['js_unsaved'] = 'Your changes to the configuration settings are not saved.';
