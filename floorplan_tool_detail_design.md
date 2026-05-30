# 間取り図画像自動加工ツール PHP実装用詳細設計書

## 1. 文書概要

本書は、XAMPP環境で動作する「間取り図画像自動加工ツール」のPHP実装に必要な詳細設計を定義する。

実装はCodexで行うことを想定し、ファイル構成、処理責務、関数設計、バリデーション、画像加工ロジック、ダウンロード処理を明確化する。

---

## 2. 実装方針

## 2-1. 基本方針

- PHP単体で動作するシンプルな構成とする。
- 初期実装ではデータベースを使用しない。
- 処理単位ごとに `batch_id` を発行し、一時ディレクトリに加工画像とメタデータを保存する。
- 画像処理はGDライブラリを使用する。
- ZIP一括ダウンロードには `ZipArchive` を使用する。
- セッションを使用してCSRFトークンを管理する。
- ローカルXAMPP利用を前提としつつ、将来的にサーバー公開できるよう責務を分離する。

---

## 2-2. 主要仕様

| 項目 | 内容 |
|---|---|
| 入力形式 | jpg / jpeg / png / gif |
| 出力形式 | gif / png / jpg |
| 出力サイズ | 500px × 500px |
| リサイズ方式 | 縦横比維持、長辺500px |
| 余白 | 白背景 |
| 配置 | 中央配置 |
| アップロード枚数 | 初期上限50枚 |
| 1ファイル上限 | 初期値10MB |
| DB | 使用しない |
| 実行環境 | XAMPP / Apache / PHP |

---

# 3. ディレクトリ構成

## 3-1. 推奨構成

```text
floorplan-tool/
├─ index.php
├─ process.php
├─ result.php
├─ download.php
├─ zip_download.php
├─ config.php
├─ functions.php
├─ cleanup.php
├─ assets/
│  ├─ css/
│  │  └─ style.css
│  └─ js/
│     └─ app.js
├─ uploads/
│  ├─ original/
│  ├─ processed/
│  └─ zip/
└─ tmp/
   └─ batches/
```

---

## 3-2. 各ファイルの責務

| ファイル | 役割 |
|---|---|
| `index.php` | アップロードフォーム表示 |
| `process.php` | アップロード受付、検証、画像加工、メタデータ保存 |
| `result.php` | 加工結果一覧表示 |
| `download.php` | 個別画像ダウンロード |
| `zip_download.php` | ZIP一括ダウンロード |
| `config.php` | 定数定義、パス設定、許可形式設定 |
| `functions.php` | 共通関数、画像処理関数、バリデーション関数 |
| `cleanup.php` | 古い一時ファイル削除処理 |
| `assets/css/style.css` | 画面スタイル |
| `assets/js/app.js` | クライアント側補助処理 |

---

# 4. 設定ファイル設計

## 4-1. `config.php`

### 定義する定数

```php
define('BASE_DIR', __DIR__);
define('UPLOAD_ORIGINAL_DIR', BASE_DIR . '/uploads/original/');
define('UPLOAD_PROCESSED_DIR', BASE_DIR . '/uploads/processed/');
define('UPLOAD_ZIP_DIR', BASE_DIR . '/uploads/zip/');
define('BATCH_DIR', BASE_DIR . '/tmp/batches/');

define('OUTPUT_SIZE', 500);
define('BACKGROUND_R', 255);
define('BACKGROUND_G', 255);
define('BACKGROUND_B', 255);

define('MAX_UPLOAD_FILES', 50);
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

define('DEFAULT_OUTPUT_FORMAT', 'png');

define('ALLOWED_INPUT_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_OUTPUT_FORMATS', ['gif', 'png', 'jpg']);

define('SESSION_KEY_CSRF', 'csrf_token');
```

---

## 4-2. 保存ディレクトリ作成

起動時または処理開始時に、以下のディレクトリが存在しない場合は作成する。

```text
uploads/original/
uploads/processed/
uploads/zip/
tmp/batches/
```

### 権限

XAMPPローカル環境では通常問題ないが、書き込み不可の場合はエラーを返す。

