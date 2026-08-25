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

    private static function isDebug(): bool
    {
        $appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
        $appDebug = strtolower((string) ($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false'));
        return $appEnv === 'local' || $appEnv === 'development' || $appDebug === 'true' || $appDebug === '1';
    }

    private static function sendSafeResponse(?Throwable $exception = null, ?array $error = null): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $isDebug = self::isDebug();

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
            if ($isDebug) {
                if ($exception !== null) {
                    $payload['debug'] = [
                        'exception' => get_class($exception),
                        'message'   => $exception->getMessage(),
                        'file'      => $exception->getFile(),
                        'line'      => $exception->getLine(),
                        'trace'     => explode("\n", $exception->getTraceAsString()),
                    ];
                } elseif ($error !== null) {
                    $payload['debug'] = $error;
                }
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($isDebug) {
            $errTitle = $exception !== null ? htmlspecialchars(get_class($exception) . ': ' . $exception->getMessage()) : ($error !== null ? htmlspecialchars($error['message']) : 'Lỗi hệ thống');
            $errFile = $exception !== null ? htmlspecialchars($exception->getFile() . ':' . $exception->getLine()) : ($error !== null ? htmlspecialchars($error['file'] . ':' . $error['line']) : '');
            $errTrace = $exception !== null ? htmlspecialchars($exception->getTraceAsString()) : '';

            echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Debug Error | TalentHub</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;padding:2rem;line-height:1.5}.card{background:#1e293b;border-radius:8px;padding:1.5rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);border:1px solid #334155;max-width:900px;margin:0 auto}h1{color:#ef4444;font-size:1.4rem;margin-top:0}.meta{color:#94a3b8;font-size:0.9rem;margin-bottom:1rem;word-break:break-all}pre{background:#090d16;color:#38bdf8;padding:1rem;border-radius:6px;overflow-x:auto;font-size:0.85rem;line-height:1.4}</style></head><body><div class="card"><h1>Lỗi hệ thống (Debug Mode)</h1><div class="meta"><strong>Message:</strong> ' . $errTitle . '<br><strong>Location:</strong> ' . $errFile . '</div><h3>Stack Trace:</h3><pre>' . $errTrace . '</pre></div></body></html>';
            return;
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
