<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\Business\Service\PaymentConfirmationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE projects (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    schoolId TEXT NOT NULL,
    mentorTeacherId TEXT,
    fundingGoal NUMERIC,
    fundingStatus TEXT NOT NULL DEFAULT 'open',
    fundingReachedAt TEXT,
    updatedAt TEXT NOT NULL
);
CREATE TABLE project_sponsorships (
    id TEXT PRIMARY KEY,
    enterpriseId TEXT NOT NULL,
    projectId TEXT NOT NULL,
    amount NUMERIC NOT NULL,
    status TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);
CREATE TABLE payment_orders (
    id TEXT PRIMARY KEY,
    enterpriseId TEXT NOT NULL,
    sponsorshipId TEXT NOT NULL,
    amount NUMERIC NOT NULL,
    currency TEXT NOT NULL,
    provider TEXT NOT NULL,
    paymentStatus TEXT NOT NULL,
    providerReference TEXT,
    paidAt TEXT,
    updatedAt TEXT NOT NULL
);
SQL);

$ids = [
    'project' => '10000000-0000-4000-8000-000000000001',
    'school' => '20000000-0000-4000-8000-000000000001',
    'enterprise' => '30000000-0000-4000-8000-000000000001',
    'sponsorship' => '40000000-0000-4000-8000-000000000001',
    'order' => '50000000-0000-4000-8000-000000000001',
];
$now = '2026-08-29 00:00:00.000000';
$pdo->prepare('INSERT INTO projects (id,title,schoolId,fundingGoal,fundingStatus,updatedAt) VALUES (?,?,?,?,?,?)')
    ->execute([$ids['project'], 'Project School', $ids['school'], 1000000, 'open', $now]);
$pdo->prepare('INSERT INTO project_sponsorships (id,enterpriseId,projectId,amount,status,updatedAt) VALUES (?,?,?,?,?,?)')
    ->execute([$ids['sponsorship'], $ids['enterprise'], $ids['project'], 1000000, 'pending_payment', $now]);
$pdo->prepare('INSERT INTO payment_orders (id,enterpriseId,sponsorshipId,amount,currency,provider,paymentStatus,updatedAt) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([$ids['order'], $ids['enterprise'], $ids['sponsorship'], 1000000, 'VND', 'vnpay', 'pending', $now]);

$service = new PaymentConfirmationService($pdo);
$result = $service->confirmPayment($ids['enterprise'], $ids['order'], ['providerReference' => 'SCHOOL-FUNDING-001'], 'school-funding-test');

$assert(($result['paymentStatus'] ?? null) === 'paid', 'payment becomes paid');
$assert(($result['projectFundingStatus'] ?? null) === 'goal_reached', 'payment updates project funding status');
$assert(($result['projectFundingPercentage'] ?? null) === 100, 'payment returns the aggregate funding percentage');
$project = $pdo->query('SELECT fundingStatus, fundingReachedAt FROM projects')->fetch(PDO::FETCH_ASSOC);
$assert(($project['fundingStatus'] ?? null) === 'goal_reached', 'project persists goal_reached');
$assert(!empty($project['fundingReachedAt']), 'project persists the first reached timestamp');
$assert($pdo->query("SELECT status FROM project_sponsorships")->fetchColumn() === 'paid', 'sponsorship becomes paid atomically');

$again = $service->confirmPayment($ids['enterprise'], $ids['order'], ['providerReference' => 'IGNORED'], 'school-funding-test-repeat');
$assert(($again['isIdempotent'] ?? null) === true, 'repeat confirmation is idempotent');
$assert(($again['projectFundingStatus'] ?? null) === 'goal_reached', 'idempotent response retains aggregate state');

echo "school_project_funding_flow_test: OK\n";
