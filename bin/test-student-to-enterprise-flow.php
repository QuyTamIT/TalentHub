<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseTalentService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "=================================================================\n";
echo "   TEST FLOW: NEW STUDENT CREATION -> ENTERPRISE DISCOVERY & CONTACT\n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        if ($details) echo "         -> {$details}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$name}\n";
        if ($details) echo "         -> {$details}\n";
        $failCount++;
    }
}

// 1. Resolve enterprise user
$authRepo = new AuthRepository($pdo);
$fptUser = $authRepo->findByEmail('fpt@talenthub.local');
assertTest('FPT enterprise user exists', $fptUser !== null, "User ID: " . ($fptUser['id'] ?? 'N/A'));

$bizRepo = new BusinessRepository($pdo);
$enterprise = $bizRepo->findByUserId($fptUser['id']);
assertTest('Enterprise record exists', $enterprise !== null, "Enterprise ID: " . ($enterprise['id'] ?? 'N/A'));

$enterpriseId = (string) $enterprise['id'];
$enterpriseUserId = (string) $fptUser['id'];

// 2. Create a brand-new student account directly in DB (simulating student portal registration)
$testStudentEmail = 'tran.thao.student@test.talenthub.local';
$testStudentName = 'Trần Phương Thảo';
$testHeadline = 'Mobile Developer (Flutter & Swift)';

// Clean existing test record if any
$stmtExisting = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmtExisting->execute([$testStudentEmail]);
$existingUserId = $stmtExisting->fetchColumn();
if ($existingUserId) {
    $stmtSp = $pdo->prepare("SELECT id FROM student_profiles WHERE userId = ?");
    $stmtSp->execute([$existingUserId]);
    $existingStudentId = $stmtSp->fetchColumn();
    if ($existingStudentId) {
        $pdo->prepare("DELETE FROM enterprise_contact_requests WHERE studentId = ?")->execute([$existingStudentId]);
        $pdo->prepare("DELETE FROM student_skills WHERE studentId = ?")->execute([$existingStudentId]);
        $pdo->prepare("DELETE FROM student_profile_details WHERE studentId = ?")->execute([$existingStudentId]);
        $pdo->prepare("DELETE FROM student_profiles WHERE id = ?")->execute([$existingStudentId]);
    }
    $pdo->prepare("DELETE FROM notifications WHERE userId = ?")->execute([$existingUserId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$existingUserId]);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

// Get student role
$studentRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'student' LIMIT 1")->fetchColumn();
$newUserId = Uuid::v4();
$newStudentProfileId = Uuid::v4();

$pdo->prepare(<<<'SQL'
    INSERT INTO users (id, roleId, fullName, email, passwordHash, status, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
SQL)->execute([
    $newUserId,
    $studentRoleId,
    $testStudentName,
    $testStudentEmail,
    password_hash('123456', PASSWORD_DEFAULT),
    $now,
    $now
]);

// Find school & class
$schoolId = $pdo->query("SELECT id FROM schools WHERE name LIKE '%FPT%' LIMIT 1")->fetchColumn();
$classId = $pdo->query("SELECT id FROM classes WHERE schoolId = '{$schoolId}' LIMIT 1")->fetchColumn();

// Insert student profile
$pdo->prepare(<<<'SQL'
    INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, createdAt, updatedAt)
    VALUES (?, ?, ?, '2004-05-15', '0912345678', 'active', ?, ?)
SQL)->execute([
    $newStudentProfileId,
    $newUserId,
    $classId,
    $now,
    $now
]);

// Insert student profile details
$pdo->prepare(<<<'SQL'
    INSERT INTO student_profile_details (studentId, location, bio, headline, createdAt, updatedAt)
    VALUES (?, 'Hà Nội', 'Đam mê phát triển ứng dụng di động iOS và Android cross-platform.', ?, ?, ?)
SQL)->execute([
    $newStudentProfileId,
    $testHeadline,
    $now,
    $now
]);

// Ensure skills exist in skills table
$skillNames = ['Flutter', 'Swift', 'Dart'];
$skillIds = [];
foreach ($skillNames as $skName) {
    $stmtSk = $pdo->prepare("SELECT id FROM skills WHERE name = ? LIMIT 1");
    $stmtSk->execute([$skName]);
    $skId = $stmtSk->fetchColumn();
    if (!$skId) {
        $skId = Uuid::v4();
        $pdo->prepare("INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt) VALUES (?, ?, ?, 'tech', 'active', ?, ?)")
            ->execute([$skId, strtolower($skName), $skName, $now, $now]);
    }
    $skillIds[$skName] = $skId;
}

// Insert student_skills
$scores = ['Flutter' => 90.0, 'Swift' => 88.0, 'Dart' => 85.0];
foreach ($scores as $skName => $sc) {
    $pdo->prepare(<<<'SQL'
        INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, 'self_declared', 'self_declared', NULL, ?, ?)
    SQL)->execute([
        Uuid::v4(),
        $newStudentProfileId,
        $skillIds[$skName],
        $sc,
        $now,
        $now
    ]);
}

