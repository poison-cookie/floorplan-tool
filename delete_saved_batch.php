<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

startSessionIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToIndexWithErrors(['保存済みバッチの削除は一覧から実行してください。']);
}

if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    redirectToIndexWithErrors([getErrorMessage('E_CSRF')]);
}

$batchId = (string) ($_POST['batch_id'] ?? '');
if (!isValidBatchId($batchId) || !is_file(getSavedMetadataPath($batchId))) {
    redirectToIndexWithErrors([getErrorMessage('E_BATCH_NOT_FOUND')]);
}

if (!deleteBatchData($batchId)) {
    redirectToIndexWithErrors(['保存済みバッチを削除できませんでした。']);
}

$_SESSION['flash_messages'] = ['保存済みバッチを削除しました。'];
header('Location: index.php');
exit;

function redirectToIndexWithErrors(array $errors): void
{
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php');
    exit;
}
