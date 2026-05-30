<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToIndexWithErrors(['保存は加工結果画面から実行してください。']);
}

if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    redirectToIndexWithErrors([getErrorMessage('E_CSRF')]);
}

try {
    ensureDirectories();
} catch (RuntimeException $exception) {
    redirectToIndexWithErrors([$exception->getMessage()]);
}

$batchId = (string) ($_POST['batch_id'] ?? '');
if (!isValidBatchId($batchId)) {
    redirectToIndexWithErrors([getErrorMessage('E_BATCH_NOT_FOUND')]);
}

$metadata = loadMetadata($batchId);
if ($metadata === null) {
    redirectToIndexWithErrors([getErrorMessage('E_BATCH_NOT_FOUND')]);
}

$savedName = sanitizeFilename((string) ($_POST['saved_name'] ?? ''));
if ($savedName === '') {
    $savedName = savedBatchDisplayName($metadata, $batchId);
}

$now = date('Y-m-d H:i:s');
$metadata['is_saved'] = true;
$metadata['saved_name'] = $savedName;
$metadata['saved_at'] = (string) ($metadata['saved_at'] ?? $now);
$metadata['updated_at'] = $now;

if (!saveMetadata($batchId, $metadata)) {
    redirectToResultWithErrors($batchId, [getErrorMessage('E_SAVE_FAILED')]);
}

$_SESSION['flash_messages'] = ['加工結果を保存しました。'];
header('Location: result.php?batch=' . rawurlencode($batchId));
exit;

function redirectToIndexWithErrors(array $errors): void
{
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php');
    exit;
}

function redirectToResultWithErrors(string $batchId, array $errors): void
{
    $_SESSION['flash_errors'] = $errors;
    header('Location: result.php?batch=' . rawurlencode($batchId));
    exit;
}
