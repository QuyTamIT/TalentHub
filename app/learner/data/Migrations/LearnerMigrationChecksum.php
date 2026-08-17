<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Migrations;

use RuntimeException;

final class LearnerMigrationChecksum
{
    public static function canonical(string $path): string
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new RuntimeException('Learner migration source is unavailable: ' . $path);
        }

        return hash('sha256', str_replace("\r\n", "\n", $source));
    }

    public static function matchesDeclared(string $path, string $declaredChecksum): bool
    {
        if (hash_equals($declaredChecksum, self::canonical($path))) {
            return true;
        }

        $legacyChecksum = hash_file('sha256', $path);
        return is_string($legacyChecksum) && hash_equals($declaredChecksum, $legacyChecksum);
    }
}
