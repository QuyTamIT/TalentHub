<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;

foreach ([
    '/app/learner/ai/Domain/RecommendationInput.php',
    '/app/learner/ai/Domain/RecommendationContext.php',
    '/app/learner/ai/Domain/RecommendationEvidence.php',
    '/app/learner/ai/Domain/RecommendationItem.php',
    '/app/learner/ai/Domain/RecommendationResult.php',
    '/app/learner/ai/Contracts/RecommendationEngine.php',
    '/app/learner/ai/Rules/RuleDefinition.php',
    '/app/learner/ai/Rules/RuleSetV1.php',
    '/app/learner/ai/Rules/RuleRecommendationEngine.php',
    '/app/learner/ai/Explanation/RecommendationExplainer.php',
] as $file) {
    $path = dirname(__DIR__) . $file;
    if (is_file($path)) {
        require_once $path;
    }
}
require_once __DIR__ . '/learner_ai_rule_cases_fixture.php';

function learner_rule_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/** @return list<string> */
function learner_rule_evidence_ids(RecommendationItem $item, string $sourceType): array
{
    $ids = [];
    foreach ($item->evidence() as $evidence) {
        if ($evidence->sourceType() === $sourceType) {
            $ids[] = $evidence->sourceId();
        }
    }
    return $ids;
}

learner_rule_assert(interface_exists(RecommendationEngine::class), 'recommendation engine contract exists');
learner_rule_assert(class_exists(RuleRecommendationEngine::class), 'rule recommendation engine exists');

$engine = new RuleRecommendationEngine();
$technical = $engine->generate(
    learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity()], [learner_rule_evaluation()]),
    learner_rule_context(),
);
learner_rule_assert($technical->engineType() === 'rule' && $technical->ruleVersion() === 'learner-rules-1.0.0', 'engine reports the immutable rule version');
learner_rule_assert(count($technical->items()) === 2, 'high R/I, verified IoT, and confirmed technical activity produce two deterministic recommendations');
learner_rule_assert($technical->items()[0]->itemType() === 'strength', 'technical strength is prioritized first');
learner_rule_assert(learner_rule_evidence_ids($technical->items()[0], 'assessment') === ['assessment-1'], 'technical strength carries the versioned Holland evidence');
learner_rule_assert(learner_rule_evidence_ids($technical->items()[0], 'skill') === ['skill-iot'], 'technical strength carries verified IoT evidence');
learner_rule_assert($technical->items()[1]->itemType() === 'activity', 'eligible technical activity follows the strength');
learner_rule_assert(learner_rule_evidence_ids($technical->items()[1], 'activity_experience') === ['activity-1'], 'eligible activity has normalized activity evidence');
learner_rule_assert(str_contains($technical->items()[0]->summary(), 'Holland phiên bản 1.0'), 'explanation names the normalized assessment version');

$communication = $engine->generate(
    learner_rule_input([], [], [], [learner_rule_evaluation('evaluation-a', 45), learner_rule_evaluation('evaluation-b', 50)]),
    learner_rule_context(['evaluation']),
);
learner_rule_assert(count($communication->items()) === 1, 'repeated low presentation scores produce one communication roadmap');
learner_rule_assert($communication->items()[0]->itemType() === 'roadmap', 'communication recommendation is an actionable roadmap');
learner_rule_assert(learner_rule_evidence_ids($communication->items()[0], 'evaluation') === ['evaluation-a', 'evaluation-b'], 'communication roadmap cites both published evaluations');

$closedActivity = $engine->generate(
    learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity('activity-closed', 'closed')], [learner_rule_evaluation()]),
    learner_rule_context(),
);
learner_rule_assert(!in_array('activity', array_map(static fn (RecommendationItem $item): string => $item->itemType(), $closedActivity->items()), true), 'closed or inactive activities are never recommended');

$missingEvaluation = $engine->generate(
    learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity()], []),
    learner_rule_context(),
);
learner_rule_assert($missingEvaluation->fallbackReason() === 'insufficient_data' && $missingEvaluation->items() === [], 'missing evaluation returns insufficient_data without speculative output');

$revokedActivity = $engine->generate(
    learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity()], [learner_rule_evaluation()], ['assessment', 'skills', 'evaluation']),
    learner_rule_context(['assessment', 'skills', 'evaluation']),
);
learner_rule_assert($revokedActivity->fallbackReason() === 'consent_required' && $revokedActivity->items() === [], 'revoked activity consent returns no activity evidence or recommendation');

$ties = $engine->generate(
    learner_rule_input([learner_rule_iot_skill('skill-b'), learner_rule_iot_skill('skill-a')], [learner_rule_holland()], [], [learner_rule_evaluation()]),
    learner_rule_context(['assessment', 'skills', 'evaluation']),
);
learner_rule_assert(count($ties->items()) === 2, 'same-priority verified IoT skills each retain a traceable recommendation');
learner_rule_assert(
    array_map(static fn (RecommendationItem $item): array => [$item->priority(), learner_rule_evidence_ids($item, 'skill')[0] ?? ''], $ties->items()) === [[20, 'skill-a'], [20, 'skill-b']],
    'score ties are ordered by rule priority then stable source ID'
);

$sameSkillAssessmentTies = $engine->generate(
    learner_rule_input([learner_rule_iot_skill('skill-iot')], [learner_rule_holland('assessment-b'), learner_rule_holland('assessment-a')], [], [learner_rule_evaluation()]),
    learner_rule_context(['assessment', 'skills', 'evaluation']),
);
learner_rule_assert(
    array_map(static fn (RecommendationItem $item): array => [learner_rule_evidence_ids($item, 'skill')[0] ?? '', learner_rule_evidence_ids($item, 'assessment')[0] ?? ''], $sameSkillAssessmentTies->items()) === [['skill-iot', 'assessment-a'], ['skill-iot', 'assessment-b']],
    'same-skill rule ties are resolved deterministically by the linked assessment source ID'
);

echo "learner_rule_recommendation_test: OK\n";
