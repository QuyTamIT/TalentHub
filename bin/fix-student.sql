-- Bootstrap missing student_profile for student@test.talenthub.local.
-- Run with: mysql -h 127.0.0.1 -u talenthub_app -ptalenthub_pass_2024 talenthub < bin/fix-student.sql

-- 1. Make sure the required parent records exist (school, class).
INSERT IGNORE INTO schools (id, name, status)
VALUES ('10000000-0000-4000-8000-000000000031', 'TalentHub Test School', 'active');

INSERT IGNORE INTO classes (id, schoolId, name, gradeLevel, academicYear)
VALUES ('10000000-0000-4000-8000-000000000032',
        '10000000-0000-4000-8000-000000000031',
        'Test Class 12A', 12, '2026-2027');

-- 2. Bootstrap student_profile linking to user student@test.talenthub.local
INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus)
SELECT '10000000-0000-4000-8000-000000000021',
       u.id,
       '10000000-0000-4000-8000-000000000032',
       '2008-05-20',
       '0900000001',
       'active'
FROM users u
WHERE u.email = 'student@test.talenthub.local'
ON DUPLICATE KEY UPDATE studyStatus = VALUES(studyStatus);

-- Verify
SELECT u.email, sp.id AS student_profile_id, sp.studyStatus
FROM users u
LEFT JOIN student_profiles sp ON sp.userId = u.id
WHERE u.email = 'student@test.talenthub.local';