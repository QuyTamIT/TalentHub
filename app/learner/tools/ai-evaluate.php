<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;

require_once dirname(__DIR__) . '/ai/bootstrap.php';

$options = getopt('', ['fixture:', 'format:']);
$fixture = is_string($options['fixture'] ?? null) ? $options['fixture'] : '';
$format = strtolower((string) ($options['format'] ?? 'text'));
if ($fixture === '' || !is_file($fixture) || !in_array($format, ['json', 'text'], true)) {
    fwrite(STDERR, "Usage: ai-evaluate.php --fixture=<rule-fixture.php> --format=json|text\n");
    exit(2);
}
require_once $fixture;
if (!function_exists('learner_rule_input') || !function_exists('learner_rule_iot_skill')) {
    fwrite(STDERR, "Fixture does not expose the learner rule input helpers.\n");
    exit(2);
}

$input = learner_rule_input(
    [learner_rule_iot_skill()],
    [learner_rule_holland()],
    [learner_rule_technical_activity()],
    [learner_rule_evaluation()],
);
$result = (new RuleRecommendationEngine())->generate($input, learner_rule_context());
$report = (new RecommendationEvaluator())->evaluate($result, $input);
$payload = [
    'metrics' => $report['metrics'],
    'violations' => $report['violations'],
    'eligible_for_visible_rollout' => false,
    'reason' => 'shadow_gate_and_explicit_release_approval_required',
];
if ($format === 'json') {
    fwrite(STDOUT, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} else {
    fwrite(STDOUT, "eligible_for_visible_rollout=false\n");
}
