<?php

declare(strict_types=1);

namespace TalentHub\Database\Exception;

use RuntimeException;

final class DatabaseConnectionException extends RuntimeException
{
    public const ERROR_CODE = 'DATABASE_CONNECTION_FAILED';

    public function __construct(
        private readonly ?string $sqlState,
    ) {
        parent::__construct('Database service is temporarily unavailable.');
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function sqlState(): ?string
    {
        return $this->sqlState;
    }
}
