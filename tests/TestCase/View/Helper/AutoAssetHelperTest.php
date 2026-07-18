<?php
declare(strict_types=1);

namespace AutoAsset\Test\TestCase\View\Helper;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use AutoAsset\View\Helper\AutoAssetHelper;

/**
 * AutoAssetHelperTest
 *
 * AutoAssetHelper によるアセット自動解決とHTML出力を検証する。
 * テスト用の一時ディレクトリに擬似的なアセットファイルを配置し、
 * 命名規則に基づくファイル解決が正しく動作することを確認する。
 */
class AutoAssetHelperTest extends TestCase
{
    /**
     * テスト対象のヘルパーインスタンス
     *
     * @var \AutoAsset\View\Helper\AutoAssetHelper
     */
    protected AutoAssetHelper $helper;

    /**
     * テスト用一時ディレクトリのパス
     *
     * @var string
     */
    protected string $tmpPath;

    /**
     * テスト前処理
     *
     * 一時ディレクトリを作成し、js/css サブディレクトリを準備する。
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // 一時ディレクトリを作成
        $this->tmpPath = sys_get_temp_dir() . DS . 'autoasset_test_' . uniqid() . DS;
        mkdir($this->tmpPath . 'js', 0777, true);
        mkdir($this->tmpPath . 'css', 0777, true);
    }

    /**
     * テスト後処理
     *
     * 一時ディレクトリを削除する。
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        // 一時ディレクトリを削除
        $this->removeDirectory($this->tmpPath);
    }

    /**
     * ディレクトリを再帰的に削除する。
     *
     * @param string $path 削除対象のディレクトリパス
     * @return void
     */
    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($path);
    }

    /**
     * テスト用ヘルパーインスタンスを生成する。
     *
     * 指定されたリクエストパラメータに基づいて ServerRequest と View を構築し、
     * AutoAssetHelper のインスタンスを返す。
     *
     * @param array<string, mixed> $requestParams リクエストパラメータ（controller, action, prefix 等）
     * @return \AutoAsset\View\Helper\AutoAssetHelper
     */
    protected function createHelper(array $requestParams = []): AutoAssetHelper
    {
        $request = new ServerRequest([
            'url' => '/',
            'params' => array_merge([
                'controller' => 'Hearings',
                'action' => 'index',
            ], $requestParams),
        ]);

        $view = new View($request);
        $helper = new AutoAssetHelper($view);

        // テスト用パスを設定
        $helper->setConfig('assetBasePath', $this->tmpPath);

        return $helper;
    }

    /**
     * テスト用のアセットファイルを作成する。
     *
     * 指定されたパスにダミーのアセットファイルを生成する。
     * 必要に応じて親ディレクトリも自動作成する。
     *
     * @param string $path アセットファイルの相対パス（例: 'js/all.js'）
     * @return void
     */
    protected function createAssetFile(string $path): void
    {
        $fullPath = $this->tmpPath . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, '// test');
    }

    /**
     * アセットファイルが存在しない場合、空配列が返されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesEmpty(): void
    {
        $helper = $this->createHelper();

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertEmpty($files);
    }

    /**
     * all.js のみが存在する場合、グローバル共通ファイルが正しく解決されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesAllOnly(): void
    {
        $helper = $this->createHelper();
        $this->createAssetFile('js/all.js');

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertCount(1, $files);
        $this->assertContains('all', $files);
    }

    /**
     * コントローラ共通ファイル（Hearings/all.js）が解決されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesController(): void
    {
        $helper = $this->createHelper();
        $this->createAssetFile('js/all.js');
        $this->createAssetFile('js/Hearings/all.js');

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertCount(2, $files);
        $this->assertEquals(['all', 'Hearings/all'], $files);
    }

    /**
     * アクション固有ファイル（Hearings/index.js）が解決されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesAction(): void
    {
        $helper = $this->createHelper();
        $this->createAssetFile('js/all.js');
        $this->createAssetFile('js/Hearings/all.js');
        $this->createAssetFile('js/Hearings/index.js');

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertCount(3, $files);
        $this->assertEquals(['all', 'Hearings/all', 'Hearings/index'], $files);
    }

    /**
     * プレフィックス付き（Admin/）の全パターンが正しく解決されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesWithPrefix(): void
    {
        $helper = $this->createHelper([
            'prefix' => 'Admin',
        ]);
        $this->createAssetFile('js/all.js');
        $this->createAssetFile('js/Hearings/all.js');
        $this->createAssetFile('js/Admin/Hearings/all.js');
        $this->createAssetFile('js/Hearings/index.js');
        $this->createAssetFile('js/Admin/Hearings/index.js');

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertCount(5, $files);
        $this->assertEquals(['all', 'Hearings/all', 'Hearings/index', 'Admin/Hearings/all', 'Admin/Hearings/index'], $files);
    }

    /**
     * プレフィックス共通ファイル（Admin/all.js）が解決されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesWithPrefixCommon(): void
    {
        $helper = $this->createHelper([
            'prefix' => 'Admin',
        ]);
        $this->createAssetFile('js/all.js');
        $this->createAssetFile('js/Admin/all.js');
        $this->createAssetFile('js/Admin/Hearings/index.js');

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertCount(3, $files);
        $this->assertEquals(['all', 'Admin/all', 'Admin/Hearings/index'], $files);
    }

    /**
     * input アクション（add）時に input.js が解決されることを検証する。
     *
     * @return void
     */
    public function testResolveFilesInputAction(): void
    {
        $helper = $this->createHelper([
            'action' => 'add',
        ]);
        $this->createAssetFile('js/all.js');
        $this->createAssetFile('js/input.js');
        $this->createAssetFile('js/Hearings/all.js');
        $this->createAssetFile('js/Hearings/input.js');

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertContains('all', $files);
        $this->assertContains('input', $files);
        $this->assertContains('Hearings/all', $files);
        $this->assertContains('Hearings/input', $files);
    }

    /**
     * scripts() の標準（非module）モードで<script>タグが正しく出力されることを検証する。
     *
     * @return void
     */
    public function testScriptsOutput(): void
    {
        $helper = $this->createHelper([
            'controller' => 'Hearings',
            'action' => 'index',
        ]);
        $helper->setConfig('module', false);
        $this->createAssetFile('js/all.js');
        $this->createAssetFile('js/Hearings/index.js');

        $helper->scripts();

        $scriptBlock = $helper->getView()->fetch('script');
        $this->assertStringContainsString('<script', $scriptBlock);
        $this->assertStringContainsString('src="/js/all.js"', $scriptBlock);
        $this->assertStringContainsString('src="/js/Hearings/index.js"', $scriptBlock);
        $this->assertStringNotContainsString('type="module"', $scriptBlock);
    }

    /**
     * styles() で<link>タグが正しく出力されることを検証する。
     *
     * @return void
     */
    public function testStylesOutput(): void
    {
        $helper = $this->createHelper([
            'controller' => 'Hearings',
            'action' => 'index',
        ]);
        $this->createAssetFile('css/all.css');
        $this->createAssetFile('css/Hearings/index.css');

        $helper->styles();

        $cssBlock = $helper->getView()->fetch('css');
        $this->assertStringContainsString('<link', $cssBlock);
        $this->assertStringContainsString('rel="stylesheet"', $cssBlock);
        $this->assertStringContainsString('href="/css/all.css"', $cssBlock);
        $this->assertStringContainsString('href="/css/Hearings/index.css"', $cssBlock);
    }

    /**
     * アセットファイルが存在しない場合、空の出力になることを検証する。
     *
     * @return void
     */
    public function testScriptsEmptyOutput(): void
    {
        $helper = $this->createHelper([
            'controller' => 'Hearings',
            'action' => 'index',
        ]);

        $helper->scripts();
        $helper->styles();

        $this->assertEmpty($helper->getView()->fetch('script'));
        $this->assertEmpty($helper->getView()->fetch('css'));
    }

    /**
     * scripts() の module モードで type="module" 属性が付与されることを検証する。
     *
     * @return void
     */
    public function testScriptsModuleOutput(): void
    {
        $helper = $this->createHelper([
            'controller' => 'Hearings',
            'action' => 'index',
        ]);
        $helper->setConfig('module', true);
        $this->createAssetFile('js/all.js');

        $helper->scripts();

        $scriptBlock = $helper->getView()->fetch('script');
        $this->assertStringContainsString('all.js', $scriptBlock);
        $this->assertStringContainsString('type="module"', $scriptBlock);
    }

    /**
     * styles() が module モードの影響を受けないことを検証する。
     *
     * @return void
     */
    public function testStylesNotAffectedByModule(): void
    {
        $helper = $this->createHelper([
            'controller' => 'Hearings',
            'action' => 'index',
        ]);
        $helper->setConfig('module', true);
        $this->createAssetFile('css/all.css');

        $helper->styles();

        $cssBlock = $helper->getView()->fetch('css');
        $this->assertStringContainsString('all.css', $cssBlock);
        $this->assertStringNotContainsString('module', $cssBlock);
    }
}
