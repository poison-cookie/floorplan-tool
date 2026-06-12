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
$action = (string) ($_POST['action'] ?? '');
$step = 10;
$scaleStep = 10;

if (!isValidBatchId($batchId) || !isValidImageId($imageId)) {
    respondJson(['success' => false, 'message' => '指定された画像を確認できません。'], 400);
}

if (!in_array($action, ['up', 'down', 'left', 'right', 'reset', 'zoom_in', 'zoom_out', 'rotate_left', 'rotate_right', 'flip_horizontal', 'flip_vertical'], true)) {
    respondJson(['success' => false, 'message' => '位置調整の指定が不正です。'], 400);
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

$item = $metadata['items'][$itemIndex];
if (($item['status'] ?? '') !== 'success') {
    respondJson(['success' => false, 'message' => '加工済み画像だけ位置調整できます。'], 400);
}

$originalPath = absolutePathFromBase((string) ($item['original_path'] ?? ''));
$processedPath = absolutePathFromBase((string) ($item['processed_path'] ?? ''));
if ($originalPath === null || $processedPath === null || !is_file($originalPath)) {
    respondJson(['success' => false, 'message' => '元画像が見つかりません。'], 404);
}

$offsetX = (int) ($item['position_offset_x'] ?? 0);
$offsetY = (int) ($item['position_offset_y'] ?? 0);
$scalePercent = clampInt((int) ($item['transform_scale_percent'] ?? 100), 20, 300);
$rotationDegrees = normalizeRotationDegrees((int) ($item['rotation_degrees'] ?? 0));
$flipHorizontal = (bool) ($item['flip_horizontal'] ?? false);
$flipVertical = (bool) ($item['flip_vertical'] ?? false);

switch ($action) {
    case 'left':
        $offsetX -= $step;
        break;
    case 'right':
        $offsetX += $step;
        break;
    case 'up':
        $offsetY -= $step;
        break;
    case 'down':
        $offsetY += $step;
        break;
    case 'reset':
        $offsetX = 0;
        $offsetY = 0;
        break;
    case 'zoom_in':
        $scalePercent = clampInt($scalePercent + $scaleStep, 20, 300);
        break;
    case 'zoom_out':
        $scalePercent = clampInt($scalePercent - $scaleStep, 20, 300);
        break;
    case 'rotate_left':
        $rotationDegrees = normalizeRotationDegrees($rotationDegrees - 90);
        break;
    case 'rotate_right':
        $rotationDegrees = normalizeRotationDegrees($rotationDegrees + 90);
        break;
    case 'flip_horizontal':
        $flipHorizontal = !$flipHorizontal;
        break;
    case 'flip_vertical':
        $flipVertical = !$flipVertical;
        break;
}

$inputExtension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
$outputFormat = normalizeOutputFormat($metadata['output_format'] ?? DEFAULT_OUTPUT_FORMAT);
$processingOptions = normalizeProcessingOptions($metadata['processing_options'] ?? []);
$processResult = processImageToSquare(
    $originalPath,
    $processedPath,
    $inputExtension,
    $outputFormat,
    $offsetX,
    $offsetY,
    $scalePercent,
    $rotationDegrees,
    $flipHorizontal,
    $flipVertical,
    $processingOptions
);

if (!$processResult['success']) {
    $message = is_string($processResult['error']) && isset(ERROR_MESSAGES[$processResult['error']])
        ? getErrorMessage($processResult['error'])
        : (string) ($processResult['error'] ?? getErrorMessage('E_PROCESS_FAILED'));
    respondJson(['success' => false, 'message' => $message], 500);
}

$metadata['items'][$itemIndex]['resized_width'] = $processResult['resized_width'];
$metadata['items'][$itemIndex]['resized_height'] = $processResult['resized_height'];
$metadata['items'][$itemIndex]['position_offset_x'] = $processResult['offset_x'];
$metadata['items'][$itemIndex]['position_offset_y'] = $processResult['offset_y'];
$metadata['items'][$itemIndex]['transform_scale_percent'] = $processResult['scale_percent'];
$metadata['items'][$itemIndex]['rotation_degrees'] = $processResult['rotation_degrees'];
$metadata['items'][$itemIndex]['flip_horizontal'] = $processResult['flip_horizontal'];
$metadata['items'][$itemIndex]['flip_vertical'] = $processResult['flip_vertical'];
$metadata['items'][$itemIndex]['processed_version'] = bin2hex(random_bytes(8));
$metadata['updated_at'] = date('Y-m-d H:i:s');

if (!saveMetadata($batchId, $metadata)) {
    respondJson(['success' => false, 'message' => getErrorMessage('E_SAVE_FAILED')], 500);
}

$imageUrl = versionedPublicPath(
    (string) $metadata['items'][$itemIndex]['processed_path'],
    (string) $metadata['items'][$itemIndex]['processed_version']
);

respondJson([
    'success' => true,
    'image_url' => $imageUrl,
    'offset_x' => $processResult['offset_x'],
    'offset_y' => $processResult['offset_y'],
    'scale_percent' => $processResult['scale_percent'],
    'rotation_degrees' => $processResult['rotation_degrees'],
    'flip_horizontal' => $processResult['flip_horizontal'],
    'flip_vertical' => $processResult['flip_vertical'],
    'resized_width' => $processResult['resized_width'],
    'resized_height' => $processResult['resized_height'],
]);

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
