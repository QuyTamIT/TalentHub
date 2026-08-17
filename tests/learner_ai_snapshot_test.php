<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\ActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\AssessmentSource;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use TalentHub\Learner\Ai\Sources\PublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\SkillSource;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

foreach ([
    '/app/learner/ai/Domain/RecommendationInput.php',
    '/app/learner/ai/Domain/RecommendationContext.php',
    '/app/learner/ai/Snapshot/RecommendationSnapshotBuilder.php',
    '/app/learner/ai/Quality/DataQualityResult.php',
    '/app/learner/ai/Quality/DataQualityGate.php',
] as $file) {
    $path = dirname(__DIR__) . $file;
    if (is_file($path)) {
        require_once $path;
    }
}

function snapshot_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

final class SnapshotStudentSource implements StudentProfileSource
{
    /** @param array<string,mixed> $profile */
    public function __construct(private readonly array $profile)
    {
    }

    public function forStudent(string $studentId): array
    {
        return $this->profile;
    }
}

final class SnapshotSkillSource implements SkillSource
{
    /** @param list<array<string,mixed>> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function forStudent(string $studentId): array
    {
        return $this->records;
    }
}

final class SnapshotAssessmentSource implements AssessmentSource
{
    /** @param list<array<string,mixed>> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function forStudent(string $studentId): array
    {
        return $this->records;
    }
}

final class SnapshotExperienceSource implements ActivityExperienceSource
{
    /** @param list<array<string,mixed>> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function forStudent(string $studentId): array
    {
        return $this->records;
    }
}

final class SnapshotEvaluationSource implements PublishedEvaluationSource
{
    /** @param list<array<string,mixed>> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function forStudent(string $studentId): array
    {
        return $this->records;
    }
}

final class SnapshotOpportunitySource implements OpportunitySource
{
    /** @param list<array<string,mixed>> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function forStudent(string $studentId): array
    {
        return $this->records;
    }
}

/** @param array<string,mixed> $data */
function snapshot_builder(array $data): RecommendationSnapshotBuilder
{
    return new RecommendationSnapshotBuilder(
        new SnapshotStudentSource($data['profile'] ?? []),
        new SnapshotSkillSource($data['skills'] ?? []),
        new SnapshotAssessmentSource($data['assessments'] ?? []),
        new SnapshotExperienceSource($data['activities'] ?? []),
        new SnapshotEvaluationSource($data['evaluations'] ?? []),
        new SnapshotOpportunitySource($data['opportunities'] ?? []),
    );
}

/** @param array<mixed> $value */
function snapshot_assert_no_private_keys(array $value): void
{
    $private = ['email', 'phone', 'dateofbirth', 'birthdate', 'name', 'fullname', 'token', 'password', 'cvurl', 'studentid', 'teacherid', 'userid'];
    $walk = static function (array $item) use (&$walk, $private): void {
        foreach ($item as $key => $child) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key) ?? '');
            snapshot_assert(!in_array($normalized, $private, true), "snapshot excludes private key {$key}");
            if (is_array($child)) {
                $walk($child);
            }
        }
    };
    $walk($value);
}

snapshot_assert(class_exists(RecommendationSnapshotBuilder::class), 'snapshot builder exists');
snapshot_assert(class_exists(DataQualityGate::class), 'data quality gate exists');

