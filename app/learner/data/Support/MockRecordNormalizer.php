<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Support;

final class MockRecordNormalizer
{
    public static function primary(array $record, string $entity, string $sourceField = 'id'): array
    {
        $record = KeyMapper::toSnake($record);
        if (!array_key_exists($sourceField, $record)) {
            return $record;
        }

        $legacyId = $record[$sourceField];
        $record['legacy_id'] = $legacyId;
        $record['id'] = Uuid::fromMockLegacy($entity, $legacyId);
        $record['id_origin'] = 'mock_compat';

        return $record;
    }

    public static function foreign(array $record, string $field, string $entity): array
    {
        if (!array_key_exists($field, $record) || $record[$field] === null || $record[$field] === '') {
            return $record;
        }

        $legacyId = $record[$field];
        $record['legacy_' . $field] = $legacyId;
        $record[$field] = Uuid::fromMockLegacy($entity, $legacyId);

        return $record;
    }

    public static function lookupId(string $entity, string|int $id): string
    {
        $value = (string) $id;
        return Uuid::isValid($value) ? strtolower($value) : Uuid::fromMockLegacy($entity, $value);
    }

    public static function matches(array $record, string|int $id, string $field = 'id'): bool
    {
        $value = (string) $id;
        return (string) ($record[$field] ?? '') === $value
            || (string) ($record['legacy_' . $field] ?? '') === $value
            || ($field === 'id' && (string) ($record['legacy_id'] ?? '') === $value);
    }
}
