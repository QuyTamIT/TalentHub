<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;

require_once dirname(__DIR__, 3) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/CompleteAiDemoDataset.php';

final class CompleteAiDemoVerifier
{
    private const HERO_MINIMUMS = [
        'skills' => 5,
        'assessments' => 4,
        'activities' => 2,
        'evaluations' => 2,
        'opportunities' => 1,
    ];

    /** @return array{ok:bool,counts:array<string,int>,violations:list<string>,heroes:array<string,array<string,mixed>>} */
    public static function verify(PDO $pdo, ?DateTimeImmutable $clock = null): array
    {
        $clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $counts = self::counts($pdo);
        $violations = [];
        $consentPolicy = new ConsentPolicy(new DatabaseConsentSource($pdo));

        foreach ([
            'organizations' => 2,
            'users' => 31,
            'teacher_profiles' => 10,
            'learner_profiles' => 19,
        ] as $name => $expected) {
            if ($counts[$name] !== $expected) {
                $violations[] = 'count_' . $name;
            }
        }
        foreach (CompleteAiDemoDataset::expectedMinimums() as $name => $minimum) {
            if (($counts[$name] ?? 0) < $minimum) {
                $violations[] = 'minimum_' . $name;
            }
        }

        if (self::invalidConsentScopes($consentPolicy) !== 0) {
            $violations[] = 'consent_scope_closure';
        }
        if (self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM test_attempts attempt
INNER JOIN student_profiles student ON student.id = attempt.studentId
INNER JOIN classes class ON class.id = student.classId
INNER JOIN talent_tests test ON test.id = attempt.testId
WHERE (attempt.id LIKE '21000000-%' OR attempt.id LIKE '22000000-%')
  AND ((class.schoolId = '20000000-0000-4000-8000-000000000001' AND test.code NOT LIKE '%\\_high')
    OR (class.schoolId LIKE '22000000-%' AND test.code NOT LIKE '%\\_college'))
SQL) !== 0) {
            $violations[] = 'assessment_band_mismatch';
        }
        if (self::invalidAssessmentClosure($pdo) !== 0) {
            $violations[] = 'assessment_closure';
        }
        if (self::invalidJourneyClosure($pdo) !== 0) {
            $violations[] = 'journey_closure';
        }
        if (self::invalidQrHashes($pdo) !== 0) {
            $violations[] = 'qr_hash_closure';
        }
        if (self::hasRawQrTokenStorage($pdo)) {
            $violations[] = 'qr_raw_token_storage';
        }

        $heroes = self::heroes($pdo, $clock, $consentPolicy, $violations);
        $violations = array_values(array_unique($violations));
        sort($violations, SORT_STRING);

