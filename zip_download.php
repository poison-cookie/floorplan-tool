<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failZipDownload('アップロード結果画面から操作してください。', 405);
}

if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    failZipDownload(getErrorMessage('E_CSRF'), 400);
}

if (!class_exists('ZipArchive')) {
    failZipDownload('ZipArchive拡張が有効ではありません。', 500);
}

$batchId = (string) ($_POST['batch_id'] ?? '');
if (!isValidBatchId($batchId)) {
    failZipDownload(getErrorMessage('E_BATCH_NOT_FOUND'), 400);
}

$metadata = loadMetadata($batchId);
if ($metadata === null) {
    failZipDownload(getErrorMessage('E_BATCH_NOT_FOUND'), 404);
}

$postedFilenames = $_POST['filenames'] ?? [];
if (!is_array($postedFilenames)) {
    $postedFilenames = [];
}

$outputFormat = normalizeOutputFormat($metadata['output_format'] ?? DEFAULT_OUTPUT_FORMAT);
$folderName = sanitizeFilename((string) ($_POST['folder_name'] ?? ''));
if ($folderName === '') {
    $folderName = 'floorplan_images_' . date('Ymd_His');
}

$successItems = [];
$filenameMap = [];

foreach (($metadata['items'] ?? []) as $item) {
    if (!is_array($item) || ($item['status'] ?? '') !== 'success') {
        continue;
    }

    $imageId = (string) ($item['image_id'] ?? '');
    if (!isValidImageId($imageId)) {
        continue;
    }

    $successItems[$imageId] = $item;
    $filenameMap[$imageId] = (string) ($postedFilenames[$imageId] ?? ($item['default_filename'] ?? ''));
}

if (count($successItems) === 0) {
    failZipDownload('ZIPに追加できる加工済み画像がありません。', 404);
}

try {
    ensureDirectories();
} catch (RuntimeException $exception) {
    failZipDownload($exception->getMessage(), 500);
}

$downloadNames = makeUniqueFilenames($filenameMap, $outputFormat);
$zipName = $folderName . '.zip';
$zipPath = UPLOAD_ZIP_DIR . DIRECTORY_SEPARATOR . $batchId . '_' . bin2hex(random_bytes(4)) . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    failZipDownload(getErrorMessage('E_ZIP_FAILED'), 500);
}

$addedCount = 0;
foreach ($successItems as $imageId => $item) {
    $path = absolutePathFromBase((string) ($item['processed_path'] ?? ''));
    if ($path === null || !is_file($path)) {
        continue;
    }

    $downloadName = $downloadNames[$imageId] ?? ($imageId . '.' . $outputFormat);
    if ($zip->addFile($path, $folderName . '/' . $downloadName, 0, 0, ZipArchive::FL_ENC_UTF_8)) {
        $addedCount++;
    }
}

$zip->close();

if ($addedCount === 0 || !is_file($zipPath)) {
    failZipDownload(getErrorMessage('E_ZIP_FAILED'), 500);
}

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($zipPath));
header('Content-Disposition: ' . buildAttachmentDisposition($zipName));
header('X-Content-Type-Options: nosniff');
readfile($zipPath);
exit;

function failZipDownload(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}
