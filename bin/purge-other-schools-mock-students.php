<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " PURGE MOCK STUDENTS: XÓA CÁC TÀI KHOẢN MẪU CŨ FPTU / CTU\n";
echo "======================================================================\n\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$mockEmails = [
    'nguyen.van.an@student.fpt.edu.vn',
    'pham.quoc.bao@student.fpt.edu.vn',
    'sv.fpt.duy@talenthub.vn',
    'sv.fpt.minh@talenthub.vn',
    'sv.fpt.nguyen@talenthub.vn',
    'sv.fpt.chau@talenthub.vn',
    'sv.fpt.quang@talenthub.vn',
    'sv.fpt.bao@talenthub.vn',
    'sv.fpt.linh@talenthub.vn',
    'le.hoang.nam@student.ctu.edu.vn',
    'hoang.mai.linh@student.ctu.edu.vn',
    'lehoangyennhi@student.ctu.edu.vn',
];

foreach ($mockEmails as $email) {
    $uId = $pdo->query("SELECT id FROM users WHERE email = '{$email}' LIMIT 1")->fetchColumn();
    if ($uId) {
        $stId = $pdo->query("SELECT id FROM student_profiles WHERE userId = '{$uId}' LIMIT 1")->fetchColumn();
        if ($stId) {
            $pdo->exec("DELETE FROM student_profile_details WHERE studentId = '{$stId}'");
            $pdo->exec("DELETE FROM student_skills WHERE studentId = '{$stId}'");
            $pdo->exec("DELETE FROM student_badges WHERE studentId = '{$stId}'");
            $pdo->exec("DELETE FROM student_profiles WHERE id = '{$stId}'");
        }
        $pdo->exec("DELETE FROM users WHERE id = '{$uId}'");
        echo " -> Deleted: {$email}\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\nPurge completed successfully.\n";
