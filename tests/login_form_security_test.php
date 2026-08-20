<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/login.php');
if (!is_string($source)) {
    throw new RuntimeException('Unable to read login.php.');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($source, '$loginCsrfToken=$session->csrfToken();'), 'Login form creates a CSRF token.');
$assert(str_contains($source, '$session->assertCsrf('), 'Login POST validates its CSRF token.');
$assert(str_contains($source, 'name="csrfToken"'), 'Login form submits its CSRF token.');
$assert(str_contains($source, 'http_response_code($exception->status);'), 'Login errors preserve their HTTP status.');

echo "login_form_security_test: OK\n";
