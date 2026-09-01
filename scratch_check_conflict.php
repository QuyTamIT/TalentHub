<?php
$env = parse_ini_file('.env');
$pdo = new PDO("mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_DATABASE'], $env['DB_USERNAME'], $env['DB_PASSWORD']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Student
$stmt = $pdo->prepare("SELECT sp.id FROM student_profiles sp WHERE sp.email = 'tamlangtu2005@gmail.com' LIMIT 1");
$stmt->execute();
$studentId = $stmt->fetchColumn();

// Check conflicting registrations
$candidateStart = '2026-09-05 01:30:00';
$candidateEnd = '2026-09-05 01:30:00'; // fallback to startAt since endAt is NULL

$stmt = $pdo->prepare("
    SELECT registration.id, existing.title, existing.startAt, existing.endAt
    FROM activity_registrations registration
    INNER JOIN activities existing ON existing.id = registration.activityId
    WHERE registration.studentId = :studentId
      AND registration.status IN ('pending','approved','waitlisted','attended')
      AND existing.startAt < :candidateEnd
      AND COALESCE(existing.endAt, existing.startAt) > :candidateStart
");
$stmt->execute([
    'studentId' => $studentId,
    'candidateEnd' => $candidateEnd,
    'candidateStart' => $candidateStart,
]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
