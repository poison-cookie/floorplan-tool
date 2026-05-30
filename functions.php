<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

function startSessionIfNeeded(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function ensureDirectories(): void
{
    $directories = [
        UPLOAD_ORIGINAL_DIR,
        UPLOAD_PROCESSED_DIR,
        UPLOAD_ZIP_DIR,
        BATCH_DIR,
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('保存ディレクトリを作成できません: ' . $directory);
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('保存ディレクトリに書き込めません: ' . $directory);
        }
    }
}

function generateCsrfToken(): string
{
    startSessionIfNeeded();

    if (empty($_SESSION[SESSION_KEY_CSRF]) || !is_string($_SESSION[SESSION_KEY_CSRF])) {
        $_SESSION[SESSION_KEY_CSRF] = bin2hex(random_bytes(32));
    }

    return $_SESSION[SESSION_KEY_CSRF];
}

function verifyCsrfToken(string $token): bool
{
    startSessionIfNeeded();

    return isset($_SESSION[SESSION_KEY_CSRF])
        && is_string($_SESSION[SESSION_KEY_CSRF])
        && hash_equals($_SESSION[SESSION_KEY_CSRF], $token);
}

function generateBatchId(): string
{
    return date('Ymd_His') . '_' . bin2hex(random_bytes(3));
}

function isValidBatchId(string $batchId): bool
{
    return preg_match(BATCH_ID_PATTERN, $batchId) === 1;
}

function isValidImageId(string $imageId): bool
{
    return preg_match(IMAGE_ID_PATTERN, $imageId) === 1;
}

function getErrorMessage(string $code): string
{
    return ERROR_MESSAGES[$code] ?? 'エラーが発生しました。';
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalizeOutputFormat(?string $format): string
{
    $format = strtolower((string) $format);

    if (!in_array($format, ALLOWED_OUTPUT_FORMATS, true)) {
        return DEFAULT_OUTPUT_FORMAT;
    }

    return $format;
}

function getMimeTypeForOutputFormat(string $format): string
{
    switch ($format) {
        case 'gif':
            return 'image/gif';
        case 'jpg':
            return 'image/jpeg';
        case 'png':
        default:
            return 'image/png';
    }
}

function normalizeFilesArray(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        if (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [[
            'name' => $files['name'] ?? '',
            'type' => $files['type'] ?? '',
            'tmp_name' => $files['tmp_name'] ?? '',
            'error' => $files['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'] ?? 0,
        ]];
    }

    $normalized = [];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $name = $files['name'][$i] ?? '';

        if ($error === UPLOAD_ERR_NO_FILE && $name === '') {
            continue;
        }

        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $error,
            'size' => $files['size'][$i] ?? 0,
        ];
    }

    return $normalized;
}

function validateUploadedFile(array $file): array
{
    $result = [
        'valid' => false,
        'extension' => null,
        'mime_type' => null,
        'width' => null,
        'height' => null,
        'error' => null,
    ];

    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError !== UPLOAD_ERR_OK) {
        if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
            $result['error'] = 'E_FILE_SIZE';
        } elseif ($uploadError === UPLOAD_ERR_NO_FILE) {
            $result['error'] = 'E_NO_FILE';
        } else {
            $result['error'] = 'E_UPLOAD_FAILED';
        }

        return $result;
    }

    if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
        $result['error'] = 'E_FILE_SIZE';
        return $result;
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_INPUT_EXTENSIONS, true)) {
        $result['error'] = 'E_INVALID_FORMAT';
        return $result;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $result['error'] = 'E_UPLOAD_FAILED';
        return $result;
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        $result['error'] = 'E_IMAGE_INVALID';
        return $result;
    }

    $mimeType = detectMimeType($tmpName, $imageInfo);
    if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
        $result['error'] = 'E_INVALID_FORMAT';
        return $result;
    }

    $result['valid'] = true;
    $result['extension'] = $extension;
    $result['mime_type'] = $mimeType;
    $result['width'] = (int) $imageInfo[0];
    $result['height'] = (int) $imageInfo[1];

    return $result;
}

function detectMimeType(string $path, array $imageInfo): string
{
    $imageMimeType = isset($imageInfo['mime']) && is_string($imageInfo['mime']) ? $imageInfo['mime'] : '';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        if (is_string($mimeType) && in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
            return $mimeType;
        }
    }

    return $imageMimeType;
}

