<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

$cssVersion = (string) (@filemtime(__DIR__ . '/assets/css/style.css') ?: time());

function manualAsset(string $path): string
{
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($absolutePath)) {
        return $path;
    }

    return $path . '?v=' . (string) filemtime($absolutePath);
}

$manualSections = [
    [
        'id' => 'overview',
        'number' => '01',
        'category' => '全体像',
        'title' => 'TOP画面でできることを確認する',
        'image' => 'assets/manual/top-overview.png',
        'alt' => 'TOP画面のヘッダー、保存済みバッチ、画像アップロード欄',
        'caption' => 'TOP画面では、新規加工、保存済みバッチの再編集、マニュアル確認を開始できます。',
        'steps' => [
            '画面上部の説明文で、このツールが「画像を加工して個別またはZIPでダウンロードする」ためのものだと確認します。',
            '「保存済みバッチ」には、あとから再編集できる加工済みデータが表示されます。',
            '新しく加工する場合は、下の「画像をアップロード」から作業を始めます。',
        ],
    ],
    [
        'id' => 'upload',
        'number' => '02',
        'category' => '新規アップロード',
        'title' => '画像を選択して取り込む',
        'image' => 'assets/manual/upload-settings.png',
        'alt' => '画像ファイルの選択欄と貼り付け欄',
        'caption' => 'ファイル選択、複数選択、スクリーンショット貼り付けに対応しています。',
        'steps' => [
            '「画像ファイル」で jpg / jpeg / png / gif の画像を選択します。複数枚をまとめて選択できます。',
            'スクリーンショットを使う場合は、画像をコピーした状態で貼り付け欄を選び、Ctrl + V で追加します。',
            '選択済みファイルの一覧にサムネイルが表示されたら、加工前の確認ができます。',
        ],
    ],
    [
        'id' => 'settings',
        'number' => '03',
        'category' => '加工設定',
        'title' => '出力形式、サイズ、背景を決める',
        'image' => 'assets/manual/processing-settings.png',
        'alt' => '出力形式と加工設定の入力欄',
        'caption' => 'プリセットを選ぶと、サイズ・リサイズ方法・背景がまとめて設定されます。',
        'steps' => [
            '出力形式は GIF / JPG / PNG から選択します。透明背景を使う場合はPNGを選びます。',
            '「プリセット」から用途に近い設定を選びます。必要に応じて幅、高さ、リサイズ方式を変更します。',
            '背景色を指定する場合はカラーピッカーで色を選びます。PNGのみ透明背景を保持できます。',
        ],
    ],
    [
        'id' => 'file-edit',
        'number' => '04',
        'category' => '加工前の調整',
        'title' => '不要な画像や範囲を整理する',
        'image' => 'assets/manual/file-editing.png',
        'alt' => '選択済みファイルのプレビュー、ファイル名欄、削除ボタン',
        'caption' => '加工前にファイル名を整え、不要な画像や範囲を整理できます。',
        'steps' => [
            '選択済みファイルの一覧で、取り込んだ画像の内容と順番を確認します。',
            'ファイル名欄には、ダウンロード時に使いたい名前を入力します。',
            '不要な画像は削除できます。画像の一部を除外したい場合は、範囲削除の操作で加工前に整えます。',
        ],
    ],
    [
        'id' => 'result',
        'number' => '05',
        'category' => '加工結果',
        'title' => '加工結果を確認する',
        'image' => 'assets/manual/result-overview.png',
        'alt' => '加工結果画面の概要、処理枚数、出力サイズ、保存欄',
        'caption' => '加工後は、処理枚数、出力サイズ、各画像のプレビューを確認します。',
        'steps' => [
            '「加工する」を押すと加工結果画面に移動します。',
            '画面上部で、成功枚数、出力サイズ、リサイズ方式、背景を確認します。',
            'カードごとにプレビューを見て、意図した仕上がりになっているか確認します。',
        ],
    ],
    [
        'id' => 'adjust',
        'number' => '06',
        'category' => '結果の微調整',
        'title' => '位置、拡大縮小、回転、反転を調整する',
        'image' => 'assets/manual/result-editing.png',
        'alt' => '加工結果カード内の位置調整ボタンと変形ボタン',
        'caption' => '画像ごとに、中央寄せ、移動、拡大縮小、90度回転、反転を調整できます。',
        'steps' => [
            '位置調整の上下左右ボタンで、画像の表示位置を少しずつ移動します。',
            '中央ボタンで位置をリセットできます。',
            '拡大、縮小、左右反転、上下反転、左右90度回転で、画像ごとに見え方を整えます。',
        ],
    ],
    [
        'id' => 'download',
        'number' => '07',
        'category' => '保存と出力',
        'title' => '保存名を決めてダウンロードする',
        'image' => 'assets/manual/download-save.png',
        'alt' => '保存名、ZIPフォルダ名、ダウンロード欄',
        'caption' => '個別ダウンロードとZIP一括ダウンロードの両方を使えます。',
        'steps' => [
            '各カードのファイル名欄で、画像ごとの保存名を調整します。',
            '1枚だけ取得する場合は、そのカードの「ダウンロード」を押します。',
            'まとめて取得する場合は、ZIP内フォルダ名を入力して「すべてZIPでダウンロード」を押します。',
        ],
    ],
    [
        'id' => 'saved-batch',
        'number' => '08',
        'category' => '再編集',
        'title' => '保存済みバッチをあとから編集する',
        'image' => 'assets/manual/saved-batches.png',
        'alt' => '保存済みバッチ一覧と編集ボタン',
        'caption' => 'よく使う加工結果は保存して、あとから編集や追加アップロードができます。',
        'steps' => [
            '加工結果画面の保存名を入力し、「このバッチを保存」を押します。',
            'TOP画面の「保存済みバッチ」に保存名、形式、枚数、更新日時が表示されます。',
            '「編集」から再度結果画面を開き、既存画像の微調整や追加アップロードを行います。',
        ],
    ],
];

