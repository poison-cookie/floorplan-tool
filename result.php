<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

$csrfToken = generateCsrfToken();
$batchId = (string) ($_GET['batch'] ?? '');
$metadata = isValidBatchId($batchId) ? loadMetadata($batchId) : null;
$items = is_array($metadata['items'] ?? null) ? $metadata['items'] : [];
$successCount = count(array_filter($items, static fn($item): bool => is_array($item) && ($item['status'] ?? '') === 'success'));
$errorCount = count($items) - $successCount;
$processingOptions = normalizeProcessingOptions(is_array($metadata['processing_options'] ?? null) ? $metadata['processing_options'] : []);
$cssVersion = (string) (@filemtime(__DIR__ . '/assets/css/style.css') ?: time());
$jsVersion = (string) (@filemtime(__DIR__ . '/assets/js/app.js') ?: time());
$errors = $_SESSION['flash_errors'] ?? [];
unset($_SESSION['flash_errors']);
$messages = $_SESSION['flash_messages'] ?? [];
unset($_SESSION['flash_messages']);
$isSavedBatch = $metadata !== null && (!empty($metadata['is_saved']) || (isValidBatchId($batchId) && is_file(getSavedMetadataPath($batchId))));
$savedBatchName = $isSavedBatch && $metadata !== null ? savedBatchDisplayName($metadata, $batchId) : '';
$zipFolderName = $metadata !== null ? sanitizeFilename((string) ($metadata['saved_name'] ?? '')) : '';
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>加工結果 | 間取り図画像加工ツール</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h($cssVersion) ?>">
</head>
<body>
    <main class="container">
        <header class="page-header page-header-compact">
            <p class="eyebrow">加工結果</p>
            <h1>加工結果</h1>
            <div class="actions">
                <a class="button button-secondary" href="index.php<?= isValidBatchId($batchId) ? '?batch=' . h(rawurlencode($batchId)) : '' ?>">アップロードへ戻る</a>
                <a class="button button-secondary" href="index.php">新しいバッチを作成</a>
            </div>
        </header>

        <?php if (!empty($errors)): ?>
            <section class="error-box" role="alert">
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

        <?php if ($metadata === null): ?>
            <section class="error-box" role="alert">
                <h2>処理データが見つかりません</h2>
                <p><?= h(getErrorMessage('E_BATCH_NOT_FOUND')) ?></p>
            </section>
        <?php else: ?>
            <section class="summary-bar" aria-label="処理概要">
                <div>
                    <span class="summary-label">処理枚数</span>
                    <strong><span data-success-count><?= h((string) $successCount) ?></span> / <span data-total-count><?= h((string) count($items)) ?></span> 枚</strong>
                </div>
                <div>
                    <span class="summary-label">出力</span>
                    <strong><?= h((string) $processingOptions['output_width']) ?> x <?= h((string) $processingOptions['output_height']) ?> <?= h(strtoupper((string) $metadata['output_format'])) ?></strong>
                </div>
                <div>
                    <span class="summary-label">方式</span>
                    <strong><?= h(resizeModeLabel((string) $processingOptions['resize_mode'])) ?></strong>
                </div>
                <div>
                    <span class="summary-label">背景</span>
                    <strong><?= !empty($processingOptions['background_transparent']) ? '透明' : h((string) $processingOptions['background_color']) ?></strong>
                </div>
            </section>

            <form class="save-batch-form" action="save_batch.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="batch_id" value="<?= h((string) $metadata['batch_id']) ?>">
                <div class="save-batch-fields">
                    <label for="saved-batch-name">保存名</label>
                    <input
                        id="saved-batch-name"
                        class="filename-input"
                        type="text"
                        name="saved_name"
                        value="<?= h($savedBatchName) ?>"
                        placeholder="例: 青山マンション 3階"
                        autocomplete="off"
                    >
                </div>
                <button class="button button-primary" type="submit"><?= $isSavedBatch ? '保存名を更新' : 'このバッチを保存' ?></button>
                <p class="helper-text save-batch-note"><?= $isSavedBatch ? '保存済みです。編集内容は保存データにも反映されます。' : 'あとで再編集できるように、この加工結果を保存します。' ?></p>
            </form>

            <?php if ($errorCount > 0): ?>
                <section class="warning-box persistent" role="status" data-error-warning>
                    <span data-error-count><?= h((string) $errorCount) ?></span>件の画像を処理できませんでした。各カードの内容を確認してください。
                </section>
            <?php endif; ?>

            <?php if ($successCount > 0): ?>
                <form id="zip-form" class="zip-form" action="zip_download.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="batch_id" value="<?= h((string) $metadata['batch_id']) ?>">
                    <div class="zip-options">
                        <label for="zip-folder-name">ZIP内フォルダ名</label>
                        <input
                            id="zip-folder-name"
                            class="filename-input"
                            type="text"
                            name="folder_name"
                            value="<?= h($zipFolderName) ?>"
                            placeholder="例: 青山マンション"
                            autocomplete="off"
                        >
                    </div>
                    <div id="zip-filename-fields"></div>
                    <button class="button button-primary" type="submit">すべてZIPでダウンロード</button>
                </form>
            <?php endif; ?>

            <section
                class="result-grid"
                aria-label="加工結果一覧"
                data-batch-id="<?= h((string) $metadata['batch_id']) ?>"
                data-csrf-token="<?= h($csrfToken) ?>"
            >
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }

                    $isSuccess = ($item['status'] ?? '') === 'success';
                    $cardNumber = $index + 1;
                    $imageId = (string) ($item['image_id'] ?? '');
                    $defaultFilename = (string) ($item['default_filename'] ?? sprintf('floorplan_%03d', $cardNumber));
                    $processedPath = (string) ($item['processed_path'] ?? '');
                    $previewPath = versionedPublicPath($processedPath, (string) ($item['processed_version'] ?? ''));
                    ?>
                    <article
                        class="result-card"
                        data-image-id="<?= h($imageId) ?>"
                        data-card-index="<?= h((string) $cardNumber) ?>"
                        data-offset-x="<?= h((string) ((int) ($item['position_offset_x'] ?? 0))) ?>"
                        data-offset-y="<?= h((string) ((int) ($item['position_offset_y'] ?? 0))) ?>"
                        data-scale-percent="<?= h((string) ((int) ($item['transform_scale_percent'] ?? 100))) ?>"
                        data-rotation-degrees="<?= h((string) ((int) ($item['rotation_degrees'] ?? 0))) ?>"
                        data-flip-horizontal="<?= !empty($item['flip_horizontal']) ? '1' : '0' ?>"
                        data-flip-vertical="<?= !empty($item['flip_vertical']) ? '1' : '0' ?>"
                    >
                        <div class="card-topline">
                            <span class="item-number">No.<?= h((string) $cardNumber) ?></span>
                            <?php if ($isSuccess): ?>
                                <span class="status-badge status-success">加工済み</span>
                            <?php else: ?>
                                <span class="status-badge status-error">エラー</span>
                            <?php endif; ?>
                            <button class="card-delete-button" type="button" data-delete-image>削除</button>
                        </div>

                        <?php if ($isSuccess): ?>
                            <a class="preview-link" href="<?= h($previewPath) ?>" target="_blank" rel="noopener">
                                <img class="preview-image" src="<?= h($previewPath) ?>" alt="<?= h('加工済み画像 ' . (string) $item['original_name']) ?>">
                            </a>
                        <?php else: ?>
                            <div class="preview-placeholder" aria-hidden="true">プレビューを表示できません</div>
                        <?php endif; ?>

                        <dl class="meta-list">
                            <div>
                                <dt>元ファイル</dt>
                                <dd><?= h((string) $item['original_name']) ?></dd>
                            </div>
                            <div>
                                <dt>出力サイズ</dt>
                                <dd><?= h((string) $processingOptions['output_width']) ?> x <?= h((string) $processingOptions['output_height']) ?></dd>
                            </div>
                            <div>
                                <dt>リサイズ方式</dt>
                                <dd><?= h(resizeModeLabel((string) $processingOptions['resize_mode'])) ?></dd>
                            </div>
                            <?php if ($isSuccess): ?>
                                <div>
                                    <dt>描画サイズ</dt>
                                    <dd><?= h((string) $item['resized_width']) ?> x <?= h((string) $item['resized_height']) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php if ($isSuccess): ?>
                            <section class="position-controls" aria-labelledby="position-title-<?= h($imageId) ?>">
                                <h3 id="position-title-<?= h($imageId) ?>">位置調整</h3>
                                <div class="position-pad" aria-label="位置調整">
                                    <button class="position-button position-up" type="button" data-position-action="up" aria-label="上へ移動">上</button>
                                    <button class="position-button position-left" type="button" data-position-action="left" aria-label="左へ移動">左</button>
                                    <button class="position-button position-reset" type="button" data-position-action="reset">中央</button>
                                    <button class="position-button position-right" type="button" data-position-action="right" aria-label="右へ移動">右</button>
                                    <button class="position-button position-down" type="button" data-position-action="down" aria-label="下へ移動">下</button>
                                </div>
                                <p class="position-status" aria-live="polite">
                                    X: <span data-offset-x><?= h((string) ((int) ($item['position_offset_x'] ?? 0))) ?></span>px /
                                    Y: <span data-offset-y><?= h((string) ((int) ($item['position_offset_y'] ?? 0))) ?></span>px
                                </p>
                            </section>

                            <section class="transform-controls" aria-labelledby="transform-title-<?= h($imageId) ?>">
                                <h3 id="transform-title-<?= h($imageId) ?>">拡大・回転・反転</h3>
                                <div class="transform-button-grid">
                                    <button class="position-button" type="button" data-position-action="zoom_in">拡大</button>
                                    <button class="position-button" type="button" data-position-action="zoom_out">縮小</button>
                                    <button class="position-button" type="button" data-position-action="rotate_left">左90度</button>
                                    <button class="position-button" type="button" data-position-action="rotate_right">右90度</button>
                                    <button class="position-button" type="button" data-position-action="flip_horizontal">左右反転</button>
                                    <button class="position-button" type="button" data-position-action="flip_vertical">上下反転</button>
                                </div>
                                <p class="transform-status" aria-live="polite">
                                    拡大率: <span data-scale-percent><?= h((string) ((int) ($item['transform_scale_percent'] ?? 100))) ?></span>% /
                                    回転: <span data-rotation-degrees><?= h((string) ((int) ($item['rotation_degrees'] ?? 0))) ?></span>度 /
                                    左右反転: <span data-flip-horizontal><?= !empty($item['flip_horizontal']) ? 'ON' : 'OFF' ?></span> /
                                    上下反転: <span data-flip-vertical><?= !empty($item['flip_vertical']) ? 'ON' : 'OFF' ?></span>
                                </p>
                            </section>

                            <form class="download-form" action="download.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="batch_id" value="<?= h((string) $metadata['batch_id']) ?>">
                                <input type="hidden" name="image_id" value="<?= h($imageId) ?>">

                                <label class="filename-label" for="filename-<?= h($imageId) ?>">ファイル名</label>
                                <div class="filename-row">
                                    <input id="filename-<?= h($imageId) ?>" class="filename-input" type="text" name="filename" value="<?= h($defaultFilename) ?>" data-filename-autosave autocomplete="off">
                                    <span class="extension-label">.<?= h((string) $metadata['output_format']) ?></span>
                                </div>
                                <p class="filename-save-status" data-filename-save-status aria-live="polite"></p>

                                <button class="button button-secondary" type="submit">ダウンロード</button>
                            </form>
                        <?php else: ?>
                            <p class="item-error"><?= h((string) ($item['error_message'] ?? getErrorMessage('E_PROCESS_FAILED'))) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
            <p class="empty-results" data-empty-results <?= count($items) > 0 ? 'hidden' : '' ?>>表示する画像がありません。</p>
        <?php endif; ?>
    </main>
    <script src="assets/js/app.js?v=<?= h($jsVersion) ?>"></script>
</body>
</html>
