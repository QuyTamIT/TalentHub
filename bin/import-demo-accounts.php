<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->beginTransaction();

try {
    $sql = file_get_contents(dirname(__DIR__) . '/Database/seeds/seed_demo_accounts.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Could not read seed_demo_accounts.sql.');
    }

    $pdo->exec($sql);
    $pdo->commit();
    echo "4 Demo accounts imported and linked successfully (Password: Talenthub@123).\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
