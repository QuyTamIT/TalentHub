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
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
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

    public function studentId(string $permission): string
    {
        $user = $this->session->requireUser();
        if (($user['role'] ?? null) !== 'student') {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Endpoint chỉ dành cho học viên.');
        }
        $this->permissions->require((string) $user['id'], $permission);
        $statement = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId = :userId LIMIT 1');
        $statement->execute(['userId' => (string) $user['id']]);
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

        return new RecommendationService(
            $repository,
            new RuleRecommendationEngine(),
            new RecommendationResultValidator(),
            new RecommendationResponseMapper(),
            static fn (string $candidate): bool => hash_equals($studentId, $candidate),
            static fn (string $candidate): array => $consent->allowedScopes($candidate),
            static fn (string $candidate, array $scopes) => $snapshotBuilder->build($candidate, $scopes),
            static fn ($input) => (new DataQualityGate())->evaluate($input),
            static fn ($input): bool => true,
        );
    }

    /** @return array<string,mixed> */
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array
    {
        return (new DatabaseRecommendationRepository($this->pdo))->appendFeedback($studentId, $itemId, $verdict, $reasonCode, $safeComment);
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
            new EducationBandResolver($this->pdo)
        );
    }

    public function assessmentService(): LearnerAssessmentService
    {
        $factory = new RepositoryFactory('database', $this->pdo);

        return new LearnerAssessmentService(
            $factory->assessment(),
            $factory->assessmentWrite()
        );
    }
}
