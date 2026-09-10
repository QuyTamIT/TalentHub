<?php
declare(strict_types=1);

namespace TalentHub\Tests\Policy;

use Tests\Assert;
use Tests\AssertionFailedException;

/**
 * PHP 8.x trait – provides `$this->assert*()` and `$this->assertThrows()` helpers
 * that delegate to the global Tests\Assert class. This keeps individual test
 * methods readable (no top-level function calls needed inside classes).
 */
trait TestCaseTrait
{
    protected function assertTrue(bool $value, string $message = ''): void
    {
        Assert::assertTrue($value, $message);
    }

    protected function assertFalse(bool $value, string $message = ''): void
    {
        Assert::assertFalse($value, $message);
    }

    /** @param mixed $expected @param mixed $actual */
    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        Assert::assertEquals($expected, $actual, $message);
    }

    /** @param mixed $expected @param mixed $actual */
    protected function assertSame($expected, $actual, string $message = ''): void
    {
        Assert::assertSame($expected, $actual, $message);
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        Assert::assertNotNull($value, $message);
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        Assert::assertNull($value, $message);
    }

    protected function assertContains(mixed $needle, array $items, string $message = ''): void
    {
        Assert::assertContains($needle, $items, $message);
    }

    protected function assertNotContains(mixed $needle, array $items, string $message = ''): void
    {
        Assert::assertNotContains($needle, $items, $message);
    }

    protected function assertArrayNotHasKey(string|int $key, array $array, string $message = ''): void
    {
        Assert::assertFalse(array_key_exists($key, $array), $message);
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        Assert::assertTrue(array_key_exists($key, $array), $message);
    }

    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        Assert::assertEquals($expected, count($array), $message);
    }

    protected function assertThrows(callable $fn, string $expectedCode, string $message = ''): void
    {
        Assert::assertThrows($fn, $expectedCode, $message);
    }

    protected function fail(string $message = ''): void
    {
        throw new AssertionFailedException($message ?: 'Test failed.');
    }

    /** @param mixed $expected @param mixed $actual */
    protected function assertNotEquals($expected, $actual, string $message = ''): void
    {
        $msg = $message !== '' ? ' ' . $message : '';
        $expectedStr = is_string($expected) ? '"' . $expected . '"' : (string) json_encode($expected);
        $this->assertFalse($expected == $actual, "Expected not-equal {$expectedStr}{$msg}");
    }
}
