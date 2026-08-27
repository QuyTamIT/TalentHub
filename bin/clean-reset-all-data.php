<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " CLEAN RESET: ĐƯA TOÀN BỘ HỆ THỐNG VỀ TRẠNG THÁI TRẮNG DỮ LIỆU HOẠT ĐỘNG\n";
echo "======================================================================\n\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// 1. Reset Recruitment & Applications
echo "[1/6] Dọn sạch bảng Đơn ứng tuyển & Tuyển dụng...\n";
$appTables = [
    'application_profile_snapshots',
    'application_status_history',
    'internship_mentor_assignments',
    'internship_applications',
    'enterprise_contact_requests',
    'enterprise_talent_access_grants',
    'student_enterprise_school_approvals',
];
foreach ($appTables as $tbl) {
    try {
        $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        echo " -> Truncated: `{$tbl}`\n";
    } catch (\Throwable $e) {
        echo " -> Note for `{$tbl}`: " . $e->getMessage() . "\n";
    }
}

// 2. Reset Assessment & Skills & Badges & Certificates
echo "\n[2/6] Dọn sạch bảng Đánh giá, Chấm điểm, Kỹ năng, Huy hiệu, Chứng chỉ...\n";
$evalTables = [
    'assessment_scores',
    'assessments',
    'learner_assessment_answers',
    'learner_assessment_attempt_metadata',
    'test_attempts',
    'test_results',
    'student_skills',
    'learner_skill_evidence',
    'student_badges',
    'student_school_certificates',
    'learner_ai_roadmap_task_events',
    'learner_ai_roadmap_tasks',
    'learner_ai_roadmap_phases',
    'learner_ai_roadmaps',
    'learner_recommendation_snapshot_evidence',
    'learner_recommendation_evidence',
    'learner_recommendation_items',
    'learner_recommendation_runs',
    'learner_recommendation_input_snapshots',
    'learner_recommendation_audit_events',
    'learner_recommendation_feedback',
    'learner_ai_consent_events',
    'learner_onboarding_states',
    'checkins',
    'experience_logs',
    'activity_registrations',
    'activity_qr_sessions',
    'activity_qr_tokens',
    'project_members',
    'project_sponsorships',
    'projects'
];
foreach ($evalTables as $tbl) {
    try {
        $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        echo " -> Truncated: `{$tbl}`\n";
    } catch (\Throwable $e) {
        echo " -> Note for `{$tbl}`: " . $e->getMessage() . "\n";
    }
}

// 3. Reset Notifications & Audit Logs
echo "\n[3/6] Dọn sạch bảng Thông báo & Activity Logs...\n";
$notifTables = ['notifications', 'audit_logs'];
foreach ($notifTables as $tbl) {
    try {
        $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        echo " -> Truncated: `{$tbl}`\n";
    } catch (\Throwable $e) {
        echo " -> Note for `{$tbl}`: " . $e->getMessage() . "\n";
    }
}

// 4. Clean up junk/test student profiles and keep the clean test student
echo "\n[4/6] Chuẩn hóa danh sách Học sinh & Lớp học...\n";

// Delete ad-hoc test accounts with @example.test or test emails
$pdo->exec("
    DELETE FROM users 
    WHERE email LIKE '%@example.test%' 
       OR email LIKE '%test.talenthub.local%'
       OR email LIKE '%@example.%'
");

// Fetch Roles
$rolesStmt = $pdo->query("SELECT id, code FROM roles");
$roleMap = [];
while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
    $roleMap[$r['code']] = $r['id'];
}

$schoolRoleId = $roleMap['school'] ?? '63ff7548-6700-52e0-973d-c9feafeeee29';
$teacherRoleId = $roleMap['teacher'] ?? '926a59f0-051a-5f52-8501-2a2fd9c2d1a4';
$enterpriseRoleId = $roleMap['enterprise'] ?? '8dcbaaac-be69-5d75-92e0-cdd0289642e3';
$studentRoleId = $roleMap['student'] ?? 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673';

// Common Password Hash: 123456
$pwdHash = password_hash('123456', PASSWORD_DEFAULT);

