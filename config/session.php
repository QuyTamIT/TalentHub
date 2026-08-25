<?php
declare(strict_types=1);

use TalentHub\Config\Environment;

$environment = Environment::appEnvironment();

return [
    'name' => getenv('SESSION_NAME') ?: 'TALENTHUBSESSID',
    'lifetime' => Environment::integer('SESSION_LIFETIME', 7200, 300, 86400),
    'secure' => Environment::boolean('SESSION_SECURE', $environment === 'production'),
    'sameSite' => 'Lax',
    'path' => '/',
    'domain' => '',
    // Do not inherit a machine-specific php.ini path (for example Laragon's
    // C:/laragon/tmp). Each deployment may override this with SESSION_SAVE_PATH.
    'savePath' => getenv('SESSION_SAVE_PATH') ?: rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'talenthub-sessions',
];
