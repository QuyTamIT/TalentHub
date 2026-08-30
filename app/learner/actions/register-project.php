<?php

declare(strict_types=1);

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseProjectMembershipCommandRepository;
use TalentHub\Learner\Data\Support\Uuid;

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/data/bootstrap.php';

/**
 * Applies one learner project registration and returns an app-relative PRG
 * destination back to the internal project detail URL.
 *
 * Learner identity is always resolved from the authenticated session; the
 * command only accepts the project identifier from the form context.
 *
 * @param array<string,mixed> $post
 */
function learner_project_registration_submit(
    PDO $pdo,
    SessionManager $session,
    array $post,
    string $method,
    ?DateTimeImmutable $now = null,
    ?string $headerCsrfToken = null,
): string {
    if (strtoupper(trim($method)) !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $rawToken = $post['csrfToken'] ?? $post['csrf_token'] ?? $headerCsrfToken;
    $session->assertCsrf(is_string($rawToken) ? $rawToken : null);

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

    $rawProjectId = trim((string) ($post['projectId'] ?? ''));
    $failureDestination = '/app/learner/project.php?id=' . rawurlencode($rawProjectId) . '&register=failed';

    try {
        $projectId = Uuid::normalizeDatabase($rawProjectId, 'project_id');
    } catch (Throwable) {
        return $failureDestination;
    }

    $repository = new DatabaseProjectMembershipCommandRepository($pdo);
    try {
        $repository->registerActiveMember(
            $studentId,
            $projectId,
            $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    } catch (ApiException) {
        return $failureDestination;
    } catch (Throwable) {
        return $failureDestination;
    }

    return '/app/learner/project.php?id=' . $projectId . '&registered=1';
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}

try {
    $sessionConfig = require dirname(__DIR__, 3) . '/config/session.php';
    $sessionConfig['name'] = SessionManager::SESSION_STUDENT;
    $session = new SessionManager($sessionConfig);
    $session->start();
    $pdo = $GLOBALS['__TALENTHUB_TEST_PDO__'] ?? null;
    if (!$pdo instanceof PDO) {
        $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
    }
    $destination = learner_project_registration_submit(
        $pdo,
        $session,
        $_POST,
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        null,
        isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : null,
    );
    header('Location: ' . app_href($destination), true, 303);
    exit;
} catch (ApiException $exception) {
    http_response_code($exception->status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $exception->getMessage();
}
