# AutoAsset

CakePHP プラグインです。コントローラとアクションに基づいて、JavaScript/CSSファイルを自動的に読み込みます。

## 概要

画面ごとに必要なアセットファイルを手動で記述する必要なく、ファイルの配置場所だけで自動的に読み込み対象を決定します。

## インストール

Packagist に公開していないため、GitHub リポジトリを直接指定してインストールします。

### 1. `composer.json` にリポジトリを追加

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/wate/cake-autoasset.git"
        }
    ],
    "require": {
        "wate/cake-autoasset": "dev-main"
    }
}
```

### 2. Composer でインストール

```bash
composer require wate/cake-autoasset:dev-main
```

### 3. プラグインを有効化

`config/plugins.php` に追加：

```php
return [
    'AutoAsset' => [],
];
```

## ファイル配置規約

`webroot/js/` または `webroot/css/` 以下に、次の規約でファイルを配置します。

```
webroot/
├── js/
│   ├── all.js              # 全画面で読み込む
│   ├── Form.js             # 特定画面種別で読み込む
│   ├── Hearings/
│   │   ├── all.js          # Hearingsコントローラ全アクション
│   │   └── index.js        # HearingsController::index
│   └── Admin/
│       └── Hearings/
│           ├── all.js      # Admin/Hearingsコントローラ全アクション
│           └── index.js    # Admin\HearingsController::index
└── css/
    └── （jsと同じ構造）
```

## 設定

`config/autoasset.php` を作成します。

```php
<?php
return [
    'AutoAsset' => [
        'all' => true,              // 全画面で読み込む
        'input' => ['add', 'edit'], // add, edit で input.js/css を読み込む
        'form' => ['add', 'edit', 'view'], // add, edit, view で form.js/css を読み込む
    ],
];
```

### 画面種別キーの設定パターン

- `true` — 全アクションで読み込む
- `['index', 'edit']` — 指定したアクションでのみ読み込む

## レイアウトへの組み込み

`templates/layout/default.php` などに以下を追加：

```php
<?= $this->AutoAsset->scripts() ?>
<?= $this->AutoAsset->styles() ?>
```

## 読み込み順序

1. `all.js/css` — グローバル共通
2. `Controller/all.js/css` — コントローラ共通（無印）
3. `Controller/action.js/css` — アクション固有（無印）
4. `Prefix/Controller/all.js/css` — コントローラ共通（プレフィックス付き）
5. `Prefix/Controller/action.js/css` — アクション固有（プレフィックス付き）

ファイルが存在する場合のみ読み込み対象となります。後から読み込まれるファイルで、先のファイルを上書きできます。

## 開発

```bash
# プラグイン単体のテスト実行
cd plugins/AutoAsset
php ../../vendor/bin/phpunit
```

## ライセンス

MIT
