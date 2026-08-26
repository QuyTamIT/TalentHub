<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/Contracts/SchoolCredentialRepository.php';
require dirname(__DIR__) . '/app/learner/data/Contracts/StatisticsRepository.php';
require dirname(__DIR__) . '/app/learner/data/Contracts/BadgeRepository.php';
require dirname(__DIR__) . '/app/learner/data/Service/CredentialRecommendationMatcher.php';
require dirname(__DIR__) . '/app/learner/data/Service/SchoolCredentialService.php';

use TalentHub\Learner\Data\Contracts\BadgeRepository;
use TalentHub\Learner\Data\Contracts\SchoolCredentialRepository;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;
use TalentHub\Learner\Data\Service\CredentialRecommendationMatcher;
use TalentHub\Learner\Data\Service\SchoolCredentialService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$credentialRepo = new class implements SchoolCredentialRepository {
    public bool $roadmapCompleted = true;

    public function studentContext(string $studentId): ?array
    {
        return ['student_id' => $studentId, 'school_id' => 'school-1', 'school_name' => 'THPT Demo', 'grade_level' => 11];
    }

    public function latestAssessmentProfile(string $studentId): array
    {
        return [
            'holland' => ['I' => 92],
            'multiple_intelligence' => ['logical' => 90],
            'disc' => ['C' => 82],
            'mbti' => ['ISTJ' => 100],
            'completed_families' => ['disc', 'holland', 'mbti', 'multiple_intelligence'],
        ];
    }

    public function verifiedSkillProfile(string $studentId): array
    {
        return ['problem_solving' => 88];
    }

    public function credentialCatalog(string $schoolId): array
    {
        return [
            [
                'kind' => 'badge', 'id' => 'badge-complete', 'code' => 'complete',
                'name' => 'Hồ sơ hoàn chỉnh', 'description' => 'Đủ bốn bài đánh giá.',
                'category' => 'assessment', 'icon_key' => 'clipboard', 'recommendation_enabled' => false,
                'recommendation_profile' => [], 'eligibility_criteria' => ['submitted_assessment_type_count' => 4],
            ],
            [
                'kind' => 'badge', 'id' => 'badge-analysis', 'code' => 'analysis',
                'name' => 'Nhà tư duy phân tích', 'description' => 'Phù hợp hồ sơ phân tích.',
                'category' => 'assessment', 'icon_key' => 'award', 'recommendation_enabled' => true,
                'recommendation_profile' => ['holland' => ['I'], 'multiple_intelligence' => ['logical'], 'disc' => ['C'], 'mbti' => ['ISTJ'], 'skills' => ['problem_solving']],
                'eligibility_criteria' => [],
            ],
            [
                'kind' => 'certificate', 'id' => 'certificate-project', 'code' => 'project',
                'name' => 'Thực hành dự án', 'description' => 'Chứng chỉ do trường cấp.',
                'issuer_name' => 'THPT Demo', 'icon_key' => 'certificate', 'recommendation_enabled' => true,
                'recommendation_profile' => ['holland' => ['I'], 'multiple_intelligence' => ['logical']],
                'eligibility_criteria' => ['submitted_assessment_type_count' => 4, 'confirmed_experience_hours' => 10],
            ],
        ];
    }

    public function issuedSchoolCertificates(string $studentId): array
    {
        return [['award_id' => 'issued-1', 'catalog_id' => 'certificate-project', 'status' => 'issued', 'issued_at' => '2026-08-26 00:00:00', 'evidence_context' => []]];
    }

    public function hasCompletedRoadmap(string $studentId): bool
    {
        return $this->roadmapCompleted;
    }
};

$statistics = new class implements StatisticsRepository {
    public function lifetimeFacts(string $studentId): array
    {
        return [
            'confirmed_experience_hours' => 12.0,
            'attended_activity_count' => 2,
            'submitted_assessment_type_count' => 4,
            'published_teacher_evaluation_count' => 1,
        ];
    }

    public function periodStatistics(string $studentId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }
};

$badges = new class implements BadgeRepository {
    public function activeRules(): array { return []; }
    public function activeRulesForStudent(string $studentId): array { return []; }
    public function awardedBadges(string $studentId): array
    {
        return [['id' => 'badge-complete', 'awardedAt' => '2026-08-26 00:00:00']];
    }
    public function isAwarded(string $studentId, string $badgeId): bool { return false; }
    public function insertAward(string $studentId, string $badgeId, string $ruleDefinitionId, string $awardedBy, array $awardContext, DateTimeImmutable $awardedAt): bool { return false; }
    public function userForStudent(string $studentId): ?array { return null; }
    public function withTransaction(callable $operation): mixed { return $operation(); }
};

$result = (new SchoolCredentialService(
    $credentialRepo,
    $statistics,
    $badges,
    new CredentialRecommendationMatcher()
))->forStudent('student-1');

$assert($result['ready'] === true, 'four completed assessment families mark recommendations ready');
$assert($result['analysis_completed'] === true, 'AI roadmap completion is exposed');
$assert($result['school']['name'] === 'THPT Demo', 'school issuer context is returned');
$assert($result['badges'][0]['status'] === 'achieved', 'awarded badge is marked achieved');
$assert($result['badges'][1]['status'] === 'recommended', 'matched catalog badge is recommended');
$assert($result['certificates'][0]['status'] === 'issued', 'issued school certificate wins over eligibility');
$assert($result['certificates'][0]['progress_percent'] === 100, 'issued certificate has complete progress');
$assert(count($result['featured']) >= 2, 'featured credentials combine awarded and recommendations');
$assert(count(array_filter($result['featured'], static fn (array $item): bool => $item['kind'] === 'badge')) <= 3, 'featured limits badges to three');
$assert(count(array_filter($result['featured'], static fn (array $item): bool => $item['kind'] === 'certificate')) <= 2, 'featured limits certificates to two');
$assert((int) $result['badges'][1]['match_score'] >= 70, 'strong recommendation keeps its match score');

$credentialRepo->roadmapCompleted = false;
$beforeRoadmap = (new SchoolCredentialService(
    $credentialRepo,
    $statistics,
    $badges,
    new CredentialRecommendationMatcher()
))->forStudent('student-1');
$assert($beforeRoadmap['analysis_completed'] === false, 'roadmap gate remains false before AI analysis');
$assert($beforeRoadmap['badges'][1]['status'] === 'locked', 'personalized badge is not recommended before AI analysis');
$assert($beforeRoadmap['badges'][1]['status_label'] === 'Chưa mở khóa', 'pre-analysis catalog is not labeled as AI output');
$assert($beforeRoadmap['badges'][0]['status'] === 'achieved', 'existing awards remain visible before AI analysis');
$assert($beforeRoadmap['certificates'][0]['status'] === 'issued', 'existing certificates remain visible before AI analysis');

echo "learner_school_credential_service_test: OK\n";
