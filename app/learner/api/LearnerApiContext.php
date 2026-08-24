<?php

declare(strict_types=1);

namespace TalentHub\Learner\Api;

require_once dirname(__DIR__) . '/ai/bootstrap.php';
require_once dirname(__DIR__) . '/data/bootstrap.php';

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Evaluation\ShadowRunService;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Model\ModelRoadmapEngine;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Persistence\DatabaseRoadmapRepository;
use TalentHub\Learner\Ai\Provider\HttpRecommendationProvider;
use TalentHub\Learner\Ai\Provider\HttpRoadmapProvider;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Quality\RoadmapQualityGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Service\RoadmapService;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Assessment\Service\AssessmentCatalogService;
use TalentHub\Learner\Assessment\Service\EducationBandResolver;
use TalentHub\Learner\Data\RepositoryFactory;
use TalentHub\Learner\Data\Service\LearnerAssessmentService;
use TalentHub\Modules\Student\Repository\LearnerOnboardingRepository;
use TalentHub\Modules\Student\Service\LearnerOnboardingGate;
use TalentHub\Modules\Student\Service\LearnerOnboardingService;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Id\RequestId;
use TalentHub\Support\Uuid;

final class LearnerApiContext
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SessionManager $session,
        private readonly PermissionService $permissions,
        private readonly string $requestId,
    ) {
    }

    public static function fromGlobals(): self
    {
        $request = Request::fromGlobals();
        $session = new SessionManager(require dirname(__DIR__, 3) . '/config/session.php');
        $session->start();
        if (isset($GLOBALS['__TALENTHUB_TEST_SESSION__']) && is_array($GLOBALS['__TALENTHUB_TEST_SESSION__'])) {
            $_SESSION = $GLOBALS['__TALENTHUB_TEST_SESSION__'];
        }
        $pdo = isset($GLOBALS['__TALENTHUB_TEST_PDO__']) && $GLOBALS['__TALENTHUB_TEST_PDO__'] instanceof PDO
            ? $GLOBALS['__TALENTHUB_TEST_PDO__']
            : (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
        return new self($pdo, $session, new PermissionService($pdo), RequestId::make($request->header('x-request-id')));
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function studentId(string $permission): string
    {
        return $this->studentIdForPermissions([$permission]);
    }

    /** @param list<string> $permissions */
    public function studentIdForPermissions(array $permissions): string
    {
        return $this->studentIdentityForPermissions($permissions)['student_id'];
    }

    /** @param list<string> $permissions @return array{student_id:string,user_id:string} */
    public function studentIdentityForPermissions(array $permissions): array
    {
        $user = $this->session->requireUser();
        if (($user['role'] ?? null) !== 'student') {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Endpoint chỉ dành cho học viên.');
        }
        foreach (array_values(array_unique($permissions)) as $permission) {
            if (!is_string($permission) || trim($permission) === '') {
                throw new \InvalidArgumentException('Student permission code must be a non-empty string.');
            }
            $this->permissions->require((string) $user['id'], $permission);
        }
        $userId = (string) $user['id'];
        $identity = [
            'student_id' => $this->resolveStudentId($userId),
            'user_id' => $userId,
        ];
        $progress = (new LearnerOnboardingService(new LearnerOnboardingRepository($this->pdo)))->reconcile(
            $identity['student_id'],
            $identity['user_id'],
            $this->requestId,
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
        );
        (new LearnerOnboardingGate())->assertApiAllowed(
            $progress,
            basename((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '')),
        );

        return $identity;
    }

    private function resolveStudentId(string $userId): string
    {
        $statement = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId = :userId LIMIT 1');
        $statement->execute(['userId' => $userId]);
        $studentId = $statement->fetchColumn();
        if ($studentId === false) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Không tìm thấy hồ sơ học viên hợp lệ.');
        }
        return (string) $studentId;
    }

    public function mutation(?string $csrfToken): void
    {
        try {
            $this->session->assertCsrf($csrfToken);
        } catch (ApiException $exception) {
            if ($exception->errorCode === 'CSRF_TOKEN_INVALID') {
                throw new ApiException(403, 'CSRF_INVALID', 'CSRF token không hợp lệ.');
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $input @param list<string> $allowed @return array<string,mixed> */
    public function allowedInput(array $input, array $allowed): array
    {
        $details = [];
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                $details[] = ['field' => (string) $field, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép gửi field này.'];
            }
        }
        if ($details !== []) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu gửi lên không hợp lệ.', $details);
        }
        return $input;
    }

    public function idempotencyKey(?string $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('/\A[A-Za-z0-9_-]{16,100}\z/', $value) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'X-Idempotency-Key không hợp lệ.', [['field' => 'X-Idempotency-Key', 'code' => 'INVALID_FORMAT', 'message' => 'Idempotency key phải có từ 16 đến 100 ký tự hợp lệ.']]);
        }
        return $value;
    }

    public function recommendationService(string $studentId): RecommendationService
    {
        $consent = new ConsentPolicy(new DatabaseConsentSource($this->pdo));
        $snapshotBuilder = new RecommendationSnapshotBuilder(
            new DatabaseStudentProfileSource($this->pdo),
            new DatabaseSkillSource($this->pdo),
            new DatabaseAssessmentSource($this->pdo),
            new DatabaseActivityExperienceSource($this->pdo),
            new DatabasePublishedEvaluationSource($this->pdo),
            new DatabaseOpportunitySource($this->pdo),
        );
        $repository = new DatabaseRecommendationRepository($this->pdo);

        $modelEngine = null;
        $modelConfig = null;
        $rolloutSelector = null;

        try {
            $env = isset($GLOBALS['__TALENTHUB_TEST_ENV__']) && is_array($GLOBALS['__TALENTHUB_TEST_ENV__'])
                ? $GLOBALS['__TALENTHUB_TEST_ENV__']
                : $_ENV;
            $config = RecommendationConfig::fromEnvironment($env);
            if ($config->enabled()) {
                $modelConfig = $config;
                $rolloutSelector = new RecommendationRolloutSelector();
                $httpTransport = $GLOBALS['__TALENTHUB_TEST_HTTP__'] ?? null;
                $provider = new HttpRecommendationProvider(
                    $config,
                    is_callable($httpTransport) ? $httpTransport : null,
                );
                $fallbackEngine = new RuleRecommendationEngine();
                $modelEngine = new ModelRecommendationEngine(
                    $provider,
                    $fallbackEngine,
                    new PromptRegistry(),
                    new RecommendationRateLimiter(
                        $config->perStudentLimit(),
                        $config->globalLimit(),
                        60,
                        static fn (): int => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp(),
                    ),
                    $config,
                    new RecommendationResultValidator(),
                    new ProviderConsentGate($consent),
                );
            }
        } catch (\Throwable) {
            $modelEngine = null;
            $modelConfig = null;
            $rolloutSelector = null;
        }

        return new RecommendationService(
            $repository,
            new RuleRecommendationEngine(),
            new RecommendationResultValidator(),
            new RecommendationResponseMapper(),
            static fn (string $candidate): bool => hash_equals($studentId, $candidate),
            static fn (string $candidate) => $consent->decision($candidate),
            static fn (string $candidate, array $scopes) => $snapshotBuilder->build($candidate, $scopes),
            static fn ($input) => (new DataQualityGate())->evaluate($input),
            static fn ($input): bool => true,
            $modelEngine,
            $modelConfig,
            $rolloutSelector,
        );
    }

    public function roadmapService(string $studentId): RoadmapService
    {
        $consent = new ConsentPolicy(new DatabaseConsentSource($this->pdo));
        $snapshotBuilder = new RecommendationSnapshotBuilder(
            new DatabaseStudentProfileSource($this->pdo),
            new DatabaseSkillSource($this->pdo),
            new DatabaseAssessmentSource($this->pdo),
            new DatabaseActivityExperienceSource($this->pdo),
            new DatabasePublishedEvaluationSource($this->pdo),
            new DatabaseOpportunitySource($this->pdo),
        );
        $runs = new DatabaseRecommendationRepository($this->pdo);
        $roadmaps = new DatabaseRoadmapRepository($this->pdo);
        try {
            $env = isset($GLOBALS['__TALENTHUB_TEST_ENV__']) && is_array($GLOBALS['__TALENTHUB_TEST_ENV__'])
                ? $GLOBALS['__TALENTHUB_TEST_ENV__']
                : $_ENV;
            $config = RecommendationConfig::fromEnvironment($env);
        } catch (\Throwable) {
            $config = RecommendationConfig::fromEnvironment(['TALENTHUB_AI_ENABLED' => 'false']);
        }
        $ruleEngine = new RuleRoadmapEngine();
        $engine = $ruleEngine;
        try {
            $decision = $consent->decision($studentId);
            $showModel = (new RecommendationRolloutSelector())->canShowRoadmapModel(
                $studentId,
                $config,
                $decision->allowedScopes(),
                true,
            );
        } catch (\Throwable) {
            $showModel = false;
        }
        if ($showModel) {
            $httpTransport = $GLOBALS['__TALENTHUB_TEST_HTTP__'] ?? null;
            $provider = new HttpRoadmapProvider($config, is_callable($httpTransport) ? $httpTransport : null);
            $engine = new ModelRoadmapEngine(
                $provider,
                $ruleEngine,
                new RoadmapPromptRegistry(),
                new RecommendationRateLimiter(
                    $config->roadmapPerStudentLimit(),
                    $config->roadmapGlobalLimit(),
                    60,
                    static fn (): int => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp(),
                ),
                $config,
                new ProviderConsentGate($consent, ['assessment']),
            );
        }

        return new RoadmapService(
            $roadmaps,
            $engine,
            static fn (string $candidate): bool => hash_equals($studentId, $candidate),
            static fn (string $candidate) => $consent->decision($candidate),
            static fn (string $candidate, array $scopes) => $snapshotBuilder->buildForRoadmap($candidate, $scopes),
            static fn ($input) => (new RoadmapQualityGate())->evaluate($input),
            static fn (string $candidate, $input, $context) => $runs->createPendingRoadmapRun($candidate, $input, $context),
            static fn (string $candidate, string $runId, $analysis) => $runs->completeRoadmapRun($candidate, $runId, $analysis),
            static fn (string $candidate, string $runId, string $code) => $runs->failRun($candidate, $runId, $code),
        );
    }

    public function shadowRunService(?RecommendationEngine $modelEngine = null): ?ShadowRunService
    {
        $repository = new DatabaseRecommendationRepository($this->pdo);
        if ($modelEngine !== null) {
            return new ShadowRunService($repository, $modelEngine, new RecommendationEvaluator());
        }
        try {
            $env = isset($GLOBALS['__TALENTHUB_TEST_ENV__']) && is_array($GLOBALS['__TALENTHUB_TEST_ENV__'])
                ? $GLOBALS['__TALENTHUB_TEST_ENV__']
                : $_ENV;
            $config = RecommendationConfig::fromEnvironment($env);
            if (!$config->enabled() || !$config->shadowEnabled()) {
                return null;
            }
            $httpTransport = $GLOBALS['__TALENTHUB_TEST_HTTP__'] ?? null;
            $provider = new HttpRecommendationProvider(
                $config,
                is_callable($httpTransport) ? $httpTransport : null,
            );
            $fallbackEngine = new RuleRecommendationEngine();
            $consent = new ConsentPolicy(new DatabaseConsentSource($this->pdo));
            $engine = new ModelRecommendationEngine(
                $provider,
                $fallbackEngine,
                new PromptRegistry(),
                new RecommendationRateLimiter(
                    $config->perStudentLimit(),
                    $config->globalLimit(),
                    60,
                    static fn (): int => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp(),
                ),
                $config,
                new RecommendationResultValidator(),
                new ProviderConsentGate($consent),
            );
            return new ShadowRunService($repository, $engine, new RecommendationEvaluator());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array
    {
        return (new DatabaseRecommendationRepository($this->pdo))->appendFeedback($studentId, $itemId, $verdict, $reasonCode, $safeComment);
    }

    /** @return array<string,mixed> */
    public function appendRoadmapFeedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array
    {
        return $this->roadmapService($studentId)->feedback($studentId, $roadmapId, $verdict, $reasonCode, $requestId);
    }

    /** @return list<string> */
    public function consentScopes(string $studentId): array
    {
        return (new ConsentPolicy(new DatabaseConsentSource($this->pdo)))->allowedScopes($studentId);
    }

    /** @return array<string,string> */
    public function appendConsent(string $studentId, string $scope, string $action): array
    {
        if (!in_array($scope, ['assessment', 'skills', 'activity', 'evaluation'], true)
            || !in_array($action, ['granted', 'revoked'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Consent không hợp lệ.');
        }
        $occurredAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $eventId = Uuid::v4();
        $insert = $this->pdo->prepare(
            'INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES (:id, :studentId, :scope, :action, :policyVersion, :occurredAt, :requestId)'
        );
        $insert->execute([
            'id' => $eventId,
            'studentId' => $studentId,
            'scope' => $scope,
            'action' => $action,
            'policyVersion' => 'learner-ai-consent-1.0',
            'occurredAt' => $occurredAt,
            'requestId' => $this->requestId,
        ]);
        return ['event_id' => $eventId, 'scope' => $scope, 'action' => $action];
    }

    public function assessmentCatalogService(): AssessmentCatalogService
    {
        $factory = new RepositoryFactory('database', $this->pdo);

        return new AssessmentCatalogService(
            $factory->assessment(),
            $this->educationBandResolver()
        );
    }

    public function educationBandResolver(): EducationBandResolver
    {
        return new EducationBandResolver($this->pdo);
    }

    public function assessmentService(): LearnerAssessmentService
    {
        $factory = new RepositoryFactory('database', $this->pdo);

        return new LearnerAssessmentService(
            $factory->assessment(),
            $factory->assessmentWrite()
        );
    }

    public function onboardingService(): LearnerOnboardingService
    {
        return new LearnerOnboardingService(new LearnerOnboardingRepository($this->pdo));
    }
}