---

# 5. データ構造設計

## 5-1. batch_id

1回の処理単位ごとに発行する一意ID。

### 形式

```text
YYYYMMDD_HHMMSS_ランダム文字列
```

### 例

```text
20260529_153000_a8f2c1
```

---

## 5-2. メタデータ保存

DBを使わず、JSONファイルで管理する。

### 保存先

```text
tmp/batches/{batch_id}.json
```

---

## 5-3. メタデータ構造

```json
{
  "batch_id": "20260529_153000_a8f2c1",
  "created_at": "2026-05-29 15:30:00",
  "output_format": "png",
  "output_size": 500,
  "items": [
    {
      "image_id": "img_001",
      "original_name": "sample01.png",
      "default_filename": "sample01",
      "original_path": "uploads/original/20260529_153000_a8f2c1/img_001.png",
      "processed_path": "uploads/processed/20260529_153000_a8f2c1/img_001.png",
      "mime_type": "image/png",
      "source_width": 1200,
      "source_height": 600,
      "resized_width": 500,
      "resized_height": 250,
      "status": "success",
      "error": null
    }
  ]
}
```

---

# 6. 処理フロー設計

## 6-1. アップロードから加工まで

```text
index.php
↓
process.php
  1. セッション開始
  2. CSRFチェック
  3. 出力形式チェック
  4. アップロードファイル存在チェック
  5. ファイル数チェック
  6. batch_id生成
  7. batch用ディレクトリ作成
  8. 各ファイルを検証
  9. 元画像を保存
  10. 画像を500×500に加工
  11. 加工済み画像を保存
  12. メタデータJSONを保存
↓
result.php?batch={batch_id}
```

---

## 6-2. 加工結果表示

```text
result.php
  1. セッション開始
  2. batch_id取得
  3. メタデータJSON読込
  4. 加工済み画像一覧表示
  5. ファイル名入力欄表示
  6. 個別ダウンロードフォーム表示
  7. ZIP一括ダウンロードフォーム表示
```

---

## 6-3. 個別ダウンロード

```text
download.php
  1. POST受信
  2. CSRFチェック
  3. batch_id / image_id / filename 検証
  4. メタデータJSON読込
  5. 対象画像の存在確認
  6. filenameをサニタイズ
  7. Content-Type / Content-Disposition を設定
  8. 画像ファイルを出力
```

---

## 6-4. ZIP一括ダウンロード

```text
zip_download.php
  1. POST受信
  2. CSRFチェック
  3. batch_id / filenames配列 検証
  4. メタデータJSON読込
  5. ZIPファイル作成
  6. 各画像を指定ファイル名でZIPに追加
  7. Content-Type / Content-Disposition を設定
  8. ZIPファイルを出力
```

---

# 7. 画像加工ロジック

## 7-1. 基本仕様

- 元画像の縦横比を維持する。
- 長辺が500pxになるようにリサイズする。
- 500px × 500pxの白背景キャンバスを作成する。
- リサイズ画像を中央配置する。
- 画像の切り抜きは行わない。

---

## 7-2. 計算式

### 元画像

```text
source_width
source_height
```

### 長辺判定

```text
long_side = max(source_width, source_height)
scale = 500 / long_side
```

### リサイズ後サイズ

```text
resized_width = round(source_width * scale)
resized_height = round(source_height * scale)
```

### 中央配置座標

```text
dst_x = floor((500 - resized_width) / 2)
dst_y = floor((500 - resized_height) / 2)
```

---

## 7-3. 例

### 横長画像

```text
source: 1200 × 600
long_side: 1200
scale: 500 / 1200 = 0.4166
resized: 500 × 250
dst_x: 0
dst_y: 125
```

### 縦長画像

```text
source: 300 × 900
long_side: 900
scale: 500 / 900 = 0.5555
resized: 167 × 500
dst_x: 166
dst_y: 0
```

---

## 7-4. GD処理方針

### 読み込み

入力形式に応じて以下を使用する。

| 拡張子 | 関数 |
|---|---|
| jpg / jpeg | `imagecreatefromjpeg()` |
| png | `imagecreatefrompng()` |
| gif | `imagecreatefromgif()` |

