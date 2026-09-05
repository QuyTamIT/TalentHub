<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolCredentialDemoDataset.php';

use TalentHub\Database\Seeds\Demo\SchoolCredentialDemoDataset;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$data = SchoolCredentialDemoDataset::forSchool('20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi');
$assert(count($data['badges']) === 6, 'six school badges');
$assert(count($data['certificates']) === 4, 'four school certificates');
$assert(count(array_filter($data['badges'], static fn (array $b): bool => $b['code_suffix'] === 'profile_complete')) === 1, 'profile completion badge exists');
$assert(count(array_filter($data['badges'], static fn (array $b): bool => $b['rule'] !== null)) === 1, 'only completion badge has an award rule');

foreach (array_merge($data['badges'], $data['certificates']) as $item) {
    $profile = $item['recommendation_profile'];
    foreach (['holland', 'multiple_intelligence', 'disc', 'mbti', 'skills'] as $key) {
        $assert(array_key_exists($key, $profile), "profile key {$key}");
    }
}

echo "learner_school_credential_seed_test: OK\n";
