<?php
require __DIR__ . '/bin/bootstrap.php';
$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/config/database.php'))->connect();
try {
    $pdo->exec("ALTER TABLE activities ADD COLUMN cancel_deadline DATETIME(6) NULL DEFAULT NULL AFTER registration_deadline");
    echo "Added cancel_deadline successfully\n";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "Column already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
