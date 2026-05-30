<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(['success' => false, 'message' => '結果画面から操作してください。'], 405);
}

if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    respondJson(['success' => false, 'message' => getErrorMessage('E_CSRF')], 400);
}

$batchId = (string) ($_POST['batch_id'] ?? '');
$imageId = (string) ($_POST['image_id'] ?? '');

if (!isValidBatchId($batchId) || !isValidImageId($imageId)) {
    respondJson(['success' => false, 'message' => '指定された画像を確認できません。'], 400);
}

$metadata = loadMetadata($batchId);
if ($metadata === null || empty($metadata['items']) || !is_array($metadata['items'])) {
    respondJson(['success' => false, 'message' => getErrorMessage('E_BATCH_NOT_FOUND')], 404);
}

$itemIndex = findItemIndex($metadata['items'], $imageId);
if ($itemIndex === null) {
    respondJson(['success' => false, 'message' => '対象画像が見つかりません。'], 404);
}

$deletedItem = $metadata['items'][$itemIndex];
$stagedFiles = is_array($deletedItem)
    ? stageRelativeFilesForDeletion([
        (string) ($deletedItem['original_path'] ?? ''),
        (string) ($deletedItem['processed_path'] ?? ''),
    ])
    : [];

if ($stagedFiles === null) {
    respondJson(['success' => false, 'message' => '画像ファイルを削除できませんでした。'], 500);
}

array_splice($metadata['items'], $itemIndex, 1);
$metadata['updated_at'] = date('Y-m-d H:i:s');

if (!saveMetadata($batchId, $metadata)) {
    rollbackStagedDeletes($stagedFiles);
    respondJson(['success' => false, 'message' => getErrorMessage('E_SAVE_FAILED')], 500);
}

$cleanupWarning = !finalizeStagedDeletes($stagedFiles);

$counts = countItems($metadata['items']);
respondJson([
    'success' => true,
    'total_count' => $counts['total'],
    'success_count' => $counts['success'],
    'error_count' => $counts['error'],
    'cleanup_warning' => $cleanupWarning,
]);

function findItemIndex(array $items, string $imageId): ?int
{
    foreach ($items as $index => $item) {
        if (is_array($item) && ($item['image_id'] ?? '') === $imageId) {
            return (int) $index;
        }
    }

    return null;
}

function stageRelativeFilesForDeletion(array $relativePaths): ?array
{
    $stagedFiles = [];

    foreach ($relativePaths as $relativePath) {
        $path = absolutePathFromBase((string) $relativePath);
        if ($path === null || !is_file($path)) {
            continue;
        }

        $stagedPath = $path . '.delete_' . bin2hex(random_bytes(4));
        if (!@rename($path, $stagedPath)) {
            rollbackStagedDeletes($stagedFiles);
            return null;
        }

        $stagedFiles[] = [
            'original' => $path,
            'staged' => $stagedPath,
        ];
    }

    return $stagedFiles;
}

function rollbackStagedDeletes(array $stagedFiles): void
{
    foreach (array_reverse($stagedFiles) as $file) {
        $original = (string) ($file['original'] ?? '');
        $staged = (string) ($file['staged'] ?? '');
        if ($staged !== '' && $original !== '' && is_file($staged) && !file_exists($original)) {
            @rename($staged, $original);
        }
    }
}

function finalizeStagedDeletes(array $stagedFiles): bool
{
    $ok = true;

    foreach ($stagedFiles as $file) {
        $staged = (string) ($file['staged'] ?? '');
        if ($staged !== '' && is_file($staged) && !@unlink($staged)) {
            $ok = false;
        }
    }

    return $ok;
}

function countItems(array $items): array
{
    $total = 0;
    $success = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $total++;
        if (($item['status'] ?? '') === 'success') {
            $success++;
        }
    }

    return [
        'total' => $total,
        'success' => $success,
        'error' => $total - $success,
    ];
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
