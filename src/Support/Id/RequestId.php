<?php
declare(strict_types=1);
namespace TalentHub\Support\Id;

final class RequestId
{
    public static function make(?string $candidate=null): string
    {
        if(is_string($candidate)&&preg_match('/\A[A-Za-z0-9_-]{16,64}\z/',$candidate)===1){return $candidate;}
        return strtoupper(bin2hex(random_bytes(13)));
    }

    public static function generate(?string $candidate=null): string
    {
        if (is_string($candidate) && $candidate !== '') {
            return self::make($candidate);
        }
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
