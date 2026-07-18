<?php
/**
 * AutoAsset プラグインのテスト用ブートストラップ
 *
 * 自己完結型ブートストラップ。本プラグインのテストはデータベースや
 * アプリレベルの設定/ルーティングに依存しないため、
 * ホストアプリケーションは不要。
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
