USE `talenthub`;

-- Lấy admin ID để bảo vệ
SELECT id INTO @adminId FROM users WHERE email = 'admin@admin.com' LIMIT 1;

SET FOREIGN_KEY_CHECKS = 0;

-- Xóa auth rate limits
DELETE FROM auth_rate_limits;

-- Xóa organization registration requests (trừ admin)
DELETE FROM organization_registration_requests WHERE email != 'admin@admin.com';

-- Xóa enterprises, schools, classes (xóa trước profile để tránh FK)
DELETE FROM enterprise_members;
DELETE FROM school_members;
DELETE FROM enterprises;
DELETE FROM schools;
DELETE FROM classes;

-- Xóa profile liên kết (tất cả, vì enterprises/schools đã xóa)
DELETE FROM student_profiles;
DELETE FROM student_profile_details;
DELETE FROM student_skills;
DELETE FROM student_badges;
DELETE FROM student_enterprise_school_approvals;
DELETE FROM teacher_profiles;
DELETE FROM teacher_class_assignments;

-- Xóa AI/learner data
DELETE FROM learner_ai_roadmaps;
DELETE FROM learner_ai_capability_profiles;

-- Xóa notifications, consents, logs
DELETE FROM privacy_consents;
DELETE FROM notifications;
DELETE FROM learner_notification_preferences;
DELETE FROM experience_logs;

-- Xóa users trừ admin
DELETE FROM users WHERE email != 'admin@admin.com';

SET FOREIGN_KEY_CHECKS = 1;

SELECT '--- Cleanup done. Remaining users ---' as info;
SELECT u.email, r.code as role FROM users u JOIN roles r ON u.roleId = r.id;
