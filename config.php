<?php

/**
 * Plugin RefNotes: Configuration
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

function refnotes_loadConfigFile($name) {
    $pluginRoot = DOKU_PLUGIN . 'refnotes/';
    $fileName = $pluginRoot . $name . '.local.dat';

    if (!file_exists($fileName)) {
        $fileName = $pluginRoot . $name . '.dat';
        if (!file_exists($fileName)) {
            $fileName = '';
        }
    }

    if ($fileName != '') {
        $result = unserialize(io_readFile($fileName, false));
    }
    else {
        $result = array();
    }

    return $result;
}
