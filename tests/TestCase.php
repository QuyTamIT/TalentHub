<?php
declare(strict_types=1);

namespace Tests;

abstract class TestCase
{
    /**
     * @param array<int, string> $failures
     */
    public static function run(string $name, callable $body, array &$failures): void
    {
        echo '  • ' . $name . ' ... ';
        try {
            $body();
            echo "ok\n";
        } catch (\Throwable $e) {
            echo "FAIL\n";
            $failures[] = sprintf("%s\n        %s: %s\n        at %s:%d", $name, $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }
}