        return [
            'ok' => $violations === [],
            'counts' => $counts,
            'violations' => $violations,
            'heroes' => $heroes,
        ];
    }

    /** @return array<string,int> */
    private static function counts(PDO $pdo): array
    {
        return [
            'organizations' => self::demoEntityCount($pdo, 'schools'),
            'users' => self::demoEntityCount($pdo, 'users'),
            'teacher_profiles' => self::demoEntityCount($pdo, 'teacher_profiles'),
            'learner_profiles' => self::demoEntityCount($pdo, 'student_profiles'),
            'learners' => self::demoEntityCount($pdo, 'student_profiles'),
            'activities' => self::ownedCount($pdo, 'activities'),
            'registrations' => self::ownedCount($pdo, 'activity_registrations'),
            'checkins' => self::ownedCount($pdo, 'checkins'),
            'experiences' => self::ownedCount($pdo, 'experience_logs'),
            'published_evaluations' => self::scalar($pdo, "SELECT COUNT(*) FROM assessments WHERE (id LIKE '21000000-%' OR id LIKE '22000000-%') AND status='published'"),
            'consent_events' => self::ownedCount($pdo, 'learner_ai_consent_events'),
            'model_runs' => self::scalar($pdo, "SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='model'"),
        ];
    }

    /** @param list<string> $violations @return array<string,array<string,mixed>> */
    private static function heroes(PDO $pdo, DateTimeImmutable $clock, ConsentPolicy $consent, array &$violations): array
    {
        $snapshot = new RecommendationSnapshotBuilder(
            new DatabaseStudentProfileSource($pdo),
            new DatabaseSkillSource($pdo),
            new DatabaseAssessmentSource($pdo),
            new DatabaseActivityExperienceSource($pdo),
            new DatabasePublishedEvaluationSource($pdo),
            new DatabaseOpportunitySource($pdo, $clock),
        );
        $qualityGate = new DataQualityGate($clock);
        $recommendations = new DatabaseRecommendationRepository($pdo);
        $results = [];

        foreach (CompleteAiDemoDataset::heroStudentIds() as $hero => $studentId) {
            $allowedScopes = $consent->allowedScopes($studentId);
            $input = $snapshot->build($studentId, $allowedScopes);
            $sourceCounts = $input->qualityFlags()['source_counts'] ?? [];
            $normalizedCounts = [];
            foreach (self::HERO_MINIMUMS as $source => $minimum) {
                $count = (int) ($sourceCounts[$source] ?? 0);
                $normalizedCounts[$source] = $count;
                if ($count < $minimum) {
                    $violations[] = 'hero_' . $hero . '_' . $source;
                }
            }
            if (count($allowedScopes) !== 4) {
                $violations[] = 'hero_' . $hero . '_consent';
            }
            $quality = $qualityGate->evaluate($input);
            if ($quality->state() !== 'ready') {
                $violations[] = 'hero_' . $hero . '_quality_' . $quality->state();
            }
            $visibleRun = $recommendations->latestForStudent($studentId);
            $visibleEngine = 'none';
            if ($visibleRun !== null) {
                $visibleEngine = is_string($visibleRun['engineType'] ?? null)
                    ? $visibleRun['engineType']
                    : 'unknown';
                if ($visibleEngine !== 'rule'
                    || ($visibleRun['status'] ?? null) !== 'completed'
                    || !is_array($visibleRun['items'] ?? null)
                    || $visibleRun['items'] === []) {
                    $violations[] = 'hero_' . $hero . '_visible_recommendation';
                }
            }
            $results[$hero] = [
                'state' => $quality->state(),
                'engine_type' => $visibleEngine,
                'consent_scopes' => count($allowedScopes),
                'source_counts' => $normalizedCounts,
            ];
        }

        return $results;
    }

    private static function invalidConsentScopes(ConsentPolicy $consent): int
    {
        $expectedScopes = ['activity', 'assessment', 'evaluation', 'skills'];
        $invalid = 0;
        foreach (CompleteAiDemoDataset::learners() as $learner) {
            if ($consent->allowedScopes($learner['student_id']) !== $expectedScopes) {
                $invalid++;
            }
        }
        return $invalid;
    }

    private static function invalidAssessmentClosure(PDO $pdo): int
    {
        return self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT attempt.id
    FROM test_attempts attempt
    LEFT JOIN learner_assessment_attempt_metadata metadata ON metadata.attemptId = attempt.id
    LEFT JOIN learner_assessment_answers answer ON answer.attemptId = attempt.id
    LEFT JOIN test_results result ON result.attemptId = attempt.id
    LEFT JOIN learner_assessment_question_versions question
      ON question.versionId = metadata.versionId AND question.required = 1
    WHERE attempt.id LIKE '21000000-%' OR attempt.id LIKE '22000000-%'
    GROUP BY attempt.id, attempt.status
    HAVING attempt.status <> 'submitted'
        OR COUNT(DISTINCT metadata.id) <> 1
        OR MAX(metadata.status = 'submitted') <> 1
        OR COUNT(DISTINCT result.id) <> 1
        OR COUNT(DISTINCT answer.id) <> COUNT(DISTINCT question.id)
) AS invalid_attempts
SQL) + self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM learner_assessment_answers answer
LEFT JOIN test_attempts attempt ON attempt.id = answer.attemptId
WHERE (answer.id LIKE '21000000-%' OR answer.id LIKE '22000000-%')
  AND attempt.id IS NULL
SQL);
    }

    private static function invalidJourneyClosure(PDO $pdo): int
    {
        return self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT registration.id
    FROM activity_registrations registration
    LEFT JOIN checkins checkin_record ON checkin_record.registrationId = registration.id
      AND checkin_record.status = 'confirmed'
      AND checkin_record.checkedInAt IS NOT NULL
      AND checkin_record.confirmedAt IS NOT NULL
    LEFT JOIN activity_qr_sessions qr ON qr.id = checkin_record.qrSessionId
      AND qr.activityId = registration.activityId
    LEFT JOIN experience_logs experience ON experience.checkinId = checkin_record.id
      AND experience.studentId = registration.studentId
      AND experience.activityId = registration.activityId
      AND experience.status = 'confirmed'
    LEFT JOIN assessments evaluation ON evaluation.studentId = registration.studentId
      AND evaluation.activityId = registration.activityId
      AND evaluation.status = 'published'
    WHERE (registration.id LIKE '21000000-%' OR registration.id LIKE '22000000-%')
      AND registration.status = 'attended'
    GROUP BY registration.id
    HAVING COUNT(DISTINCT checkin_record.id) <> 1
        OR COUNT(DISTINCT qr.id) <> 1
        OR COUNT(DISTINCT experience.id) <> 1
        OR COUNT(DISTINCT evaluation.id) <> 1
) AS invalid_attended
SQL) + self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM checkins checkin_record
LEFT JOIN activity_registrations registration ON registration.id = checkin_record.registrationId
  AND registration.status = 'attended'
