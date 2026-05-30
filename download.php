<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failDownload('アップロード結果画面から操作してください。', 405);
}

if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    failDownload(getErrorMessage('E_CSRF'), 400);
}

$batchId = (string) ($_POST['batch_id'] ?? '');
$imageId = (string) ($_POST['image_id'] ?? '');

if (!isValidBatchId($batchId) || !isValidImageId($imageId)) {
    failDownload('指定された画像を確認できません。', 400);
}

$metadata = loadMetadata($batchId);
if ($metadata === null) {
    failDownload(getErrorMessage('E_BATCH_NOT_FOUND'), 404);
}

$item = findItemByImageId($metadata, $imageId);
if ($item === null || ($item['status'] ?? '') !== 'success') {
    failDownload('ダウンロードできる加工済み画像が見つかりません。', 404);
}

$processedPath = absolutePathFromBase((string) ($item['processed_path'] ?? ''));
if ($processedPath === null || !is_file($processedPath)) {
    failDownload('加工済み画像ファイルが見つかりません。', 404);
}

$outputFormat = normalizeOutputFormat($metadata['output_format'] ?? DEFAULT_OUTPUT_FORMAT);
$downloadNames = makeUniqueFilenames([$imageId => (string) ($_POST['filename'] ?? '')], $outputFormat);
$downloadName = $downloadNames[$imageId] ?? ('floorplan_001.' . $outputFormat);

sendFileDownload($processedPath, $downloadName, getMimeTypeForOutputFormat($outputFormat));

function failDownload(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function sendFileDownload(string $path, string $downloadName, string $mimeType): void
{
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . buildAttachmentDisposition($downloadName));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
