<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Support\SharedStudentAdapter;

require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function shared_adapter_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$view = SharedStudentAdapter::toView([
    'id' => '11111111-1111-4111-8111-111111111111',
    'userId' => '22222222-2222-4222-8222-222222222222',
    'email' => 'student@example.test',
    'fullName' => 'Nguyễn Văn A',
    'school' => ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'TalentHub School'],
    'class' => ['id' => '44444444-4444-4444-8444-444444444444', 'name' => '12A1'],
    'dateOfBirth' => '2008-01-02',
    'phone' => '0900000000',
    'studyStatus' => 'active',
], [
    'metrics' => ['profileCompletion' => 100, 'studyStatus' => 'active'],
]);

shared_adapter_assert($view['id'] === '11111111-1111-4111-8111-111111111111', 'student profile id is preserved');
shared_adapter_assert($view['user_id'] === '22222222-2222-4222-8222-222222222222', 'user id is mapped');
shared_adapter_assert($view['name'] === 'Nguyễn Văn A', 'fullName maps to name');
shared_adapter_assert($view['initials'] === 'A', 'initials are deterministic');
shared_adapter_assert($view['class'] === '12A1', 'class name is mapped');
shared_adapter_assert($view['school'] === 'TalentHub School', 'school name is mapped');
shared_adapter_assert($view['verified'] === false, 'foundation does not invent verification');
shared_adapter_assert($view['streak_days'] === 0, 'unknown metrics use safe zero');

echo "learner_shared_student_adapter_test: OK\n";
