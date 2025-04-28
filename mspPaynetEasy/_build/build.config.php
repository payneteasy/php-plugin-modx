<?php

/* define package */
const PKG_NAME = 'mspPaynetEasy';
define('PKG_NAME_LOWER', strtolower(PKG_NAME));

const PKG_VERSION = '1.0.0';
const PKG_RELEASE = 'pl';
const PKG_AUTO_INSTALL = true;

/* define paths */
if (isset($_SERVER['MODX_BASE_PATH'])) {
    define('MODX_BASE_PATH', $_SERVER['MODX_BASE_PATH']);
}
elseif (file_exists(dirname(__FILE__, 3) . '/core')) {
    define('MODX_BASE_PATH', dirname(__FILE__, 3) . '/');
}
else {
    define('MODX_BASE_PATH', dirname(__FILE__, 4) . '/');
}
const MODX_CORE_PATH = MODX_BASE_PATH . 'core/';
const MODX_ASSETS_PATH = MODX_BASE_PATH . 'assets/';

/* define urls */
const MODX_BASE_URL = '/';
const MODX_CORE_URL = MODX_BASE_URL . 'core/';
const MODX_ASSETS_URL = MODX_BASE_URL . 'assets/';

/* define build options */
const BUILD_SETTING_UPDATE = false;
