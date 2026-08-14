<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Support;

final class KeyMapper
{
    public static function toSnake(array $value): array
    {
        $mapped = [];
        foreach ($value as $key => $item) {
            $mappedKey = is_string($key) ? self::snakeKey($key) : $key;
            $mapped[$mappedKey] = is_array($item) ? self::toSnake($item) : $item;
        }

        return $mapped;
    }

    private static function snakeKey(string $key): string
    {
        // Single uppercase keys are domain values (for example RIASEC dimensions),
        // not camelCase field names.
        if (preg_match('/^[A-Z]$/', $key) === 1) {
            return $key;
        }

        $snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key) ?? $key;
        return strtolower($snake);
    }
}
