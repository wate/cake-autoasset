<?php
declare(strict_types=1);

namespace AutoAsset;

use Cake\Core\BasePlugin;

/**
 * AutoAssetPlugin
 *
 * コントローラー/アクションに対応するアセット(JS/CSS)を自動読み込みするための
 * プラグイン。プラグインの登録のみを行い、実処理は AutoAssetHelper に委譲する。
 */
class AutoAssetPlugin extends BasePlugin
{
}
