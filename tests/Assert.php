<?php
declare(strict_types=1);

/**
 * Minimal PHPUnit-style assertions for the policy test suite.
 *
 * The project has no PHPUnit dependency; we ship a tiny harness so policy
 * tests can be run with `php tests/Policy/run.php`. Assertions are kept
 * deliberately small and crash-on-failure to keep tests fast and readable.
 */

namespace Tests;

final class Assert
{
    public static function assertTrue(bool $value, string $message = ''): void
    {
        if (!$value) {
            throw new AssertionFailedException($message !== '' ? $message : 'Expected true, got false.');
        }
    }

    public static function assertFalse(bool $value, string $message = ''): void
    {
        if ($value) {
            throw new AssertionFailedException($message !== '' ? $message : 'Expected false, got true.');
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            throw new AssertionFailedException(sprintf(
                "Expected %s, got %s.%s",
                self::stringify($expected),
                self::stringify($actual),
                $message !== '' ? ' ' . $message : '',
            ));
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailedException(sprintf(
                "Expected identical %s, got %s.%s",
                self::stringify($expected),
                self::stringify($actual),
                $message !== '' ? ' ' . $message : '',
            ));
        }
    }

    public static function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            throw new AssertionFailedException($message !== '' ? $message : 'Expected non-null value.');
        }
    }

    public static function assertNull(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            throw new AssertionFailedException($message !== '' ? $message : 'Expected null, got ' . self::stringify($value));
        }
    }

    /**
     * @param array<int, mixed> $items
     */
    public static function assertContains(mixed $needle, array $items, string $message = ''): void
    {
        if (!in_array($needle, $items, true)) {
            throw new AssertionFailedException(sprintf(
                "Expected collection to contain %s.%s",
                self::stringify($needle),
                $message !== '' ? ' ' . $message : '',
            ));
        }
    }

    /**
     * @param array<int, mixed> $items
     */
    public static function assertNotContains(mixed $needle, array $items, string $message = ''): void
    {
        if (in_array($needle, $items, true)) {
            throw new AssertionFailedException(sprintf(
                "Expected collection NOT to contain %s.%s",
                self::stringify($needle),
                $message !== '' ? ' ' . $message : '',
            ));
        }
    }

    public static function assertThrows(callable $fn, string $expectedCode, string $message = ''): void
    {
        try {
            $fn();
        } catch (\TalentHub\Http\ApiException $e) {
            if ($e->errorCode !== $expectedCode) {
                throw new AssertionFailedException(sprintf(
                    "Expected exception code %s, got %s.%s",
                    $expectedCode,
                    $e->errorCode,
                    $message !== '' ? ' ' . $message : '',
                ));
            }
            return;
        } catch (\Throwable $e) {
            throw new AssertionFailedException(sprintf(
                "Expected ApiException with code %s, got %s: %s.%s",
                $expectedCode,
                $e::class,
                $e->getMessage(),
                $message !== '' ? ' ' . $message : '',
            ));
        }
        throw new AssertionFailedException(sprintf(
            "Expected exception with code %s to be thrown, but no exception was raised.%s",
            $expectedCode,
            $message !== '' ? ' ' . $message : '',
        ));
    }

    public static function assertDoesNotThrow(callable $fn, string $message = ''): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            throw new AssertionFailedException(sprintf(
                "Expected no exception, but %s was thrown: %s.%s",
                $e::class,
                $e->getMessage(),
                $message !== '' ? ' ' . $message : '',
            ));
        }
    }

    private static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '<unprintable>';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }
}
