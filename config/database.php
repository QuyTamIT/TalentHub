<?php

declare(strict_types=1);

use TalentHub\Config\Environment;

$appEnvironment = Environment::appEnvironment();

return [
    'driver' => 'mysql',
    'host' => Environment::required('DB_HOST'),
    'port' => Environment::integer('DB_PORT', 3306, 1, 65535),
    'database' => Environment::required('DB_DATABASE'),
    'username' => Environment::required('DB_USERNAME'),
    'password' => Environment::databasePassword($appEnvironment),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'connectTimeout' => Environment::integer('DB_CONNECT_TIMEOUT', 5, 1, 60),
    'persistent' => Environment::boolean('DB_PERSISTENT', false),
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ],
];
