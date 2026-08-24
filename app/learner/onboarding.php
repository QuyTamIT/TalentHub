<?php

declare(strict_types=1);

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\LearnerOnboardingRepository;
use TalentHub\Modules\Student\Service\LearnerOnboardingService;
use TalentHub\Support\Id\RequestId;

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';

/**
 * Apply one fixed onboarding decision and return an app-relative redirect.
 *
 * @param array<string,mixed> $post
 */
function learner_onboarding_decide(
    PDO $pdo,
    SessionManager $session,
    array $post,
    string $method,
    string $requestId,
    ?string $ip,
): string {
    if (strtoupper($method) !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $session->assertCsrf(isset($post['csrfToken']) && is_string($post['csrfToken']) ? $post['csrfToken'] : null);
    $action = isset($post['action']) && is_string($post['action']) ? strtolower(trim($post['action'])) : '';
    if (!in_array($action, ['accept', 'decline'], true)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Lựa chọn onboarding không hợp lệ.');
    }

    $user = $session->requireUser();
    if (($user['role'] ?? null) !== 'student') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Chức năng chỉ dành cho học viên.');
    }

    $statement = $pdo->prepare('SELECT id FROM student_profiles WHERE userId = :userId LIMIT 1');
    $statement->execute(['userId' => (string) $user['id']]);
    $studentId = $statement->fetchColumn();
    if (!is_string($studentId) || $studentId === '') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Không tìm thấy hồ sơ học viên hợp lệ.');
    }

    $service = new LearnerOnboardingService(new LearnerOnboardingRepository($pdo));
    if ($action === 'decline') {
        $service->decline($studentId, (string) $user['id'], $requestId, $ip);
        $session->destroy();
        return '/login.php?onboarding=declined';
    }

    $progress = $service->accept($studentId, (string) $user['id'], $requestId, $ip);
    $destination = $progress['next_url'] ?? null;
    return is_string($destination) && $destination !== ''
        ? $destination
        : '/app/learner/index.php';
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}

try {
    $session = new SessionManager(require dirname(__DIR__, 2) . '/config/session.php');
    $session->start();
    $pdo = $GLOBALS['__TALENTHUB_TEST_PDO__'] ?? null;
    if (!$pdo instanceof PDO) {
        $pdo = (new Connection(require dirname(__DIR__, 2) . '/config/database.php'))->connect();
    }
    $destination = learner_onboarding_decide(
        $pdo,
        $session,
        $_POST,
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        RequestId::make($_SERVER['HTTP_X_REQUEST_ID'] ?? null),
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );
    header('Location: ' . app_href($destination), true, 303);
    exit;
} catch (ApiException $exception) {
    http_response_code($exception->status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $exception->getMessage();
}
