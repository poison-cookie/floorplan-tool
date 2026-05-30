<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

$directoryError = null;
try {
    ensureDirectories();
} catch (RuntimeException $exception) {
    $directoryError = $exception->getMessage();
}

$csrfToken = generateCsrfToken();
$errors = $_SESSION['flash_errors'] ?? [];
unset($_SESSION['flash_errors']);
$messages = $_SESSION['flash_messages'] ?? [];
unset($_SESSION['flash_messages']);

$appendBatchId = (string) ($_GET['batch'] ?? '');
$appendMetadata = isValidBatchId($appendBatchId) ? loadMetadata($appendBatchId) : null;
if ($appendMetadata === null) {
    $appendBatchId = '';
}
$isAppendMode = $appendBatchId !== '';
$selectedOutputFormat = normalizeOutputFormat($appendMetadata['output_format'] ?? DEFAULT_OUTPUT_FORMAT);
$cssVersion = (string) (@filemtime(__DIR__ . '/assets/css/style.css') ?: time());
$jsVersion = (string) (@filemtime(__DIR__ . '/assets/js/app.js') ?: time());
$savedBatches = [];

if ($directoryError !== null) {
    $errors[] = $directoryError;
} else {
    try {
        $savedBatches = listSavedBatches();
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>間取り図画像自動加工ツール</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h($cssVersion) ?>">
</head>
<body>
    <main class="container">
        <header class="page-header">
            <p class="eyebrow">Floorplan Image Tool</p>
            <h1>間取り図画像自動加工ツール</h1>
            <p class="lead">
                アップロードした間取り図画像を、縦横比を維持したまま長辺500pxにリサイズし、500px × 500pxの画像として出力します。
                縦長・横長画像の場合は、短辺側に白い余白を付けて中央配置します。
            </p>
            <?php if ($isAppendMode): ?>
                <div class="actions">
                    <a class="button button-secondary" href="result.php?batch=<?= h(rawurlencode($appendBatchId)) ?>">加工結果確認へ戻る</a>
                    <a class="button button-secondary" href="index.php">新しいバッチに切り替える</a>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($errors)): ?>
            <section class="error-box" role="alert" aria-labelledby="error-title">
                <h2 id="error-title">処理できませんでした</h2>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <section class="success-box" role="status">
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="saved-batches" aria-labelledby="saved-batches-title">
            <div class="section-heading-row">
                <h2 id="saved-batches-title">保存済みバッチ</h2>
            </div>

            <?php if (empty($savedBatches)): ?>
                <p class="helper-text">保存済みの加工結果はまだありません。</p>
            <?php else: ?>
                <ul class="saved-batch-list">
                    <?php foreach ($savedBatches as $savedBatch): ?>
                        <?php $isSelectedSavedBatch = $isAppendMode && (string) $savedBatch['batch_id'] === $appendBatchId; ?>
                        <li class="saved-batch-item">
                            <div class="saved-batch-main">
                                <div class="saved-batch-title">
                                    <strong><?= h((string) $savedBatch['saved_name']) ?></strong>
                                    <?php if ($isSelectedSavedBatch): ?>
                                        <span class="current-batch-badge">選択中</span>
                                    <?php endif; ?>
                                </div>
                                <span>
                                    <?= h(strtoupper((string) $savedBatch['output_format'])) ?> /
                                    <?= h((string) $savedBatch['success_count']) ?> / <?= h((string) $savedBatch['total_count']) ?>枚 /
                                    更新: <?= h((string) $savedBatch['updated_at']) ?>
                                </span>
                                <code><?= h((string) $savedBatch['batch_id']) ?></code>
                            </div>
                            <div class="saved-batch-actions">
                                <a class="button button-secondary button-small" href="result.php?batch=<?= h(rawurlencode((string) $savedBatch['batch_id'])) ?>">再編集</a>
                                <form class="inline-form" action="delete_saved_batch.php" method="post" onsubmit="return confirm('保存済みバッチと画像ファイルを削除します。よろしいですか？');">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="batch_id" value="<?= h((string) $savedBatch['batch_id']) ?>">
                                    <button class="button button-danger button-small" type="submit">削除</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="upload-box" aria-labelledby="upload-title">
            <h2 id="upload-title">画像をアップロード</h2>
            <form id="upload-form" action="process.php" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="append_batch_id" value="<?= h($appendBatchId) ?>">
                <?php if ($isAppendMode): ?>
                    <input type="hidden" name="output_format" value="<?= h($selectedOutputFormat) ?>">
                <?php endif; ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= h((string) MAX_FILE_SIZE) ?>">

                <div class="form-group">
                    <label for="images">画像ファイル</label>
                    <input
                        id="images"
                        type="file"
                        name="images[]"
                        accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif"
                        multiple
                    >
                    <p id="file-count" class="helper-text">ファイルはまだ選択されていません。</p>
                </div>

                <div id="paste-zone" class="paste-zone" tabindex="0" role="button" aria-describedby="paste-status">
                    <strong>スクリーンショットを貼り付け</strong>
                    <span>範囲スクリーンショット後、この画面で Ctrl + V</span>
                </div>
                <p id="paste-status" class="helper-text" aria-live="polite"></p>

                <fieldset class="form-group">
                    <legend>出力形式</legend>
                    <?php if ($isAppendMode): ?>
                        <p class="fixed-format-note">
                            この加工結果に追加中です。出力形式は <?= h(strtoupper($selectedOutputFormat)) ?> に固定されます。
                        </p>
                    <?php endif; ?>
                    <div class="radio-row<?= $isAppendMode ? ' radio-row-disabled' : '' ?>" role="radiogroup" aria-label="出力形式">
                        <label>
                            <input type="radio" name="output_format" value="png" <?= $selectedOutputFormat === 'png' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>>
                            PNG
                        </label>
                        <label>
                            <input type="radio" name="output_format" value="jpg" <?= $selectedOutputFormat === 'jpg' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>>
                            JPG
                        </label>
                        <label>
                            <input type="radio" name="output_format" value="gif" <?= $selectedOutputFormat === 'gif' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>>
                            GIF
                        </label>
                    </div>
                </fieldset>

                <section class="selected-files" aria-labelledby="selected-files-title">
                    <div class="section-heading-row">
                        <h3 id="selected-files-title">選択ファイル一覧</h3>
                        <?php if ($isAppendMode): ?>
                            <a class="button button-secondary button-small" href="result.php?batch=<?= h(rawurlencode($appendBatchId)) ?>">加工結果確認へ戻る</a>
                        <?php endif; ?>
                    </div>
                    <ul id="file-list" class="file-list" aria-live="polite"></ul>
                </section>

                <div id="client-warning" class="warning-box" role="alert" hidden></div>

                <div class="actions">
                    <button class="button button-primary" type="submit">加工する</button>
                    <?php if ($isAppendMode): ?>
                        <a class="button button-secondary" href="index.php">新しいバッチに切り替える</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <div id="crop-modal" class="crop-modal" role="dialog" aria-modal="true" aria-labelledby="crop-modal-title" hidden>
            <div class="crop-dialog">
                <div class="crop-header">
                    <h2 id="crop-modal-title">削除範囲を選択</h2>
                    <button id="crop-close" class="button button-secondary" type="button">閉じる</button>
                </div>
                <div class="crop-canvas-wrap">
                    <canvas id="crop-canvas"></canvas>
                </div>
                <div class="crop-actions">
                    <button id="crop-reset" class="button button-secondary" type="button">選択を解除</button>
                    <button id="crop-cancel" class="button button-secondary" type="button">キャンセル</button>
                    <button id="crop-apply" class="button button-primary" type="button">削除を適用</button>
                </div>
                <p id="crop-status" class="helper-text" aria-live="polite"></p>
            </div>
        </div>

        <aside class="notice" aria-labelledby="notice-title">
            <h2 id="notice-title">注意事項</h2>
            <ul>
                <li>対応形式: jpg / jpeg / png / gif</li>
                <li>一度に最大<?= h((string) MAX_UPLOAD_FILES) ?>枚まで処理できます。</li>
                <li>1ファイルあたり<?= h((string) (MAX_FILE_SIZE / 1024 / 1024)) ?>MBまでアップロードできます。</li>
                <li>範囲スクリーンショットは保存せず、そのまま貼り付けできます。</li>
                <li>出力形式は gif / png / jpg から選択できます。</li>
            </ul>
        </aside>
    </main>
    <script src="assets/js/app.js?v=<?= h($jsVersion) ?>"></script>
</body>
</html>
