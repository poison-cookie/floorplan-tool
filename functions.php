<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

function startSessionIfNeeded(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!is_dir(SESSION_DIR)) {
            @mkdir(SESSION_DIR, 0775, true);
        }
        if (is_dir(SESSION_DIR) && is_writable(SESSION_DIR)) {
            session_save_path(SESSION_DIR);
        }
        session_start();
    }
}

function ensureDirectories(): void
{
    $directories = [
        UPLOAD_ORIGINAL_DIR,
        UPLOAD_PROCESSED_DIR,
        UPLOAD_ZIP_DIR,
        SESSION_DIR,
        BATCH_DIR,
        DATA_DIR,
        SAVED_BATCH_DIR,
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
    return date('Ymd_His') . '_' . bin2hex(random_bytes(8));
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

function normalizeProcessingOptions(array $input = [], ?array $fallback = null): array
{
    $fallback = is_array($fallback) ? $fallback : [];
    $presetKey = (string) ($input['preset'] ?? $fallback['preset'] ?? 'floorplan_square');
    $preset = PROCESSING_PRESETS[$presetKey] ?? PROCESSING_PRESETS['floorplan_square'];

    $options = array_merge($preset, $fallback, $input);
    $width = clampInt((int) ($options['output_width'] ?? DEFAULT_OUTPUT_WIDTH), MIN_OUTPUT_DIMENSION, MAX_OUTPUT_DIMENSION);
    $height = clampInt((int) ($options['output_height'] ?? DEFAULT_OUTPUT_HEIGHT), MIN_OUTPUT_DIMENSION, MAX_OUTPUT_DIMENSION);
    $resizeMode = strtolower((string) ($options['resize_mode'] ?? DEFAULT_RESIZE_MODE));
    if (!in_array($resizeMode, ALLOWED_RESIZE_MODES, true)) {
        $resizeMode = DEFAULT_RESIZE_MODE;
    }

    $backgroundColor = normalizeHexColor((string) ($options['background_color'] ?? DEFAULT_BACKGROUND_COLOR));
    $transparent = filter_var($options['background_transparent'] ?? DEFAULT_BACKGROUND_TRANSPARENT, FILTER_VALIDATE_BOOLEAN);

    return [
        'preset' => isset(PROCESSING_PRESETS[$presetKey]) ? $presetKey : 'custom',
        'output_width' => $width,
        'output_height' => $height,
        'resize_mode' => $resizeMode,
        'background_color' => $backgroundColor,
        'background_transparent' => $transparent,
    ];
}

function normalizeHexColor(string $color): string
{
    $color = trim($color);
    if (preg_match('/^#?[0-9a-fA-F]{6}$/', $color) !== 1) {
        return DEFAULT_BACKGROUND_COLOR;
    }

    return '#' . strtolower(ltrim($color, '#'));
}

function resizeModeLabel(string $mode): string
{
    switch ($mode) {
        case 'contain':
            return '全体を収める';
        case 'cover':
            return '余白なしで切り抜く';
        case 'stretch':
            return '指定サイズに引き伸ばす';
        case 'width':
            return '幅基準';
        case 'height':
            return '高さ基準';
        default:
            return $mode;
    }
}

function hexColorToRgb(string $color): array
{
    $color = ltrim(normalizeHexColor($color), '#');
    return [
        hexdec(substr($color, 0, 2)),
        hexdec(substr($color, 2, 2)),
        hexdec(substr($color, 4, 2)),
    ];
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
    int $offsetY = 0,
    int $scalePercent = 100,
    int $rotationDegrees = 0,
    bool $flipHorizontal = false,
    bool $flipVertical = false,
    ?array $processingOptions = null
): array
{
    $options = normalizeProcessingOptions($processingOptions ?? []);
    return processImageWithOptions(
        $sourcePath,
        $destPath,
        $inputExtension,
        $outputFormat,
        $offsetX,
        $offsetY,
        $scalePercent,
        $rotationDegrees,
        $flipHorizontal,
        $flipVertical,
        $options
    );
}

function processImageWithOptions(
    string $sourcePath,
    string $destPath,
    string $inputExtension,
    string $outputFormat,
    int $offsetX,
    int $offsetY,
    int $scalePercent,
    int $rotationDegrees,
    bool $flipHorizontal,
    bool $flipVertical,
    array $processingOptions
): array
{
    $processingOptions = normalizeProcessingOptions($processingOptions);
    $result = [
        'success' => false,
        'source_width' => null,
        'source_height' => null,
        'resized_width' => null,
        'resized_height' => null,
        'offset_x' => 0,
        'offset_y' => 0,
        'scale_percent' => 100,
        'rotation_degrees' => 0,
        'flip_horizontal' => false,
        'flip_vertical' => false,
        'output_width' => $processingOptions['output_width'],
        'output_height' => $processingOptions['output_height'],
        'resize_mode' => $processingOptions['resize_mode'],
        'error' => null,
    ];

    if (!extension_loaded('gd')) {
        $result['error'] = 'The GD extension is not enabled.';
        return $result;
    }

    $sourceImage = createImageResource($sourcePath, $inputExtension);
    if ($sourceImage === false) {
        $result['error'] = 'E_IMAGE_INVALID';
        return $result;
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    $scalePercent = clampInt($scalePercent, 20, 300);
    $rotationDegrees = normalizeRotationDegrees($rotationDegrees);
    $workingImage = transformImageResource($sourceImage, $rotationDegrees, $flipHorizontal, $flipVertical, $processingOptions);
    if ($workingImage === false) {
        imagedestroy($sourceImage);
        $result['error'] = 'E_PROCESS_FAILED';
        return $result;
    }

    $workingWidth = imagesx($workingImage);
    $workingHeight = imagesy($workingImage);
    $outputWidth = (int) $processingOptions['output_width'];
    $outputHeight = (int) $processingOptions['output_height'];

    if ($workingWidth <= 0 || $workingHeight <= 0 || $outputWidth <= 0 || $outputHeight <= 0) {
        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        imagedestroy($sourceImage);
        $result['error'] = 'E_IMAGE_INVALID';
        return $result;
    }

    if ((string) $processingOptions['resize_mode'] === 'stretch') {
        $scaleX = ($outputWidth / $workingWidth) * ($scalePercent / 100);
        $scaleY = ($outputHeight / $workingHeight) * ($scalePercent / 100);
        $resizedWidth = max(1, (int) round($workingWidth * $scaleX));
        $resizedHeight = max(1, (int) round($workingHeight * $scaleY));
    } else {
        $baseScale = imageBaseScale($workingWidth, $workingHeight, $outputWidth, $outputHeight, (string) $processingOptions['resize_mode']);
        $scale = $baseScale * ($scalePercent / 100);
        $resizedWidth = max(1, (int) round($workingWidth * $scale));
        $resizedHeight = max(1, (int) round($workingHeight * $scale));
    }
    $centerX = (int) floor(($outputWidth - $resizedWidth) / 2);
    $centerY = (int) floor(($outputHeight - $resizedHeight) / 2);
    $dstX = clampCanvasPosition($centerX + $offsetX, $resizedWidth, $outputWidth);
    $dstY = clampCanvasPosition($centerY + $offsetY, $resizedHeight, $outputHeight);

    $canvas = imagecreatetruecolor($outputWidth, $outputHeight);
    if ($canvas === false) {
        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        imagedestroy($sourceImage);
        $result['error'] = 'E_PROCESS_FAILED';
        return $result;
    }

    fillCanvasBackground($canvas, $outputFormat, $processingOptions);

    $copied = imagecopyresampled(
        $canvas,
        $workingImage,
        $dstX,
        $dstY,
        0,
        0,
        $resizedWidth,
        $resizedHeight,
        $workingWidth,
        $workingHeight
    );

    if (!$copied) {
        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        imagedestroy($sourceImage);
        imagedestroy($canvas);
        $result['error'] = 'E_PROCESS_FAILED';
        return $result;
    }

    $destDirectory = dirname($destPath);
    if (!is_dir($destDirectory) && !mkdir($destDirectory, 0775, true) && !is_dir($destDirectory)) {
        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        imagedestroy($sourceImage);
        imagedestroy($canvas);
        $result['error'] = 'E_SAVE_FAILED';
        return $result;
    }

    $saved = saveImageResource($canvas, $destPath, normalizeOutputFormat($outputFormat));

    if ($workingImage !== $sourceImage) {
        imagedestroy($workingImage);
    }
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
    $result['scale_percent'] = $scalePercent;
    $result['rotation_degrees'] = $rotationDegrees;
    $result['flip_horizontal'] = $flipHorizontal;
    $result['flip_vertical'] = $flipVertical;
    $result['output_width'] = $outputWidth;
    $result['output_height'] = $outputHeight;
    $result['resize_mode'] = $processingOptions['resize_mode'];

    return $result;
}

function imageBaseScale(int $sourceWidth, int $sourceHeight, int $outputWidth, int $outputHeight, string $resizeMode): float
{
    $scaleX = $outputWidth / $sourceWidth;
    $scaleY = $outputHeight / $sourceHeight;

    switch ($resizeMode) {
        case 'cover':
            return max($scaleX, $scaleY);
        case 'stretch':
            return min($scaleX, $scaleY);
        case 'width':
            return $scaleX;
        case 'height':
            return $scaleY;
        case 'contain':
        default:
            return min($scaleX, $scaleY);
    }
}

function fillCanvasBackground($canvas, string $outputFormat, array $processingOptions): void
{
    $transparent = !empty($processingOptions['background_transparent']) && normalizeOutputFormat($outputFormat) === 'png';
    if ($transparent) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $background = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $background);
        imagealphablending($canvas, true);
        return;
    }

    [$r, $g, $b] = hexColorToRgb((string) ($processingOptions['background_color'] ?? DEFAULT_BACKGROUND_COLOR));
    $background = imagecolorallocate($canvas, $r, $g, $b);
    imagefill($canvas, 0, 0, $background);
}

function transformImageResource($sourceImage, int $rotationDegrees, bool $flipHorizontal, bool $flipVertical, ?array $processingOptions = null)
{
    $workingImage = $sourceImage;
    $rotationDegrees = normalizeRotationDegrees($rotationDegrees);
    $processingOptions = normalizeProcessingOptions($processingOptions ?? []);

    if ($rotationDegrees !== 0) {
        if (!empty($processingOptions['background_transparent'])) {
            imagealphablending($workingImage, false);
            imagesavealpha($workingImage, true);
            $background = imagecolorallocatealpha($workingImage, 0, 0, 0, 127);
        } else {
            [$r, $g, $b] = hexColorToRgb((string) $processingOptions['background_color']);
            $background = imagecolorallocate($workingImage, $r, $g, $b);
        }
        $rotationAngle = (360 - $rotationDegrees) % 360;
        $rotatedImage = imagerotate($workingImage, $rotationAngle, $background);
        if ($rotatedImage === false) {
            return false;
        }

        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        $workingImage = $rotatedImage;
    }

    if ($flipHorizontal && !imageflip($workingImage, IMG_FLIP_HORIZONTAL)) {
        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        return false;
    }

    if ($flipVertical && !imageflip($workingImage, IMG_FLIP_VERTICAL)) {
        if ($workingImage !== $sourceImage) {
            imagedestroy($workingImage);
        }
        return false;
    }

    return $workingImage;
}

function normalizeRotationDegrees(int $rotationDegrees): int
{
    $rotationDegrees %= 360;
    if ($rotationDegrees < 0) {
        $rotationDegrees += 360;
    }

    return intdiv($rotationDegrees + 45, 90) * 90 % 360;
}

function clampInt(int $value, int $min, int $max): int
{
    if ($max < $min) {
        return $min;
    }

    return min(max($value, $min), $max);
}

function clampCanvasPosition(int $position, int $imageLength, ?int $canvasLength = null): int
{
    $visibleLength = min(10, max(1, $imageLength));
    $canvasLength = $canvasLength ?? OUTPUT_SIZE;
    return clampInt($position, -($imageLength - $visibleLength), $canvasLength - $visibleLength);
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
    $metadata['batch_id'] = $batchId;
    $shouldSaveBatch = shouldPersistSavedBatch($batchId, $metadata);
    if ($shouldSaveBatch) {
        $metadata['is_saved'] = true;
    }

    if ($shouldSaveBatch) {
        if (!writeMetadataFile(getSavedMetadataPath($batchId), $metadata)) {
            return false;
        }

        writeMetadataFile(getMetadataPath($batchId), $metadata);
        return true;
    }

    if (!writeMetadataFile(getMetadataPath($batchId), $metadata)) {
        return false;
    }

    return true;
}

function loadMetadata(string $batchId): ?array
{
    if (!isValidBatchId($batchId)) {
        return null;
    }

    foreach ([getSavedMetadataPath($batchId), getMetadataPath($batchId)] as $path) {
        $metadata = readMetadataFile($path);
        if ($metadata !== null) {
            return $metadata;
        }
    }

    return null;
}

function getMetadataPath(string $batchId): string
{
    return BATCH_DIR . DIRECTORY_SEPARATOR . $batchId . '.json';
}

function getSavedMetadataPath(string $batchId): string
{
    return SAVED_BATCH_DIR . DIRECTORY_SEPARATOR . $batchId . '.json';
}

function shouldPersistSavedBatch(string $batchId, array $metadata): bool
{
    return !empty($metadata['is_saved']) || is_file(getSavedMetadataPath($batchId));
}

function readMetadataFile(string $path): ?array
{
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

function writeMetadataFile(string $path, array $metadata): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return writeFileAtomically($path, $json);
}

function writeFileAtomically(string $path, string $contents): bool
{
    $directory = dirname($path);
    $temporaryPath = $directory . DIRECTORY_SEPARATOR . '.tmp_' . basename($path) . '_' . bin2hex(random_bytes(4));

    if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows' && is_file($path) && !@unlink($path)) {
        @unlink($temporaryPath);
        return false;
    }

    if (!@rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        return false;
    }

    return true;
}