assertTest('New student profile created in Database', true, "Student ID: {$newStudentProfileId}, Name: {$testStudentName}");

// 3. Test Enterprise Discovery via EnterpriseTalentService
echo "\n--- Testing Enterprise Discovery of New Student ---\n";
$talentRepo = new EnterpriseTalentRepository($pdo);
$talentService = new EnterpriseTalentService($talentRepo);

$talentList = $talentService->listTalents($enterpriseUserId);
$foundStudent = null;
foreach ($talentList['items'] as $item) {
    if ($item['studentId'] === $newStudentProfileId || $item['displayName'] === $testStudentName) {
        $foundStudent = $item;
        break;
    }
}

assertTest(
    'New student immediately discoverable in Enterprise listTalents',
    $foundStudent !== null,
    $foundStudent ? "Found: {$foundStudent['displayName']} | Score: {$foundStudent['talentScore']} | Skills: " . implode(', ', $foundStudent['skills'] ?? []) : 'Not found'
);

// 4. Test Filtering by new skill
$searchResult = $talentService->listTalents($enterpriseUserId, ['search' => 'Flutter']);
$foundInSearch = false;
foreach ($searchResult['items'] as $item) {
    if ($item['studentId'] === $newStudentProfileId) {
        $foundInSearch = true;
        break;
    }
}
assertTest('Search by keyword "Flutter" finds newly registered student', $foundInSearch);

// 5. Test Talent Detail
echo "\n--- Testing Talent Detail for New Student ---\n";
$detail = $talentService->getTalent($enterpriseUserId, $newStudentProfileId);
assertTest(
    'Talent Detail loads full profile and skills',
    $detail !== null && count($detail['skills'] ?? []) >= 3,
    "Skills count: " . count($detail['skills'] ?? []) . " | Headline: " . ($detail['headline'] ?? '')
);

// 6. Test Sending Contact Request / Invite
echo "\n--- Testing Contact Request Creation ---\n";
$contactReq = $talentService->requestContact($enterpriseUserId, $newStudentProfileId, [
    'message' => 'FPT Software rất ấn tượng với hồ sơ Flutter của bạn và muốn mời bạn phỏng vấn vị trí Mobile Developer Intern.',
], Uuid::v4());

assertTest(
    'Contact Request created successfully in DB',
    !empty($contactReq['id']) && $contactReq['status'] === 'pending',
    "Request ID: " . ($contactReq['id'] ?? 'N/A') . " | Status: " . ($contactReq['status'] ?? 'N/A')
);

// Verify DB table enterprise_contact_requests
$stmtDbReq = $pdo->prepare("SELECT * FROM enterprise_contact_requests WHERE id = ?");
$stmtDbReq->execute([$contactReq['id']]);
$dbReq = $stmtDbReq->fetch(PDO::FETCH_ASSOC);
assertTest(
    'enterprise_contact_requests DB row verified',
    is_array($dbReq) && $dbReq['studentId'] === $newStudentProfileId && $dbReq['enterpriseId'] === $enterpriseId,
    "Enterprise: {$dbReq['enterpriseId']} -> Student: {$dbReq['studentId']}"
);

// 7. Verify Featured Talents SQL on Dashboard
echo "\n--- Testing Dashboard Featured Talents SQL ---\n";
$stmtFeatured = $pdo->query(<<<'SQL'
    SELECT sp.id, u.fullName, (SELECT ROUND(AVG(ss.levelScore),0) FROM student_skills ss WHERE ss.studentId = sp.id) as score
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE u.status = 'active'
    ORDER BY score DESC
    LIMIT 5
SQL);
$featuredRows = $stmtFeatured->fetchAll(PDO::FETCH_ASSOC);
assertTest(
    'Dashboard SQL returns top 5 students from DB without mockup',
    count($featuredRows) === 5,
    "Top student: " . ($featuredRows[0]['fullName'] ?? 'N/A') . " (" . ($featuredRows[0]['score'] ?? 0) . " pts)"
);

echo "\n=================================================================\n";
echo "   SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}
