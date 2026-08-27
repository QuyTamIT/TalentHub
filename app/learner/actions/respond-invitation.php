<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Rbac\RoleCodes;

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Authenticate Student
$user = PortalGuard::requireRole(RoleCodes::STUDENT, '/app/learner/notifications.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 3) . '/config/session.php', ['name' => SessionManager::SESSION_STUDENT]));
$session->start();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

try {
    $session->assertCsrf($_POST['csrfToken'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    $notificationId = trim((string) ($_POST['notificationId'] ?? ''));
    $decision = strtolower(trim((string) ($_POST['decision'] ?? 'accept')));
    $newStatus = ($decision === 'accept') ? 'accepted' : 'declined';
    $userId = (string) $user['id'];

    $config = require dirname(__DIR__, 3) . '/config/database.php';
    $pdo = (new Connection($config))->connect();

    // Get notification
    $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND userId = ? LIMIT 1");
    $notifStmt->execute([$notificationId, $userId]);
    $notif = $notifStmt->fetch(PDO::FETCH_ASSOC);

    $enterpriseName = 'FPT Software';
    $postTitle = 'Thực tập sinh';
    $applicationId = null;

    if ($notif) {
        $deepLink = (string) ($notif['deepLink'] ?? '');
        $appStmt = $pdo->prepare("
            SELECT ia.id, ia.postId, ia.status, e.name as enterpriseName, ip.title as postTitle
            FROM internship_applications ia
            JOIN student_profiles sp ON sp.id = ia.studentId
            JOIN internship_posts ip ON ip.id = ia.postId
            JOIN enterprises e ON e.id = ip.enterpriseId
            WHERE sp.userId = ? AND (? LIKE CONCAT('%', ia.postId, '%') OR ia.status IN ('invited', 'accepted', 'declined'))
            ORDER BY ia.updatedAt DESC
            LIMIT 1
        ");
        $appStmt->execute([$userId, $deepLink]);
        $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);

        if ($appRow) {
            $applicationId = $appRow['id'];
            $enterpriseName = $appRow['enterpriseName'];
            $postTitle = $appRow['postTitle'];

            $updApp = $pdo->prepare("UPDATE internship_applications SET status = ?, updatedAt = NOW(6) WHERE id = ?");
            $updApp->execute([$newStatus, $applicationId]);
        }

        // Mark notification read
        $pdo->prepare("UPDATE notifications SET readAt = NOW(6) WHERE id = ?")->execute([$notificationId]);
    }

    $msg = ($newStatus === 'accepted')
        ? "Bạn đã chấp nhận lời mời thực tập từ {$enterpriseName}!"
        : "Bạn đã từ chối lời mời thực tập.";

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => $newStatus,
            'message' => $msg,
            'enterpriseName' => $enterpriseName,
            'postTitle' => $postTitle,
            'applicationId' => $applicationId,
            'notificationId' => $notificationId,
        ]);
        exit;
    }

    $_SESSION['learnerNotificationFlash'] = $msg;
    header('Location: ' . app_href('/app/learner/notifications.php'));
    exit;

} catch (\Throwable $e) {
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi xử lý phản hồi: ' . $e->getMessage(),
        ]);
        exit;
    }

    $_SESSION['learnerNotificationFlash'] = 'Lỗi xử lý phản hồi: ' . $e->getMessage();
    header('Location: ' . app_href('/app/learner/notifications.php'));
    exit;
}
