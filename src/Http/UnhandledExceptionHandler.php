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
        $ref = self::requestReference();
        self::writeErrorLog($ref, (string) $exception);
        self::sendSafeResponse($exception, null, $ref);
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
        $ref = self::requestReference();
        self::writeErrorLog($ref, sprintf('Fatal PHP error: %s in %s:%d', $error['message'], $error['file'], $error['line']));
        self::sendSafeResponse(null, $error, $ref);
    }

    /** Tạo mã tham chiếu gọn (vd RL7FA2C4) để đối chiếu log. */
    private static function requestReference(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $seed = $method . '|' . $uri . '|' . (string) microtime(true);
        return 'RL' . strtoupper(substr(hash('crc32b', $seed), 0, 6));
    }

    /** Ghi exception thô ra storage/errors/<ref>.log (bổ sung cho error_log của PHP). */
    private static function writeErrorLog(string $ref, string $detail): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/errors';
        try {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $lines = [
                'time=' . date('c'),
                'ref=' . $ref,
                'method=' . ($_SERVER['REQUEST_METHOD'] ?? ''),
                'uri=' . ($_SERVER['REQUEST_URI'] ?? ''),
                'referer=' . ($_SERVER['HTTP_REFERER'] ?? ''),
                'ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user=' . (($_SESSION['user_name'] ?? $_SESSION['email'] ?? $_SESSION['user_id'] ?? '') ?: '(chưa đăng nhập)'),
                'detail=' . $detail,
                '---',
            ];
            @file_put_contents($dir . '/' . $ref . '.log', implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // không để lỗi ghi log làm ngắt xử lý.
        }
    }

    private static function sendSafeResponse(?Throwable $exception = null, ?array $error = null, string $ref = ''): void
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
            if ($ref !== '') {
                $payload['error']['reference'] = $ref;
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $refHtml = $ref !== '' ? '<p class="error-ref">Mã lỗi: <code>' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</code> — chi tiết tại <code>storage/errors/' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '.log</code></p>' : '';
        echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lỗi hệ thống | TalentHub</title></head><body><main><h1>Không thể tải trang</h1><p>Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.</p>'
            . $refHtml
            . '</main></body></html>';
    }

    private static function expectsJson(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return (is_string($path) && str_contains($path, '/api/')) || str_contains($accept, 'application/json');
    }
}