// 4.1. Core School Account: school@talenthub.local
$schoolUserId = 'cf711da3-ef58-429b-b52f-d3bff8b60e05';
$schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
$pdo->prepare("
    INSERT INTO users (id, email, passwordHash, fullName, roleId, status, createdAt, updatedAt)
    VALUES (?, 'school@talenthub.local', ?, 'Ban Đào tạo Cao đẳng Quốc tế BTEC FPT', ?, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), roleId = VALUES(roleId), status = 'active', updatedAt = NOW()
")->execute([$schoolUserId, $pwdHash, $schoolRoleId]);

$pdo->prepare("
    INSERT INTO schools (id, name, level, address, phone, email, website, academicYear, status, createdAt, updatedAt)
    VALUES (?, 'Cao đẳng Quốc tế BTEC FPT', 'college', 'Tòa nhà BTEC FPT, Trịnh Văn Bô, Nam Từ Liêm, Hà Nội', '024 7300 9268', 'btec@fpt.edu.vn', 'https://btec.fpt.edu.vn', '2025-2026', 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name), status = 'active', updatedAt = NOW()
")->execute([$schoolId]);

$pdo->prepare("
    INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt, updatedAt)
    VALUES (?, ?, ?, 'admin', NOW(), NOW())
    ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), memberRole = 'admin', updatedAt = NOW()
")->execute([Uuid::v4(), $schoolId, $schoolUserId]);

// 4.2. Core Teacher Account: teacher@talenthub.local
$teacherUserId = '2b102e3b-9e3a-43fe-a7f2-2bad676bbf97';
$teacherProfileId = '2b102e3b-9e3a-43fe-a7f2-2bad676bbf98';
$pdo->prepare("
    INSERT INTO users (id, email, passwordHash, fullName, roleId, status, createdAt, updatedAt)
    VALUES (?, 'teacher@talenthub.local', ?, 'ThS. Nguyễn Văn Hùng', ?, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), roleId = VALUES(roleId), status = 'active', updatedAt = NOW()
")->execute([$teacherUserId, $pwdHash, $teacherRoleId]);

$pdo->prepare("
    INSERT INTO teacher_profiles (id, userId, schoolId, specialization, bio, createdAt, updatedAt)
    VALUES (?, ?, ?, 'Trí tuệ Nhân tạo & NLP', 'Trưởng bộ môn Trí tuệ Nhân tạo BTEC FPT', NOW(), NOW())
    ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), specialization = VALUES(specialization), bio = VALUES(bio), updatedAt = NOW()
")->execute([$teacherProfileId, $teacherUserId, $schoolId]);

// 4.3. Core Enterprise Account: fpt@talenthub.local
$enterpriseUserId = '31000000-0000-4000-8000-000000000015';
$enterpriseId = '31000000-0000-4000-8000-000000000015';
$pdo->prepare("
    INSERT INTO users (id, email, passwordHash, fullName, roleId, status, createdAt, updatedAt)
    VALUES (?, 'fpt@talenthub.local', ?, 'FPT Software', ?, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), roleId = VALUES(roleId), status = 'active', updatedAt = NOW()
")->execute([$enterpriseUserId, $pwdHash, $enterpriseRoleId]);

$pdo->prepare("
    INSERT INTO enterprises (id, name, taxCode, industry, companySize, address, phone, email, website, verificationStatus, status, createdAt, updatedAt)
    VALUES (?, 'Công ty TNHH Phần mềm FPT (FPT Software)', '0101778163', 'Công nghệ thông tin / AI / Phần mềm', '10000+', 'FPT Tower, 10 Phạm Văn Bạch, Cầu Giấy, Hà Nội', '024 7300 7300', 'fpt@talenthub.local', 'https://fptsoftware.com', 'verified', 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name), verificationStatus = 'verified', status = 'active', updatedAt = NOW()
")->execute([$enterpriseId]);

$pdo->prepare("
    INSERT INTO enterprise_members (id, enterpriseId, userId, memberRole, createdAt, updatedAt)
    VALUES (?, ?, ?, 'admin', NOW(), NOW())
    ON DUPLICATE KEY UPDATE enterpriseId = VALUES(enterpriseId), memberRole = 'admin', updatedAt = NOW()
")->execute([Uuid::v4(), $enterpriseId, $enterpriseUserId]);

// 4.4. Ensure Class BTEC-AI-2026A
$classId = 'a1e2894b-2386-5404-9695-78a78f5a60d3';
$pdo->prepare("
    INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt)
    VALUES (?, ?, 'BTEC-AI-2026A', 2, '2025-2026', 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), name = VALUES(name), gradeLevel = 2, status = 'active', updatedAt = NOW()
")->execute([$classId, $schoolId]);

// 4.5. Clean Student Account: vuducanh@student.edu.vn (New Clean State)
$studentUserId = '24000000-0000-4000-8000-000000000019';
$studentProfileId = '24000000-0000-4000-8000-000000000009';
$pdo->prepare("
    INSERT INTO users (id, email, passwordHash, fullName, roleId, status, createdAt, updatedAt)
    VALUES (?, 'vuducanh@student.edu.vn', ?, 'Vũ Đức Anh', ?, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), roleId = VALUES(roleId), status = 'active', updatedAt = NOW()
")->execute([$studentUserId, $pwdHash, $studentRoleId]);

$pdo->prepare("
    INSERT INTO student_profiles (id, userId, classId, studyStatus, talentScore, dateOfBirth, phone, createdAt, updatedAt)
    VALUES (?, ?, ?, 'active', NULL, '2004-05-15', '0912345678', NOW(), NOW())
    ON DUPLICATE KEY UPDATE classId = VALUES(classId), studyStatus = 'active', talentScore = NULL, updatedAt = NOW()
")->execute([$studentProfileId, $studentUserId, $classId]);

$pdo->prepare("
    INSERT INTO student_profile_details (studentId, headline, bio, location, createdAt, updatedAt)
    VALUES (?, 'Kỹ sư Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP)', 'Sinh viên lớp BTEC-AI-2026A. Hồ sơ mới bắt đầu hành trình phát triển năng lực.', 'Hà Nội', NOW(), NOW())
    ON DUPLICATE KEY UPDATE headline = VALUES(headline), bio = VALUES(bio), updatedAt = NOW()
")->execute([$studentProfileId]);

// Clean student@talenthub.local alias if exists
$demoStudentUserId = '6f0bea58-f68c-4a18-8344-09e5975d6a76';
$demoStudentProfileId = '65a3409c-996c-4b85-ab9c-ea9a39dcc0e6';
$pdo->prepare("
    INSERT INTO users (id, email, passwordHash, fullName, roleId, status, createdAt, updatedAt)
    VALUES (?, 'student@talenthub.local', ?, 'Demo Student', ?, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), roleId = VALUES(roleId), status = 'active', updatedAt = NOW()
")->execute([$demoStudentUserId, $pwdHash, $studentRoleId]);

$pdo->prepare("
    INSERT INTO student_profiles (id, userId, classId, studyStatus, talentScore, dateOfBirth, phone, createdAt, updatedAt)
    VALUES (?, ?, ?, 'active', NULL, '2004-01-01', '0912345679', NOW(), NOW())
    ON DUPLICATE KEY UPDATE classId = VALUES(classId), studyStatus = 'active', talentScore = NULL, updatedAt = NOW()
")->execute([$demoStudentProfileId, $demoStudentUserId, $classId]);

// Reset all other existing students' talentScore to NULL/clean state
$pdo->exec("UPDATE student_profiles SET talentScore = NULL WHERE id != '{$studentProfileId}'");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n[5/6] Khởi tạo & Cập nhật các tài khoản mẫu thành công!\n";
echo " 1. School Admin: school@talenthub.local | Mật khẩu: 123456\n";
echo " 2. Teacher:      teacher@talenthub.local | Mật khẩu: 123456\n";
echo " 3. Enterprise:   fpt@talenthub.local     | Mật khẩu: 123456\n";
echo " 4. Student:      vuducanh@student.edu.vn | Mật khẩu: 123456 (Trạng thái Mới tinh: 0 điểm, 0 kỹ năng)\n";

// 6. Final verification of counters
echo "\n[6/6] Kiểm tra các bộ đếm sau khi Reset:\n";
$appCount = (int) $pdo->query("SELECT COUNT(*) FROM internship_applications")->fetchColumn();
$notifCount = (int) $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$scoreCount = (int) $pdo->query("SELECT COUNT(*) FROM student_skills")->fetchColumn();
$badgeCount = (int) $pdo->query("SELECT COUNT(*) FROM student_badges")->fetchColumn();
$certCount = (int) $pdo->query("SELECT COUNT(*) FROM student_school_certificates")->fetchColumn();
$testCount = (int) $pdo->query("SELECT COUNT(*) FROM test_attempts")->fetchColumn();

echo " - Đơn ứng tuyển (internship_applications): {$appCount}\n";
echo " - Thông báo (notifications): {$notifCount}\n";
echo " - Kỹ năng sinh viên (student_skills): {$scoreCount}\n";
echo " - Huy hiệu (student_badges): {$badgeCount}\n";
echo " - Chứng chỉ (student_school_certificates): {$certCount}\n";
echo " - Bài thi đánh giá (test_attempts): {$testCount}\n\n";

echo "======================================================================\n";
echo " HỆ THỐNG ĐÃ ĐƯỢC RESET VỀ TRẠNG THÁI SẠCH HOÀN TOÀN 100%!\n";
echo "======================================================================\n";
