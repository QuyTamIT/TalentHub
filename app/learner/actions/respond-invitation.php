<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Service\InternshipInvitationResponseService;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Support\Id\RequestId;

$user = PortalGuard::requireRole(RoleCodes::STUDENT, '/app/learner/notifications.php');
$sessionConfig = require dirname(__DIR__, 3) . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_STUDENT;
$session = new SessionManager($sessionConfig);
$session->start();
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }
    $rawToken = $_POST['csrfToken'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $session->assertCsrf(is_string($rawToken) ? $rawToken : null);

    $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
    $studentStatement = $pdo->prepare('SELECT id FROM student_profiles WHERE userId = :userId LIMIT 1');
    $studentStatement->execute(['userId' => (string) $user['id']]);
    $studentId = $studentStatement->fetchColumn();
    if (!is_string($studentId) || $studentId === '') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Không tìm thấy hồ sơ học viên hợp lệ.');
    }

    $result = (new InternshipInvitationResponseService($pdo))->respond(
        $studentId,
        (string) $user['id'],
        trim((string) ($_POST['notificationId'] ?? '')),
        strtolower(trim((string) ($_POST['decision'] ?? ''))),
        RequestId::make($_SERVER['HTTP_X_REQUEST_ID'] ?? null),
    );
    $message = $result['status'] === 'accepted'
        ? "Bạn đã chấp nhận lời mời thực tập từ {$result['enterpriseName']}!"
        : 'Bạn đã từ chối lời mời thực tập.';

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result + ['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $_SESSION['learnerNotificationFlash'] = $message;
    header('Location: ' . app_href('/app/learner/notifications.php'), true, 303);
    exit;
} catch (ApiException $exception) {
    if ($isAjax) {
        http_response_code($exception->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['learnerNotificationFlash'] = $exception->getMessage();
    header('Location: ' . app_href('/app/learner/notifications.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Learner invitation response failed: ' . $exception->getMessage());
    if ($isAjax) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Chưa thể xử lý lời mời lúc này.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['learnerNotificationFlash'] = 'Chưa thể xử lý lời mời lúc này.';
    header('Location: ' . app_href('/app/learner/notifications.php'), true, 303);
    exit;
}
