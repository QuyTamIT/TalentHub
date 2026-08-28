<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$stmt = $pdo->query("
    SELECT a.id, a.title, a.status, a.schoolId, a.createdByTeacherId, a.startAt, a.endAt,
           s.name AS schoolName, u.fullName AS teacherName
    FROM activities a
    LEFT JOIN schools s ON s.id = a.schoolId
    LEFT JOIN teacher_profiles tp ON tp.id = a.createdByTeacherId
    LEFT JOIN users u ON u.id = tp.userId
    ORDER BY a.startAt DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "All Activities in DB:\n";
foreach ($rows as $r) {
    echo "- [{$r['status']}] '{$r['title']}' | Teacher: {$r['teacherName']} ({$r['createdByTeacherId']}) | School: {$r['schoolName']} | Start: {$r['startAt']} | End: {$r['endAt']}\n";
}
