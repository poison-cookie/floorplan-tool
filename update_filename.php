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

$itemIndex = null;
foreach ($metadata['items'] as $index => $item) {
    if (is_array($item) && ($item['image_id'] ?? '') === $imageId) {
        $itemIndex = $index;
        break;
    }
}

if ($itemIndex === null) {
    respondJson(['success' => false, 'message' => '対象画像が見つかりません。'], 404);
}

$filename = sanitizeFilename((string) ($_POST['filename'] ?? ''));
if ($filename === '') {
    $filename = sanitizeFilename((string) ($metadata['items'][$itemIndex]['default_filename'] ?? ''));
}
if ($filename === '') {
    $filename = $imageId;
}

$metadata['items'][$itemIndex]['default_filename'] = $filename;
$metadata['updated_at'] = date('Y-m-d H:i:s');

if (!saveMetadata($batchId, $metadata)) {
    respondJson(['success' => false, 'message' => getErrorMessage('E_SAVE_FAILED')], 500);
}

respondJson([
    'success' => true,
    'filename' => $filename,
]);

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
