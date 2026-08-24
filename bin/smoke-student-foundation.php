<?php
declare(strict_types=1);

$test = dirname(__DIR__) . '/tests/learner_foundation_mysql_test.php';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test);
passthru($command, $exitCode);
exit($exitCode);
