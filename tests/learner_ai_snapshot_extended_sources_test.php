<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Ai\Sources\LearnerAiExtendedSource;
use TalentHub\Learner\Data\Contracts\TalentPassportRepository;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function learner_ai_snapshot_extended_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

learner_ai_snapshot_extended_assert(interface_exists(LearnerAiExtendedSource::class), 'canonical source adapter contract is loaded');
learner_ai_snapshot_extended_assert(class_exists(AiSourceRegistry::class), 'source registry is loaded');

final class LearnerAiSnapshotExtendedFixtureSource implements LearnerAiExtendedSource
{
    public function __construct(
        private string $type,
        private string $scope,
        private array $fields,
        private array $records,
    )
    {
    }

    public function sourceType(): string { return $this->type; }
    public function schemaVersion(): string { return $this->type . '-1.0.0'; }
    public function consentScope(): string { return $this->scope; }
    public function allowedFields(): array { return $this->fields; }
    public function refreshTrigger(): string { return $this->type . '_changed'; }
    public function readForStudent(string $studentId): array { return $this->records; }
    public function changedSince(string $studentId, ?string $versionOrTimestamp): bool { return true; }
}

$source = new LearnerAiSnapshotExtendedFixtureSource('certificate', 'profile', ['title', 'issuer', 'updated_at'], [
    [
        'source_id' => 'certificate-2',
        'title' => 'Mới nhất',
        'issuer' => 'Catalog',
        'updated_at' => '2026-08-26T02:00:00+00:00',
        'email' => 'must-not-leak@example.test',
    ],
]);
$registry = new AiSourceRegistry([$source]);
$records = $registry->readForStudent('student-1', ['profile']);
learner_ai_snapshot_extended_assert(count($records) === 1, 'registry reads current database/catalog record');
learner_ai_snapshot_extended_assert($records[0]['source_type'] === 'certificate', 'source type is present');
learner_ai_snapshot_extended_assert($records[0]['schema_version'] === 'certificate-1.0.0', 'schema version is present');
learner_ai_snapshot_extended_assert($records[0]['consent_scope'] === 'profile', 'consent scope is present');
learner_ai_snapshot_extended_assert(isset($records[0]['evidence_ref']), 'evidence reference is present');
learner_ai_snapshot_extended_assert(!isset($records[0]['data']['email']), 'fields outside allow-list are removed');

$sourceWithNewRecord = new LearnerAiSnapshotExtendedFixtureSource('certificate', 'profile', ['title', 'issuer', 'updated_at'], [
    ['source_id' => 'certificate-1', 'title' => 'Cũ', 'issuer' => 'Catalog', 'updated_at' => '2026-08-25T02:00:00+00:00'],
    ['source_id' => 'certificate-2', 'title' => 'Mới nhất', 'issuer' => 'Catalog', 'updated_at' => '2026-08-26T02:00:00+00:00'],
]);
$nextRegistry = new AiSourceRegistry([$sourceWithNewRecord]);
$nextRecords = $nextRegistry->readForStudent('student-1', ['profile']);
learner_ai_snapshot_extended_assert(count($nextRecords) === 2, 'new catalog record appears without prompt changes');
learner_ai_snapshot_extended_assert($nextRecords[0]['source_id'] === 'certificate-1', 'source ordering is deterministic');

$allTypes = [
    'profile' => 'profile',
    'skill' => 'skills',
    'assessment' => 'assessment',
    'achievement' => 'profile',
    'certificate' => 'profile',
    'project' => 'activity',
    'activity' => 'activity',
    'checkin' => 'activity',
    'badge' => 'profile',
    'progress' => 'activity',
    'mentor_evaluation' => 'evaluation',
    'teacher_feedback' => 'evaluation',
    'roadmap_feedback' => 'evaluation',
    'opportunity' => 'activity',
];
$allSources = [];
foreach ($allTypes as $type => $scope) {
    $allSources[] = new LearnerAiSnapshotExtendedFixtureSource(
        $type,
        $scope,
        ['label', 'updated_at'],
        [['source_id' => $type . '-1', 'label' => $type, 'updated_at' => '2026-08-26T02:00:00+00:00']],
    );
}
$allRecords = (new AiSourceRegistry($allSources))->readForStudent(
    'student-1',
    ['activity', 'assessment', 'evaluation', 'profile', 'skills'],
);
$actualTypes = array_values(array_unique(array_column($allRecords, 'source_type')));
sort($actualTypes, SORT_STRING);
$expectedTypes = array_keys($allTypes);
sort($expectedTypes, SORT_STRING);
learner_ai_snapshot_extended_assert($actualTypes === $expectedTypes, 'snapshot registry covers every approved source type');

$revokedRecords = (new AiSourceRegistry($allSources))->readForStudent('student-1', ['assessment', 'profile', 'skills']);
learner_ai_snapshot_extended_assert(
    array_values(array_filter($revokedRecords, static fn (array $row): bool => in_array($row['consent_scope'], ['activity', 'evaluation'], true))) === [],
    'revoked consent excludes the source from the next snapshot',
);

