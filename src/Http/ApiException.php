<?php
declare(strict_types=1);
namespace TalentHub\Http;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @param list<array{field:string,code:string,message:string}> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) { parent::__construct($message); }
}
