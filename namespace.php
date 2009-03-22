<?php

/**
 * Plugin RefNotes: Namespace heplers
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/**
 * Returns canonic name for a namespace
 */
function refnotes_canonizeNamespace($name) {
    return preg_replace('/:{2,}/', ':', ':' . $name . ':');
}

/**
 * Returns name of the parent namespace
 */
function refnotes_getParentNamespace($name) {
    return preg_replace('/\w+:$/', '', $name);
}
