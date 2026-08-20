<?php
declare(strict_types=1);

use TalentHub\Database\Seeds\Demo\CompleteAiDemoDataset;

require_once dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php';

function demo_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$clock = new DateTimeImmutable('2026-08-20 00:00:00.000000', new DateTimeZone('UTC'));
$learners = CompleteAiDemoDataset::learners();
$activities = CompleteAiDemoDataset::activities($clock);
$heroes = CompleteAiDemoDataset::heroStudentIds();

demo_contract_assert(count($learners) === 19, '11 high-school plus 8 university learners');
demo_contract_assert(count(array_filter($learners, fn (array $r): bool => $r['band'] === 'high')) === 11, '11 high-school learners');
demo_contract_assert(count(array_filter($learners, fn (array $r): bool => $r['band'] === 'college')) === 8, '8 university learners');
demo_contract_assert(count(CompleteAiDemoDataset::fptTeachers()) === 4, '4 FPT lecturers');
demo_contract_assert(count($activities) === 18, '10 THPT plus 8 FPT activities');
demo_contract_assert($heroes['high'] === '20000000-0000-4000-8000-000000000060', 'existing THPT hero is stable');
demo_contract_assert(str_starts_with($heroes['college'], '22000000-'), 'FPT hero uses university namespace');

$emails = array_column(array_filter($learners, fn (array $r): bool => $r['band'] === 'college'), 'email');
demo_contract_assert(in_array('sv.fpt.an@talenthub.vn', $emails, true), 'college hero login exists');
demo_contract_assert(count(array_unique($emails)) === 8, 'university emails are unique');
foreach ($emails as $email) {
    demo_contract_assert(str_ends_with($email, '@talenthub.vn'), 'demo email uses TalentHub domain');
    demo_contract_assert(!str_ends_with($email, '@fpt.edu.vn'), 'no official FPT address is fabricated');
}

foreach (CompleteAiDemoDataset::assessmentPlan() as $studentId => $codes) {
    $band = array_values(array_filter($learners, fn (array $r): bool => $r['student_id'] === $studentId))[0]['band'];
    demo_contract_assert(count($codes) >= 2, 'every learner has at least two assessments');
    foreach ($codes as $code) {
        demo_contract_assert(str_ends_with($code, '_' . $band), 'assessment code matches learner band');
    }
}

demo_contract_assert(count(CompleteAiDemoDataset::registrationPlan()) === 40, 'exactly 40 registrations');
demo_contract_assert(CompleteAiDemoDataset::expectedMinimums() === [
    'learners' => 19,
    'activities' => 18,
    'registrations' => 40,
    'checkins' => 20,
    'experiences' => 20,
    'published_evaluations' => 20,
    'consent_events' => 76,
], 'minimum contract is exact');

$source = file_get_contents(dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php');
demo_contract_assert(is_string($source), 'dataset source is readable');
foreach (['sk-', 'TALENTHUB_AI_API_KEY', 'rawToken', 'DELETE FROM', 'TRUNCATE ', 'DROP TABLE'] as $forbidden) {
    demo_contract_assert(!str_contains($source, $forbidden), 'dataset excludes secret/destructive token: ' . $forbidden);
}

echo "complete_ai_demo_dataset_contract_test: OK\n";
