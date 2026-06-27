<?php
declare(strict_types=1);

namespace AutoAsset\Test\TestCase\View\Helper;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use AutoAsset\View\Helper\AutoAssetHelper;

/**
 * AutoAssetHelper Test Case
 */
class AutoAssetHelperTest extends TestCase
{
    /**
     * @var AutoAssetHelper
     */
    protected AutoAssetHelper $helper;

    /**
     * @var string
     */
    protected string $tmpPath;

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        // 一時ディレクトリを削除
        $this->removeDirectory($this->tmpPath);
    }

    /**
     * ディレクトリを再帰的に削除
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
     * Helperを初期化
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
     * テスト用ファイルを作成
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
     * Test resolveFiles with no files
     */
    public function testResolveFilesEmpty(): void
    {
        $helper = $this->createHelper();

        $method = new \ReflectionMethod($helper, 'resolveFiles');
        $files = $method->invoke($helper, 'js');

        $this->assertEmpty($files);
    }

    /**
     * Test resolveFiles with all.js only
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
     * Test resolveFiles with controller common
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
     * Test resolveFiles with action specific
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
     * Test resolveFiles with prefix
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
     * Test resolveFiles with input action
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
     * Test scripts() HTML output (default: no module)
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
     * Test styles() HTML output (<link> tag)
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
     * Test empty output when no files exist
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
     * Test scripts() with module mode
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
     * Test styles() are not affected by module mode
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