function listSavedBatches(): array
{
    ensureDirectories();

    $paths = glob(SAVED_BATCH_DIR . DIRECTORY_SEPARATOR . '*.json') ?: [];
    $batches = [];

    foreach ($paths as $path) {
        $batchId = pathinfo($path, PATHINFO_FILENAME);
        if (!isValidBatchId($batchId)) {
            continue;
        }

        $metadata = readMetadataFile($path);
        if ($metadata === null) {
            continue;
        }

        $items = is_array($metadata['items'] ?? null) ? $metadata['items'] : [];
        $successCount = count(array_filter($items, static fn($item): bool => is_array($item) && ($item['status'] ?? '') === 'success'));
        $updatedAt = (string) ($metadata['updated_at'] ?? $metadata['saved_at'] ?? $metadata['created_at'] ?? '');

        $batches[] = [
            'batch_id' => $batchId,
            'saved_name' => savedBatchDisplayName($metadata, $batchId),
            'output_format' => normalizeOutputFormat($metadata['output_format'] ?? DEFAULT_OUTPUT_FORMAT),
            'total_count' => count($items),
            'success_count' => $successCount,
            'updated_at' => $updatedAt,
            'updated_timestamp' => strtotime($updatedAt) ?: 0,
        ];
    }

    usort($batches, static function (array $a, array $b): int {
        return ($b['updated_timestamp'] <=> $a['updated_timestamp'])
            ?: strcmp((string) $b['batch_id'], (string) $a['batch_id']);
    });

    return $batches;
}

