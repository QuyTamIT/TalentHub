<?php
declare(strict_types=1);

$file = $argv[1] ?? '';
if ($file === '') {
    exit(1);
}

$path = dirname(__DIR__) . '/app/school/' . $file;
if (!file_exists($path)) {
    exit(1);
}

ob_start();
try {
    include $path;
    $output = ob_get_clean();
    echo $output;
} catch (Throwable $e) {
    ob_end_clean();
    echo "FATAL: " . $e->getMessage();
    exit(1);
}
