<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
$classes = $pdo->query("SELECT id, name, gradeLevel, status FROM classes WHERE schoolId = '{$btecSchoolId}'")->fetchAll(PDO::FETCH_ASSOC);
print_r($classes);