function sanitizeFilename(string $filename): string
{
    $filename = trim($filename);
    $filename = preg_replace('/^\xEF\xBB\xBF/u', '', $filename) ?? $filename;
    $filename = preg_replace('/\.[A-Za-z0-9]{1,8}$/u', '', $filename) ?? $filename;
    $filename = preg_replace('/[\\\\\/:\*\?"<>|]+/u', '_', $filename) ?? $filename;
    $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? $filename;
    $filename = trim($filename, " \t\n\r\0\x0B.");

    return $filename;
}

function makeUniqueFilenames(array $filenameMap, string $extension): array
{
    $extension = normalizeOutputFormat($extension);
    $result = [];
    $used = [];
    $blankIndex = 1;

    foreach ($filenameMap as $imageId => $filename) {
        $base = sanitizeFilename((string) $filename);

        if ($base === '') {
            $base = sprintf('floorplan_%03d', $blankIndex);
            $blankIndex++;
        }

        $candidateBase = $base;
        $suffix = 2;

        while (true) {
            $finalName = $candidateBase . '.' . $extension;
            $comparisonKey = normalizeFilenameComparisonKey($finalName);

            if (!isset($used[$comparisonKey])) {
                break;
            }

            $candidateBase = $base . '_' . $suffix;
            $suffix++;
        }

        $result[$imageId] = $finalName;
        $used[$comparisonKey] = true;
    }

    return $result;
}

function normalizeFilenameComparisonKey(string $filename): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($filename, 'UTF-8');
    }

    return strtolower($filename);
}

function createImageResource(string $path, string $extension)
{
    switch (strtolower($extension)) {
        case 'jpg':
        case 'jpeg':
            return @imagecreatefromjpeg($path);
        case 'png':
            return @imagecreatefrompng($path);
        case 'gif':
            return @imagecreatefromgif($path);
        default:
            return false;
    }
}

