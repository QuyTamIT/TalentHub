<?php

declare(strict_types=1);

namespace TalentHub\Config;

use InvalidArgumentException;
use RuntimeException;

final class Environment
{
    private const ALLOWED_APP_ENVIRONMENTS = ['local', 'test', 'staging', 'production'];

    private function __construct()
    {
    }

    public static function appEnvironment(): string
    {
        $environment = strtolower(self::required('APP_ENV'));
        if (!in_array($environment, self::ALLOWED_APP_ENVIRONMENTS, true)) {
            throw new InvalidArgumentException(
                'APP_ENV must be one of: ' . implode(', ', self::ALLOWED_APP_ENVIRONMENTS) . '.'
            );
        }

        return $environment;
    }

    public static function required(string $name, bool $allowEmpty = false): string
    {
        $value = self::read($name);
        if ($value === null || (!$allowEmpty && $value === '')) {
            throw new RuntimeException("Required environment variable {$name} is missing.");
        }

        return $value;
    }

    public static function integer(string $name, int $default, int $minimum, int $maximum): int
    {
        $value = self::read($name);
        if ($value === null || $value === '') {
            return $default;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("Environment variable {$name} must be an integer.");
        }

        $integer = (int) $value;
        if ($integer < $minimum || $integer > $maximum) {
            throw new InvalidArgumentException(
                "Environment variable {$name} must be between {$minimum} and {$maximum}."
            );
        }

        return $integer;
    }

    public static function boolean(string $name, bool $default = false): bool
    {
        $value = self::read($name);
        if ($value === null || $value === '') {
            return $default;
        }

        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($boolean === null) {
            throw new InvalidArgumentException("Environment variable {$name} must be true or false.");
        }

        return $boolean;
    }

    public static function databasePassword(string $appEnvironment): string
    {
        $password = self::readRaw('DB_PASSWORD');
        if (($password === null || $password === '') && in_array($appEnvironment, ['staging', 'production'], true)) {
            throw new RuntimeException('DB_PASSWORD must not be empty in staging or production.');
        }

        return $password ?? '';
    }

    private static function read(string $name): ?string
    {
        $value = self::readRaw($name);
        return $value === null ? null : trim($value);
    }

    private static function readRaw(string $name): ?string
    {
        if (array_key_exists($name, $_ENV)) {
            return is_string($_ENV[$name]) ? $_ENV[$name] : null;
        }
        if (array_key_exists($name, $_SERVER)) {
            return is_string($_SERVER[$name]) ? $_SERVER[$name] : null;
        }

        $value = getenv($name);
        return $value === false ? null : $value;
    }
}
