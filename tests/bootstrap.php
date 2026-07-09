<?php
/**
 * Test bootstrap for AutoAsset plugin.
 *
 * Self-contained bootstrap: this plugin's tests do not touch the database or
 * app-level config/routing, so no host application is required to run them.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Cake\Core\Configure;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

define('WWW_ROOT', sys_get_temp_dir() . DS . 'autoasset_test_webroot' . DS);
define('CONFIG', dirname(__DIR__) . DS . 'config' . DS);

Configure::write('debug', true);
Configure::write('App.encoding', 'UTF-8');
Configure::write('App.cssBaseUrl', 'css/');
Configure::write('App.jsBaseUrl', 'js/');
Configure::write('App.imageBaseUrl', 'img/');
