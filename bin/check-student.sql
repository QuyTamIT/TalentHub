-- Diagnostic query: check if student@test.talenthub.local has a student_profiles row.
-- Run with: mysql -h 127.0.0.1 -u talenthub_app -ptalenthub_pass_2024 talenthub < bin/check-student.sql

SELECT u.id AS user_id, u.email, u.fullName, r.code AS role_code,
       sp.id AS student_profile_id, sp.studyStatus
FROM users u
LEFT JOIN roles r ON r.id = u.roleId
LEFT JOIN student_profiles sp ON sp.userId = u.id
WHERE u.email = 'student@test.talenthub.local';

-- Bootstrap missing student_profile (only run if the query above returned NULL for student_profile_id)
-- INSERT IGNORE INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus)
-- SELECT '10000000-0000-4000-8000-000000000021', u.id,
--        (SELECT id FROM classes WHERE name = 'Test Class 12A' LIMIT 1),
--        '2008-05-20', '0900000001', 'active'
-- FROM users u WHERE u.email = 'student@test.talenthub.local';