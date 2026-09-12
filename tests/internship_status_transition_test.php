<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Learner\Data\ReadModel\ApplicationReadModel;
use TalentHub\Learner\Data\Enums\ApplicationStatus;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

// 1. Verify Enum Normalization
$assert(ApplicationStatus::normalize('accepted') === ApplicationStatus::Accepted, 'accepted normalizes to Accepted');
$assert(ApplicationStatus::normalize('hired') === ApplicationStatus::Accepted, 'hired normalizes to Accepted');
$assert(ApplicationStatus::normalize('submitted') === ApplicationStatus::Submitted, 'submitted normalizes to Submitted');

// 2. Verify ApplicationReadModel pipeline and labels
$record = [
    'id' => 'test-app-id',
    'status' => 'accepted',
    'submitted_at_formatted' => '10:00 · 12/09/2026',
    'updated_at_formatted' => '11:00 · 12/09/2026',
];
$viewModel = ApplicationReadModel::application($record);
$assert($viewModel['status_label'] === 'Đã nhận', "Accepted status label must be 'Đã nhận', got: {$viewModel['status_label']}");
$assert($viewModel['can_withdraw'] === false, 'Accepted application cannot be withdrawn');

$steps = $viewModel['pipeline'];
$assert(count($steps) === 4, 'Pipeline has 4 stages');
$assert($steps[0]['state'] === 'complete', 'Step 1 submitted is complete');
$assert($steps[1]['state'] === 'complete', 'Step 2 reviewing is complete when accepted');
$assert($steps[2]['state'] === 'complete', 'Step 3 interview is complete when accepted');
$assert($steps[3]['state'] === 'complete', 'Step 4 decision is complete when accepted');
$assert(str_contains($steps[3]['desc'], 'Chúc mừng! Bạn đã trúng tuyển thực tập'), 'Step 4 congratulates student');

// 3. Verify Database direct transition submitted -> accepted
$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new \TalentHub\Database\Connection($config))->connect();
$repo = new InternshipRepository($pdo);

$app = $pdo->query("
    SELECT ia.id, ia.postId, ia.studentId, ia.status, ip.enterpriseId, em.userId 
    FROM internship_applications ia 
    JOIN internship_posts ip ON ip.id = ia.postId 
    JOIN enterprise_members em ON em.enterpriseId = ip.enterpriseId
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($app) {
    // Reset to submitted
    $pdo->prepare("UPDATE internship_applications SET status = 'submitted' WHERE id = ?")->execute([$app['id']]);
    
    // Direct approve
    $updated = $repo->review(
        $app['enterpriseId'],
        $app['userId'],
        $app['id'],
        'submitted',
        'accepted',
        'Direct acceptance via test'
    );
    $assert($updated['status'] === 'accepted', 'Application status in DB is accepted');

    // Updating note on accepted application should also succeed
    $updatedAgain = $repo->review(
        $app['enterpriseId'],
        $app['userId'],
        $app['id'],
        'accepted',
        'accepted',
        'Updated note on accepted application'
    );
    $assert($updatedAgain['status'] === 'accepted', 'Status remains accepted after note update');
}

echo "internship_status_transition_test (all): OK\n";