$complete = [
    'profile' => ['study_status' => 'active', 'email' => 'learner@example.test', 'full_name' => 'Private Learner'],
    'skills' => [
        ['student_skill_id' => 'skill-record-b', 'skill_id' => 'skill-b', 'code' => 'iot', 'name' => 'IoT', 'level_score' => 80, 'verification_status' => 'verified', 'source_updated_at' => '2026-08-11T09:00:00+00:00', 'access_token' => 'secret'],
        ['student_skill_id' => 'skill-record-a', 'skill_id' => 'skill-a', 'code' => 'python', 'name' => 'Python', 'level_score' => 90, 'verification_status' => 'self_declared', 'source_updated_at' => '2026-08-10T09:00:00+00:00'],
    ],
    'assessments' => [[
        'attempt_id' => 'attempt-1', 'result_id' => 'result-1', 'test_code' => 'holland', 'test_type' => 'interest', 'assessment_version' => '1.0.0', 'scoring_version' => 'holland-riasec-1.0', 'result_code' => 'RIA',
        'dimension_scores' => ['I' => 70, 'R' => 82], 'submitted_at' => '2026-08-01T08:00:00+00:00',
    ]],
    'activities' => [[
        'experience_id' => 'experience-1', 'activity_id' => 'activity-1', 'activity_category' => 'workshop', 'hours' => 4.5, 'confirmed_at' => '2026-08-02T09:00:00+00:00',
    ]],
    'evaluations' => [[
        'evaluation_id' => 'evaluation-1', 'activity_id' => 'activity-1', 'overall_score' => 92, 'presentation_score' => 55, 'published_at' => '2026-08-03T09:00:00+00:00',
    ]],
    'opportunities' => [[
        'opportunity_id' => 'opportunity-1', 'enterprise_id' => 'enterprise-1', 'title' => 'IoT internship', 'location' => 'Da Nang', 'deadline_at' => '2026-09-01T00:00:00+00:00',
    ]],
];

$orderedInput = snapshot_builder($complete)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation']);
$reordered = $complete;
$reordered['skills'] = array_reverse($complete['skills']);
$reordered['assessments'][0]['dimension_scores'] = ['R' => 82, 'I' => 70];
$reorderedInput = snapshot_builder($reordered)->build('student-a', ['evaluation', 'activity', 'skills', 'assessment']);
snapshot_assert($orderedInput->schemaVersion() === '1.0', 'snapshot has the first versioned schema');
snapshot_assert($orderedInput->contentHash() === $reorderedInput->contentHash(), 'row order and object key order do not change the SHA-256 snapshot hash');
snapshot_assert($orderedInput->canonicalJson() === $reorderedInput->canonicalJson(), 'row order produces the same canonical JSON');
snapshot_assert(strlen($orderedInput->contentHash()) === 64, 'snapshot exposes a SHA-256 content hash');
snapshot_assert_no_private_keys($orderedInput->payload());
snapshot_assert(str_contains($orderedInput->canonicalJson(), 'learner@example.test') === false, 'canonical snapshot excludes direct identifiers');
snapshot_assert(($orderedInput->sourceUpdatedAt()['assessment'] ?? null) === '2026-08-01T08:00:00.000000+00:00', 'snapshot records source timestamps with microseconds');
snapshot_assert(count($orderedInput->evidenceReferences()) === 6, 'snapshot contains minimized evidence references for all source records');
snapshot_assert(in_array([
    'observed_at' => '2026-08-02T09:00:00.000000+00:00',
    'safe_value' => [
        'activity_category' => 'workshop',
        'confirmed_at' => '2026-08-02T09:00:00.000000+00:00',
        'hours' => 4.5,
    ],
    'source_id' => 'experience-1',
    'source_type' => 'activity_experience',
], $orderedInput->evidenceReferences(), true), 'snapshot evidence references retain the exact minimized source value without learner identity');
snapshot_assert(
    ($orderedInput->payload()['evaluations'][0]['presentation_score'] ?? null) === 55.0,
    'snapshot retains the scalar published presentation score required by the rule baseline'
);