function savedBatchDisplayName(array $metadata, string $batchId): string
{
    $savedName = sanitizeFilename((string) ($metadata['saved_name'] ?? ''));
    return $savedName !== '' ? $savedName : $batchId;
}

function deleteBatchData(string $batchId): bool
{
    if (!isValidBatchId($batchId)) {
        return false;
    }

    $ok = true;
    $ok = deleteDirectoryInside(UPLOAD_ORIGINAL_DIR . DIRECTORY_SEPARATOR . $batchId, UPLOAD_ORIGINAL_DIR) && $ok;
    $ok = deleteDirectoryInside(UPLOAD_PROCESSED_DIR . DIRECTORY_SEPARATOR . $batchId, UPLOAD_PROCESSED_DIR) && $ok;

    foreach (glob(UPLOAD_ZIP_DIR . DIRECTORY_SEPARATOR . $batchId . '_*.zip') ?: [] as $zipPath) {
        $ok = deleteFileInside($zipPath, UPLOAD_ZIP_DIR) && $ok;
    }

    foreach ([getMetadataPath($batchId), getSavedMetadataPath($batchId)] as $metadataPath) {
        if (is_file($metadataPath) && !@unlink($metadataPath)) {
            $ok = false;
        }
    }

    return $ok;
}

function deleteDirectoryInside(string $directory, string $allowedParent): bool
{
    if (!is_dir($directory)) {
        return true;
    }

    $target = realpath($directory);
    $parent = realpath($allowedParent);
    if ($target === false || $parent === false || !isPathInside($target, $parent)) {
        return false;
    }

    try {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
    } catch (UnexpectedValueException $exception) {
        return false;
    }

    foreach ($items as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            if (!@rmdir($path)) {
                return false;
            }
        } elseif (!@unlink($path)) {
            return false;
        }
    }

    return @rmdir($target);
}

function deleteFileInside(string $path, string $allowedParent): bool
{
    if (!is_file($path)) {
        return true;
    }

    $target = realpath($path);
    $parent = realpath($allowedParent);
    if ($target === false || $parent === false || !isPathInside($target, $parent)) {
        return false;
    }

    return @unlink($target);
}

function isPathInside(string $target, string $parent): bool
{
    $target = str_replace('\\', '/', $target);
    $parent = rtrim(str_replace('\\', '/', $parent), '/') . '/';

    return strpos($target, $parent) === 0;
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

function versionedPublicPath(string $relativePath, ?string $version = null): string
{
    $relativePath = trim($relativePath);
    if ($relativePath === '') {
        return '';
    }

    $version = trim((string) $version);
    if ($version === '') {
        $absolutePath = absolutePathFromBase($relativePath);
        if ($absolutePath !== null && is_file($absolutePath)) {
            $version = (string) filemtime($absolutePath);
        }
    }

    if ($version === '') {
        return $relativePath;
    }

    $separator = strpos($relativePath, '?') === false ? '?' : '&';
    return $relativePath . $separator . 'v=' . rawurlencode($version);
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