function processImageToSquare(
    string $sourcePath,
    string $destPath,
    string $inputExtension,
    string $outputFormat,
    int $offsetX = 0,
    int $offsetY = 0
): array
{
    $result = [
        'success' => false,
        'source_width' => null,
        'source_height' => null,
        'resized_width' => null,
        'resized_height' => null,
        'offset_x' => 0,
        'offset_y' => 0,
        'error' => null,
    ];

    if (!extension_loaded('gd')) {
        $result['error'] = 'GD拡張が有効ではありません。';
        return $result;
    }

    $sourceImage = createImageResource($sourcePath, $inputExtension);
    if ($sourceImage === false) {
        $result['error'] = 'E_IMAGE_INVALID';
        return $result;
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    $longSide = max($sourceWidth, $sourceHeight);

    if ($longSide <= 0) {
        imagedestroy($sourceImage);
        $result['error'] = 'E_IMAGE_INVALID';
        return $result;
    }

    $scale = OUTPUT_SIZE / $longSide;
    $resizedWidth = max(1, (int) round($sourceWidth * $scale));
    $resizedHeight = max(1, (int) round($sourceHeight * $scale));
    $centerX = (int) floor((OUTPUT_SIZE - $resizedWidth) / 2);
    $centerY = (int) floor((OUTPUT_SIZE - $resizedHeight) / 2);
    $dstX = clampCanvasPosition($centerX + $offsetX, $resizedWidth);
    $dstY = clampCanvasPosition($centerY + $offsetY, $resizedHeight);

    $canvas = imagecreatetruecolor(OUTPUT_SIZE, OUTPUT_SIZE);
    if ($canvas === false) {
        imagedestroy($sourceImage);
        $result['error'] = 'E_PROCESS_FAILED';
        return $result;
    }

    $background = imagecolorallocate($canvas, BACKGROUND_R, BACKGROUND_G, BACKGROUND_B);
    imagefill($canvas, 0, 0, $background);

    $copied = imagecopyresampled(
        $canvas,
        $sourceImage,
        $dstX,
        $dstY,
        0,
        0,
        $resizedWidth,
        $resizedHeight,
        $sourceWidth,
        $sourceHeight
    );

    if (!$copied) {
        imagedestroy($sourceImage);
        imagedestroy($canvas);
        $result['error'] = 'E_PROCESS_FAILED';
        return $result;
    }

    $destDirectory = dirname($destPath);
    if (!is_dir($destDirectory) && !mkdir($destDirectory, 0775, true) && !is_dir($destDirectory)) {
        imagedestroy($sourceImage);
        imagedestroy($canvas);
        $result['error'] = 'E_SAVE_FAILED';
        return $result;
    }

    $saved = saveImageResource($canvas, $destPath, normalizeOutputFormat($outputFormat));

    imagedestroy($sourceImage);
    imagedestroy($canvas);

    if (!$saved) {
        $result['error'] = 'E_SAVE_FAILED';
        return $result;
    }

    $result['success'] = true;
    $result['source_width'] = $sourceWidth;
    $result['source_height'] = $sourceHeight;
    $result['resized_width'] = $resizedWidth;
    $result['resized_height'] = $resizedHeight;
    $result['offset_x'] = $dstX - $centerX;
    $result['offset_y'] = $dstY - $centerY;

    return $result;
}

function clampInt(int $value, int $min, int $max): int
{
    if ($max < $min) {
        return $min;
    }

    return min(max($value, $min), $max);
}

function clampCanvasPosition(int $position, int $imageLength): int
{
    $visibleLength = min(10, max(1, $imageLength));
    return clampInt($position, -($imageLength - $visibleLength), OUTPUT_SIZE - $visibleLength);
}

function saveImageResource($image, string $destPath, string $outputFormat): bool
{
    switch ($outputFormat) {
        case 'gif':
            return imagegif($image, $destPath);
        case 'jpg':
            return imagejpeg($image, $destPath, 90);
        case 'png':
        default:
            return imagepng($image, $destPath, 6);
    }
}

function saveMetadata(string $batchId, array $metadata): bool
{
    if (!isValidBatchId($batchId)) {
        return false;
    }

    ensureDirectories();

    $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return file_put_contents(getMetadataPath($batchId), $json, LOCK_EX) !== false;
}

function loadMetadata(string $batchId): ?array
{
    if (!isValidBatchId($batchId)) {
        return null;
    }

    $path = getMetadataPath($batchId);
    if (!is_file($path)) {
        return null;
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $metadata = json_decode($json, true);
    return is_array($metadata) ? $metadata : null;
}

function getMetadataPath(string $batchId): string
{
    return BATCH_DIR . DIRECTORY_SEPARATOR . $batchId . '.json';
}

function findItemByImageId(array $metadata, string $imageId): ?array
{
    if (!isValidImageId($imageId) || empty($metadata['items']) || !is_array($metadata['items'])) {
        return null;
    }

    foreach ($metadata['items'] as $item) {
        if (is_array($item) && ($item['image_id'] ?? '') === $imageId) {
            return $item;
        }
    }

    return null;
}

function relativePathFromBase(string $absolutePath): string
{
    $base = rtrim(str_replace('\\', '/', BASE_DIR), '/') . '/';
    $path = str_replace('\\', '/', $absolutePath);

    if (strpos($path, $base) === 0) {
        return substr($path, strlen($base));
    }

    return $path;
}

function absolutePathFromBase(string $relativePath): ?string
{
    $relativePath = str_replace('\\', '/', trim($relativePath));

    if (
        $relativePath === ''
        || strpos($relativePath, '..') !== false
        || strpos($relativePath, "\0") !== false
        || strpos($relativePath, '//') !== false
        || preg_match('/^[A-Za-z]:\//', $relativePath)
    ) {
        return null;
    }

    $basePath = realpath(BASE_DIR);
    $candidatePath = BASE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
    $resolvedPath = realpath($candidatePath);

    if ($basePath === false || $resolvedPath === false) {
        return null;
    }

    $basePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
    $resolvedPathForCheck = str_replace('\\', '/', $resolvedPath);

    if (strpos($resolvedPathForCheck, $basePath) !== 0) {
        return null;
    }

    return $resolvedPath;
}

function buildAttachmentDisposition(string $downloadName): string
{
    return 'attachment; filename="' . addcslashes(asciiFallbackFilename($downloadName), '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName);
}

function asciiFallbackFilename(string $downloadName): string
{
    $extension = pathinfo($downloadName, PATHINFO_EXTENSION);
    $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? '';
    $fallback = trim($fallback, '.');

    if ($fallback === '' || $fallback === $extension) {
        $fallback = 'download';

        if ($extension !== '') {
            $fallback .= '.' . preg_replace('/[^A-Za-z0-9]+/', '', $extension);
        }
    }

    return $fallback;
}
