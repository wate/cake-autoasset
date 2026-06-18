<?php
declare(strict_types=1);

namespace AutoAsset\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;
use Cake\View\View;
use Exception;

/**
 * AutoAssetHelper
 *
 * コントローラー/アクションに対応するアセットを自動的に読み込む
 */
class AutoAssetHelper extends Helper
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'assetBasePath' => WWW_ROOT,
        'all' => true,
        'input' => ['add', 'edit'],
        'form' => ['add', 'edit', 'view'],
    ];

    /**
     * @var array<string>
     */
    protected array $helpers = ['Html'];

    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        try {
            Configure::load('autoasset');
            if (Configure::check('AutoAsset')) {
                $this->setConfig(Configure::read('AutoAsset'));
            }
        } catch (Exception $e) {
            // 設定ファイルがない場合はデフォルト設定を使用
        }
        parent::initialize($config);
    }

    /**
     * JSファイルの自動読み込み
     *
     * @return string
     */
    public function scripts(): string
    {
        $files = $this->resolveFiles('js');
        $output = '';
        foreach ($files as $file) {
            $output .= $this->Html->script($file, ['block' => true]);
        }
        return $output;
    }

    /**
     * CSSファイルの自動読み込み
     *
     * @return string
     */
    public function styles(): string
    {
        $files = $this->resolveFiles('css');
        $output = '';
        foreach ($files as $file) {
            $output .= $this->Html->css($file, ['block' => true]);
        }
        return $output;
    }

    /**
     * アセットファイルの解決
     *
     * @param string $ext 拡張子（js/css）
     * @return array<string> 読み込むファイルパスの配列
     */
    protected function resolveFiles(string $ext): array
    {
        $request = $this->getView()->getRequest();
        $controller = $request->getParam('controller');
        $action = $request->getParam('action');
        $prefix = $request->getParam('prefix');

        $files = [];
        $basePath = $this->getConfig('assetBasePath') . $ext . DS;
        $config = $this->getConfig();

        // ファイルパターンごとに判定（グローバル共通ファイル）
        foreach ($config as $pattern => $condition) {
            if (!is_string($pattern)) {
                continue;
            }

            // true: 常に読み込む（グローバル共通ファイル）
            if ($condition === true) {
                if (file_exists($basePath . $pattern . '.' . $ext)) {
                    $files[] = $pattern;
                }
                continue;
            }

            // 配列: アクション該当時に読み込む（グローバル画面種別ファイル）
            if (is_array($condition) && in_array($action, $condition, true)) {
                if (file_exists($basePath . $pattern . '.' . $ext)) {
                    $files[] = $pattern;
                }
            }
        }

        // コントローラ共通（無印）
        foreach ($config as $pattern => $condition) {
            if (!is_string($pattern)) {
                continue;
            }

            if ($condition === true || (is_array($condition) && in_array($action, $condition, true))) {
                $controllerPath = $controller . DS . $pattern;
                if (file_exists($basePath . $controllerPath . '.' . $ext)) {
                    $files[] = str_replace(DS, '/', $controllerPath);
                }
            }
        }

        // アクション固有（無印）
        $actionPath = $controller . DS . $action;
        if (file_exists($basePath . $actionPath . '.' . $ext)) {
            $files[] = str_replace(DS, '/', $actionPath);
        }

        // コントローラ共通（プレフィックス付き）
        if ($prefix) {
            foreach ($config as $pattern => $condition) {
                if (!is_string($pattern)) {
                    continue;
                }

                if ($condition === true || (is_array($condition) && in_array($action, $condition, true))) {
                    $prefixControllerPath = $prefix . DS . $controller . DS . $pattern;
                    if (file_exists($basePath . $prefixControllerPath . '.' . $ext)) {
                        $files[] = str_replace(DS, '/', $prefixControllerPath);
                    }
                }
            }
        }

        // アクション固有（プレフィックス付き）
        if ($prefix) {
            $prefixActionPath = $prefix . DS . $controller . DS . $action;
            if (file_exists($basePath . $prefixActionPath . '.' . $ext)) {
                $files[] = str_replace(DS, '/', $prefixActionPath);
            }
        }

        return array_values(array_unique($files));
    }
}
