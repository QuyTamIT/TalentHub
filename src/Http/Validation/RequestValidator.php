<?php
declare(strict_types=1);

namespace TalentHub\Http\Validation;

use InvalidArgumentException;

/**
 * RequestValidator - Reusable validation utility for API requests
 */
final class RequestValidator
{
    /**
     * Validate UUID v4 format
     */
    public static function uuid(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'UUID must be a string, %s given',
                get_debug_type($value)
            ));
        }

        $pattern = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';
        
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid UUID format: %s',
                $value
            ));
        }

        return $value;
    }

    /**
     * Validate email format
     */    public static function email(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Email must be a string, %s given',
                get_debug_type($value)
            ));
        }

        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid email format: %s',
                $value
            ));
        }

        return $value;
    }

    /**
     * Validate date format (Y-m-d)
     */
    public static function date(mixed $value, ?string $format = 'Y-m-d'): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Date must be a string, %s given',
                get_debug_type($value)
            ));
        }

        $date = \DateTime::createFromFormat($format, $value);
        
        if ($date === false || $date->format($format) !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Invalid date format: %s (expected: %s)',
                $value,
                $format
            ));
        }

        return $value;
    }

    /**
     * Validate integer in range
     */    public static function integer(mixed $value, ?int $min = null, ?int $max = null): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf(
                'Integer must be an integer, %s given',
                get_debug_type($value)
            ));
        }

        if ($min !== null && $value < $min) {
            throw new InvalidArgumentException(sprintf(
                'Integer must be >= %d, %d given',
                $min,
                $value
            ));
        }

        if ($max !== null && $value > $max) {
            throw new InvalidArgumentException(sprintf(
                'Integer must be <= %d, %d given',
                $max,
                $value
            ));
        }

        return $value;
    }

    /**
     * Validate positive integer
     */
    public static function positiveInt(mixed $value, int $min = 0): int
    {
        return self::integer($value, $min, PHP_INT_MAX);
    }

    /**
     * Validate string length
     */
    public static function string(mixed $value, int $min = 0, int $max = PHP_INT_MAX): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'String must be a string, %s given',
                get_debug_type($value)
            ));
        }

        $len = mb_strlen($value, 'UTF-8');

        if ($len < $min) {
            throw new InvalidArgumentException(sprintf(
                'String must be at least %d characters, %d given',
                $min,
                $len
            ));
        }

        if ($len > $max) {
            throw new InvalidArgumentException(sprintf(
                'String must be at most %d characters, %d given',
                $max,
                $len
            ));
        }

        return $value;
    }

    /**
     * Validate required field exists and is not empty
     */
    public static function required(mixed $value, string $field = 'value'): mixed
    {
        if (is_null($value) || $value === '') {
            throw new InvalidArgumentException(sprintf(
                '%s is required',
                $field
            ));
        }

        return $value;
    }

    /**
     * Validate array
     */
    public static function array(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Array expected, %s given',
                get_debug_type($value)
            ));
        }

        return $value;
    }

    /**
     * Validate enum values
     */
    public static function enum(mixed $value, array $allowedValues): mixed
    {
        if (!in_array($value, $allowedValues, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value: %s (allowed: %s)',
                $value,
                implode(', ', $allowedValues)
            ));
        }

        return $value;
    }

    /**
     * Validate boolean
     */
    public static function bool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf(
                'Boolean expected, %s given',
                get_debug_type($value)
            ));
        }

        return $value;
    }

    /**
     * Validate numeric (int or float)
     */
    public static function numeric(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                'Numeric expected, %s given',
                get_debug_type($value)
            ));
        }

        return (float) $value;
    }

    /**
     * Sanitize string (strip HTML tags, trim, escape)
     */
    public static function sanitize(mixed $value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }

        if (!is_string($value)) {
            return (string) $value;
        }

        $value = strip_tags($value);
        $value = trim($value);
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize array (sanitize each item)
     */
    public static function sanitizeArray(array $array): array
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitize($value);
            }
        }

        return $sanitized;
    }
}
