# 間取り図画像自動加工ツール 仕様書・詳細設計書

# 間取り図画像自動加工ツール 画面仕様書

## 1. 文書概要

本書は、XAMPP環境で利用する「間取り図画像自動加工ツール」の画面仕様を定義する。

本ツールは、複数の間取り図画像を一括アップロードし、縦横比を維持したまま長辺を500pxに合わせてリサイズしたうえで、最終的に500px × 500pxの画像として出力する。

加工後の画像は画面上で確認しながら、各画像ごとにファイル名を手動変更し、個別またはZIP形式で一括ダウンロードできるものとする。

---

## 2. 対象環境

| 項目 | 内容 |
|---|---|
| 利用環境 | ローカルPC上のXAMPP |
| Webサーバー | Apache |
| サーバーサイド | PHP |
| フロントエンド | HTML / CSS / JavaScript |
| 画像処理 | PHP GDライブラリ |
| 利用URL例 | `http://localhost/floorplan-tool/` |

---

## 3. 画面一覧

| 画面ID | 画面名 | ファイル | 概要 |
|---|---|---|---|
| SCR-01 | アップロード画面 | `index.php` | 複数画像の選択、出力形式の選択、加工実行 |
| SCR-02 | 加工結果確認画面 | `result.php` | 加工済み画像の確認、ファイル名変更、個別/一括ダウンロード |
| SCR-03 | エラー表示 | 各画面内 | 入力エラー、処理エラー、保存エラー等の表示 |

---

# 4. SCR-01 アップロード画面

## 4-1. 画面目的

ユーザーが複数の間取り図画像を選択し、出力形式を指定したうえで、画像加工処理を開始する。

---

## 4-2. 画面URL

```text
http://localhost/floorplan-tool/index.php
```

---

## 4-3. 画面構成

| No | 要素 | 種別 | 必須 | 内容 |
|---|---|---|---|---|
| 1 | 画面タイトル | テキスト | - | 「間取り図画像自動加工ツール」 |
| 2 | 説明文 | テキスト | - | 長辺500px・500×500出力・白余白の説明 |
| 3 | 画像ファイル選択 | file input | 必須 | 複数画像を選択 |
| 4 | 出力形式選択 | radio または select | 必須 | gif / png / jpg |
| 5 | 選択ファイル一覧 | 表示領域 | 任意 | 選択されたファイル名を表示 |
| 6 | 加工実行ボタン | submit | 必須 | アップロード・加工処理を実行 |
| 7 | 注意事項 | テキスト | - | 対応形式、枚数、サイズ制限 |
| 8 | エラー表示領域 | テキスト | - | エラー発生時に表示 |

---

## 4-4. ファイル選択項目

### HTML仕様

```html
<input type="file" name="images[]" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" multiple>
```

### 要件

- 複数選択を許可する。
- 10枚以上の画像を一括選択できる。
- 初期想定上限は50枚とする。
- 対応形式は `jpg`, `jpeg`, `png`, `gif` とする。
- ブラウザ側の `accept` は補助的な制御とし、サーバー側でも必ず検証する。

---

## 4-5. 出力形式選択

| 値 | 表示名 | 拡張子 |
|---|---|---|
| gif | GIF | `.gif` |
| png | PNG | `.png` |
| jpg | JPG | `.jpg` |

### 初期値

```text
png
```

### 補足

間取り図は線や文字が多いため、初期値はPNGを推奨する。  
ただし既存運用でGIFが必要な場合に備え、GIFも選択可能とする。

---

## 4-6. アップロード画面の文言例

### 説明文

```text
アップロードした間取り図画像を、縦横比を維持したまま長辺500pxにリサイズし、500px × 500pxの画像として出力します。
縦長・横長画像の場合は、短辺側に白い余白を付けて中央配置します。
```

### 注意事項

```text
対応形式：jpg / jpeg / png / gif
一度に最大50枚まで処理できます。
出力形式は gif / png / jpg から選択できます。
```

---

## 4-7. 入力チェック

### クライアント側チェック

JavaScriptで以下を補助的にチェックする。

| チェック項目 | 内容 |
|---|---|
| ファイル未選択 | 未選択の場合は加工実行不可 |
| ファイル数 | 50枚を超える場合は警告 |
| 拡張子 | jpg/jpeg/png/gif 以外は警告 |
| 選択ファイル一覧 | 選択済みファイル名を表示 |

