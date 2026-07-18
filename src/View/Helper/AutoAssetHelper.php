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
 * コントローラー/アクションに対応するアセットを自動的に読み込むヘルパー。
 * 設定ファイル(autoasset.php)に基づき、命名規則に従ったJS/CSSファイルを
 * 自動解決してHTML出力する。
 *
 * 解決順序:
 *   1. グローバル共通 (例: all.js)
 *   2. グローバル画面種別 (例: input.js, form.js)
 *   3. コントローラ共通 (例: Users/all.js)
 *   4. アクション固有 (例: Users/index.js)
 *   5. プレフィックス共通 (例: Admin/all.js)
 *   6. コントローラ共通+プレフィックス (例: Admin/Users/all.js)
 *   7. アクション固有+プレフィックス (例: Admin/Users/index.js)
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 */
class AutoAssetHelper extends Helper
{
    /**
     * デフォルト設定
     *
     * - assetBasePath: アセットファイルのベースパス
     * - module: ES moduleモードフラグ
     * - patterns: 解決パターン定義
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'assetBasePath' => WWW_ROOT,
        'module' => false,
        'patterns' => [
            'all' => true,
            'input' => ['add', 'edit'],
            'form' => ['add', 'edit', 'view'],
        ],
    ];

    /**
     * 使用するヘルパー
     *
     * @var array<string>
     */
    protected array $helpers = ['Html'];

    /**
     * 初期化処理
     *
     * autoasset.php 設定ファイルを読み込み、デフォルト設定を上書きする。
     * 設定ファイルが存在しない場合はデフォルト設定のまま動作する。
     *
     * @param array<string, mixed> $config 初期化オプション
     * @return void
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
     * JSファイルの自動読み込みを実行する。
     *
     * 解決されたJSファイルを<script>タグとしてブロックに出力する。
     * moduleモードが有効な場合は type="module" 属性を付与する。
     *
     * @return string 出力結果（blockオプションにより実際はビューブロックに格納される）
     */
    public function scripts(): string
    {
        $files = $this->resolveFiles('js');
        $output = '';
        $options = [];
        if ($this->getConfig('module')) {
            $options['type'] = 'module';
        }
        foreach ($files as $file) {
            $output .= $this->Html->script($file, $options + ['block' => true]);
        }
        return $output;
    }

    /**
     * CSSファイルの自動読み込みを実行する。
     *
     * 解決されたCSSファイルを<link>タグとしてブロックに出力する。
     *
     * @return string 出力結果（blockオプションにより実際はビューブロックに格納される）
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
     * アセットファイルを命名規則に従って解決する。
     *
     * 以下の優先順位でファイルを探索する:
     * 1. グローバル共通 (patterns の条件が true のもの)
     * 2. グローバル画面種別 (patterns の条件が配列でアクションが合致するもの)
     * 3. コントローラ共通 (例: Users/all.js)
     * 4. アクション固有 (例: Users/index.js)
     * 5. プレフィックス共通 (例: Admin/all.js)
     * 6. コントローラ共通+プレフィックス (例: Admin/Users/all.js)
     * 7. アクション固有+プレフィックス (例: Admin/Users/index.js)
     *
     * @param string $ext 拡張子（'js' または 'css'）
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
        $patterns = $this->getConfig('patterns') ?: [];

        // ファイルパターンごとに判定（グローバル共通ファイル）
        foreach ($patterns as $pattern => $condition) {
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
        foreach ($patterns as $pattern => $condition) {
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

        // プレフィックス共通（例: Admin/all.css）
        if ($prefix) {
            foreach ($patterns as $pattern => $condition) {
                if (!is_string($pattern)) {
                    continue;
                }

                if ($condition === true || (is_array($condition) && in_array($action, $condition, true))) {
                    $prefixCommonPath = $prefix . DS . $pattern;
                    if (file_exists($basePath . $prefixCommonPath . '.' . $ext)) {
                        $files[] = str_replace(DS, '/', $prefixCommonPath);
                    }
                }
            }
        }

        // コントローラ共通（プレフィックス付き）
        if ($prefix) {
            foreach ($patterns as $pattern => $condition) {
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
