<?php
/**
 * TalentHub - Enterprise Action: Send Internship / Job Invitation to Candidate
 */
declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';
require dirname(__DIR__, 3) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Bootstrap\EnterpriseAppContext;
use TalentHub\Support\Uuid;

header('Content-Type: application/json; charset=utf-8');

try {
    $context = (new EnterpriseAppContext())->boot();
    $user = $context['user'];
    $enterprise = $context['enterprise'];
    $csrfToken = $context['csrfToken'];
    $pdo = $context['pdo'];

    if (!$user || !$enterprise) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Phiên làm việc doanh nghiệp đã hết hạn. Vui lòng đăng nhập lại.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
        exit;
    }

    // CSRF Check
    $submittedToken = $_POST['csrfToken'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($csrfToken !== null && (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Mã bảo mật CSRF không hợp lệ hoặc đã hết hạn.']);
        exit;
    }

    $studentId = trim((string) ($_POST['studentId'] ?? ''));
    $postId = trim((string) ($_POST['postId'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if (empty($studentId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn ứng viên cần gửi lời mời.']);
        exit;
    }

    if (empty($postId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn vị trí tuyển dụng thực tập.']);
        exit;
    }

    // 1. Verify student exists
    $stStmt = $pdo->prepare("
        SELECT sp.id as studentId, sp.userId, u.fullName, u.email
        FROM student_profiles sp
        JOIN users u ON u.id = sp.userId
        WHERE sp.id = ?
        LIMIT 1
    ");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ sinh viên.']);
        exit;
    }

    // 2. Verify internship post belongs to enterprise
    $postStmt = $pdo->prepare("
        SELECT id, title, enterpriseId
        FROM internship_posts
        WHERE id = ? AND enterpriseId = ?
        LIMIT 1
    ");
    $postStmt->execute([$postId, $enterprise['id']]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vị trí tuyển dụng không tồn tại hoặc không thuộc doanh nghiệp của bạn.']);
        exit;
    }

    $enterpriseName = $enterprise['name'] ?? 'Doanh nghiệp';
    $postTitle = $post['title'] ?? 'Vị trí thực tập';
    $studentName = $student['fullName'] ?? 'Sinh viên';
    $studentUserId = $student['userId'];

    // 3. Save into internship_applications (Status: 'invited')
    $appCheck = $pdo->prepare("
        SELECT id, status FROM internship_applications
        WHERE postId = ? AND studentId = ?
        LIMIT 1
    ");
    $appCheck->execute([$postId, $studentId]);
    $existingApp = $appCheck->fetch(PDO::FETCH_ASSOC);

    $invitationPrefix = "[LỜI MỜI THỰC TẬP TỪ " . mb_strtoupper($enterpriseName) . "]\n";
    $finalMessage = $invitationPrefix . ($message !== '' ? $message : "Trân trọng mời bạn tham gia ứng tuyển và thực tập cho vị trí {$postTitle} tại {$enterpriseName}.");

    if ($existingApp) {
        $updApp = $pdo->prepare("
            UPDATE internship_applications
            SET status = 'invited',
                message = ?,
                updatedAt = NOW()
            WHERE id = ?
        ");
        $updApp->execute([$finalMessage, $existingApp['id']]);
        $appId = $existingApp['id'];
    } else {
        $appId = Uuid::uuid4();
        $insApp = $pdo->prepare("
            INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt)
            VALUES (?, ?, ?, 'invited', ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                status = 'invited',
                message = VALUES(message),
                updatedAt = NOW()
        ");
        $insApp->execute([$appId, $postId, $studentId, $finalMessage]);
    }

    // 4. Save into notifications table for the student (Support re-sending without uq_notifications_user_event duplicate collision)
    $notifId = Uuid::v4();
    $notifTitle = "Lời mời thực tập từ " . $enterpriseName;
    $notifMsg = "Doanh nghiệp {$enterpriseName} vừa gửi lời mời bạn tham gia thực tập cho vị trí: {$postTitle}." . ($message !== '' ? "\nLời nhắn: \"{$message}\"" : "");
    $deepLink = "/app/student/internships/" . $postId;

    // Unique eventKey per invitation + post + timestamp to guarantee uniqueness and allow multiple invites
    $eventKey = 'internship_invitation_' . $postId . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

    $insNotif = $pdo->prepare("
        INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt)
        VALUES (?, ?, ?, 'internship_invitation', ?, ?, ?, NOW(6))
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            message = VALUES(message),
            deepLink = VALUES(deepLink),
            readAt = NULL,
            createdAt = NOW(6)
    ");
    $insNotif->execute([$notifId, $studentUserId, $eventKey, $notifTitle, $notifMsg, $deepLink]);

    echo json_encode([
        'success' => true,
        'message' => "Đã gửi lời mời thực tập vị trí '{$postTitle}' đến sinh viên {$studentName} thành công!",
        'data' => [
            'applicationId' => $appId,
            'studentId' => $studentId,
            'studentName' => $studentName,
            'postTitle' => $postTitle,
            'status' => 'invited'
        ]
    ]);
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra trong quá trình gửi lời mời: ' . $e->getMessage()
    ]);
    exit;
}
