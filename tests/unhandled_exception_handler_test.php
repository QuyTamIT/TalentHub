<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Http\UnhandledExceptionHandler;

function exception_handler_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SERVER['REQUEST_URI'] = '/app/learner/index.php';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
http_response_code(200);
ob_start();
UnhandledExceptionHandler::handle(new RuntimeException('secret SQLSTATE and C:\\private\\path'));
$html = (string) ob_get_clean();
exception_handler_assert(http_response_code() === 500, 'HTML failures return HTTP 500.');
exception_handler_assert(str_contains($html, 'Không thể tải trang'), 'HTML failures return a safe message.');
exception_handler_assert(!str_contains($html, 'SQLSTATE'), 'HTML failures do not expose SQL details.');
exception_handler_assert(!str_contains($html, 'private'), 'HTML failures do not expose filesystem paths.');

$_SERVER['REQUEST_URI'] = '/app/learner/api/v1/example.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
http_response_code(200);
ob_start();
UnhandledExceptionHandler::handle(new RuntimeException('another secret'));
$json = (string) ob_get_clean();
$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
exception_handler_assert(http_response_code() === 500, 'API failures return HTTP 500.');
exception_handler_assert(($payload['error']['code'] ?? null) === 'INTERNAL_ERROR', 'API failures return a stable safe code.');
exception_handler_assert(!str_contains($json, 'another secret'), 'API failures do not expose exception details.');

echo "unhandled_exception_handler_test: OK\n";
