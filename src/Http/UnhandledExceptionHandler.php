<?php
declare(strict_types=1);

namespace TalentHub\Http;

use Throwable;

final class UnhandledExceptionHandler
{
    private static bool $handled = false;

    public static function register(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        set_exception_handler([self::class, 'handle']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handle(Throwable $exception): void
    {
        self::$handled = true;
        error_log((string) $exception);
        self::sendSafeResponse($exception);
    }

    public static function handleShutdown(): void
    {
        if (self::$handled) {
            return;
        }
        $error = error_get_last();
        if (!is_array($error) || !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            return;
        }
        error_log(sprintf('Fatal PHP error: %s in %s:%d', $error['message'], $error['file'], $error['line']));
        self::sendSafeResponse(null, $error);
    }

    private static function sendSafeResponse(?Throwable $exception = null, ?array $error = null): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::expectsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            $payload = [
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Không thể xử lý yêu cầu. Vui lòng thử lại sau.',
                ],
            ];
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lỗi hệ thống | TalentHub</title></head><body><main><h1>Không thể tải trang</h1><p>Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.</p></main></body></html>';
    }

    private static function expectsJson(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return (is_string($path) && str_contains($path, '/api/')) || str_contains($accept, 'application/json');
    }
}
