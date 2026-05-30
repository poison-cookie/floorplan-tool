<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

function redirectToIndexWithErrors(array $errors): void
{
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToIndexWithErrors(['アップロード画面から操作してください。']);
}

if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    redirectToIndexWithErrors([getErrorMessage('E_CSRF')]);
}

try {
    ensureDirectories();
} catch (RuntimeException $exception) {
    redirectToIndexWithErrors([$exception->getMessage()]);
}

$outputFormat = normalizeOutputFormat($_POST['output_format'] ?? DEFAULT_OUTPUT_FORMAT);
$files = normalizeFilesArray($_FILES['images'] ?? []);
$postedFilenames = $_POST['upload_filenames'] ?? [];
if (!is_array($postedFilenames)) {
    $postedFilenames = [];
}
$appendBatchId = (string) ($_POST['append_batch_id'] ?? '');
$appendMetadata = isValidBatchId($appendBatchId) ? loadMetadata($appendBatchId) : null;
if ($appendMetadata !== null) {
    $outputFormat = normalizeOutputFormat($appendMetadata['output_format'] ?? DEFAULT_OUTPUT_FORMAT);
}

if (count($files) === 0) {
    redirectToIndexWithErrors([getErrorMessage('E_NO_FILE')]);
}

if ($appendMetadata !== null) {
    $existingItems = is_array($appendMetadata['items'] ?? null) ? $appendMetadata['items'] : [];
    if (count($existingItems) + count($files) > MAX_UPLOAD_FILES) {
        redirectToIndexWithErrors([getErrorMessage('E_TOO_MANY_FILES')]);
    }
} elseif (count($files) > MAX_UPLOAD_FILES) {
    redirectToIndexWithErrors([getErrorMessage('E_TOO_MANY_FILES')]);
}

$batchId = $appendMetadata !== null ? $appendBatchId : generateBatchId();
$originalBatchDir = UPLOAD_ORIGINAL_DIR . DIRECTORY_SEPARATOR . $batchId;
$processedBatchDir = UPLOAD_PROCESSED_DIR . DIRECTORY_SEPARATOR . $batchId;

foreach ([$originalBatchDir, $processedBatchDir] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        redirectToIndexWithErrors([getErrorMessage('E_SAVE_FAILED')]);
    }
}

$metadata = $appendMetadata ?? [
    'batch_id' => $batchId,
    'created_at' => date('Y-m-d H:i:s'),
    'output_format' => $outputFormat,
    'output_size' => OUTPUT_SIZE,
    'items' => [],
];
$metadata['items'] = is_array($metadata['items'] ?? null) ? $metadata['items'] : [];
$metadata['output_format'] = normalizeOutputFormat($metadata['output_format'] ?? $outputFormat);
$metadata['output_size'] = OUTPUT_SIZE;
$metadata['updated_at'] = date('Y-m-d H:i:s');
$outputFormat = (string) $metadata['output_format'];
$nextImageNumber = nextImageNumber($metadata['items']);

foreach ($files as $index => $file) {
    $imageNumber = $nextImageNumber + $index;
    $imageId = sprintf('img_%03d', $imageNumber);
    $originalName = normalizeOriginalFilename((string) ($file['name'] ?? ''));
    $postedFilename = sanitizeFilename((string) ($postedFilenames[$index] ?? ''));
    $defaultFilename = $postedFilename !== ''
        ? $postedFilename
        : sanitizeFilename(pathinfo($originalName, PATHINFO_FILENAME));

    if ($defaultFilename === '') {
        $defaultFilename = sprintf('floorplan_%03d', $imageNumber);
    }

    $item = [
        'image_id' => $imageId,
        'original_name' => $originalName,
        'default_filename' => $defaultFilename,
        'original_path' => null,
        'processed_path' => null,
        'mime_type' => null,
        'source_width' => null,
        'source_height' => null,
        'resized_width' => null,
        'resized_height' => null,
        'position_offset_x' => 0,
        'position_offset_y' => 0,
        'transform_scale_percent' => 100,
        'rotation_degrees' => 0,
        'flip_horizontal' => false,
        'flip_vertical' => false,
        'processed_version' => null,
        'status' => 'error',
        'error' => null,
        'error_message' => null,
    ];

    $validation = validateUploadedFile($file);
    if (!$validation['valid']) {
        $item['error'] = $validation['error'];
        $item['error_message'] = errorMessageForItem($validation['error']);
        $metadata['items'][] = $item;
        continue;
    }

    $inputExtension = (string) $validation['extension'];
    $originalPath = $originalBatchDir . DIRECTORY_SEPARATOR . $imageId . '.' . $inputExtension;
    $processedPath = $processedBatchDir . DIRECTORY_SEPARATOR . $imageId . '.' . $outputFormat;

    if (!move_uploaded_file((string) $file['tmp_name'], $originalPath)) {
        $item['error'] = 'E_SAVE_FAILED';
        $item['error_message'] = getErrorMessage('E_SAVE_FAILED');
        $metadata['items'][] = $item;
        continue;
    }

    $processResult = processImageToSquare($originalPath, $processedPath, $inputExtension, $outputFormat);
    if (!$processResult['success']) {
        $item['original_path'] = relativePathFromBase($originalPath);
        $item['mime_type'] = $validation['mime_type'];
        $item['source_width'] = $validation['width'];
        $item['source_height'] = $validation['height'];
        $item['error'] = $processResult['error'];
        $item['error_message'] = errorMessageForItem($processResult['error']);
        $metadata['items'][] = $item;
        continue;
    }

    $item['original_path'] = relativePathFromBase($originalPath);
    $item['processed_path'] = relativePathFromBase($processedPath);
    $item['mime_type'] = $validation['mime_type'];
    $item['source_width'] = $processResult['source_width'];
    $item['source_height'] = $processResult['source_height'];
    $item['resized_width'] = $processResult['resized_width'];
    $item['resized_height'] = $processResult['resized_height'];
    $item['position_offset_x'] = $processResult['offset_x'];
    $item['position_offset_y'] = $processResult['offset_y'];
    $item['transform_scale_percent'] = $processResult['scale_percent'];
    $item['rotation_degrees'] = $processResult['rotation_degrees'];
    $item['flip_horizontal'] = $processResult['flip_horizontal'];
    $item['flip_vertical'] = $processResult['flip_vertical'];
    $item['processed_version'] = bin2hex(random_bytes(8));
    $item['status'] = 'success';
    $metadata['items'][] = $item;
}

if (!saveMetadata($batchId, $metadata)) {
    redirectToIndexWithErrors([getErrorMessage('E_SAVE_FAILED')]);
}

header('Location: result.php?batch=' . rawurlencode($batchId));
exit;

function nextImageNumber(array $items): int
{
    $max = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $imageId = (string) ($item['image_id'] ?? '');
        if (preg_match('/^img_(\d+)$/', $imageId, $matches) === 1) {
            $max = max($max, (int) $matches[1]);
        }
    }

    return $max + 1;
}

function normalizeOriginalFilename(string $filename): string
{
    $filename = str_replace('\\', '/', $filename);
    $filename = basename($filename);

    return $filename !== '' ? $filename : 'unknown';
}

function errorMessageForItem($error): string
{
    if (is_string($error) && isset(ERROR_MESSAGES[$error])) {
        return getErrorMessage($error);
    }

    return is_string($error) && $error !== '' ? $error : '画像を処理できませんでした。';
}
