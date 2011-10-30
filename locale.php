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
        $this->plugin->setupLocale();

        if ($strip) {
            $pattern = '/^' . $prefix . '_(.+)$/';
        }
        else {
            $pattern = '/^(' . $prefix . '_.+)$/';
        }

        $result = array();

        foreach ($this->plugin->lang as $key => $value) {
            if (preg_match($pattern, $key, $match) == 1) {
                $result[$match[1]] = $value;
            }
        }

        return $result;
    }
}
