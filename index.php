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
$selectedProcessingOptions = normalizeProcessingOptions(is_array($appendMetadata['processing_options'] ?? null) ? $appendMetadata['processing_options'] : []);
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
    <title>間取り図画像加工ツール</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h($cssVersion) ?>">
</head>
<body>
    <main class="container">
        <header class="page-header">
            <p class="eyebrow">画像加工ツール</p>
            <h1>間取り図画像加工ツール</h1>
            <p class="lead">
                画像をアップロードし、プリセットまたは任意の加工設定を適用して、個別ファイルまたはZIPでダウンロードできます。
            </p>
            <div class="actions">
                <a class="button button-secondary" href="manual.php">マニュアルを見る</a>
                <?php if ($isAppendMode): ?>
                    <a class="button button-secondary" href="result.php?batch=<?= h(rawurlencode($appendBatchId)) ?>">加工結果へ戻る</a>
                    <a class="button button-secondary" href="index.php">新しいバッチを作成</a>
                <?php endif; ?>
            </div>
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
                <p class="helper-text">保存済みバッチはまだありません。</p>
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
                                    <?= h((string) $savedBatch['success_count']) ?> / <?= h((string) $savedBatch['total_count']) ?> 枚 /
                                    更新: <?= h((string) $savedBatch['updated_at']) ?>
                                </span>
                                <code><?= h((string) $savedBatch['batch_id']) ?></code>
                            </div>
                            <div class="saved-batch-actions">
                                <a class="button button-secondary button-small" href="result.php?batch=<?= h(rawurlencode((string) $savedBatch['batch_id'])) ?>">編集</a>
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
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= h((string) MAX_FILE_SIZE) ?>">

                <?php if ($isAppendMode): ?>
                    <input type="hidden" name="output_format" value="<?= h($selectedOutputFormat) ?>">
                    <input type="hidden" name="processing_preset" value="<?= h((string) $selectedProcessingOptions['preset']) ?>">
                    <input type="hidden" name="output_width" value="<?= h((string) $selectedProcessingOptions['output_width']) ?>">
                    <input type="hidden" name="output_height" value="<?= h((string) $selectedProcessingOptions['output_height']) ?>">
                    <input type="hidden" name="resize_mode" value="<?= h((string) $selectedProcessingOptions['resize_mode']) ?>">
                    <input type="hidden" name="background_color" value="<?= h((string) $selectedProcessingOptions['background_color']) ?>">
                    <?php if (!empty($selectedProcessingOptions['background_transparent'])): ?>
                        <input type="hidden" name="background_transparent" value="1">
                    <?php endif; ?>
                <?php endif; ?>

                <div class="form-group">
                    <label for="images">画像ファイル</label>
                    <input id="images" type="file" name="images[]" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" multiple>
                    <p id="file-count" class="helper-text">ファイルはまだ選択されていません。</p>
                </div>

                <div id="paste-zone" class="paste-zone" tabindex="0" role="button" aria-describedby="paste-status">
                    <strong>スクリーンショットを貼り付け</strong>
                    <span>スクリーンショット取得後、この画面で Ctrl + V を押してください。</span>
                </div>
                <p id="paste-status" class="helper-text" aria-live="polite"></p>

                <fieldset class="form-group">
                    <legend>出力形式</legend>
                    <?php if ($isAppendMode): ?>
                        <p class="fixed-format-note">この保存済みバッチの出力形式は <?= h(strtoupper($selectedOutputFormat)) ?> に固定されています。</p>
                    <?php endif; ?>
                    <div class="radio-row<?= $isAppendMode ? ' radio-row-disabled' : '' ?>" role="radiogroup" aria-label="出力形式">
                        <label><input type="radio" name="output_format" value="gif" <?= $selectedOutputFormat === 'gif' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>> GIF</label>
                        <label><input type="radio" name="output_format" value="jpg" <?= $selectedOutputFormat === 'jpg' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>> JPG</label>
                        <label><input type="radio" name="output_format" value="png" <?= $selectedOutputFormat === 'png' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>> PNG</label>
                        <label><input type="radio" name="output_format" value="webp" <?= $selectedOutputFormat === 'webp' ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>> WEBP</label>
                    </div>
                </fieldset>

                <fieldset class="form-group">
                    <legend>加工設定</legend>
                    <?php if ($isAppendMode): ?>
                        <p class="fixed-format-note">
                            このバッチの固定設定:
                            <?= h((string) $selectedProcessingOptions['output_width']) ?> x <?= h((string) $selectedProcessingOptions['output_height']) ?>,
                            <?= h(resizeModeLabel((string) $selectedProcessingOptions['resize_mode'])) ?>,
                            <?= !empty($selectedProcessingOptions['background_transparent']) ? '透明背景' : h((string) $selectedProcessingOptions['background_color']) ?>。
                        </p>
                    <?php endif; ?>
                    <div class="settings-grid-inner<?= $isAppendMode ? ' settings-disabled' : '' ?>">
                        <label>
                            プリセット
                            <select name="processing_preset" data-preset-select <?= $isAppendMode ? 'disabled' : '' ?>>
                                <?php foreach (PROCESSING_PRESETS as $presetKey => $preset): ?>
                                    <option
                                        value="<?= h((string) $presetKey) ?>"
                                        data-width="<?= h((string) $preset['output_width']) ?>"
                                        data-height="<?= h((string) $preset['output_height']) ?>"
                                        data-mode="<?= h((string) $preset['resize_mode']) ?>"
                                        data-bg="<?= h((string) $preset['background_color']) ?>"
                                        data-transparent="<?= !empty($preset['background_transparent']) ? '1' : '0' ?>"
                                        data-format="<?= h((string) $preset['output_format']) ?>"
                                        <?= (string) $selectedProcessingOptions['preset'] === (string) $presetKey ? 'selected' : '' ?>
                                    ><?= h((string) $preset['label']) ?></option>
                                <?php endforeach; ?>
                                <option value="custom" <?= (string) $selectedProcessingOptions['preset'] === 'custom' ? 'selected' : '' ?>>カスタム</option>
                            </select>
                        </label>
                        <label>
                            幅
                            <input type="number" name="output_width" min="<?= h((string) MIN_OUTPUT_DIMENSION) ?>" max="<?= h((string) MAX_OUTPUT_DIMENSION) ?>" value="<?= h((string) $selectedProcessingOptions['output_width']) ?>" <?= $isAppendMode ? 'disabled' : '' ?>>
                        </label>
                        <label>
                            高さ
                            <input type="number" name="output_height" min="<?= h((string) MIN_OUTPUT_DIMENSION) ?>" max="<?= h((string) MAX_OUTPUT_DIMENSION) ?>" value="<?= h((string) $selectedProcessingOptions['output_height']) ?>" <?= $isAppendMode ? 'disabled' : '' ?>>
                        </label>
                        <label>
                            リサイズ方式
                            <select name="resize_mode" <?= $isAppendMode ? 'disabled' : '' ?>>
                                <?php foreach (ALLOWED_RESIZE_MODES as $mode): ?>
                                    <option value="<?= h($mode) ?>" <?= (string) $selectedProcessingOptions['resize_mode'] === $mode ? 'selected' : '' ?>><?= h(resizeModeLabel($mode)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            背景色
                            <input type="color" name="background_color" value="<?= h((string) $selectedProcessingOptions['background_color']) ?>" <?= $isAppendMode ? 'disabled' : '' ?>>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="background_transparent" value="1" <?= !empty($selectedProcessingOptions['background_transparent']) ? 'checked' : '' ?> <?= $isAppendMode ? 'disabled' : '' ?>>
                            PNG出力で透明背景にする
                        </label>
                    </div>
                </fieldset>

                <section class="selected-files" aria-labelledby="selected-files-title">
                    <div class="section-heading-row">
                        <h3 id="selected-files-title">選択ファイル</h3>
                        <?php if ($isAppendMode): ?>
                            <a class="button button-secondary button-small" href="result.php?batch=<?= h(rawurlencode($appendBatchId)) ?>">加工結果へ戻る</a>
                        <?php endif; ?>
                    </div>
                    <ul id="file-list" class="file-list" aria-live="polite"></ul>
                </section>

                <div id="client-warning" class="warning-box" role="alert" hidden></div>

                <div class="actions">
                    <button class="button button-primary" type="submit">加工する</button>
                    <?php if ($isAppendMode): ?>
                        <a class="button button-secondary" href="index.php">新しいバッチを作成</a>
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
                    <button id="crop-reset" class="button button-secondary" type="button">選択解除</button>
                    <button id="crop-cancel" class="button button-secondary" type="button">キャンセル</button>
                    <button id="crop-apply" class="button button-primary" type="button">削除を適用</button>
                </div>
                <p id="crop-status" class="helper-text" aria-live="polite"></p>
            </div>
        </div>

        <aside class="notice" aria-labelledby="notice-title">
            <h2 id="notice-title">注意事項</h2>
            <ul>
                <li>対応入力形式: jpg / jpeg / png / gif</li>
                <li>一度に最大<?= h((string) MAX_UPLOAD_FILES) ?>枚まで処理できます。</li>
                <li>1ファイルあたり最大<?= h((string) (MAX_FILE_SIZE / 1024 / 1024)) ?>MBです。</li>
                <li>出力サイズは <?= h((string) MIN_OUTPUT_DIMENSION) ?> から <?= h((string) MAX_OUTPUT_DIMENSION) ?> px の範囲で指定できます。</li>
                <li>透明背景はPNG出力時のみ保持されます。</li>
            </ul>
        </aside>
    </main>
    <script src="assets/js/app.js?v=<?= h($jsVersion) ?>"></script>
</body>
</html>