WHERE (checkin_record.id LIKE '21000000-%' OR checkin_record.id LIKE '22000000-%')
  AND checkin_record.status = 'confirmed'
  AND registration.id IS NULL
SQL) + self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM experience_logs experience
LEFT JOIN checkins checkin_record ON checkin_record.id = experience.checkinId
LEFT JOIN activity_registrations registration ON registration.id = checkin_record.registrationId
  AND registration.status = 'attended'
  AND registration.studentId = experience.studentId
  AND registration.activityId = experience.activityId
WHERE (experience.id LIKE '21000000-%' OR experience.id LIKE '22000000-%')
  AND experience.status = 'confirmed'
  AND registration.id IS NULL
SQL) + self::scalar($pdo, <<<'SQL'
SELECT COUNT(*)
FROM assessments evaluation
LEFT JOIN activity_registrations registration ON registration.studentId = evaluation.studentId
  AND registration.activityId = evaluation.activityId
  AND registration.status = 'attended'
WHERE (evaluation.id LIKE '21000000-%' OR evaluation.id LIKE '22000000-%')
  AND evaluation.status = 'published'
  AND registration.id IS NULL
SQL);
    }

    private static function invalidQrHashes(PDO $pdo): int
    {
        $invalid = 0;
        foreach (['activity_qr_sessions', 'activity_qr_tokens'] as $table) {
            if (!self::tableExists($pdo, $table)) {
                continue;
            }
            if (!self::tableHasColumn($pdo, $table, 'tokenHash')) {
                $invalid++;
                continue;
            }
            $escapedTable = str_replace('`', '``', $table);
            $invalid += self::scalar(
                $pdo,
                "SELECT COUNT(*) FROM `{$escapedTable}` WHERE tokenHash IS NULL OR tokenHash NOT REGEXP '^[a-f0-9]{64}$'",
            );
            $invalid += self::scalar(
                $pdo,
                "SELECT COUNT(*) FROM (SELECT tokenHash FROM `{$escapedTable}` GROUP BY tokenHash HAVING COUNT(*) <> 1) AS duplicate_hashes",
            );
        }
        return $invalid;
    }

    private static function hasRawQrTokenStorage(PDO $pdo): bool
    {
        $columns = $pdo->query(<<<'SQL'
SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN ('activity_qr_sessions', 'activity_qr_tokens')
  AND LOWER(REPLACE(column_name, '_', '')) IN ('token', 'rawtoken', 'plaintexttoken', 'qrtoken')
ORDER BY table_name, column_name
SQL)->fetchAll(PDO::FETCH_ASSOC);
        $rawColumnFound = false;
        foreach ($columns as $column) {
            $rawColumnFound = true;
            $table = (string) ($column['table_name'] ?? '');
            $name = (string) ($column['column_name'] ?? '');
            if (!in_array($table, ['activity_qr_sessions', 'activity_qr_tokens'], true)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                return true;
            }
            $escapedTable = str_replace('`', '``', $table);
            $escapedColumn = str_replace('`', '``', $name);
            if (self::scalar($pdo, "SELECT COUNT(*) FROM `{$escapedTable}` WHERE `{$escapedColumn}` IS NOT NULL AND `{$escapedColumn}` <> ''") > 0) {
                return true;
            }
        }
        return $rawColumnFound;
    }

    private static function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
SQL);
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = :table
SQL);
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private static function ownedCount(PDO $pdo, string $table): int
    {
        return self::scalar($pdo, 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . "` WHERE id LIKE '21000000-%' OR id LIKE '22000000-%'");
    }

    private static function demoEntityCount(PDO $pdo, string $table): int
    {
        return self::scalar($pdo, 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . "` WHERE id LIKE '20000000-%' OR id LIKE '22000000-%'");
    }

    private static function scalar(PDO $pdo, string $sql): int
    {
        return (int) $pdo->query($sql)->fetchColumn();
    }
}
