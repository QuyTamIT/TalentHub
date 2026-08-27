<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$schoolRepo = new TalentHub\Modules\School\Repository\SchoolRepository($pdo);
$schoolService = new TalentHub\Modules\School\Service\SchoolDashboardService($schoolRepo, $pdo);

$schoolUserId = $pdo->query("SELECT id FROM users WHERE email = 'school@talenthub.local' LIMIT 1")->fetchColumn();
$teachers = $schoolService->teachers($schoolUserId, 100, 0);
echo "=== TEACHERS FOR BTEC FPT ===\n";
print_r($teachers);

$oversight = $schoolService->internshipOversight($schoolUserId);
echo "\n=== INTERNSHIP OVERSIGHT ===\n";
print_r($oversight);
