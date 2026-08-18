<?php
declare(strict_types=1);

$root = dirname(__DIR__);
foreach (['index.php', 'login.php', 'register.php', 'role-selection.php'] as $file) {
    $source = file_get_contents($root . '/' . $file);
    if (!is_string($source) || !str_contains($source, 'rel="icon"') || !str_contains($source, 'assets/images/logo.svg')) {
        throw new RuntimeException($file . ' must declare the shared SVG favicon.');
    }
}
if (!is_file($root . '/assets/images/logo.svg')) {
    throw new RuntimeException('Shared SVG favicon asset is missing.');
}
echo "public_favicon_test: OK\n";
