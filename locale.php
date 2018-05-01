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
    public function getByPrefix($prefix, $strip = true) {
        return $this->plugin->getActionLocaleByPrefix($prefix, $strip);
    }
}
