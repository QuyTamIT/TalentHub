<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->beginTransaction();

try {
    $passwordHash = password_hash('Talenthub@123', PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Could not hash the demo account password.');
    }

    $accounts = [
        ['31000000-0000-4000-8000-000000000001', 'enterprise@talenthub.local', 'Nguyễn Minh Anh', 'enterprise'],
        ['31000000-0000-4000-8000-000000000002', 'school@talenthub.local', 'Trần Hoàng Nam', 'school'],
        ['31000000-0000-4000-8000-000000000003', 'teacher@talenthub.local', 'Lê Thu Hà', 'teacher'],
    ];
    $user = $pdo->prepare(
        "INSERT INTO users(id,email,passwordHash,fullName,roles,status)
         VALUES(?,?,?,?,?,'active')
         ON DUPLICATE KEY UPDATE passwordHash=VALUES(passwordHash),fullName=VALUES(fullName),roles=VALUES(roles),status='active'"
    );
    foreach ($accounts as $account) {
        $user->execute([$account[0], $account[1], $passwordHash, $account[2], $account[3]]);
    }

    $pdo->prepare(
        "INSERT INTO enterprises(id,name,status,email,verificationStatus)
         VALUES(?,?,'active',?,'verified')
         ON DUPLICATE KEY UPDATE name=VALUES(name),status='active',email=VALUES(email),verificationStatus='verified'"
    )->execute([
        '32000000-0000-4000-8000-000000000001',
        'Công ty TalentHub Demo',
        'enterprise@talenthub.local',
    ]);
    $pdo->prepare(
        "INSERT INTO enterprise_members(id,enterpriseId,userId,role)
         VALUES(?,?,?,'owner')
         ON DUPLICATE KEY UPDATE enterpriseId=VALUES(enterpriseId),userId=VALUES(userId),role='owner'"
    )->execute([
        '33000000-0000-4000-8000-000000000001',
        '32000000-0000-4000-8000-000000000001',
        '31000000-0000-4000-8000-000000000001',
    ]);
    $pdo->prepare(
        'INSERT INTO teacher_profiles(id,userId,schoolId,isSchoolAdmin)
         VALUES(?,?,?,0)
         ON DUPLICATE KEY UPDATE userId=VALUES(userId),schoolId=VALUES(schoolId),isSchoolAdmin=0'
    )->execute([
        '34000000-0000-4000-8000-000000000001',
        '31000000-0000-4000-8000-000000000003',
        'da811c4f-2f74-4fdd-80b0-dd6f26109783',
    ]);

    $pdo->commit();
    echo "Demo accounts imported.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
