<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();

    echo "=== Bootstrap student_profile for student@test.talenthub.local ===\n\n";

    $studentUserId = '10000000-0000-4000-8000-000000000011';
    $studentProfileId = '10000000-0000-4000-8000-000000000021';
    $schoolId = '10000000-0000-4000-8000-000000000031';
    $classId = '10000000-0000-4000-8000-000000000032';

    // 1. Make sure user exists
    $stmt = $pdo->prepare('SELECT id, roleId FROM users WHERE id = ?');
    $stmt->execute([$studentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        fwrite(STDERR, "FAIL: User $studentUserId not found. Run php bin/seed.php --testing first.\n");
        exit(1);
    }

    $roleStmt = $pdo->query("SELECT id FROM roles WHERE code = 'student'");
    $studentRoleId = $roleStmt->fetchColumn();
    if (!$studentRoleId) {
        fwrite(STDERR, "FAIL: role 'student' not found. Run RolePermissionSeeder first.\n");
        exit(1);
    }

    if ($user['roleId'] !== $studentRoleId) {
        $stmt = $pdo->prepare('UPDATE users SET roleId = ? WHERE id = ?');
        $stmt->execute([$studentRoleId, $studentUserId]);
        echo "✓ Fixed user roleId to student\n";
    }

    // 2. Ensure school + class exist
    $schoolInsert = $pdo->prepare(
        'INSERT IGNORE INTO schools (id, name, status) VALUES (?, ?, ?)'
    );
    $schoolInsert->execute([$schoolId, 'TalentHub Test School', 'active']);
    echo "✓ Ensured school $schoolId exists\n";

    $classInsert = $pdo->prepare(
        'INSERT IGNORE INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES (?, ?, ?, ?, ?)'
    );
    $classInsert->execute([$classId, $schoolId, 'Test Class 12A', 12, '2026-2027']);
    echo "✓ Ensured class $classId exists\n";

    // 3. Insert or update student_profile
    $profileInsert = $pdo->prepare(
        'INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           classId = VALUES(classId),
           studyStatus = VALUES(studyStatus)'
    );
    $profileInsert->execute([
        $studentProfileId,
        $studentUserId,
        $classId,
        '2008-05-20',
        '0900000001',
        'active',
    ]);
    echo "✓ Inserted/updated student_profile $studentProfileId\n\n";

    // Verify
    $stmt = $pdo->prepare('SELECT id, classId, studyStatus FROM student_profiles WHERE userId = ?');
    $stmt->execute([$studentUserId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "=== Verification ===\n";
    echo "student_profile.id = " . $profile['id'] . "\n";
    echo "student_profile.classId = " . $profile['classId'] . "\n";
    echo "student_profile.studyStatus = " . $profile['studyStatus'] . "\n\n";

    echo "🎉 student@test.talenthub.local is now ready. Login again.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
