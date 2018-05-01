<?php

/**
 * Plugin RefNotes: Localization
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

class refnotes_localization {

    private static $instance = NULL;

    private $plugin;

    /**
     *
     */
    public static function initialize($plugin) {
        if (self::$instance == NULL) {
            self::$instance = new refnotes_localization($plugin);
        }
    }

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            throw new Exception('Shared refnotes_localization instance is not properly initialized.');
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct($plugin) {
        $this->plugin = $plugin;
    }

    /**
     *
     */
    public function getLang($id) {
        return $this->plugin->getLang($id);
    }

    /**
     *
     */
    public function getFileName($id) {
        return $this->plugin->localFN($id);
    }

    /**
     *
     */
    public function getLangJSPrefix() {
        $jslang = array('js_status', 'js_loading', 'js_loaded',
                        'js_loading_failed', 'js_invalid_data',
                        'js_saving', 'js_saved', 'js_saving_failed',
                        'js_invalid_ns_name', 'js_ns_name_exists',
                        'js_delete_ns', 'js_invalid_note_name',
                        'js_note_name_exists', 'js_delete_note',
                        'js_unsaved');

        $this->plugin->setupLocale();

        $result = array();

        foreach ($jslang as $key) {
            $newkey = substr($key, 2);
            $result[$newkey] = $this->plugin->getLang($key);
        }

        return $result;
    }

    /**
     *
     */
    public function getLangDBKPrefix() {
        $dbklang = array('dbk_author', 'dbk_authors', 'dbk_chapter',
                         'dbk_edition', 'dbk_isbn', 'dbk_issn',
                         'dbk_journal', 'dbk_month', 'dbk_note-name',
                         'dbk_note-page', 'dbk_note-pages', 'dbk_note-text',
                         'dbk_page', 'dbk_pages', 'dbk_published',
                         'dbk_publisher', 'dbk_ref-author', 'dbk_ref-authors',
                         'dbk_title', 'dbk_url', 'dbk_volume', 'dbk_year');

        $this->plugin->setupLocale();

        $result = array();

        foreach ($dbklang as $key) {
            $newkey = substr($key, 3);
            $result[$newkey] = $this->plugin->getLang($key);
        }

        return $result;
    }
}