$changed = $complete;
$changed['skills'][0]['level_score'] = 81;
$changedInput = snapshot_builder($changed)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation']);
snapshot_assert($orderedInput->contentHash() !== $changedInput->contentHash(), 'a minimized source value change produces a new content hash');
$microsecondFirst = $complete;
$microsecondFirst['skills'][0]['source_updated_at'] = '2026-08-11T09:00:00.000001+00:00';
$microsecondSecond = $complete;
$microsecondSecond['skills'][0]['source_updated_at'] = '2026-08-11T09:00:00.000002+00:00';
snapshot_assert(
    snapshot_builder($microsecondFirst)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation'])->contentHash()
        !== snapshot_builder($microsecondSecond)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation'])->contentHash(),
    'a microsecond-only source timestamp change produces a new content hash'
);
$naiveUtc = $complete;
$naiveUtc['skills'][0]['source_updated_at'] = '2026-08-11 09:00:00.123456';
$originalTimezone = date_default_timezone_get();
date_default_timezone_set('Asia/Bangkok');
try {
    snapshot_assert(
        (snapshot_builder($naiveUtc)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation'])->sourceUpdatedAt()['skill'] ?? null)
            === '2026-08-11T09:00:00.123456+00:00',
        'snapshot treats timezone-less source timestamps as UTC'
    );
} finally {
    date_default_timezone_set($originalTimezone);
}
$payloadCopy = $orderedInput->payload();
$payloadCopy['skills'] = [];
snapshot_assert(count($orderedInput->payload()['skills']) === 2, 'snapshot values cannot be mutated through a returned payload copy');
$privateInput = new RecommendationInput(['access_token' => 'must-not-persist'], [], [], []);
snapshot_assert(str_contains($privateInput->canonicalJson(), 'must-not-persist') === false, 'snapshot value object removes token-like private keys defensively');
$mutableObject = (object) ['score' => 82];
$nestedObjectRejected = false;
try {
    new RecommendationInput(['scores' => ['result' => $mutableObject]], [], [], []);
} catch (InvalidArgumentException) {
    $nestedObjectRejected = true;
}
$mutableObject->score = 0;
snapshot_assert($nestedObjectRejected, 'snapshot rejects nested mutable objects rather than retaining mutable references');

$qualityGate = new DataQualityGate(new DateTimeImmutable('2026-08-16T00:00:00+00:00'));
$ready = $qualityGate->evaluate($orderedInput);
snapshot_assert($ready->state() === 'ready', 'complete consented input is ready for rule recommendations');
snapshot_assert($ready->completionActions() === [], 'ready input needs no completion action');

$revoked = snapshot_builder($complete)->build('student-a', ['skills']);
$revokedResult = $qualityGate->evaluate($revoked);
snapshot_assert($revokedResult->state() === 'consent_required', 'a revoked or absent scope returns consent_required before data quality');
snapshot_assert($revokedResult->missingConsentScopes() === ['activity', 'assessment', 'evaluation'], 'consent result identifies every missing source scope');

$incomplete = $complete;
$incomplete['skills'] = [$complete['skills'][0]];
$incomplete['assessments'] = [];
$incomplete['activities'] = [];
$incomplete['evaluations'] = [];
$incompleteInput = snapshot_builder($incomplete)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation']);
$incompleteResult = $qualityGate->evaluate($incompleteInput);
snapshot_assert($incompleteResult->state() === 'insufficient_data', 'consented but incomplete input returns insufficient_data');
snapshot_assert($incompleteResult->missingCategories() === ['assessment', 'skills', 'experience', 'evaluations'], 'every missing baseline category has an explicit outcome');
snapshot_assert(count($incompleteResult->completionActions()) === 4, 'every missing category has a safe completion action');

$stale = $complete;
$stale['assessments'][0]['submitted_at'] = '2025-08-15T00:00:00+00:00';
$staleResult = $qualityGate->evaluate(snapshot_builder($stale)->build('student-a', ['assessment', 'skills', 'activity', 'evaluation']));
snapshot_assert($staleResult->state() === 'insufficient_data', 'assessment older than the maximum age is insufficient');
snapshot_assert($staleResult->missingCategories() === ['assessment'], 'stale assessment only requests an updated assessment');

echo "learner_ai_snapshot_test: OK\n";
