<?php
declare(strict_types=1);

use TalentHub\Config\Environment;

$environment = Environment::appEnvironment();

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

return [
    'name' => getenv('SESSION_NAME') ?: 'TALENTHUBSESSID',
    'lifetime' => Environment::integer('SESSION_LIFETIME', 86400 * 7, 300, 2592000),
    'secure' => $isHttps && Environment::boolean('SESSION_SECURE', true),
    'sameSite' => 'Lax',
    'path' => '/',
    'domain' => '',
    // Do not inherit a machine-specific php.ini path (for example Laragon's
    // C:/laragon/tmp). Each deployment may override this with SESSION_SAVE_PATH.
    'savePath' => getenv('SESSION_SAVE_PATH') ?: rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'talenthub-sessions',
];
