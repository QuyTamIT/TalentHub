<?php
declare(strict_types=1);

/**
 * Entry point for the policy test suite.
 *
 *   php tests/Policy/run.php
 *
 * Returns 0 on success, 1 on any failure. Uses the lightweight
 * `Tests\TestCase` runner, intentionally independent of PHPUnit.
 */

require dirname(__DIR__) . '/Assert.php';
require dirname(__DIR__) . '/AssertionFailedException.php';
require dirname(__DIR__) . '/TestCase.php';

// Load test support files first
require_once dirname(__DIR__) . '/Policy/TestCaseTrait.php';

// Autoload: src/ classes and tests/ classes
spl_autoload_register(static function (string $class): void {
    $root = dirname(__DIR__, 2);

    // TalentHub\* → src/
    if (str_starts_with($class, 'TalentHub\\')) {
        $relative = substr($class, strlen('TalentHub\\'));
        $srcFile = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($srcFile)) { require_once $srcFile; return; }
        // TalentHub\Tests\* → tests/
        if (str_starts_with($relative, 'Tests\\')) {
            $inner = substr($relative, strlen('Tests\\'));
            $testFile = dirname(__DIR__) . '/' . str_replace('\\', '/', $inner) . '.php';
            if (is_file($testFile)) { require_once $testFile; }
        }
    }
    // Tests\* → tests/
    if (str_starts_with($class, 'Tests\\')) {
        $relative = substr($class, strlen('Tests\\'));
        $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});

// Load all src/ dependencies
$root = dirname(__DIR__, 2);
foreach ([
    '/src/Support/Clock/ClockInterface.php',
    '/src/Support/Clock/SystemClock.php',
    '/src/Support/Clock/FixedClock.php',
    '/src/Http/ApiException.php',
    '/src/Domain/ErrorCodes.php',
    '/src/Domain/PolicyViolation.php',
    '/src/Domain/Activity/ActivityPolicy.php',
    '/src/Domain/Activity/CatalogBucket.php',
    '/src/Domain/Activity/RegistrationPolicy.php',
    '/src/Domain/Internship/InternshipPolicy.php',
    '/src/Domain/Profile/ProfilePolicy.php',
] as $path) {
    require_once $root . $path;
}

// Load test suite classes
foreach (glob(dirname(__DIR__) . '/Policy/*Test.php') as $file) {
    require_once $file;
}

use Tests\AssertionFailedException;

$suites = [
    new \TalentHub\Tests\Policy\ActivityPolicyTest(),
    new \TalentHub\Tests\Policy\RegistrationPolicyTest(),
    new \TalentHub\Tests\Policy\InternshipPolicyTest(),
    new \TalentHub\Tests\Policy\ProfilePolicyTest(),
];

$failures = [];
$total = 0;
foreach ($suites as $suite) {
    echo "\n== " . $suite->name() . " ==\n";
    $methods = get_class_methods($suite);
    foreach ($methods as $method) {
        if (str_starts_with($method, '__') || $method === 'name' || $method === 'tests') {
            continue;
        }
        $total++;
        echo '  • ' . $method . ' ... ';
        try {
            $suite->$method();
            echo "ok\n";
        } catch (\Throwable $e) {
            echo "FAIL\n";
            $failures[] = sprintf(
                "%s::%s\n        %s: %s\n        at %s:%d",
                $suite::class,
                $method,
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            );
        }
    }
}

echo "\n--- Summary ---\n";
echo 'Tests: ' . $total . "\n";
echo 'Failures: ' . count($failures) . "\n";

if ($failures !== []) {
    foreach ($failures as $i => $f) {
        echo sprintf("  [%d] %s\n", $i + 1, $f);
    }
    exit(1);
}

echo "All policy tests passed.\n";
exit(0);
