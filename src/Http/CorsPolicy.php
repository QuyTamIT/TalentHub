<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class CorsPolicy
{
    public static function enforceSameOrigin(Request $request, ?string $host): void
    {
        $origin=$request->header('origin');
        if($origin===null||$origin===''){return;}
        $originHost=parse_url($origin,PHP_URL_HOST);$originPort=parse_url($origin,PHP_URL_PORT);
        $expected=strtolower(trim((string)$host));$actual=is_string($originHost)?strtolower($originHost).($originPort!==null?':'.$originPort:''):'';
        if($expected===''||!hash_equals($expected,$actual)){throw new ApiException(403,'CORS_ORIGIN_DENIED','Origin không được phép.');}
    }
}
