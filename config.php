<?php
declare(strict_types=1);

define('BASE_DIR', __DIR__);

define('UPLOAD_ORIGINAL_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'original');
define('UPLOAD_PROCESSED_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'processed');
define('UPLOAD_ZIP_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'zip');
define('BATCH_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'batches');
define('SESSION_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'sessions');
define('DATA_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'data');
define('SAVED_BATCH_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'saved_batches');

define('UPLOAD_ORIGINAL_PUBLIC_PATH', 'uploads/original');
define('UPLOAD_PROCESSED_PUBLIC_PATH', 'uploads/processed');
define('UPLOAD_ZIP_PUBLIC_PATH', 'uploads/zip');

define('OUTPUT_SIZE', 500);
define('DEFAULT_OUTPUT_WIDTH', 500);
define('DEFAULT_OUTPUT_HEIGHT', 500);
define('MIN_OUTPUT_DIMENSION', 50);
define('MAX_OUTPUT_DIMENSION', 4000);
define('BACKGROUND_R', 255);
define('BACKGROUND_G', 255);
define('BACKGROUND_B', 255);
define('DEFAULT_BACKGROUND_COLOR', '#ffffff');
define('DEFAULT_BACKGROUND_TRANSPARENT', false);
define('DEFAULT_RESIZE_MODE', 'contain');

define('MAX_UPLOAD_FILES', 50);
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

define('DEFAULT_OUTPUT_FORMAT', 'png');
define('ALLOWED_INPUT_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_OUTPUT_FORMATS', ['gif', 'png', 'jpg', 'webp']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_RESIZE_MODES', ['contain', 'cover', 'stretch', 'width', 'height']);
define('PROCESSING_PRESETS', [
    'floorplan_square' => [
        'label' => '間取り図 500 正方形',
        'output_width' => 500,
        'output_height' => 500,
        'resize_mode' => 'contain',
        'background_color' => '#ffffff',
        'background_transparent' => false,
        'output_format' => 'png',
    ],
    'large_square' => [
        'label' => '大きめ正方形 1200',
        'output_width' => 1200,
        'output_height' => 1200,
        'resize_mode' => 'contain',
        'background_color' => '#ffffff',
        'background_transparent' => false,
        'output_format' => 'png',
    ],
    'photo_4_3' => [
        'label' => '写真 800 x 600 切り抜き',
        'output_width' => 800,
        'output_height' => 600,
        'resize_mode' => 'cover',
        'background_color' => '#ffffff',
        'background_transparent' => false,
        'output_format' => 'jpg',
    ],
    'social_square' => [
        'label' => 'SNS 1080 正方形',
        'output_width' => 1080,
        'output_height' => 1080,
        'resize_mode' => 'cover',
        'background_color' => '#ffffff',
        'background_transparent' => false,
        'output_format' => 'jpg',
    ],
    'transparent_thumb' => [
        'label' => '透明サムネイル 300',
        'output_width' => 300,
        'output_height' => 300,
        'resize_mode' => 'contain',
        'background_color' => '#ffffff',
        'background_transparent' => true,
        'output_format' => 'png',
    ],
]);

define('SESSION_KEY_CSRF', 'csrf_token');
define('BATCH_ID_PATTERN', '/^\d{8}_\d{6}_[a-f0-9]{6,16}$/');
define('IMAGE_ID_PATTERN', '/^img_\d{3,}$/');

define('ERROR_MESSAGES', [
    'E_NO_FILE' => '画像ファイルを選択してください。',
    'E_TOO_MANY_FILES' => '一度に処理できる画像は50枚までです。',
    'E_INVALID_FORMAT' => '対応していないファイル形式です。',
    'E_FILE_SIZE' => 'ファイルサイズが上限を超えています。',
    'E_UPLOAD_FAILED' => 'ファイルのアップロードに失敗しました。',
    'E_IMAGE_INVALID' => '画像ファイルとして読み込めませんでした。',
    'E_PROCESS_FAILED' => '画像の加工に失敗しました。',
    'E_SAVE_FAILED' => 'ファイルの保存に失敗しました。',
    'E_BATCH_NOT_FOUND' => '処理データが見つかりません。',
    'E_ZIP_FAILED' => 'ZIPファイルの作成に失敗しました。',
    'E_CSRF' => '不正なリクエストです。画面を再読み込みしてから操作してください。',
]);
