<?php
require __DIR__ . '/bin/bootstrap.php';
$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/config/database.php'))->connect();
try {
    $pdo->exec("ALTER TABLE activities ADD COLUMN registration_deadline DATETIME(6) NULL DEFAULT NULL AFTER endAt");
    echo "Added registration_deadline successfully\n";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "Column already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
