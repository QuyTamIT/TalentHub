<?php

declare(strict_types=1);

$root = __DIR__;
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';
$uri = str_replace('\\', '/', $uri);
if ($uri === '') {
    $uri = '/';
}

$filePath = $root . $uri;

if ($uri !== '/' && is_file($filePath)) {
    return false;
}

if (is_dir($filePath)) {
    $index = rtrim($filePath, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($index)) {
        $scriptName = rtrim($uri, '/') . '/index.php';
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['PHP_SELF'] = $scriptName;
        $_SERVER['SCRIPT_FILENAME'] = $index;
        require $index;
        return true;
    }
}

if ($uri === '/') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
    require $root . '/index.php';
    return true;
}

$base = basename($uri);
if ($base !== '' && !str_contains($base, '.')) {
    $phpFile = $filePath . '.php';
    if (is_file($phpFile)) {
        $scriptName = $uri . '.php';
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['PHP_SELF'] = $scriptName;
        $_SERVER['SCRIPT_FILENAME'] = $phpFile;
        require $phpFile;
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo '404 Not Found';
return true;
