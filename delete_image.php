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
array_splice($metadata['items'], $itemIndex, 1);

if (!saveMetadata($batchId, $metadata)) {
    respondJson(['success' => false, 'message' => getErrorMessage('E_SAVE_FAILED')], 500);
}

if (is_array($deletedItem)) {
    deleteRelativeFile((string) ($deletedItem['original_path'] ?? ''));
    deleteRelativeFile((string) ($deletedItem['processed_path'] ?? ''));
}

$counts = countItems($metadata['items']);
respondJson([
    'success' => true,
    'total_count' => $counts['total'],
    'success_count' => $counts['success'],
    'error_count' => $counts['error'],
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

function deleteRelativeFile(string $relativePath): void
{
    $path = absolutePathFromBase($relativePath);
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
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
