<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Modules\Teacher\Repository\TeacherRepository;

// 1. Kiểm tra session và quyền (đảm bảo request an toàn)
$session = new SessionManager(array_merge(require dirname(__DIR__, 3) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();
$user = $_SESSION['user'] ?? null;

if (!$user || ($user['role'] ?? '') !== 'teacher') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
        
        // Lấy teacher_id từ database dựa trên user_id
        $teacherRepo = new TeacherRepository($pdo);
        $teacherInfo = $teacherRepo->findByUserId((string) $user['id']);
        $teacherId = $teacherInfo['id'] ?? null;
        
        if (!$teacherId) {
            throw new Exception('Không tìm thấy thông tin giáo viên.');
        }

        // 1. Tiếp nhận dữ liệu POST
        $activityId = $_POST['activity_id'] ?? '';
        $durationMinutes = (int) ($_POST['duration_minutes'] ?? 15);
        $maxScans = (int) ($_POST['scan_limit'] ?? 100);
        $confirmedHours = (float) ($_POST['experience_hours'] ?? 1.0);

        // 2. Sinh Token ngẫu nhiên (Opaque token)
        $rawToken = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $rawToken); // Hash lại để lưu DB bảo mật
        $sessionId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        ); // Sinh UUID cho bảng

        // 3. Tính toán thời gian: expires_at = NOW() + duration_minutes (UTC)
        $expiresAt = gmdate('Y-m-d H:i:s.u', time() + ($durationMinutes * 60));

        // 4. Lưu Database bằng PDO an toàn
        $pdo->beginTransaction();
        
        // Cập nhật policy về giờ kinh nghiệm
        $policyStmt = $pdo->prepare('
            INSERT INTO activity_experience_policies (activityId, confirmedHours, createdAt, updatedAt)
            VALUES (:activityId, :confirmedHours, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE confirmedHours = VALUES(confirmedHours), updatedAt = UTC_TIMESTAMP(6)
        ');
        $policyStmt->execute([
            'activityId' => $activityId,
            'confirmedHours' => $confirmedHours
        ]);

        // Insert vào bảng phiên QR
        $stmt = $pdo->prepare('
            INSERT INTO activity_qr_sessions 
                (id, activityId, createdByTeacherId, tokenHash, status, expiresAt, maxScans, usedScans)
            VALUES 
                (:sessionId, :activityId, :teacherId, :tokenHash, \'active\', :expiresAt, :maxScans, 0)
        ');
        $stmt->execute([
            'sessionId' => $sessionId,
            'activityId' => $activityId,
            'teacherId' => $teacherId,
            'tokenHash' => $tokenHash,
            'expiresAt' => $expiresAt,
            'maxScans' => $maxScans
        ]);
        
        $pdo->commit();

        // 5. Trả về kết quả JSON chứa chuỗi token cho Frontend vẽ mã QR
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'message' => 'Tạo phiên QR thành công',
            'token' => $rawToken
        ]);
        exit;

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
