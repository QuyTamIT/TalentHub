<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

try {
    $options = getopt('', ['apply', 'school-id:', 'activity-id:', 'teacher-id:']);
    $apply = array_key_exists('apply', $options);
    $schoolId = trim((string) ($options['school-id'] ?? ''));
    $activityId = trim((string) ($options['activity-id'] ?? ''));
    $teacherId = trim((string) ($options['teacher-id'] ?? ''));

    if (!$apply) {
        echo "DRY RUN: no database changes will be made.\n";
        echo "Apply usage: php bin/fix-student.php --apply --school-id=<uuid> --activity-id=<uuid> --teacher-id=<uuid>\n";
    }
    if ($apply && (!Uuid::isValid($schoolId) || !Uuid::isValid($activityId) || !Uuid::isValid($teacherId))) {
        throw new RuntimeException('--apply requires valid --school-id, --activity-id and --teacher-id UUID values.');
    }
    if ($apply && Environment::appEnvironment() === 'production' && !Environment::boolean('ALLOW_SYNTHETIC_DATA_MUTATION')) {
        throw new RuntimeException('Synthetic evaluation sync is blocked in production unless ALLOW_SYNTHETIC_DATA_MUTATION=true.');
    }

    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();

    if (!$apply) {
        $eligible = (int) $pdo->query(
            "SELECT COUNT(*) FROM activity_registrations WHERE status IN ('approved', 'attended')"
        )->fetchColumn();
        echo "Eligible registrations across all activities: {$eligible}. Provide an explicit scope before applying.\n";
        exit(0);
    }

    $scope = $pdo->prepare(<<<'SQL'
        SELECT teacher.id
        FROM teacher_profiles teacher
        INNER JOIN activities activity
          ON activity.createdByTeacherId = teacher.id
         AND activity.schoolId = teacher.schoolId
        WHERE teacher.id = :teacher_id
          AND teacher.schoolId = :school_id
          AND activity.id = :activity_id
        LIMIT 1
        SQL);
    $scope->execute(['teacher_id' => $teacherId, 'school_id' => $schoolId, 'activity_id' => $activityId]);
    if (!$scope->fetchColumn()) {
        throw new RuntimeException('Teacher, activity and school do not form one managed grading scope.');
    }

    $criteria = $pdo->query(
        "SELECT id, minScore, maxScore FROM assessment_criteria WHERE status = 'active' ORDER BY displayOrder ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($criteria === []) {
        throw new RuntimeException('No active assessment criteria are configured.');
    }

    $studentsStmt = $pdo->prepare(<<<'SQL'
        SELECT student.id AS student_id, user.fullName
        FROM activity_registrations registration
        INNER JOIN student_profiles student ON student.id = registration.studentId
        INNER JOIN classes class ON class.id = student.classId AND class.schoolId = ?
        INNER JOIN users user ON user.id = student.userId
        WHERE registration.activityId = ?
          AND registration.status IN ('approved', 'attended')
          AND student.studyStatus = 'active'
        ORDER BY user.fullName, student.id
        SQL);
    $studentsStmt->execute([$schoolId, $activityId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $hasTalentScoreColumn = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'student_profiles' AND column_name = 'talentScore' LIMIT 1"
    )->fetchColumn();
    $sampleComments = [
        'Em thể hiện tư duy hệ thống tốt và phối hợp nhóm hiệu quả.',
        'Em hoàn thành nhiệm vụ đúng hạn và xử lý vấn đề kỹ thuật chủ động.',
        'Em nắm vững kiến thức chuyên môn; cần tự tin hơn khi thuyết trình.',
    ];

    $count = 0;
    $skipped = 0;
    $pdo->beginTransaction();
    foreach ($students as $index => $student) {
        $studentId = (string) $student['student_id'];
        $overallScore = (float) (82 + (($index * 3) % 16));
        $comment = $sampleComments[$index % count($sampleComments)];

        $assessmentStmt = $pdo->prepare(
            'SELECT id, status, teacherId FROM assessments WHERE teacherId = ? AND studentId = ? AND activityId = ? LIMIT 1 FOR UPDATE'
        );
        $assessmentStmt->execute([$teacherId, $studentId, $activityId]);
        $assessment = $assessmentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (($assessment['status'] ?? null) === 'published') {
            $skipped++;
            continue;
        }

        if ($assessment !== null) {
            $assessmentId = (string) $assessment['id'];
            $update = $pdo->prepare(
                "UPDATE assessments SET overallScore = ?, comment = ?, status = 'published', publishedAt = NOW(), version = version + 1, updatedAt = NOW() WHERE id = ? AND teacherId = ? AND status = 'draft'"
            );
            $update->execute([$overallScore, $comment, $assessmentId, $teacherId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Draft assessment changed during repair.');
            }
        } else {
            $assessmentId = Uuid::v4();
            $insert = $pdo->prepare(
                "INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, 'published', NOW(), 1, NOW(), NOW())"
            );
            $insert->execute([$assessmentId, $teacherId, $studentId, $activityId, $overallScore, $comment]);
        }

        $scoreInsert = $pdo->prepare(
            'INSERT INTO assessment_scores (id, assessmentId, criteriaId, score, createdAt, updatedAt) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE score = VALUES(score), updatedAt = NOW()'
        );
        foreach ($criteria as $criterion) {
            $min = (float) $criterion['minScore'];
            $max = (float) $criterion['maxScore'];
            $criterionScore = round($min + (($max - $min) * $overallScore / 100), 2);
            $scoreInsert->execute([Uuid::v4(), $assessmentId, $criterion['id'], $criterionScore]);
        }

        $talentScore = $overallScore;
        $skills = $pdo->prepare('UPDATE student_skills SET levelScore = ?, updatedAt = NOW() WHERE studentId = ?');
        $skills->execute([$talentScore, $studentId]);
        if ($hasTalentScoreColumn) {
            $profile = $pdo->prepare('UPDATE student_profiles SET talentScore = ?, updatedAt = NOW() WHERE id = ?');
            $profile->execute([$talentScore, $studentId]);
        }
        $count++;
    }
    $pdo->commit();

    echo "Synced {$count} assessments; skipped {$skipped} immutable published assessments.\n";
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
