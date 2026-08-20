<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Rules\CareerGroupClassifier;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
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
learner_rule_assert(class_exists(CareerGroupClassifier::class), 'career group classifier exists');

$engine = new RuleRecommendationEngine();
$technical = $engine->generate(
    learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity()], [learner_rule_evaluation()]),
    learner_rule_context(),
);
learner_rule_assert($technical->engineType() === 'rule' && $technical->ruleVersion() === 'learner-rules-1.0.0', 'engine reports the immutable rule version');
learner_rule_assert(count($technical->items()) === 3, 'high R/I, verified IoT, and confirmed technical activity produce three deterministic recommendations (IoT strength, Holland strength, technical activity)');
learner_rule_assert($technical->items()[0]->itemType() === 'strength', 'technical strength is prioritized first');
learner_rule_assert(learner_rule_evidence_ids($technical->items()[0], 'assessment') === ['assessment-1'], 'technical strength carries the versioned Holland evidence');
learner_rule_assert(learner_rule_evidence_ids($technical->items()[0], 'skill') === ['skill-iot'], 'technical strength carries verified IoT evidence');
learner_rule_assert($technical->items()[1]->itemType() === 'strength' && $technical->items()[1]->action()['career_group'] === 'technical', 'Holland career group strength follows IoT strength');
learner_rule_assert($technical->items()[2]->itemType() === 'activity', 'eligible technical activity follows the strength items');
learner_rule_assert(learner_rule_evidence_ids($technical->items()[2], 'activity_experience') === ['activity-1'], 'eligible activity has normalized activity evidence');
learner_rule_assert(str_contains($technical->items()[0]->summary(), 'Holland phiên bản 1.0'), 'explanation names the normalized assessment version');

$bandedTechnical = $engine->generate(
    learner_rule_input(
        [learner_rule_iot_skill()],
        [learner_rule_holland('assessment-banded', 'holland_high')],
        [],
        [learner_rule_evaluation()],
        ['assessment', 'skills', 'activity', 'evaluation'],
        [learner_rule_opportunity('opportunity-technical', 'career_technical')],
    ),
    learner_rule_context(),
);
$bandedActions = array_map(static fn (RecommendationItem $item): array => $item->action(), $bandedTechnical->items());
learner_rule_assert(
    count(array_filter($bandedActions, static fn (array $action): bool => ($action['career_group'] ?? null) === 'technical')) >= 2,
    'banded Holland produces technical career-group and activity actions',
);

$unknownCategory = $engine->generate(
    learner_rule_input(
        [],
        [learner_rule_holland('assessment-unknown')],
        [],
        [],
        ['assessment', 'activity'],
        [learner_rule_opportunity('opportunity-unknown', 'career_unknown')],
    ),
    learner_rule_context(['assessment', 'activity']),
);
learner_rule_assert(
    !in_array('register_activity', array_map(static fn (array $action): string => (string) ($action['type'] ?? ''), array_map(static fn (RecommendationItem $item): array => $item->action(), $unknownCategory->items())), true),
    'unknown opportunity category is never recommended',
);

$invalidHollandCode = $engine->generate(
    learner_rule_input(
        [learner_rule_iot_skill()],
        [learner_rule_holland('assessment-invalid-code', 'holland_unknown')],
        [learner_rule_technical_activity()],
        [],
        ['assessment', 'skills', 'activity'],
    ),
    learner_rule_context(['assessment', 'skills', 'activity']),
);
learner_rule_assert(
    $invalidHollandCode->items() === [],
    'invalid Holland suffix cannot activate legacy technical rules',
);

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

$noMatchingData = $engine->generate(
    learner_rule_input([], [], [], []),
    learner_rule_context(),
);
learner_rule_assert($noMatchingData->fallbackReason() === 'insufficient_data' && $noMatchingData->items() === [], 'empty input returns insufficient_data without speculative output');

$revokedActivity = $engine->generate(
    learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity()], [learner_rule_evaluation()], ['assessment', 'skills', 'evaluation']),
    learner_rule_context(['assessment', 'skills', 'evaluation']),
);
learner_rule_assert($revokedActivity->fallbackReason() === 'consent_required' && $revokedActivity->items() === [], 'unconsented activity data returns consent_required');

$ties = $engine->generate(
    learner_rule_input([learner_rule_iot_skill('skill-b'), learner_rule_iot_skill('skill-a')], [learner_rule_holland()], [], [learner_rule_evaluation()]),
    learner_rule_context(['assessment', 'skills', 'evaluation']),
);
learner_rule_assert(count($ties->items()) === 3, 'verified IoT skills plus Holland strength retain traceable recommendations');
learner_rule_assert(
    array_map(static fn (RecommendationItem $item): array => [$item->priority(), learner_rule_evidence_ids($item, 'skill')[0] ?? ''], array_slice($ties->items(), 0, 2)) === [[20, 'skill-a'], [20, 'skill-b']],
    'score ties are ordered by rule priority then stable source ID'
);

$sameSkillAssessmentTies = $engine->generate(
    learner_rule_input([learner_rule_iot_skill('skill-iot')], [learner_rule_holland('assessment-b'), learner_rule_holland('assessment-a')], [], [learner_rule_evaluation()]),
    learner_rule_context(['assessment', 'skills', 'evaluation']),
);
learner_rule_assert(
    array_map(static fn (RecommendationItem $item): array => [learner_rule_evidence_ids($item, 'skill')[0] ?? '', learner_rule_evidence_ids($item, 'assessment')[0] ?? ''], array_slice($sameSkillAssessmentTies->items(), 0, 2)) === [['skill-iot', 'assessment-a'], ['skill-iot', 'assessment-b']],
    'same-skill rule ties are resolved deterministically by the linked assessment source ID'
);

echo "learner_rule_recommendation_test: OK\n";
