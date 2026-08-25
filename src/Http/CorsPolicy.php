<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class CorsPolicy
{
    public function apply(?Request $request = null): void
    {
        $origin = $request ? $request->header('origin') : ($_SERVER['HTTP_ORIGIN'] ?? null);
        if ($origin !== null && $origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } else {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Request-Id, Accept');
        header('Access-Control-Max-Age: 86400');

        $method = $request ? $request->method : strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Content-Length: 0');
            exit;
        }
    }

    public static function enforceSameOrigin(Request $request, ?string $host): void
    {
        $origin=$request->header('origin');
        if($origin===null||$origin===''){return;}
        $originHost=parse_url($origin,PHP_URL_HOST);$originPort=parse_url($origin,PHP_URL_PORT);
        $expected=strtolower(trim((string)$host));$actual=is_string($originHost)?strtolower($originHost).($originPort!==null?':'.$originPort:''):'';
        if($expected===''||!hash_equals($expected,$actual)){throw new ApiException(403,'CORS_ORIGIN_DENIED','Origin không được phép.');}
    }
}
