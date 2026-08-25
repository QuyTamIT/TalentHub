-- ============================================================================
-- TalentHub - Database Seed Script for 4 Demo Accounts
-- Compatible with MySQL 8+ / MariaDB (HeidiSQL / Laragon)
-- All accounts password: Talenthub@123
-- ============================================================================

USE `talenthub`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. SEED CANONICAL ROLES
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `isSystem`, `createdAt`, `updatedAt`)
VALUES 
    ('c8b7001c-6496-5dcf-ab0a-dd384c1ba673', 'student', 'Student', 'Học sinh hoặc sinh viên', 1, NOW(6), NOW(6)),
    ('926a59f0-051a-5f52-8501-2a2fd9c2d1a4', 'teacher', 'Teacher', 'Giáo viên hoặc huấn luyện viên', 1, NOW(6), NOW(6)),
    ('63ff7548-6700-52e0-973d-c9feafeeee29', 'school', 'School', 'Nhân sự quản trị nhà trường', 1, NOW(6), NOW(6)),
    ('8dcbaaac-be69-5d75-92e0-cdd0289642e3', 'enterprise', 'Enterprise', 'Nhân sự doanh nghiệp', 1, NOW(6), NOW(6)),
    ('5b572619-2cf9-5b8a-bf4e-58ee4d5df925', 'platform_admin', 'Platform Admin', 'TalentHub platform administrator', 1, NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE 
    `code` = VALUES(`code`),
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `isSystem` = 1,
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 2. SEED DEMO SCHOOL & CLASS
-- ----------------------------------------------------------------------------
INSERT INTO `schools` (`id`, `name`, `status`, `logoUrl`, `address`, `phone`, `email`, `website`, `level`, `studentCount`, `teacherCount`, `academicYear`, `createdAt`, `updatedAt`)
VALUES (
    '20000000-0000-4000-8000-000000000001',
    'THPT Nguyễn Trãi',
    'active',
    '/assets/img/schools/logo-nguyen-trai.png',
    '12 Sư Vạn Hạnh, Quận 10, TP. Hồ Chí Minh',
    '028-3863-1234',
    'c3-nguyentrai@hcm.edu.vn',
    'https://thptnguyentrai.edu.vn',
    'Trung học Phổ thông',
    10,
    5,
    '2025-2026',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `status` = 'active',
    `academicYear` = VALUES(`academicYear`),
    `updatedAt` = NOW(6);

INSERT INTO `classes` (`id`, `schoolId`, `name`, `gradeLevel`, `academicYear`, `status`, `createdAt`, `updatedAt`)
VALUES (
    '20000000-0000-4000-8000-000000000030',
    '20000000-0000-4000-8000-000000000001',
    '10A',
    10,
    '2025-2026',
    'active',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `gradeLevel` = VALUES(`gradeLevel`),
    `status` = 'active',
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 3. SEED DEMO ENTERPRISE
-- ----------------------------------------------------------------------------
INSERT INTO `enterprises` (`id`, `name`, `status`, `logoUrl`, `industry`, `companySize`, `foundedYear`, `description`, `email`, `phone`, `website`, `taxCode`, `address`, `verificationStatus`, `createdAt`, `updatedAt`)
VALUES (
    '32000000-0000-4000-8000-000000000001',
    'FPT Software',
    'active',
    NULL,
    'Công nghệ thông tin',
    '1000+',
    1999,
    'Công ty TNHH Phần mềm FPT - Doanh nghiệp công nghệ hàng đầu.',
    'enterprise@talenthub.local',
    '02473007575',
    'https://fpt-software.com',
    '0101234567',
    'Khu CNC Hòa Lạc, Thạch Thất, Hà Nội',
    'verified',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `status` = 'active',
    `email` = VALUES(`email`),
    `verificationStatus` = 'verified',
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 4. SEED 4 DEMO USERS (Password: Talenthub@123)
-- Hash: $2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `roleId`, `email`, `passwordHash`, `fullName`, `status`, `createdAt`, `updatedAt`)
VALUES 
    (
        '31000000-0000-4000-8000-000000000004',
        'c8b7001c-6496-5dcf-ab0a-dd384c1ba673',
        'student@talenthub.local',
        '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
        'Nguyễn Văn An',
        'active',
        NOW(6),
        NOW(6)
    ),
    (
        '31000000-0000-4000-8000-000000000003',
        '926a59f0-051a-5f52-8501-2a2fd9c2d1a4',
        'teacher@talenthub.local',
        '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
        'Thầy Nguyễn Văn Bình',
        'active',
        NOW(6),
        NOW(6)
    ),
    (
        '31000000-0000-4000-8000-000000000002',
        '63ff7548-6700-52e0-973d-c9feafeeee29',
        'school@talenthub.local',
        '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
        'Ban Giám hiệu THPT Nguyễn Trãi',
        'active',
        NOW(6),
        NOW(6)
    ),
    (
        '31000000-0000-4000-8000-000000000001',
        '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
        'enterprise@talenthub.local',
        '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
        'FPT Software HR',
        'active',
        NOW(6),
        NOW(6)
    )
ON DUPLICATE KEY UPDATE 
    `roleId` = VALUES(`roleId`),
    `passwordHash` = VALUES(`passwordHash`),
    `fullName` = VALUES(`fullName`),
    `status` = 'active',
    `updatedAt` = NOW(6);

-- Đảm bảo roleId đúng chuẩn cho cả trường hợp user đã tồn tại với ID khác
UPDATE `users` SET `roleId` = 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673', `passwordHash` = '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W', `status` = 'active' WHERE `email` = 'student@talenthub.local';
UPDATE `users` SET `roleId` = '926a59f0-051a-5f52-8501-2a2fd9c2d1a4', `passwordHash` = '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W', `status` = 'active' WHERE `email` = 'teacher@talenthub.local';
UPDATE `users` SET `roleId` = '63ff7548-6700-52e0-973d-c9feafeeee29', `passwordHash` = '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W', `status` = 'active' WHERE `email` = 'school@talenthub.local';
UPDATE `users` SET `roleId` = '8dcbaaac-be69-5d75-92e0-cdd0289642e3', `passwordHash` = '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W', `status` = 'active' WHERE `email` = 'enterprise@talenthub.local';

-- ----------------------------------------------------------------------------
-- 5. SEED STUDENT PROFILE (student@talenthub.local)
-- ----------------------------------------------------------------------------
DELETE FROM `student_profiles` WHERE `userId` IN (SELECT `id` FROM `users` WHERE `email` = 'student@talenthub.local');

INSERT INTO `student_profiles` (`id`, `userId`, `classId`, `dateOfBirth`, `phone`, `studyStatus`, `createdAt`, `updatedAt`)
SELECT 
    '35000000-0000-4000-8000-000000000001',
    u.`id`,
    '20000000-0000-4000-8000-000000000030',
    '2008-05-15',
    '0987654321',
    'active',
    NOW(6),
    NOW(6)
FROM `users` u
WHERE u.`email` = 'student@talenthub.local'
LIMIT 1;

-- ----------------------------------------------------------------------------
-- 6. SEED TEACHER PROFILE (teacher@talenthub.local)
-- ----------------------------------------------------------------------------
DELETE FROM `teacher_profiles` WHERE `userId` IN (SELECT `id` FROM `users` WHERE `email` = 'teacher@talenthub.local');

INSERT INTO `teacher_profiles` (`id`, `userId`, `schoolId`, `isSchoolAdmin`, `phone`, `specialization`, `bio`, `createdAt`, `updatedAt`)
SELECT 
    '34000000-0000-4000-8000-000000000001',
    u.`id`,
    '20000000-0000-4000-8000-000000000001',
    0,
    '0912345678',
    'Toán - Tin học',
    'Giáo viên Tin học & Hướng nghiệp Công nghệ',
    NOW(6),
    NOW(6)
FROM `users` u
WHERE u.`email` = 'teacher@talenthub.local'
LIMIT 1;

-- ----------------------------------------------------------------------------
-- 7. SEED SCHOOL MEMBERSHIP (school@talenthub.local)
-- ----------------------------------------------------------------------------
DELETE FROM `school_members` WHERE `userId` IN (SELECT `id` FROM `users` WHERE `email` = 'school@talenthub.local');

INSERT INTO `school_members` (`id`, `schoolId`, `userId`, `memberRole`, `createdAt`, `updatedAt`)
SELECT 
    '36000000-0000-4000-8000-000000000001',
    '20000000-0000-4000-8000-000000000001',
    u.`id`,
    'admin',
    NOW(6),
    NOW(6)
FROM `users` u
WHERE u.`email` = 'school@talenthub.local'
LIMIT 1;

-- ----------------------------------------------------------------------------
-- 8. SEED ENTERPRISE MEMBERSHIP (enterprise@talenthub.local)
-- ----------------------------------------------------------------------------
DELETE FROM `enterprise_members` WHERE `userId` IN (SELECT `id` FROM `users` WHERE `email` = 'enterprise@talenthub.local');

INSERT INTO `enterprise_members` (`id`, `enterpriseId`, `userId`, `memberRole`, `createdAt`, `updatedAt`)
SELECT 
    '33000000-0000-4000-8000-000000000001',
    e.`id`,
    u.`id`,
    'admin',
    NOW(6),
    NOW(6)
FROM `users` u
JOIN `enterprises` e ON (e.`id` = '32000000-0000-4000-8000-000000000001' OR e.`email` = 'enterprise@talenthub.local' OR e.`name` = 'FPT Software')
WHERE u.`email` = 'enterprise@talenthub.local'
LIMIT 1;

SET FOREIGN_KEY_CHECKS = 1;