$limits = [
    '対応形式' => 'jpg / jpeg / png / gif',
    '一度に処理できる枚数' => (string) MAX_UPLOAD_FILES . '枚まで',
    '1ファイルの上限' => (string) (MAX_FILE_SIZE / 1024 / 1024) . 'MBまで',
    '出力サイズの範囲' => (string) MIN_OUTPUT_DIMENSION . 'px から ' . (string) MAX_OUTPUT_DIMENSION . 'px',
    '透明背景' => 'PNG出力時のみ保持',
];
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>操作マニュアル | 間取り図画像加工ツール</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h($cssVersion) ?>">
</head>
<body>
    <main class="container manual-container">
        <header class="page-header manual-header">
            <div>
                <p class="eyebrow">操作マニュアル</p>
                <h1>間取り図画像加工ツール 操作マニュアル</h1>
                <p class="lead">
                    初めて使う人でも作業できるように、画像の取り込みから加工、調整、保存、ダウンロードまでを順番に説明します。
                </p>
            </div>
            <div class="actions manual-header-actions">
                <a class="button button-secondary" href="index.php">TOPへ戻る</a>
            </div>
        </header>

        <nav class="manual-toc" aria-labelledby="manual-toc-title">
            <h2 id="manual-toc-title">目次</h2>
            <ol class="manual-toc-list">
                <?php foreach ($manualSections as $section): ?>
                    <li>
                        <a href="#<?= h($section['id']) ?>">
                            <span><?= h($section['number']) ?></span>
                            <?= h($section['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <?php foreach ($manualSections as $section): ?>
            <section id="<?= h($section['id']) ?>" class="manual-section" aria-labelledby="manual-title-<?= h($section['id']) ?>">
                <div class="manual-section-header">
                    <span class="manual-number"><?= h($section['number']) ?></span>
                    <div>
                        <p class="manual-category"><?= h($section['category']) ?></p>
                        <h2 id="manual-title-<?= h($section['id']) ?>"><?= h($section['title']) ?></h2>
                    </div>
                </div>
                <div class="manual-section-body">
                    <ol class="manual-step-list">
                        <?php foreach ($section['steps'] as $step): ?>
                            <li><?= h($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <figure class="manual-photo">
                        <img src="<?= h(manualAsset($section['image'])) ?>" alt="<?= h($section['alt']) ?>" loading="lazy">
                        <figcaption><?= h($section['caption']) ?></figcaption>
                    </figure>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="manual-section manual-reference" aria-labelledby="manual-limits-title">
            <div class="manual-section-header">
                <span class="manual-number">09</span>
                <div>
                    <p class="manual-category">注意事項</p>
                    <h2 id="manual-limits-title">対応形式と上限を確認する</h2>
                </div>
            </div>
            <dl class="manual-limit-list">
                <?php foreach ($limits as $label => $value): ?>
                    <div>
                        <dt><?= h($label) ?></dt>
                        <dd><?= h($value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    </main>
</body>
</html>
