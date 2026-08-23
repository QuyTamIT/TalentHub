<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\InternshipApplicationCommandRepository;
use TalentHub\Learner\Data\Service\ApplicationCommandService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$repository = new class implements InternshipApplicationCommandRepository {
    public array $calls = [];
    public function grantApplicationProfileConsent(string $studentId, string $userId, string $requestId): array { $this->calls[] = ['consent', func_get_args()]; return ['scope' => 'application_profile_share']; }
    public function submit(string $studentId, string $userId, string $requestId, string $postId, string $message): array { $this->calls[] = ['submit', func_get_args()]; return ['id' => 'application']; }
    public function readForStudent(string $studentId): array { $this->calls[] = ['list', func_get_args()]; return ['items' => []]; }
    public function readOneForStudent(string $studentId, string $applicationId): array { $this->calls[] = ['detail', func_get_args()]; return ['id' => $applicationId]; }
    public function withdraw(string $studentId, string $userId, string $requestId, string $applicationId, string $reason): array { $this->calls[] = ['withdraw', func_get_args()]; return ['status' => 'withdrawn']; }
};

$service = new ApplicationCommandService($repository);
$student = '10000000-0000-4000-8000-000000000001';
$user = '20000000-0000-4000-8000-000000000001';
$post = '30000000-0000-4000-8000-000000000001';
$application = '40000000-0000-4000-8000-000000000001';

$consent = $service->grantConsent($student, $user, 'request-1', true);
$assert(($consent['scope'] ?? null) === 'application_profile_share', 'explicit consent delegates to repository');
$submitted = $service->submit($student, $user, 'request-2', $post, '  Xin ứng tuyển  ');
$assert(($submitted['id'] ?? null) === 'application', 'submit delegates to repository');
$assert($repository->calls[1][1][4] === 'Xin ứng tuyển', 'message is trimmed');
$assert($service->list($student) === ['items' => []], 'list delegates owner scope');
$assert(($service->detail($student, $application)['id'] ?? null) === $application, 'detail delegates owner scope');
$assert(($service->withdraw($student, $user, 'request-3', $application, '  Đổi kế hoạch  ')['status'] ?? null) === 'withdrawn', 'withdraw delegates');
$assert($repository->calls[4][1][4] === 'Đổi kế hoạch', 'withdraw reason is trimmed');

try {
    $service->grantConsent($student, $user, 'request-4', false);
    $assert(false, 'consent without explicit confirmation must fail');
} catch (ApiException $exception) {
    $assert($exception->errorCode === 'CONSENT_CONFIRMATION_REQUIRED', 'consent confirmation has stable error code');
}

foreach ([
    static fn () => $service->submit($student, $user, 'request-5', 'not-a-uuid', ''),
    static fn () => $service->submit($student, $user, 'request-6', $post, str_repeat('x', 501)),
    static fn () => $service->withdraw($student, $user, 'request-7', $application, str_repeat('x', 501)),
] as $invalid) {
    try {
        $invalid();
        $assert(false, 'invalid application command must fail');
    } catch (ApiException $exception) {
        $assert($exception->errorCode === 'VALIDATION_FAILED', 'invalid application command uses validation error');
    }
}

$endpointSource = file_get_contents(dirname(__DIR__) . '/app/learner/api/v1/applications.php');
$assert(is_string($endpointSource), 'learner application endpoint source is readable');
$assert(str_contains($endpointSource, "privacy_consent.manage_own") && str_contains($endpointSource, "'grant-consent'"), 'consent is a separate permission-protected command');
$assert(str_contains($endpointSource, "internship_application.create_own") && str_contains($endpointSource, "internship_application.withdraw_own") && str_contains($endpointSource, "internship_application.read_own"), 'endpoint enforces exact owner permissions');
$assert(str_contains($endpointSource, "mutation(\$request->header('x-csrf-token'))"), 'mutations enforce CSRF');
$assert(!str_contains($endpointSource, "['studentId'"), 'endpoint never accepts student identity from request JSON');
$learnerRepositorySource = file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseApplicationCommandRepository.php');
$assert(is_string($learnerRepositorySource), 'learner command repository source is readable');
$assert(!str_contains($learnerRepositorySource, 'ia.reviewerNote'), 'learner reads never expose internal reviewer notes');

$routerSource = file_get_contents(dirname(__DIR__) . '/src/Bootstrap/Application.php');
$assert(is_string($routerSource), 'shared router source is readable');
foreach (['internship_post.read_own_business', 'internship_post.create_own_business', 'internship_post.update_own_business', 'internship_post.publish_own_business', 'internship_post.close_own_business', 'internship_application.read_own_business', 'internship_application.review_own_business', 'internship_application.read_cv_own_business'] as $permission) {
    $assert(str_contains($routerSource, $permission), "shared Enterprise router enforces {$permission}");
}

echo "learner_application_api_test: OK\n";
