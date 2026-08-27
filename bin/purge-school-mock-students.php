<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " PURGE MOCK STUDENTS: XÓA SẠCH DỮ LIỆU SINH VIÊN TRƯỜNG BTEC FPT VỀ 0\n";
echo "======================================================================\n\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';

// 1. Find all student profiles linked to BTEC school classes
$btecClasses = $pdo->query("SELECT id FROM classes WHERE schoolId = '{$btecSchoolId}'")->fetchAll(PDO::FETCH_COLUMN);
$classIdsStr = "'" . implode("','", $btecClasses) . "'";

echo "[Step 1] Finding student profiles under BTEC classes ({$classIdsStr})...\n";
$btecProfiles = $pdo->query("SELECT id, userId FROM student_profiles WHERE classId IN ({$classIdsStr})")->fetchAll(PDO::FETCH_ASSOC);
echo " -> Found " . count($btecProfiles) . " student profiles in BTEC.\n";

$profileIds = array_column($btecProfiles, 'id');
$userIds = array_column($btecProfiles, 'userId');

// 2. Delete related records for these student profiles
if (!empty($profileIds)) {
    $pList = "'" . implode("','", $profileIds) . "'";
    $pdo->exec("DELETE FROM student_profile_details WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM student_skills WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM student_badges WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM student_school_certificates WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM test_attempts WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM internship_applications WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM student_profiles WHERE id IN ({$pList})");
    echo " -> Deleted " . count($profileIds) . " student_profiles and child table records.\n";
}

// 3. Delete mock users with @student.btec.talenthub.local or specific mock emails
echo "[Step 2] Deleting mock user accounts...\n";
$mockEmailPatterns = [
    '%@student.btec.talenthub.local%',
    '%@student.btec.fpt.edu.vn%',
    'tamlangtu2005@gmail.com',
    'abcd@gmail.com',
    'vuducanh@student.edu.vn',
    'student@talenthub.local',
];
foreach ($mockEmailPatterns as $pattern) {
    $delUsers = $pdo->prepare("DELETE FROM users WHERE email LIKE ?");
    $delUsers->execute([$pattern]);
    echo " -> Deleted users matching: {$pattern}\n";
}

// 4. Ensure BTEC classes are intact and empty
echo "[Step 3] Verifying classes for Cao đẳng Quốc tế BTEC FPT...\n";
$classCount = (int) $pdo->query("SELECT COUNT(*) FROM classes WHERE schoolId = '{$btecSchoolId}'")->fetchColumn();
echo " -> Classes count in BTEC FPT: {$classCount}\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// 5. Final count check for BTEC FPT
$remainingStudents = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM student_profiles sp 
    JOIN classes c ON c.id = sp.classId 
    WHERE c.schoolId = '{$btecSchoolId}'
")->fetchColumn();

echo "\n======================================================================\n";
echo " TOTAL STUDENTS IN BTEC FPT: {$remainingStudents}\n";
echo "======================================================================\n";
