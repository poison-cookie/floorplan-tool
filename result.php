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
$cssVersion = (string) (@filemtime(__DIR__ . '/assets/css/style.css') ?: time());
$jsVersion = (string) (@filemtime(__DIR__ . '/assets/js/app.js') ?: time());
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>加工結果確認 | 間取り図画像自動加工ツール</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h($cssVersion) ?>">
</head>
<body>
    <main class="container">
        <header class="page-header page-header-compact">
            <p class="eyebrow">Processing Result</p>
            <h1>加工結果確認</h1>
            <div class="actions">
                <a class="button button-secondary" href="index.php<?= isValidBatchId($batchId) ? '?batch=' . h(rawurlencode($batchId)) : '' ?>">アップロード画面へ戻る</a>
            </div>
        </header>

        <?php if ($metadata === null): ?>
            <section class="error-box" role="alert">
                <h2>処理データが見つかりません</h2>
                <p><?= h(getErrorMessage('E_BATCH_NOT_FOUND')) ?></p>
            </section>
        <?php else: ?>
            <section class="summary-bar" aria-label="処理概要">
                <div>
                    <span class="summary-label">処理枚数</span>
                    <strong><span data-success-count><?= h((string) $successCount) ?></span> / <span data-total-count><?= h((string) count($items)) ?></span>枚</strong>
                </div>
                <div>
                    <span class="summary-label">出力形式</span>
                    <strong><?= h(strtoupper((string) $metadata['output_format'])) ?></strong>
                </div>
                <div>
                    <span class="summary-label">出力サイズ</span>
                    <strong><?= h((string) $metadata['output_size']) ?> × <?= h((string) $metadata['output_size']) ?></strong>
                </div>
                <div>
                    <span class="summary-label">処理ID</span>
                    <strong><?= h((string) $metadata['batch_id']) ?></strong>
                </div>
            </section>

            <?php if ($errorCount > 0): ?>
                <section class="warning-box persistent" role="status" data-error-warning>
                    <span data-error-count><?= h((string) $errorCount) ?></span>件の画像を処理できませんでした。カード内のエラー内容を確認してください。
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
                            placeholder="例: ○○マンション"
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
                    ?>
                    <article
                        class="result-card"
                        data-image-id="<?= h($imageId) ?>"
                        data-card-index="<?= h((string) $cardNumber) ?>"
                        data-offset-x="<?= h((string) ((int) ($item['position_offset_x'] ?? 0))) ?>"
                        data-offset-y="<?= h((string) ((int) ($item['position_offset_y'] ?? 0))) ?>"
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
                            <a class="preview-link" href="<?= h((string) $item['processed_path']) ?>" target="_blank" rel="noopener">
                                <img
                                    class="preview-image"
                                    src="<?= h((string) $item['processed_path']) ?>"
                                    alt="<?= h('加工済み画像: ' . (string) $item['original_name']) ?>"
                                >
                            </a>
                        <?php else: ?>
                            <div class="preview-placeholder" aria-hidden="true">Preview unavailable</div>
                        <?php endif; ?>

                        <dl class="meta-list">
                            <div>
                                <dt>元ファイル名</dt>
                                <dd><?= h((string) $item['original_name']) ?></dd>
                            </div>
                            <div>
                                <dt>画像サイズ</dt>
                                <dd><?= h((string) OUTPUT_SIZE) ?> × <?= h((string) OUTPUT_SIZE) ?></dd>
                            </div>
                            <div>
                                <dt>出力形式</dt>
                                <dd><?= h(strtoupper((string) $metadata['output_format'])) ?></dd>
                            </div>
                            <?php if ($isSuccess): ?>
                                <div>
                                    <dt>リサイズ後</dt>
                                    <dd><?= h((string) $item['resized_width']) ?> × <?= h((string) $item['resized_height']) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php if ($isSuccess): ?>
                            <section class="position-controls" aria-labelledby="position-title-<?= h($imageId) ?>">
                                <h3 id="position-title-<?= h($imageId) ?>">位置調整</h3>
                                <div class="position-pad" aria-label="上下左右の位置調整">
                                    <button class="position-button position-up" type="button" data-position-action="up" aria-label="上へ移動">↑</button>
                                    <button class="position-button position-left" type="button" data-position-action="left" aria-label="左へ移動">←</button>
                                    <button class="position-button position-reset" type="button" data-position-action="reset">中央</button>
                                    <button class="position-button position-right" type="button" data-position-action="right" aria-label="右へ移動">→</button>
                                    <button class="position-button position-down" type="button" data-position-action="down" aria-label="下へ移動">↓</button>
                                </div>
                                <p class="position-status" aria-live="polite">
                                    横: <span data-offset-x><?= h((string) ((int) ($item['position_offset_x'] ?? 0))) ?></span>px /
                                    縦: <span data-offset-y><?= h((string) ((int) ($item['position_offset_y'] ?? 0))) ?></span>px
                                </p>
                            </section>

                            <form class="download-form" action="download.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="batch_id" value="<?= h((string) $metadata['batch_id']) ?>">
                                <input type="hidden" name="image_id" value="<?= h($imageId) ?>">

                                <label class="filename-label" for="filename-<?= h($imageId) ?>">ファイル名</label>
                                <div class="filename-row">
                                    <input
                                        id="filename-<?= h($imageId) ?>"
                                        class="filename-input"
                                        type="text"
                                        name="filename"
                                        value="<?= h($defaultFilename) ?>"
                                        autocomplete="off"
                                    >
                                    <span class="extension-label">.<?= h((string) $metadata['output_format']) ?></span>
                                </div>

                                <button class="button button-secondary" type="submit">個別ダウンロード</button>
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
