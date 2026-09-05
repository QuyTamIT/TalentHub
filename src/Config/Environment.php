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
            $value = is_string($_ENV[$name]) ? $_ENV[$name] : null;
            if ($value !== null && $value !== '') return $value;
        }
        if (array_key_exists($name, $_SERVER)) {
            $value = is_string($_SERVER[$name]) ? $_SERVER[$name] : null;
            if ($value !== null && $value !== '') return $value;
        }

        $value = getenv($name);
        if ($value !== false && $value !== '') return $value;

        // Some threaded web SAPIs expose empty placeholders and may skip the
        // bootstrap loader on a later request. Recover the project value
        // directly so required configuration never becomes intermittently
        // unavailable.
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (!is_file($envPath) || !is_readable($envPath)) return $value === false ? null : $value;
        foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $trim = ltrim(trim($line), "\xEF\xBB\xBF");
            if ($trim === '' || str_starts_with($trim, '#')) continue;
            if (str_starts_with($trim, 'export ')) $trim = trim(substr($trim, 7));
            $eq = strpos($trim, '=');
            if ($eq === false || trim(substr($trim, 0, $eq)) !== $name) continue;
            $candidate = trim(substr($trim, $eq + 1));
            if (strlen($candidate) >= 2 && (($candidate[0] === '"' && $candidate[-1] === '"') || ($candidate[0] === "'" && $candidate[-1] === "'"))) {
                $candidate = substr($candidate, 1, -1);
            }
            return $candidate;
        }
        return $value === false ? null : $value;
    }
}
