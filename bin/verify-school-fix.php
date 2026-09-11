<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolDemoSeeder.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Modules\School\Service\SchoolAuthorization;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "1. Checking table school_enterprise_partnerships..." . PHP_EOL;
$stmt = $pdo->query("SHOW TABLES LIKE 'school_enterprise_partnerships'");
$table = $stmt->fetchColumn();
if ($table !== 'school_enterprise_partnerships') {
    throw new RuntimeException("Table school_enterprise_partnerships does not exist!");
}
echo "   [OK] Table school_enterprise_partnerships exists." . PHP_EOL;

echo "2. Checking rows in school_enterprise_partnerships..." . PHP_EOL;
$count = (int) $pdo->query("SELECT COUNT(*) FROM school_enterprise_partnerships")->fetchColumn();
echo "   [OK] Count: {$count} partnerships." . PHP_EOL;

echo "3. Testing SchoolRepository::dashboardMetrics()..." . PHP_EOL;
$schoolStmt = $pdo->query("SELECT id, name FROM schools LIMIT 1");
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
if (!$school) {
    echo "   [OK] 0 schools in database (clean production handover state)." . PHP_EOL;
} else {
    $schoolId = (string) $school['id'];
    echo "   School: {$school['name']} ({$schoolId})" . PHP_EOL;

    $repo = new SchoolRepository($pdo);
    $metrics = $repo->dashboardMetrics($schoolId);
    echo "   [OK] Metrics loaded successfully: " . json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo "4. Testing SchoolDashboardService::dashboard()..." . PHP_EOL;
$repo = new SchoolRepository($pdo);
$service = new SchoolDashboardService($repo, $pdo, new SchoolAuthorization($pdo));
$dash = $service->dashboard('00000000-0000-0000-0000-000000000000');
echo "   [OK] Dashboard empty-state loaded safely: KPIs count = " . count($dash['kpis']) . ", Top talents = " . count($dash['topTalents']) . PHP_EOL;

echo "5. Testing SchoolPartnershipRepository..." . PHP_EOL;
$partnerRepo = new SchoolPartnershipRepository($pdo);
$partnerships = $partnerRepo->listEnterprisePartnerships('10000000-0000-4000-8000-000000000003');
echo "   [OK] Enterprise partnerships list count: " . count($partnerships) . PHP_EOL;

echo PHP_EOL . "=== ALL CHECKS PASSED SUCCESSFULLY ===" . PHP_EOL;
