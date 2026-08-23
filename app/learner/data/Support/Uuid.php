<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Support;

use TalentHub\Learner\Data\Exceptions\LearnerDataMappingException;

final class Uuid
{
    private const MOCK_NAMESPACE = 'bf7c10d0-25a4-5f65-a663-6f83c18e581c';

    public static function isValid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }

    public static function normalizeDatabase(string $value, string $field = 'id'): string
    {
        $normalized = strtolower(trim($value));
        if (!self::isValid($normalized)) {
            throw new LearnerDataMappingException("Invalid database UUID in {$field}: {$value}");
        }

        return $normalized;
    }

    public static function fromMockLegacy(string $entity, string|int $legacyId): string
    {
        $namespace = hex2bin(str_replace('-', '', self::MOCK_NAMESPACE));
        if ($namespace === false) {
            throw new LearnerDataMappingException('Invalid learner mock UUID namespace.');
        }

        $hash = sha1($namespace . strtolower(trim($entity)) . ':' . (string) $legacyId, true);
        $bytes = substr($hash, 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
