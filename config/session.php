<?php
declare(strict_types=1);

use TalentHub\Config\Environment;

$environment = Environment::appEnvironment();

return [
    'name' => getenv('SESSION_NAME') ?: 'TALENTHUBSESSID',
    'lifetime' => Environment::integer('SESSION_LIFETIME', 7200, 300, 86400),
    'secure' => Environment::boolean('SESSION_SECURE', $environment === 'production'),
    'sameSite' => 'Lax',
];
