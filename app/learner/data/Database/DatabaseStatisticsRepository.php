<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseStatisticsRepository extends AbstractDatabaseRepository implements StatisticsRepository
{
    public function lifetimeFacts(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $hoursSql = <<<'SQL'
            SELECT COALESCE(SUM(hours), 0) AS total_hours
            FROM experience_logs
            WHERE studentId = :student_id AND status = 'confirmed'
        SQL;
        $hoursRow = $this->fetchOne('lifetimeExperienceHours', $hoursSql, ['student_id' => $studentId]);

        $attendedSql = <<<'SQL'
            SELECT COUNT(DISTINCT ar.id) AS attended_count
            FROM activity_registrations ar
            INNER JOIN checkins c ON c.registrationId = ar.id
            WHERE ar.studentId = :student_id
              AND ar.status = 'attended'
              AND c.status = 'confirmed'
        SQL;
        $attendedRow = $this->fetchOne('lifetimeAttendedActivities', $attendedSql, ['student_id' => $studentId]);

        $assessmentSql = <<<'SQL'
            SELECT COUNT(DISTINCT tt.type) AS test_type_count
            FROM test_attempts ta
            INNER JOIN talent_tests tt ON tt.id = ta.testId
            INNER JOIN test_results tr ON tr.attemptId = ta.id
            WHERE ta.studentId = :student_id AND ta.status = 'submitted'
        SQL;
        $assessmentRow = $this->fetchOne('lifetimeAssessmentTypes', $assessmentSql, ['student_id' => $studentId]);

        $evalSql = <<<'SQL'
            SELECT COUNT(*) AS eval_count
            FROM assessments
            WHERE studentId = :student_id
              AND status = 'published'
              AND publishedAt IS NOT NULL
        SQL;
        $evalRow = $this->fetchOne('lifetimePublishedEvaluations', $evalSql, ['student_id' => $studentId]);

        return [
            'confirmed_experience_hours' => round((float) ($hoursRow['total_hours'] ?? 0.0), 2),
            'attended_activity_count' => (int) ($attendedRow['attended_count'] ?? 0),
            'submitted_assessment_type_count' => (int) ($assessmentRow['test_type_count'] ?? 0),
            'published_teacher_evaluation_count' => (int) ($evalRow['eval_count'] ?? 0),
        ];
    }

    public function periodStatistics(string $studentId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $fromUtc = $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $toUtc = $to->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $params = [
            'student_id' => $studentId,
            'from_time' => $fromUtc,
            'to_time' => $toUtc,
        ];

        $hoursSql = <<<'SQL'
            SELECT COALESCE(SUM(hours), 0) AS total_hours
            FROM experience_logs
            WHERE studentId = :student_id
              AND status = 'confirmed'
              AND confirmedAt >= :from_time
              AND confirmedAt < :to_time
        SQL;
        $hoursRow = $this->fetchOne('periodHours', $hoursSql, $params);

        $activitiesSql = <<<'SQL'
            SELECT COUNT(DISTINCT ar.id) AS activity_count
            FROM activity_registrations ar
            INNER JOIN checkins c ON c.registrationId = ar.id
            WHERE ar.studentId = :student_id
              AND ar.status = 'attended'
              AND c.status = 'confirmed'
              AND c.confirmedAt >= :from_time
              AND c.confirmedAt < :to_time
        SQL;
        $activitiesRow = $this->fetchOne('periodActivities', $activitiesSql, $params);

        $assessmentsSql = <<<'SQL'
            SELECT COUNT(*) AS assessment_count
            FROM test_attempts ta
            INNER JOIN test_results tr ON tr.attemptId = ta.id
            WHERE ta.studentId = :student_id
              AND ta.status = 'submitted'
              AND ta.submittedAt >= :from_time
              AND ta.submittedAt < :to_time
        SQL;
        $assessmentsRow = $this->fetchOne('periodAssessments', $assessmentsSql, $params);

        $evalSql = <<<'SQL'
            SELECT COUNT(*) AS eval_count
            FROM assessments
            WHERE studentId = :student_id
              AND status = 'published'
              AND publishedAt IS NOT NULL
              AND publishedAt >= :from_time
              AND publishedAt < :to_time
        SQL;
        $evalRow = $this->fetchOne('periodEvaluations', $evalSql, $params);

        $badgeCount = 0;
        if ($this->hasTable('student_badges')) {
            $badgeSql = <<<'SQL'
                SELECT COUNT(*) AS badge_count
                FROM student_badges
                WHERE studentId = :student_id
                  AND awardedAt >= :from_time
                  AND awardedAt < :to_time
            SQL;
            $badgeRow = $this->fetchOne('periodBadges', $badgeSql, $params);
            $badgeCount = (int) ($badgeRow['badge_count'] ?? 0);
        }

        // Daily buckets
        $dailyBucketsSql = <<<'SQL'
            SELECT DATE(confirmedAt) AS log_date, COALESCE(SUM(hours), 0) AS daily_hours
            FROM experience_logs
            WHERE studentId = :student_id
              AND status = 'confirmed'
              AND confirmedAt >= :from_time
              AND confirmedAt < :to_time
            GROUP BY DATE(confirmedAt)
        SQL;
        $dailyRows = $this->fetchAll('periodDailyBuckets', $dailyBucketsSql, $params);
        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dailyMap[(string) $row['log_date']] = round((float) ($row['daily_hours'] ?? 0.0), 2);
        }

        $buckets = [];
        $currentDate = $from->setTimezone(new DateTimeZone('UTC'));
        $endDate = $to->setTimezone(new DateTimeZone('UTC'));
        $dayInterval = new DateInterval('P1D');

        while ($currentDate < $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $buckets[] = [
                'date' => $dateStr,
                'label' => $currentDate->format('d/m'),
                'hours' => $dailyMap[$dateStr] ?? 0.0,
            ];
            $currentDate = $currentDate->add($dayInterval);
        }

        // Category distribution
        $catSql = <<<'SQL'
            SELECT a.category, COALESCE(SUM(el.hours), 0) AS category_hours
            FROM experience_logs el
            INNER JOIN activities a ON a.id = el.activityId
            WHERE el.studentId = :student_id
              AND el.status = 'confirmed'
              AND el.confirmedAt >= :from_time
              AND el.confirmedAt < :to_time
            GROUP BY a.category
            ORDER BY category_hours DESC, a.category ASC
        SQL;
        $catRows = $this->fetchAll('periodCategories', $catSql, $params);
        $categories = [];
        foreach ($catRows as $catRow) {
            $categories[] = [
                'category' => (string) ($catRow['category'] ?? 'general'),
                'hours' => round((float) ($catRow['category_hours'] ?? 0.0), 2),
            ];
        }

        return [
            'hours' => round((float) ($hoursRow['total_hours'] ?? 0.0), 2),
            'activities' => (int) ($activitiesRow['activity_count'] ?? 0),
            'assessments' => (int) ($assessmentsRow['assessment_count'] ?? 0),
            'evaluations' => (int) ($evalRow['eval_count'] ?? 0),
            'badges' => $badgeCount,
            'experience_buckets' => $buckets,
            'category_distribution' => $categories,
        ];
    }

    private function hasTable(string $table): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