### キャンバス作成

```php
$canvas = imagecreatetruecolor(500, 500);
$white = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $white);
```

### リサイズ・配置

```php
imagecopyresampled(
    $canvas,
    $sourceImage,
    $dstX,
    $dstY,
    0,
    0,
    $resizedWidth,
    $resizedHeight,
    $sourceWidth,
    $sourceHeight
);
```

### 保存

出力形式に応じて以下を使用する。

| 出力形式 | 関数 |
|---|---|
| gif | `imagegif()` |
| png | `imagepng()` |
| jpg | `imagejpeg()` |

---

# 8. 関数設計

## 8-1. `ensureDirectories(): void`

### 役割

必要なディレクトリが存在するか確認し、なければ作成する。

---

## 8-2. `generateCsrfToken(): string`

### 役割

セッションにCSRFトークンを生成・保存する。

---

## 8-3. `verifyCsrfToken(string $token): bool`

### 役割

POSTされたCSRFトークンを検証する。

---

## 8-4. `generateBatchId(): string`

### 役割

一意の処理IDを生成する。

### 戻り値例

```text
20260529_153000_a8f2c1
```

---

## 8-5. `normalizeFilesArray(array $files): array`

### 役割

`$_FILES['images']` の複数アップロード構造を扱いやすい配列に変換する。

---

## 8-6. `validateUploadedFile(array $file): array`

### 役割

アップロード画像を検証する。

### チェック内容

- PHPアップロードエラー
- ファイルサイズ
- 拡張子
- MIMEタイプ
- `getimagesize()` による画像判定

### 戻り値

```php
[
    'valid' => true,
    'extension' => 'png',
    'mime_type' => 'image/png',
    'width' => 1200,
    'height' => 600,
    'error' => null,
]
```

---

## 8-7. `sanitizeFilename(string $filename): string`

### 役割

ユーザー入力ファイル名をダウンロード用に安全な形へ整形する。

### 処理内容

- 前後空白除去
- 拡張子除去
- 禁止文字を `_` に置換
- 制御文字を除去
- 空欄の場合は呼び出し元で補完

### 禁止文字

```text
\ / : * ? " < > |
```

---

## 8-8. `makeUniqueFilenames(array $filenameMap, string $extension): array`

### 役割

同名ファイルが存在する場合に連番を付与する。

### 例

```text
101.png
101_2.png
101_3.png
```

---

## 8-9. `createImageResource(string $path, string $extension): GdImage|false`

### 役割

拡張子に応じて画像リソースを作成する。

---

## 8-10. `processImageToSquare(string $sourcePath, string $destPath, string $inputExtension, string $outputFormat): array`

### 役割

画像を500×500に加工して保存する。

### 戻り値

```php
[
    'success' => true,
    'source_width' => 1200,
    'source_height' => 600,
    'resized_width' => 500,
    'resized_height' => 250,
    'error' => null,
]
```

---

## 8-11. `saveMetadata(string $batchId, array $metadata): bool`

### 役割

処理結果をJSONで保存する。

---

## 8-12. `loadMetadata(string $batchId): ?array`

### 役割

指定された `batch_id` のJSONを読み込む。

---

## 8-13. `findItemByImageId(array $metadata, string $imageId): ?array`

### 役割

メタデータ内から対象画像を取得する。

---

# 9. バリデーション設計

## 9-1. 出力形式チェック

許可値のみ受け付ける。

```php
['gif', 'png', 'jpg']
```

未指定または不正値の場合は `png` とするか、エラーにする。  
初期実装ではエラーではなく `png` にフォールバックしてもよい。

---

## 9-2. アップロードファイル数

```text
1枚以上50枚以下
```

50枚を超える場合は処理を中断する。

---

## 9-3. ファイルサイズ

```text
1ファイルあたり10MB以下
```

超過時は該当ファイルをエラーとする。

---

## 9-4. MIMEタイプ

許可するMIMEタイプは以下。

```text
image/jpeg
image/png
image/gif
```

