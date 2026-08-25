<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/Testing/MinimalAuthRbacSeeder.php';
require_once dirname(__DIR__) . '/Database/seeds/Demo/SchoolDemoSeeder.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

// Ensure test seed data is loaded
$env = 'test';
putenv('TALENTHUB_TEST_PASSWORD=SecretPassword123!');
$_ENV['TALENTHUB_TEST_PASSWORD'] = 'SecretPassword123!';

$seeder = new MinimalAuthRbacSeeder();
$seeder->run($pdo, $env, 'SecretPassword123!');

$schoolSeeder = new SchoolDemoSeeder();
$schoolSeeder->run($pdo, $env, 'SecretPassword123!');

// Setup session for business user
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$session = new SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => '10000000-0000-4000-8000-000000000014',
    'email' => 'business@test.talenthub.local',
    'fullName' => 'Test Business User',
    'role' => 'enterprise',
    'status' => 'active'
]);

// Ensure privacy consent & talent grant for smoke test candidate
$studentId = '20000000-0000-4000-8000-000000000060';
$entId = '30000000-0000-4000-8000-000000000002';
$consentId = '80000000-0000-4000-8000-000000000001';
$grantId = '80000000-0000-4000-8000-000000000002';
$now = gmdate('Y-m-d H:i:s.u');
$exp = gmdate('Y-m-d H:i:s.u', time() + 30 * 86400);

try {
    $pdo->prepare("INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, createdAt) VALUES (?, ?, 'enterprise_talent_discovery', 1, '1.0', ?, ?)")->execute([$consentId, $studentId, $now, $now]);
} catch (\Throwable $e) {}

try {
    $pdo->prepare("INSERT INTO enterprise_talent_access_grants (id, studentId, enterpriseId, consentId, scope, grantedAt, expiresAt, createdAt, updatedAt) VALUES (?, ?, ?, ?, 'enterprise_talent_discovery', ?, ?, ?, ?)")->execute([$grantId, $studentId, $entId, $consentId, $now, $exp, $now, $now]);
} catch (\Throwable $e) {}

echo "=== Testing Talent Search Page Rendering ===\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents.php';

ob_start();
require dirname(__DIR__) . '/app/enterprise/talents.php';
$output = ob_get_clean();

assert(str_contains($output, 'Tìm nhân tài'), 'Page title "Tìm nhân tài" must be present');
assert(str_contains($output, 'enterprise-session-boot'), 'Inline enterprise session boot JSON must be present');
assert(str_contains($output, 'talent-search.js'), 'talent-search.js must be loaded');
echo " [PASS] Talent Search page rendered successfully.\n";

echo "=== Testing Candidate Detail Page (Numeric ID=1) ===\n";
$_GET['id'] = '1';
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents/detail.php?id=1';

ob_start();
require dirname(__DIR__) . '/app/enterprise/talents/detail.php';
$detailOutput1 = ob_get_clean();

assert(str_contains($detailOutput1, 'Nguyễn Văn An'), 'Candidate 1 name must be present');
assert(str_contains($detailOutput1, 'Đại học Bách Khoa Hà Nội'), 'Candidate 1 school must be present');
assert(str_contains($detailOutput1, 'Quay lại Tìm nhân tài'), 'Back button must be present');
assert(str_contains($detailOutput1, 'Hồ sơ đã bảo vệ danh tính') || str_contains($detailOutput1, 'Gửi yêu cầu'), 'Privacy/Contact feature must be present');
echo " [PASS] Candidate Detail for ID=1 rendered successfully.\n";

echo "=== Testing Candidate Detail Page (Numeric ID=2) ===\n";
$_GET['id'] = '2';
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents/detail.php?id=2';

ob_start();
require dirname(__DIR__) . '/app/enterprise/talents/detail.php';
$detailOutput2 = ob_get_clean();

assert(str_contains($detailOutput2, 'Lê Thị Bích Ngọc'), 'Candidate 2 name must be present');
assert(str_contains($detailOutput2, 'Quay lại Tìm nhân tài'), 'Back button must be present');
echo " [PASS] Candidate Detail for ID=2 rendered successfully.\n";

echo "=== Testing Candidate Detail Page (Database Student Profile UUID) ===\n";
$_GET['id'] = '20000000-0000-4000-8000-000000000060';
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents/detail.php?id=20000000-0000-4000-8000-000000000060';

ob_start();
require dirname(__DIR__) . '/app/enterprise/talents/detail.php';
$detailOutputDb = ob_get_clean();

assert(str_contains($detailOutputDb, 'Nguyễn Văn Minh'), 'Seeded DB student name must be present');
assert(str_contains($detailOutputDb, 'THPT Nguyễn Trãi'), 'Seeded DB student school must be present');
assert(str_contains($detailOutputDb, 'Quay lại Tìm nhân tài'), 'Back button must be present');
echo " [PASS] Candidate Detail for UUID rendered successfully.\n";

echo "=== Testing Candidate Detail Page (Invalid Non-existent ID) ===\n";
$_GET['id'] = '99999';
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents/detail.php?id=99999';

ob_start();
require dirname(__DIR__) . '/app/enterprise/talents/detail.php';
$detailOutputInvalid = ob_get_clean();

assert(str_contains($detailOutputInvalid, 'Không tìm thấy hồ sơ nhân tài'), 'Empty state header must be present for invalid ID');
assert(str_contains($detailOutputInvalid, '99999'), 'Invalid ID must be displayed in empty state message');
assert(str_contains($detailOutputInvalid, 'Quay lại Tìm nhân tài'), 'Back button must be present in empty state');
echo " [PASS] Invalid Candidate ID correctly renders not-found state with back button.\n";

echo "=== ALL SMOKE TESTS PASSED! ===\n";
