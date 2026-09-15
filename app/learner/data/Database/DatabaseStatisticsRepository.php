<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;
use TalentHub\Learner\Data\Exceptions\LearnerDataQueryException;
use TalentHub\Learner\Data\Support\Uuid;

require_once __DIR__ . '/../Service/EvidenceBackedScoreService.php';

final class DatabaseStatisticsRepository extends AbstractDatabaseRepository implements StatisticsRepository
{
    public function __construct(PDO $pdo, private readonly ?\TalentHub\Learner\Data\Service\ScoreViewer $scoreViewer = null)
    {
        parent::__construct($pdo);
    }

    private function officialScores(string $studentId): array
    {
        return (new \TalentHub\Learner\Data\Service\EvidenceBackedScoreService($this->pdo))->forStudent(
            $studentId, $this->scoreViewer ?? \TalentHub\Learner\Data\Service\ScoreViewer::fromSession()
        );
    }

    public function lifetimeFacts(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $hoursSql = <<<'SQL'
            SELECT COALESCE(SUM(el.hours), 0) AS total_hours
            FROM experience_logs el
            INNER JOIN checkins c ON c.id = el.checkinId
                AND c.status = 'confirmed'
                AND c.confirmedAt IS NOT NULL
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
                AND ar.studentId = el.studentId
                AND ar.activityId = el.activityId
                AND ar.status = 'attended'
            WHERE el.studentId = :student_id
              AND el.status = 'confirmed'
              AND el.confirmedAt IS NOT NULL
        SQL;
        $hoursRow = $this->fetchOne('lifetimeExperienceHours', $hoursSql, ['student_id' => $studentId]);

        $attendedSql = <<<'SQL'
            SELECT COUNT(DISTINCT ar.id) AS attended_count
            FROM activity_registrations ar
            INNER JOIN checkins c ON c.registrationId = ar.id
            WHERE ar.studentId = :student_id
              AND ar.status = 'attended'
              AND c.status = 'confirmed'
              AND c.confirmedAt IS NOT NULL
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
            SELECT COALESCE(SUM(el.hours), 0) AS total_hours
            FROM experience_logs el
            INNER JOIN checkins c ON c.id = el.checkinId
                AND c.status = 'confirmed'
                AND c.confirmedAt IS NOT NULL
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
                AND ar.studentId = el.studentId
                AND ar.activityId = el.activityId
                AND ar.status = 'attended'
            WHERE el.studentId = :student_id
              AND el.status = 'confirmed'
              AND el.confirmedAt IS NOT NULL
              AND el.confirmedAt >= :from_time
              AND el.confirmedAt < :to_time
        SQL;
        $hoursRow = $this->fetchOne('periodHours', $hoursSql, $params);

        $activitiesSql = <<<'SQL'
            SELECT COUNT(DISTINCT ar.id) AS activity_count
            FROM activity_registrations ar
            INNER JOIN checkins c ON c.registrationId = ar.id
            WHERE ar.studentId = :student_id
              AND ar.status = 'attended'
              AND c.status = 'confirmed'
              AND c.confirmedAt IS NOT NULL
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
            SELECT DATE(el.confirmedAt) AS log_date, COALESCE(SUM(el.hours), 0) AS daily_hours
            FROM experience_logs el
            INNER JOIN checkins c ON c.id = el.checkinId
                AND c.status = 'confirmed'
                AND c.confirmedAt IS NOT NULL
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
                AND ar.studentId = el.studentId
                AND ar.activityId = el.activityId
                AND ar.status = 'attended'
            WHERE el.studentId = :student_id
              AND el.status = 'confirmed'
              AND el.confirmedAt IS NOT NULL
              AND el.confirmedAt >= :from_time
              AND el.confirmedAt < :to_time
            GROUP BY DATE(el.confirmedAt)
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
            INNER JOIN checkins c ON c.id = el.checkinId
                AND c.status = 'confirmed'
                AND c.confirmedAt IS NOT NULL
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
                AND ar.studentId = el.studentId
                AND ar.activityId = el.activityId
                AND ar.status = 'attended'
            INNER JOIN activities a ON a.id = el.activityId
            WHERE el.studentId = :student_id
              AND el.status = 'confirmed'
              AND el.confirmedAt IS NOT NULL
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

    public function checkinStreakDays(string $studentId, ?DateTimeImmutable $now = null): int
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $streakSql = <<<'SQL'
            SELECT DISTINCT DATE(c.confirmedAt) AS checkin_date
            FROM checkins c
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
            WHERE ar.studentId = :student_id
              AND c.status = 'confirmed'
              AND c.confirmedAt IS NOT NULL
            ORDER BY checkin_date DESC
        SQL;

        try {
            $rows = $this->fetchAll('checkinStreakDays', $streakSql, ['student_id' => $studentId]);
        } catch (LearnerDataQueryException) {
            return 0;
        }

        $dates = [];
        foreach ($rows as $row) {
            $date = (string) ($row['checkin_date'] ?? '');
            if ($date !== '') {
                $dates[$date] = true;
            }
        }

        if ($dates === []) {
            return 0;
        }

        $cursor = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->setTime(0, 0);
        // Grace: allow the streak to start yesterday when today has no check-in yet.
        if (!isset($dates[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('-1 day');
        }

        $streak = 0;
        while (isset($dates[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    public function skillCompetencies(string $studentId): array
    {
        $result = $this->officialScores(Uuid::normalizeDatabase($studentId, 'student_id'));
        $skills = [];
        foreach ($result['skills'] as $skill) {
            if ($skill['state'] !== 'scored' || $skill['score'] === null) continue;
            $skills[] = ['name' => $skill['name'], 'category' => $skill['category'], 'score' => $skill['score']];
        }
        usort($skills, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['name'], $b['name']));
        return $skills;
    }

    public function psychometricResults(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $families = ['holland', 'mbti', 'disc', 'gardner'];
        $results = array_fill_keys($families, null);

        $submittedTypesSql = <<<'SQL'
            SELECT tt.type AS test_type, MAX(ta.submittedAt) AS last_submitted
            FROM test_attempts ta
            INNER JOIN talent_tests tt ON tt.id = ta.testId
            WHERE ta.studentId = :student_id
              AND ta.status = 'submitted'
            GROUP BY tt.type
            ORDER BY last_submitted DESC
        SQL;

        try {
            $submittedRows = $this->fetchAll('psychometricSubmittedTypes', $submittedTypesSql, ['student_id' => $studentId]);
        } catch (LearnerDataQueryException) {
            return $results;
        }

        $detailRows = [];
        try {
            $detailsSql = <<<'SQL'
                SELECT tt.type AS test_type, tr.resultCode AS result_code, tr.summary AS result_summary,
                       tr.dimensionScoresJson AS dimension_scores_json, ta.submittedAt AS submitted_at
                FROM test_attempts ta
                INNER JOIN talent_tests tt ON tt.id = ta.testId
                INNER JOIN test_results tr ON tr.attemptId = ta.id
                WHERE ta.studentId = :student_id
                  AND ta.status = 'submitted'
                ORDER BY ta.submittedAt DESC
            SQL;
            $detailRows = $this->fetchAll('psychometricResultDetails', $detailsSql, ['student_id' => $studentId]);
        } catch (LearnerDataQueryException) {
            $detailRows = [];
        }

        $latestDetailByType = [];
        foreach ($detailRows as $row) {
            $type = (string) ($row['test_type'] ?? '');
            if ($type !== '' && !isset($latestDetailByType[$type])) {
                $latestDetailByType[$type] = $row;
            }
        }

        foreach ($submittedRows as $row) {
            $type = (string) ($row['test_type'] ?? '');
            $family = $this->psychometricFamily($type);
            if ($family === null || $results[$family] !== null) {
                continue;
            }

            $detail = $latestDetailByType[$type] ?? null;
            if ($detail === null) {
                $results[$family] = [
                    'type' => $type,
                    'result_code' => '',
                    'summary' => '',
                    'dimension_scores' => [],
                    'submitted_at' => (string) ($row['last_submitted'] ?? ''),
                ];
                continue;
            }

            try {
                $decodedScores = $this->decodeJson($detail['dimension_scores_json'] ?? null, 'dimensionScoresJson');
                $dimensionScores = [];
                foreach ($decodedScores as $scoreKey => $scoreValue) {
                    $dimensionScores[(string) $scoreKey] = (float) $scoreValue;
                }
            } catch (\Throwable) {
                $dimensionScores = [];
            }

            $results[$family] = [
                'type' => $type,
                'result_code' => (string) ($detail['result_code'] ?? ''),
                'summary' => (string) ($detail['result_summary'] ?? ''),
                'dimension_scores' => $dimensionScores,
                'submitted_at' => (string) ($detail['submitted_at'] ?? ''),
            ];
        }

        return $results;
    }

    public function latestPublishedEvaluation(string $studentId): array
    {
        $rows = $this->officialScores(Uuid::normalizeDatabase($studentId, 'student_id'))['teacher_context_assessments'];
        usort($rows, static fn($a,$b) => strcmp($b['published_at'],$a['published_at']) ?: strcmp($b['assessment_id'],$a['assessment_id']));
        $row = $rows[0] ?? null;
        return ['total_score' => $row['overall_score'] ?? null, 'comment' => $row['comment'] ?? '',
            'published_at' => $row['published_at'] ?? null, 'criteria' => $row['criteria'] ?? []];
    }

    public function projectStatistics(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        if (!$this->hasTable('projects') || !$this->hasTable('project_members')) {
            return ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'leader_roles' => 0, 'featured' => []];
        }

        $projectsSql = <<<'SQL'
            SELECT p.title AS project_title, p.status AS project_status, pm.role AS member_role
            FROM projects p
            INNER JOIN project_members pm ON pm.projectId = p.id
            WHERE pm.studentId = :student_id
              AND pm.status = 'active'
              AND p.status = 'completed'
            ORDER BY p.createdAt DESC, p.title ASC
        SQL;

        try {
            $rows = $this->fetchAll('projectStatistics', $projectsSql, ['student_id' => $studentId]);
        } catch (LearnerDataQueryException) {
            return ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'leader_roles' => 0, 'featured' => []];
        }

        $stats = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'leader_roles' => 0, 'featured' => []];
        foreach ($rows as $row) {
            $status = (string) ($row['project_status'] ?? '');
            $role = (string) ($row['member_role'] ?? 'member');
            $stats['total']++;
            if ($status === 'completed') {
                $stats['completed']++;
            } elseif ($status === 'in_progress') {
                $stats['in_progress']++;
            }
            if ($this->isLeaderRole($role)) {
                $stats['leader_roles']++;
            }
            if (count($stats['featured']) < 3) {
                $stats['featured'][] = [
                    'name' => (string) ($row['project_title'] ?? ''),
                    'role' => $role,
                    'status' => $status,
                ];
            }
        }

        return $stats;
    }

    private function psychometricFamily(string $type): ?string
    {
        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'holland', 'holland-riasec' => 'holland',
            'mbti' => 'mbti',
            'disc' => 'disc',
            'multiple-intelligence', 'multiple_intelligence', 'gardner' => 'gardner',
            default => null,
        };
    }

    private function isLeaderRole(string $role): bool
    {
        $normalized = strtolower(trim($role));
        return str_contains($normalized, 'lead') || str_contains($normalized, 'truong') || str_contains($normalized, 'trưởng');
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

    private function hasColumn(string $table, string $column): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("PRAGMA table_info({$table})");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (strcasecmp((string) ($col['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