$hashOne = (new RecommendationSnapshotBuilder($nextRegistry))->build('student-1', ['profile'])->contentHash();
$sourceWithNewRecord = new LearnerAiSnapshotExtendedFixtureSource('certificate', 'profile', ['title', 'issuer', 'updated_at'], [
    ['source_id' => 'certificate-1', 'title' => 'Cũ', 'issuer' => 'Catalog', 'updated_at' => '2026-08-25T02:00:00+00:00'],
    ['source_id' => 'certificate-2', 'title' => 'Đã cập nhật', 'issuer' => 'Catalog', 'updated_at' => '2026-08-26T03:00:00+00:00'],
]);
$changedHash = (new RecommendationSnapshotBuilder(new AiSourceRegistry([$sourceWithNewRecord])))->build('student-1', ['profile'])->contentHash();
learner_ai_snapshot_extended_assert($hashOne !== $changedHash, 'snapshot hash changes when source data changes');

$passport = new class implements TalentPassportRepository {
    public array $certificates = [
        ['id' => 'passport-cert-1', 'title' => 'Chứng chỉ mới', 'updatedAt' => '2026-08-26T04:00:00+00:00'],
    ];

    public function aggregateForStudent(string $studentId): array
    {
        return [
            'certificates' => $this->certificates,
            'projects' => [['id' => 'passport-project-1', 'title' => 'Dự án mới', 'updatedAt' => '2026-08-26T04:00:00+00:00']],
            'badges' => [['id' => 'passport-badge-1', 'name' => 'Huy hiệu mới', 'awardedAt' => '2026-08-26T04:00:00+00:00']],
            'progress' => [['id' => 'passport-progress-1', 'code' => 'experience', 'current' => 3, 'target' => 10, 'percent' => 30, 'status' => 'in_progress', 'updatedAt' => '2026-08-26T04:00:00+00:00']],
            'checkins' => [['id' => 'passport-checkin-1', 'activityId' => 'activity-1', 'hours' => 3, 'status' => 'confirmed', 'confirmedAt' => '2026-08-26T04:00:00+00:00']],
            'teacher_feedback' => [['id' => 'passport-feedback-1', 'activityId' => 'activity-1', 'overallScore' => 8, 'comment' => 'Tiến bộ tốt', 'status' => 'published', 'publishedAt' => '2026-08-26T04:00:00+00:00']],
            'roadmap_feedback' => [['id' => 'passport-roadmap-feedback-1', 'verdict' => 'helpful', 'reasonCode' => 'useful_direction', 'count' => 1, 'updatedAt' => '2026-08-26T04:00:00+00:00']],
            'source_availability' => [
                'achievement' => ['status' => 'unavailable', 'reason' => 'canonical_source_not_available'],
                'mentor_evaluation' => ['status' => 'unavailable', 'reason' => 'canonical_source_not_available'],
                'certificate' => ['status' => 'available', 'reason' => null],
                'project' => ['status' => 'available', 'reason' => null],
                'badge' => ['status' => 'available', 'reason' => null],
                'progress' => ['status' => 'available', 'reason' => null],
                'checkin' => ['status' => 'available', 'reason' => null],
                'teacher_feedback' => ['status' => 'available', 'reason' => null],
                'roadmap_feedback' => ['status' => 'available', 'reason' => null],
            ],
        ];
    }
};
$passportRegistry = new AiSourceRegistry();
$passportRegistry->registerTalentPassportSources($passport);
$passportRecords = $passportRegistry->readForStudent('student-1', ['activity', 'evaluation', 'skills']);
$passportTypes = array_values(array_unique(array_column($passportRecords, 'source_type')));
sort($passportTypes, SORT_STRING);
learner_ai_snapshot_extended_assert(
    $passportTypes === ['badge', 'certificate', 'checkin', 'progress', 'project', 'roadmap_feedback', 'teacher_feedback'],
    'production Talent Passport registration reads every canonical aggregate source',
);
learner_ai_snapshot_extended_assert($passportRecords[0]['data']['name'] === 'Huy hiệu mới', 'talent passport data is allow-listed without copying the source repository');
$passportInput = $passportRegistry->buildInput('student-1', ['activity', 'evaluation', 'skills']);
learner_ai_snapshot_extended_assert(
    ($passportInput->qualityFlags()['missing_source_types'] ?? []) === ['achievement', 'mentor_evaluation'],
    'missing canonical sources are explicit instead of silently empty',
);
learner_ai_snapshot_extended_assert(
    ($passportInput->qualityFlags()['source_availability']['achievement']['reason'] ?? null) === 'canonical_source_not_available',
    'missing source includes a stable machine-readable reason',
);

$beforeNewRecordHash = $passportInput->contentHash();
$passport->certificates[] = ['id' => 'passport-cert-2', 'title' => 'Chứng chỉ vừa cấp', 'updatedAt' => '2026-08-26T05:00:00+00:00'];
$afterNewRecordInput = $passportRegistry->buildInput('student-1', ['activity', 'evaluation', 'skills']);
learner_ai_snapshot_extended_assert(
    count($afterNewRecordInput->payload()['certificates'] ?? []) === 2,
    'a new repository record appears through production registry wiring without prompt changes',
);
learner_ai_snapshot_extended_assert(
    $beforeNewRecordHash !== $afterNewRecordInput->contentHash(),
    'a new repository record changes the production snapshot hash',
);
learner_ai_snapshot_extended_assert(
    in_array('workshop', $passportInput->qualityFlags()['blocked_catalog_types'], true),
    'missing workshop/group/contest catalogs are explicitly marked blocked instead of being simulated',
);

echo "learner_ai_snapshot_extended_sources_test: OK\n";
