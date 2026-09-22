<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\RepositoryFactory;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$now = gmdate('Y-m-d H:i:s.u');

function richId(string $suffix): string
{
    return sprintf('25000000-0000-4000-8000-%012d', (int) $suffix);
}

function richConfirmAttendance(
    PDO $pdo,
    string $registrationId,
    string $studentId,
    string $activityId,
    string $teacherProfileId,
    float $confirmedHours,
    string $now
): void {
    $hours = number_format(min(24.0, max(0.0, $confirmedHours)), 2, '.', '');
    $pdo->prepare(
        "INSERT INTO activity_experience_policies (activityId, confirmedHours, createdAt, updatedAt)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE confirmedHours = VALUES(confirmedHours), updatedAt = VALUES(updatedAt)"
    )->execute([$activityId, $hours, $now, $now]);

    $qrId = richId((string) (7500000 + (int) substr($activityId, -4)));
    $tokenHash = hash('sha256', 'rich-seed-qr:' . $activityId);
    $pdo->prepare(
        "INSERT INTO activity_qr_sessions
            (id, activityId, createdByTeacherId, tokenHash, status, expiresAt, maxScans, usedScans, revokedAt, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, 'active', ?, 200, 0, NULL, ?, ?)
         ON DUPLICATE KEY UPDATE status = 'active', revokedAt = NULL, updatedAt = VALUES(updatedAt)"
    )->execute([
        $qrId,
        $activityId,
        $teacherProfileId,
        $tokenHash,
        (new DateTimeImmutable('+365 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
        $now,
        $now,
    ]);

    $existingCheckin = $pdo->prepare('SELECT id FROM checkins WHERE registrationId = ? LIMIT 1');
    $existingCheckin->execute([$registrationId]);
    $checkinId = $existingCheckin->fetchColumn();
    if (!is_string($checkinId) || $checkinId === '') {
        $checkinId = Uuid::v4();
        $pdo->prepare(
            "INSERT INTO checkins (id, registrationId, qrSessionId, status, checkedInAt, confirmedAt, createdAt)
             VALUES (?, ?, ?, 'confirmed', ?, ?, ?)"
        )->execute([$checkinId, $registrationId, $qrId, $now, $now, $now]);
        $pdo->prepare(
            'UPDATE activity_qr_sessions SET usedScans = LEAST(maxScans, usedScans + 1), updatedAt = ? WHERE id = ?'
        )->execute([$now, $qrId]);
    } else {
        $pdo->prepare(
            "UPDATE checkins SET status = 'confirmed', confirmedAt = COALESCE(confirmedAt, ?), checkedInAt = COALESCE(checkedInAt, ?) WHERE id = ?"
        )->execute([$now, $now, $checkinId]);
    }

    $existingExp = $pdo->prepare('SELECT id FROM experience_logs WHERE checkinId = ? LIMIT 1');
    $existingExp->execute([$checkinId]);
    $expId = $existingExp->fetchColumn();
    if (!is_string($expId) || $expId === '') {
        $pdo->prepare(
            "INSERT INTO experience_logs (id, studentId, activityId, checkinId, hours, status, auditReason, confirmedAt, createdAt)
             VALUES (?, ?, ?, ?, ?, 'confirmed', 'rich_seed_attendance', ?, ?)"
        )->execute([Uuid::v4(), $studentId, $activityId, $checkinId, $hours, $now, $now]);
        return;
    }

    $pdo->prepare(
        "UPDATE experience_logs
         SET hours = ?, status = 'confirmed', confirmedAt = COALESCE(confirmedAt, ?), auditReason = 'rich_seed_attendance'
         WHERE id = ?"
    )->execute([$hours, $now, $expId]);
}

echo "Fix rich-seed consistency...\n";

$pdo->beginTransaction();
try {
    $deletedBadges = $pdo->exec(
        "DELETE FROM student_badges
         WHERE JSON_UNQUOTE(JSON_EXTRACT(awardContext, '$.source')) = 'rich_seed'"
    );
    echo "  Removed fake rich_seed badges: {$deletedBadges}\n";

    $fullSetEmails = $pdo->query(
        "SELECT sp.id
         FROM student_profiles sp
         INNER JOIN users u ON u.id = sp.userId
         WHERE sp.id LIKE '25000000-0000-4000-8000-%'
           AND (
             SELECT COUNT(DISTINCT tt.type)
             FROM test_attempts ta
             INNER JOIN talent_tests tt ON tt.id = ta.testId
             INNER JOIN test_results tr ON tr.attemptId = ta.id
             WHERE ta.studentId = sp.id AND ta.status = 'submitted'
           ) >= 4"
    )->fetchAll(PDO::FETCH_COLUMN);

    $fullIds = array_values(array_filter(array_map('strval', $fullSetEmails)));
    if ($fullIds !== []) {
        $placeholders = implode(',', array_fill(0, count($fullIds), '?'));
        $delCert = $pdo->prepare(
            "DELETE FROM student_certificates
             WHERE JSON_UNQUOTE(JSON_EXTRACT(evidenceContext, '$.source')) = 'rich_seed'
               AND studentId NOT IN ({$placeholders})"
        );
        $delCert->execute($fullIds);
        echo '  Removed rich_seed certs for incomplete students: ' . $delCert->rowCount() . "\n";
    } else {
        $deletedCerts = $pdo->exec(
            "DELETE FROM student_certificates
             WHERE JSON_UNQUOTE(JSON_EXTRACT(evidenceContext, '$.source')) = 'rich_seed'"
        );
        echo "  Removed all rich_seed certs (no 4/4 students found): {$deletedCerts}\n";
    }

    $teacherId = (string) $pdo->query(
        "SELECT tp.id FROM teacher_profiles tp
         INNER JOIN activities a ON a.createdByTeacherId = tp.id
         WHERE a.id LIKE '25000000-0000-4000-8000-000000007%'
         LIMIT 1"
    )->fetchColumn();
    if ($teacherId === '') {
        $teacherId = (string) $pdo->query('SELECT id FROM teacher_profiles LIMIT 1')->fetchColumn();
    }

    $regs = $pdo->query(
        "SELECT ar.id AS registrationId, ar.studentId, ar.activityId,
                LEAST(24, COALESCE(NULLIF(p.confirmedHours, 0), TIMESTAMPDIFF(HOUR, a.startAt, a.endAt), 1)) AS hours
         FROM activity_registrations ar
         INNER JOIN activities a ON a.id = ar.activityId
         LEFT JOIN activity_experience_policies p ON p.activityId = a.id
         WHERE ar.status = 'attended'
           AND ar.id LIKE '25000000-0000-4000-8000-%'
           AND a.id LIKE '25000000-0000-4000-8000-%'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $confirmed = 0;
    foreach ($regs as $row) {
        richConfirmAttendance(
            $pdo,
            (string) $row['registrationId'],
            (string) $row['studentId'],
            (string) $row['activityId'],
            $teacherId,
            (float) $row['hours'],
            $now
        );
        $confirmed++;
    }
    echo "  Backfilled checkin+hours for attended regs: {$confirmed}\n";

    require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
    $badgeAward = (new RepositoryFactory('database', $pdo))->badgeAwardService();
    $students = $pdo->query(
        "SELECT id FROM student_profiles WHERE id LIKE '25000000-0000-4000-8000-%'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $awardedTotal = 0;
    foreach ($students as $studentId) {
        $awarded = $badgeAward->evaluateAndAward((string) $studentId, 'system');
        $awardedTotal += count($awarded);
    }
    echo "  Rule-based badges awarded: {$awardedTotal}\n";

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

$sample = $pdo->query(
    "SELECT sp.id,
            (SELECT COALESCE(SUM(el.hours), 0)
             FROM experience_logs el
             INNER JOIN checkins c ON c.id = el.checkinId AND c.status = 'confirmed' AND c.confirmedAt IS NOT NULL
             INNER JOIN activity_registrations ar ON ar.id = c.registrationId
               AND ar.studentId = el.studentId AND ar.activityId = el.activityId AND ar.status = 'attended'
             WHERE el.studentId = sp.id AND el.status = 'confirmed' AND el.confirmedAt IS NOT NULL) AS hours,
            (SELECT COUNT(*) FROM student_badges sb WHERE sb.studentId = sp.id) AS badges,
            (SELECT COUNT(DISTINCT tt.type)
             FROM test_attempts ta
             INNER JOIN talent_tests tt ON tt.id = ta.testId
             INNER JOIN test_results tr ON tr.attemptId = ta.id
             WHERE ta.studentId = sp.id AND ta.status = 'submitted') AS assessments
     FROM student_profiles sp
     WHERE sp.id LIKE '25000000-0000-4000-8000-%'
     ORDER BY badges DESC, hours DESC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

echo "\nSample after fix (hours / badges / assessments):\n";
foreach ($sample as $row) {
    echo sprintf(
        "  %s | %sh | %s badges | %s assessments\n",
        substr((string) $row['id'], -4),
        $row['hours'],
        $row['badges'],
        $row['assessments']
    );
}

echo "\n[OK] Rich-seed consistency fixed\n";