`finfo_file()` または `getimagesize()` の結果を用いて判定する。

---

## 9-5. 拡張子

許可する拡張子は以下。

```text
jpg
jpeg
png
gif
```

大文字小文字は区別せず、小文字化して判定する。

---

## 9-6. ファイル名

ダウンロード時にユーザー入力ファイル名を必ずサニタイズする。

### サニタイズ例

| 入力 | 出力 |
|---|---|
| `101号室/間取り` | `101号室_間取り` |
| `A:202` | `A_202` |
| `   sample   ` | `sample` |
| 空欄 | `floorplan_001` |

---

# 10. ダウンロード設計

## 10-1. 個別ダウンロード

### リクエスト

```http
POST /floorplan-tool/download.php
```

### POST項目

| 項目 | 必須 | 内容 |
|---|---|---|
| csrf_token | 必須 | CSRFトークン |
| batch_id | 必須 | 処理ID |
| image_id | 必須 | 画像ID |
| filename | 必須 | ユーザー入力ファイル名 |

### レスポンスヘッダー例

```php
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
```

---

## 10-2. ZIP一括ダウンロード

### リクエスト

```http
POST /floorplan-tool/zip_download.php
```

### POST項目

| 項目 | 必須 | 内容 |
|---|---|---|
| csrf_token | 必須 | CSRFトークン |
| batch_id | 必須 | 処理ID |
| filenames | 必須 | `image_id => filename` の配列 |

### ZIP作成仕様

- 一時ZIPファイルを `uploads/zip/` に作成する。
- ZIP内の各ファイル名は、ユーザーが指定したファイル名を使用する。
- 同名がある場合は連番を付与する。
- ZIP作成後、ダウンロードレスポンスとして出力する。

### ZIPファイル名

```text
floorplan_images_YYYYMMDD_HHMMSS.zip
```

---

# 11. エラー処理設計

## 11-1. エラー管理方針

- アップロード時点の重大エラーは `index.php` に戻して表示する。
- 個別ファイル単位のエラーは、可能であれば他ファイルの処理は継続する。
- 加工成功・失敗の結果はメタデータに保存する。
- 確認画面では成功画像のみダウンロード対象とする。

---

## 11-2. エラーメッセージ一覧

| コード | メッセージ |
|---|---|
| E_NO_FILE | 画像ファイルを選択してください。 |
| E_TOO_MANY_FILES | 一度に処理できる画像は50枚までです。 |
| E_INVALID_FORMAT | 対応していないファイル形式です。 |
| E_FILE_SIZE | ファイルサイズが上限を超えています。 |
| E_UPLOAD_FAILED | ファイルのアップロードに失敗しました。 |
| E_IMAGE_INVALID | 画像ファイルとして読み込めませんでした。 |
| E_PROCESS_FAILED | 画像の加工に失敗しました。 |
| E_SAVE_FAILED | ファイルの保存に失敗しました。 |
| E_BATCH_NOT_FOUND | 処理データが見つかりません。 |
| E_ZIP_FAILED | ZIPファイルの作成に失敗しました。 |
| E_CSRF | 不正なリクエストです。画面を再読み込みしてください。 |

---

# 12. セキュリティ設計

## 12-1. CSRF対策

- `index.php` 表示時にCSRFトークンを発行する。
- `process.php`, `download.php`, `zip_download.php` で検証する。

---

## 12-2. ファイルアップロード対策

- 拡張子だけで判定しない。
- MIMEタイプも確認する。
- `getimagesize()` で画像として認識できるか確認する。
- 保存ファイル名はシステム側で採番する。
- ユーザー入力ファイル名を保存パスに直接使用しない。
- アップロード先にPHPファイルを置かせない。
- `.htaccess` で `uploads/` 配下のPHP実行を防止できる場合は設定する。

---

## 12-3. パストラバーサル対策

- `batch_id` は許可形式にマッチするもののみ受け付ける。
- `image_id` はメタデータ内に存在するもののみ使用する。
- POSTされたファイルパスは一切信用しない。
- 実ファイルパスは必ずメタデータから取得する。

---

