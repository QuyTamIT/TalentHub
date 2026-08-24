<?php

declare(strict_types=1);

namespace TalentHub\Database;

final class ProtectedDatabasePolicy
{
    public const PRIMARY = 'talenthub';
    public const LEGACY_BACKUP = 'talenthub_local';

    private function __construct()
    {
    }

    public static function isProtected(string $database): bool
    {
        $normalized = strtolower(trim($database));

        return in_array($normalized, [self::PRIMARY, self::LEGACY_BACKUP], true);
    }

    public static function allowsExplicitPrimaryWrite(string $database, bool $approved): bool
    {
        return $approved && $database === self::PRIMARY;
    }
}