### サーバー側チェック

サーバー側で必ず以下をチェックする。

| チェック項目 | 内容 |
|---|---|
| ファイル未選択 | `$_FILES['images']` が空の場合はエラー |
| PHPアップロードエラー | `UPLOAD_ERR_OK` 以外はエラー |
| ファイル数上限 | 50枚超過はエラー |
| ファイルサイズ | 1ファイルあたり10MB超過はエラー |
| 拡張子 | jpg/jpeg/png/gif のみ許可 |
| MIMEタイプ | image/jpeg, image/png, image/gif のみ許可 |
| 画像実体 | `getimagesize()` で画像として判定できること |

---

## 4-8. 画面遷移

### 正常時

```text
index.php
↓
process.php
↓
result.php?batch=一意の処理ID
```

### 異常時

```text
index.php に戻り、エラーメッセージを表示
```

---

# 5. SCR-02 加工結果確認画面

## 5-1. 画面目的

加工済み画像を一覧で確認し、各画像ごとにファイル名を手動変更したうえで、個別または一括でダウンロードできるようにする。

---

## 5-2. 画面URL

```text
http://localhost/floorplan-tool/result.php?batch={batch_id}
```

---

## 5-3. 画面構成

| No | 要素 | 種別 | 内容 |
|---|---|---|---|
| 1 | 画面タイトル | テキスト | 「加工結果確認」 |
| 2 | 処理概要 | テキスト | 処理枚数、出力形式、出力サイズ |
| 3 | 加工結果一覧 | カード/テーブル | 加工後画像を一覧表示 |
| 4 | ファイル名入力欄 | text input | 画像ごとに手動変更 |
| 5 | 個別ダウンロードボタン | button/link | 画像ごとにダウンロード |
| 6 | ZIP一括ダウンロードボタン | button | 全画像をZIPでダウンロード |
| 7 | 戻るボタン | link | アップロード画面へ戻る |
| 8 | エラー表示領域 | テキスト | エラー発生時に表示 |

---

## 5-4. 加工結果一覧の表示項目

| 項目 | 内容 |
|---|---|
| No | 連番 |
| プレビュー画像 | 500px × 500pxの加工済み画像を縮小表示 |
| 元ファイル名 | アップロード時のファイル名 |
| 画像サイズ | `500 × 500` |
| 出力形式 | gif / png / jpg |
| ファイル名入力欄 | 拡張子なしのファイル名を入力 |
| 個別ダウンロード | 設定されたファイル名でダウンロード |

---

## 5-5. プレビュー仕様

### 表示サイズ

実画像は500px × 500pxだが、一覧上では以下のサイズで表示する。

```css
.preview-image {
  width: 160px;
  height: 160px;
  object-fit: contain;
  border: 1px solid #ddd;
  background: #fff;
}
```

### クリック時

任意仕様として、クリック時にモーダルまたは別タブで500px × 500pxの実画像を確認できるようにしてもよい。

初期実装では、画像リンクを新規タブで開く仕様でよい。

---

## 5-6. ファイル名入力欄仕様

### 入力対象

拡張子を除いたファイル名のみ入力する。

### 例

| 入力値 | 出力形式 | 実際のダウンロード名 |
|---|---|---|
| `101` | png | `101.png` |
| `A-202_madori` | gif | `A-202_madori.gif` |
| `room_001` | jpg | `room_001.jpg` |

### 初期値

元ファイル名から拡張子を除いた値を初期表示する。

例：

```text
元ファイル名：sample_floorplan.png
初期値：sample_floorplan
```

### 入力可能文字

基本的には以下を許可する。

```text
半角英数字
日本語
ハイフン -
アンダースコア _
丸括弧 ()
全角文字
```

### 禁止文字

以下は除去またはアンダースコアに置換する。

```text
\ / : * ? " < > |
```

### 空欄時

空欄の場合は以下の形式で自動補完する。

```text
floorplan_001
floorplan_002
floorplan_003
```

### 同名時

同名が存在する場合は、自動で連番を付与する。

```text
101.png
101_2.png
101_3.png
```

---

## 5-7. 個別ダウンロード仕様

