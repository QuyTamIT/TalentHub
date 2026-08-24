<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();

    echo "=== Diagnostic for student@test.talenthub.local ===\n\n";

    // 1. Check user exists
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.fullName, u.status, r.code AS role_code, u.roleId
         FROM users u
         LEFT JOIN roles r ON r.id = u.roleId
         WHERE u.email = ?'
    );
    $stmt->execute(['student@test.talenthub.local']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ User student@test.talenthub.local DOES NOT EXIST in users table\n";
        echo "   Action: run php bin/seed.php --testing\n";
        exit(1);
    }

    echo "✅ User found:\n";
    echo "   id       = " . $user['id'] . "\n";
    echo "   email    = " . $user['email'] . "\n";
    echo "   fullName = " . $user['fullName'] . "\n";
    echo "   role     = " . ($user['role_code'] ?? '(null)') . "\n";
    echo "   status   = " . $user['status'] . "\n\n";

    if (($user['role_code'] ?? '') !== 'student') {
        echo "❌ User role is NOT 'student' (got '" . ($user['role_code'] ?? 'null') . "')\n";
        exit(1);
    }

    // 2. Check student_profile
    $stmt = $pdo->prepare('SELECT id, classId, studyStatus FROM student_profiles WHERE userId = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        echo "❌ student_profile DOES NOT EXIST for this user\n";
        echo "   Action: run mysql < bin/fix-student.sql OR php bin/seed.php --testing\n";
        exit(1);
    }

    echo "✅ student_profile found:\n";
    echo "   id          = " . $profile['id'] . "\n";
    echo "   classId     = " . $profile['classId'] . "\n";
    echo "   studyStatus = " . $profile['studyStatus'] . "\n\n";

    // 3. Check class + school
    $stmt = $pdo->prepare('SELECT id, name, gradeLevel, schoolId FROM classes WHERE id = ?');
    $stmt->execute([$profile['classId']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        echo "❌ Class " . $profile['classId'] . " DOES NOT EXIST\n";
        exit(1);
    }

    echo "✅ Class found:\n";
    echo "   id        = " . $class['id'] . "\n";
    echo "   name      = " . $class['name'] . "\n";
    echo "   schoolId  = " . $class['schoolId'] . "\n\n";

    $stmt = $pdo->prepare('SELECT id, name FROM schools WHERE id = ?');
    $stmt->execute([$class['schoolId']]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        echo "❌ School " . $class['schoolId'] . " DOES NOT EXIST\n";
        exit(1);
    }

    echo "✅ School found:\n";
    echo "   id   = " . $school['id'] . "\n";
    echo "   name = " . $school['name'] . "\n\n";

    echo "🎉 All checks PASSED. Student should be able to access /app/learner/\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
