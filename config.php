<?php
declare(strict_types=1);

define('BASE_DIR', __DIR__);

define('UPLOAD_ORIGINAL_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'original');
define('UPLOAD_PROCESSED_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'processed');
define('UPLOAD_ZIP_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'zip');
define('BATCH_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'batches');
define('DATA_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'data');
define('SAVED_BATCH_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'saved_batches');

define('UPLOAD_ORIGINAL_PUBLIC_PATH', 'uploads/original');
define('UPLOAD_PROCESSED_PUBLIC_PATH', 'uploads/processed');
define('UPLOAD_ZIP_PUBLIC_PATH', 'uploads/zip');

define('OUTPUT_SIZE', 500);
define('BACKGROUND_R', 255);
define('BACKGROUND_G', 255);
define('BACKGROUND_B', 255);

define('MAX_UPLOAD_FILES', 50);
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

define('DEFAULT_OUTPUT_FORMAT', 'png');
define('ALLOWED_INPUT_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_OUTPUT_FORMATS', ['gif', 'png', 'jpg']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

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
    'E_CSRF' => '不正なリクエストです。画面を再読み込みしてください。',
]);
