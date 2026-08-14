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
}
