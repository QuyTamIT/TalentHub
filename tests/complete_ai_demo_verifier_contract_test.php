<?php

declare(strict_types=1);

use TalentHub\Database\Seeds\Demo\CompleteAiDemoVerifier;

require_once dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoVerifier.php';

function demo_verifier_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

demo_verifier_assert(
    !CompleteAiDemoVerifier::hasRequiredVisibleRecommendation(null, true),
    'strict demo verification rejects a hero without any visible recommendation run',
);
demo_verifier_assert(
    !CompleteAiDemoVerifier::hasRequiredVisibleRecommendation([
        'status' => 'completed', 'engineType' => 'rule', 'items' => [['id' => 'rule-item']],
    ], true),
    'strict demo verification rejects a completed rule run',
);
demo_verifier_assert(
    CompleteAiDemoVerifier::hasRequiredVisibleRecommendation([
        'status' => 'completed', 'engineType' => 'model', 'items' => [['id' => 'model-item']],
    ], true),
    'strict demo verification accepts a completed model run with items',
);
demo_verifier_assert(
    CompleteAiDemoVerifier::hasRequiredVisibleRecommendation([
        'status' => 'completed', 'engineType' => 'rule', 'items' => [['id' => 'rule-item']],
    ], false),
    'non-strict demo verification accepts a completed rule run with items',
);

echo "complete_ai_demo_verifier_contract_test: OK\n";
