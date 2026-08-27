<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Contracts\TalentPassportRepository;
use TalentHub\Learner\Data\Mock\MockTalentPassportRepository;
use TalentHub\Learner\Data\ReadModel\TalentPassportReadModel;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function passport_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$studentId = '0191316b-1000-4000-8000-000000000001';

// 1. ReadModel stable keys and structure
$rawAggregate = [
    'student' => [
        'id' => $studentId,
        'full_name' => 'Database Learner',
        'email' => 'learner@example.com',
        'class_name' => '12A1',
        'school_name' => 'THPT Chuyên',
        'study_status' => 'studying',
    ],
    'skills' => [
        [
            'skill_id' => 'sk-1',
            'code' => 'PYTHON',
            'name' => 'Python',
            'category' => 'technical',
            'level_score' => 85.0,
            'source_type' => 'assessment',
            'verification_status' => 'verified',
            'verified_at' => '2026-08-01 10:00:00',
        ],
    ],
    'experience' => [
        'confirmed_hours' => 15.5,
        'confirmed_entries' => [
            [
                'id' => 'exp-1',
                'activity_id' => 'act-1',
                'title' => 'Olympic Tin học',
                'hours' => 15.5,
                'status' => 'confirmed',
                'confirmed_at' => '2026-08-10 12:00:00',
            ],
        ],
    ],
    'assessment_results' => [
        [
            'attempt_id' => 'att-1',
            'test_code' => 'LOGIC-01',
            'test_name' => 'Tư duy logic',
            'result_code' => 'PASS',
            'summary' => 'Tốt',
            'dimension_scores' => ['analytical' => 85],
            'submitted_at' => '2026-08-05 14:00:00',
        ],
    ],
    'teacher_evaluations' => [
        [
            'id' => 'ev-1',
            'teacher_name' => 'Thầy Minh',
            'activity_title' => 'Olympic Tin học',
            'overall_score' => 9.0,
            'comment' => 'Xuất sắc',
            'status' => 'published',
            'published_at' => '2026-08-12 09:00:00',
            'criteria_scores' => [
                ['criteria_name' => 'Chuyên cần', 'score' => 9.0],
            ],
        ],
    ],
    'activity_summary' => [
        'registered_count' => 3,
        'attended_count' => 2,
        'confirmed_hours' => 15.5,
    ],
    'certificates' => [],
    'projects' => [],
    'badges' => [],
    'source_timestamps' => [
        'skills' => '2026-08-01 10:00:00',
        'experience' => '2026-08-10 12:00:00',
    ],
    'capabilities' => [
        'certificates' => false,
        'projects' => false,
        'badges' => false,
    ],
];

$view = TalentPassportReadModel::fromAggregate($rawAggregate);

$expectedKeys = [
    'student', 'skills', 'experience', 'assessment_results', 'teacher_evaluations',
    'activity_summary', 'certificates', 'projects', 'badges', 'ai_capability_profile', 'source_timestamps', 'capabilities',
];
passport_contract_assert(array_keys($view) === $expectedKeys, 'Talent Passport shape is stable and exact');
passport_contract_assert($view['certificates'] === [] && $view['projects'] === [] && $view['badges'] === [], 'future facts remain empty');
passport_contract_assert($view['experience']['confirmed_hours'] === 15.5, 'confirmed hours preserved');
passport_contract_assert(count($view['skills']) === 1, 'skills preserved');
passport_contract_assert($view['capabilities']['certificates'] === false, 'capabilities flags preserved');

// 2. ReadModel handles empty/absent aggregate safely
$emptyAggregate = [
    'student' => ['id' => $studentId],
    'skills' => [],
    'experience' => ['confirmed_hours' => 0.0, 'confirmed_entries' => []],
    'assessment_results' => [],
    'teacher_evaluations' => [],
    'activity_summary' => [],
    'certificates' => [],
    'projects' => [],
    'badges' => [],
    'source_timestamps' => [],
    'capabilities' => ['certificates' => false, 'projects' => false, 'badges' => false],
];
$emptyView = TalentPassportReadModel::fromAggregate($emptyAggregate);
passport_contract_assert(array_keys($emptyView) === $expectedKeys, 'Empty aggregate has stable keys');
passport_contract_assert($emptyView['source_timestamps'] === [], 'missing timestamps are not replaced with now');
passport_contract_assert($emptyView['experience']['confirmed_hours'] === 0.0, 'default confirmed hours is 0.0');

// 3. MockTalentPassportRepository contract
$mockFixture = [
    'student' => ['id' => $studentId, 'full_name' => 'Mock Learner'],
    'skills' => [['name' => 'Problem Solving']],
    'experience' => ['confirmed_hours' => 8.0, 'confirmed_entries' => []],
    'assessment_results' => [],
    'teacher_evaluations' => [],
    'activity_summary' => ['registered_count' => 1],
    'certificates' => [],
    'projects' => [],
    'badges' => [],
    'source_timestamps' => [],
    'capabilities' => ['certificates' => false, 'projects' => false, 'badges' => false],
];

$mockRepo = new MockTalentPassportRepository($mockFixture);
passport_contract_assert($mockRepo instanceof TalentPassportRepository, 'Mock repository implements TalentPassportRepository');
$mockResult = $mockRepo->aggregateForStudent($studentId);
passport_contract_assert($mockResult['student']['full_name'] === 'Mock Learner', 'Mock repository returns fixture');

echo "learner_talent_passport_contract_test: OK\n";
