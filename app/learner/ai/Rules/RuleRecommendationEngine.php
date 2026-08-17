<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rules;

use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;

final class RuleRecommendationEngine implements RecommendationEngine
{
    public function __construct(private readonly RuleSetV1 $ruleSet = new RuleSetV1())
    {
    }

    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        $facts = $this->facts($input);
        $allowedScopes = array_fill_keys($context->allowedScopes(), true);

        if ($this->containsUnconsentedSourceData($facts, $allowedScopes)) {
            return $this->safeResult('consent_required');
        }
        if (($facts['evaluations'] ?? []) === []) {
            return $this->safeResult(isset($allowedScopes['evaluation']) ? 'insufficient_data' : 'consent_required');
        }

        $candidates = [];
        foreach ($this->ruleSet->definitions() as $definition) {
            if (!$this->allows($definition, $allowedScopes) || !$definition->matches($facts)) {
                continue;
            }
            foreach ($definition->buildItems($facts) as $built) {
                $evidence = $definition->mapEvidence($facts, $built);
                if ($evidence === []) {
                    continue;
                }
                $candidates[] = [
                    'item' => new RecommendationItem(
                        (string) ($built['item_type'] ?? ''),
                        (string) ($built['title'] ?? ''),
                        (string) ($built['summary'] ?? ''),
                        $definition->priority(),
                        (string) ($built['confidence_band'] ?? ''),
                        is_array($built['action'] ?? null) ? $built['action'] : [],
                        $evidence,
                    ),
                    'priority' => $definition->priority(),
                    'source_id' => (string) ($built['sort_source_id'] ?? $built['source_id'] ?? ''),
                    'rule_id' => $definition->id(),
                ];
            }
        }

        usort($candidates, static fn (array $left, array $right): int => [
            $left['priority'], $left['source_id'], $left['rule_id'],
        ] <=> [
            $right['priority'], $right['source_id'], $right['rule_id'],
        ]);

        return new RecommendationResult(
            'rule',
            RuleSetV1::VERSION,
            null,
            null,
            null,
            null,
            array_map(static fn (array $candidate): RecommendationItem => $candidate['item'], $candidates),
        );
    }

    /** @param array<string,bool> $allowedScopes */
    private function allows(RuleDefinition $definition, array $allowedScopes): bool
    {
        foreach ($definition->requiredScopes() as $scope) {
            if (!isset($allowedScopes[$scope])) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,bool> $allowedScopes */
    private function containsUnconsentedSourceData(array $facts, array $allowedScopes): bool
    {
        foreach (['skills' => 'skills', 'assessments' => 'assessment', 'activities' => 'activity', 'evaluations' => 'evaluation'] as $fact => $scope) {
            if (($facts[$fact] ?? []) !== [] && !isset($allowedScopes[$scope])) {
                return true;
            }
        }
        return false;
    }

    private function safeResult(string $reason): RecommendationResult
    {
        return new RecommendationResult('rule', RuleSetV1::VERSION, null, null, null, $reason, []);
    }

    /** @return array<string,mixed> */
    private function facts(RecommendationInput $input): array
    {
        $facts = [
            'skills' => [],
            'assessments' => [],
            'activities' => [],
            'evaluations' => [],
            'technical_matches' => [],
            'technical_activities' => [],
            'low_presentation_evaluations' => [],
        ];
        foreach ($input->evidenceReferences() as $reference) {
            $fact = $this->fact($reference);
            if ($fact === null) {
                continue;
            }
            match ($fact['source_type']) {
                'skill' => $facts['skills'][] = $fact,
                'assessment' => $facts['assessments'][] = $fact,
                'activity_experience' => $facts['activities'][] = $fact,
                'evaluation' => $facts['evaluations'][] = $fact,
                default => null,
            };
        }
        foreach ($facts['assessments'] as $assessment) {
            if ($assessment['test_code'] !== 'holland' || $assessment['holland_r'] < 70 || $assessment['holland_i'] < 70) {
                continue;
            }
            foreach ($facts['skills'] as $skill) {
                if ($skill['code'] === 'iot' && $skill['verification_status'] === 'verified') {
                    $facts['technical_matches'][] = ['assessment' => $assessment, 'skill' => $skill];
                }
            }
        }
        foreach ($facts['activities'] as $activity) {
            if (!in_array($activity['status'], ['closed', 'inactive', 'cancelled'], true) && $this->isTechnicalCategory($activity['activity_category'])) {
                $facts['technical_activities'][] = $activity;
            }
        }
        foreach ($facts['evaluations'] as $evaluation) {
            if ($evaluation['presentation_score'] !== null && $evaluation['presentation_score'] < 60) {
                $facts['low_presentation_evaluations'][] = $evaluation;
            }
        }
        foreach (['skills', 'assessments', 'activities', 'evaluations', 'technical_activities', 'low_presentation_evaluations'] as $key) {
            usort($facts[$key], static fn (array $left, array $right): int => $left['source_id'] <=> $right['source_id']);
        }
        usort($facts['technical_matches'], static fn (array $left, array $right): int => [$left['skill']['source_id'], $left['assessment']['source_id']] <=> [$right['skill']['source_id'], $right['assessment']['source_id']]);
        return $facts;
    }

    /** @param array{source_type:string,source_id:string,observed_at:?string,safe_value:array<string,mixed>} $reference @return array<string,mixed>|null */
    private function fact(array $reference): ?array
    {
        $sourceType = $reference['source_type'];
        $sourceId = trim($reference['source_id']);
        $safeValue = $reference['safe_value'];
        if ($sourceId === '' || !in_array($sourceType, ['skill', 'assessment', 'activity_experience', 'evaluation'], true)) {
            return null;
        }
        $fact = [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'observed_at' => $reference['observed_at'],
            'safe_value' => $safeValue,
            'contribution_label' => 'rule_source',
        ];
        return match ($sourceType) {
            'skill' => $fact + [
                'code' => strtolower(trim((string) ($safeValue['code'] ?? ''))),
                'verification_status' => strtolower(trim((string) ($safeValue['verification_status'] ?? ''))),
            ],
            'assessment' => $fact + $this->assessmentFacts($safeValue),
            'activity_experience' => $fact + [
                'activity_category' => strtolower(trim((string) ($safeValue['activity_category'] ?? ''))),
                'status' => strtolower(trim((string) ($safeValue['status'] ?? 'active'))),
            ],
            'evaluation' => $fact + [
                'presentation_score' => is_numeric($safeValue['presentation_score'] ?? null) ? (float) $safeValue['presentation_score'] : null,
            ],
        };
    }

    /** @param array<string,mixed> $safeValue @return array<string,mixed> */
    private function assessmentFacts(array $safeValue): array
    {
        $scores = is_array($safeValue['dimension_scores'] ?? null) ? $safeValue['dimension_scores'] : [];
        return [
            'test_code' => strtolower(trim((string) ($safeValue['test_code'] ?? ''))),
            'assessment_version' => trim((string) ($safeValue['assessment_version'] ?? '')),
            'holland_r' => is_numeric($scores['R'] ?? null) ? (float) $scores['R'] : 0.0,
            'holland_i' => is_numeric($scores['I'] ?? null) ? (float) $scores['I'] : 0.0,
        ];
    }

    private function isTechnicalCategory(string $category): bool
    {
        return str_contains($category, 'technical') || str_contains($category, 'iot') || str_contains($category, 'workshop') || str_contains($category, 'lab');
    }
}