### 動作

各画像の「個別ダウンロード」ボタンを押すと、該当画像のみをダウンロードする。

### ダウンロード時のファイル名

画面上のファイル名入力欄の値を使う。

### 実装方式

JavaScriptで対象画像IDと入力ファイル名をPOST送信し、`download.php` でダウンロードレスポンスを返す。

### 送信項目

| 項目 | 内容 |
|---|---|
| batch_id | 処理単位ID |
| image_id | 画像ID |
| filename | ユーザー入力ファイル名 |
| csrf_token | CSRFトークン |

---

## 5-8. ZIP一括ダウンロード仕様

### 動作

「すべてZIPでダウンロード」ボタンを押すと、加工済み画像すべてをZIP化してダウンロードする。

### ZIP内ファイル名

各画像のファイル名入力欄の値を使用する。

### ZIPファイル名

```text
floorplan_images_YYYYMMDD_HHMMSS.zip
```

### 送信項目

| 項目 | 内容 |
|---|---|
| batch_id | 処理単位ID |
| filenames | image_id と filename の対応配列 |
| csrf_token | CSRFトークン |

---

## 5-9. 画面上の操作ボタン

| ボタン | 表示位置 | 動作 |
|---|---|---|
| 個別ダウンロード | 各画像カード内 | 該当画像を1枚ダウンロード |
| すべてZIPでダウンロード | 画面上部または下部 | 全画像をZIP化してダウンロード |
| 戻る | 画面上部または下部 | アップロード画面に戻る |

---

## 5-10. エラー表示

### 表示位置

画面上部にエラー領域を配置する。

### 表示例

```text
ファイル名に使用できない文字が含まれていたため、一部を置換しました。
ZIPファイルの作成に失敗しました。
対象画像が見つかりません。
```

---

# 6. 画面デザイン方針

## 6-1. 全体方針

- 業務用ツールとして、装飾よりも操作性を優先する。
- 1画面で処理結果とファイル名編集が完結する構成とする。
- プレビュー、元ファイル名、変更後ファイル名、ダウンロード操作を横並びまたはカード形式で表示する。

---

## 6-2. 推奨レイアウト

### アップロード画面

```text
[タイトル]

[説明文]

[画像ファイル選択]
[出力形式選択： ○ PNG  ○ JPG  ○ GIF]

[選択ファイル一覧]

[加工する]
```

### 加工結果確認画面

```text
[タイトル]
処理枚数：12枚 / 出力形式：PNG / サイズ：500×500

[すべてZIPでダウンロード]

------------------------------------------------
No.1
[プレビュー画像]
元ファイル名：sample01.png
ファイル名：[ sample01          ] .png
[個別ダウンロード]
------------------------------------------------
No.2
[プレビュー画像]
元ファイル名：sample02.jpg
ファイル名：[ 101_madori       ] .png
[個別ダウンロード]
------------------------------------------------
```

---

# 7. JavaScript仕様

## 7-1. アップロード画面

### 実装内容

- ファイル選択時に選択ファイル名一覧を表示する。
- ファイル数を表示する。
- 50枚を超えた場合は警告する。
- 非対応拡張子が含まれる場合は警告する。
- 未選択時は送信を止める。

---

## 7-2. 確認画面

### 実装内容

- ファイル名入力欄の禁止文字をリアルタイムで置換する。
- 個別ダウンロード時に該当ファイル名をPOST送信する。
- ZIP一括ダウンロード時に全ファイル名をまとめてPOST送信する。
- 空欄がある場合は送信前に自動補完または警告する。
- 同名がある場合は送信前に警告または自動調整する。

---

# 8. アクセシビリティ・操作性

- ボタン名は具体的にする。
- 画像には代替テキストを設定する。
- エラーは赤色だけでなくテキストでも明示する。
- ファイル選択後、選択数を表示する。
- ダウンロード前に加工結果を目視確認できるようにする。

---

# 9. 画面仕様上の前提

- 本ツールはローカルXAMPP環境での社内利用を前提とする。
- ログイン機能は初期実装では不要。
- データベースは初期実装では不要。
- 一時ファイルはローカルディレクトリに保存する。
- 必要に応じて後日、DB管理・履歴管理・Webサーバー公開に拡張できる構成とする。


---

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
