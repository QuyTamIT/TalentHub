<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/Service/CredentialRecommendationMatcher.php';

use TalentHub\Learner\Data\Service\CredentialRecommendationMatcher;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$profile = [
    'holland' => ['I' => 92, 'R' => 75],
    'multiple_intelligence' => ['logical' => 90, 'spatial' => 78],
    'disc' => ['C' => 84],
    'mbti' => ['ISTJ' => 100],
    'skills' => ['problem_solving' => 88, 'python' => 76],
];
$catalog = [
    ['code' => 'analytical', 'name' => 'Nhà tư duy phân tích', 'recommendation_profile' => ['holland' => ['I'], 'multiple_intelligence' => ['logical'], 'disc' => ['C'], 'mbti' => ['ISTJ'], 'skills' => ['problem_solving']]],
    ['code' => 'creative', 'name' => 'Nhà sáng tạo', 'recommendation_profile' => ['holland' => ['A'], 'multiple_intelligence' => ['spatial'], 'disc' => ['I'], 'mbti' => ['ENFP'], 'skills' => ['creative_design']]],
];

$ranked = (new CredentialRecommendationMatcher())->rank($profile, $catalog, 2);
$assert($ranked[0]['code'] === 'analytical', 'analytical profile ranks first');
$assert((int) $ranked[0]['match_score'] >= 70, 'strong profile score');
$assert(trim((string) $ranked[0]['reason']) !== '', 'reason is present');
$assert(count((new CredentialRecommendationMatcher())->rank([], $catalog, 2)) === 2, 'empty profile still returns fallback catalog');
echo "learner_school_credential_matcher_test: OK\n";