# 13. XAMPP / PHP設定

## 13-1. 確認対象

複数画像アップロードを行うため、以下の設定を確認する。

```ini
upload_max_filesize
post_max_size
max_file_uploads
memory_limit
max_execution_time
max_input_time
```

---

## 13-2. 推奨値

```ini
upload_max_filesize = 10M
post_max_size = 128M
max_file_uploads = 50
memory_limit = 256M
max_execution_time = 120
max_input_time = 120
```

---

## 13-3. GD拡張

XAMPPの `php.ini` で以下が有効であることを確認する。

```ini
extension=gd
```

必要に応じてApacheを再起動する。

---

## 13-4. ZipArchive拡張

XAMPPのPHPで `ZipArchive` が利用可能であることを確認する。  
利用できない場合は `zip` 拡張の有効化を確認する。

---

# 14. CSS設計方針

## 14-1. 基本方針

- 業務ツールとしてシンプルな見た目にする。
- 確認画面では画像カードをグリッド表示する。
- 画像の確認性を優先する。

---

## 14-2. 主要クラス

| クラス名 | 用途 |
|---|---|
| `.container` | 画面全体の幅調整 |
| `.upload-box` | アップロードフォーム領域 |
| `.notice` | 注意文 |
| `.error-box` | エラー表示 |
| `.result-grid` | 加工結果一覧 |
| `.result-card` | 各画像カード |
| `.preview-image` | プレビュー画像 |
| `.filename-input` | ファイル名入力 |
| `.button` | 共通ボタン |
| `.button-primary` | 主要ボタン |
| `.button-secondary` | 補助ボタン |

---

# 15. JavaScript設計

## 15-1. `assets/js/app.js`

### アップロード画面

- ファイル選択時に選択ファイル数を表示する。
- 選択ファイル名一覧を表示する。
- 非対応拡張子が含まれていれば警告する。
- 50枚を超える場合は警告する。

### 確認画面

- ファイル名入力欄で禁止文字を置換する。
- ZIPダウンロード前に空欄を補完する。
- 同名ファイル名がある場合は自動連番または警告する。
- 個別ダウンロード時に該当ファイル名をPOSTする。

---

# 16. 初期実装で不要なもの

以下は初期実装では不要とする。

- ログイン機能
- データベース
- 画像履歴管理
- 画像の並び替え
- ドラッグ＆ドロップ
- WebP対応
- 複数形式の同時出力
- API化
- 外部サーバー公開

---

# 17. 将来拡張

将来的には以下を追加できる設計にしておく。

- ドラッグ＆ドロップアップロード
- WebP出力
- 画像並び替え
- 不要画像の除外
- ファイル名一括置換
- 連番一括設定
- DBによる処理履歴管理
- 物件IDとの紐付け
- 社内サーバー上での共有利用
- WordPressや物件CMSへの直接登録

---

# 18. Codex実装指示用まとめ

## 実装対象

XAMPP環境で動作するPHP製の間取り図画像加工ツールを作成する。

## 必須機能

- 複数画像アップロード
- jpg / jpeg / png / gif 入力対応
- gif / png / jpg 出力形式選択
- 画像を500px × 500pxに加工
- 縦横比維持
- 長辺500px基準でリサイズ
- 短辺側は白余白
- 中央配置
- 加工後プレビュー一覧
- 各画像ごとの手動ファイル名変更
- 個別ダウンロード
- ZIP一括ダウンロード
- CSRF対策
- ファイル名サニタイズ
- メタデータJSON管理

## 実装ファイル

```text
index.php
process.php
result.php
download.php
zip_download.php
config.php
functions.php
cleanup.php
assets/css/style.css
assets/js/app.js
```

## 注意事項

- ユーザー入力ファイル名を保存パスに直接使わない。
- 画像ファイルの検証は拡張子・MIMEタイプ・getimagesizeで行う。
- PHPファイル等のアップロードを許可しない。
- 出力画像は必ず500px × 500pxとする。
- 元画像を切り抜かない。
- GIF入力はアニメーションGIFの場合でも初期実装では1フレーム目のみ処理対象でよい。
