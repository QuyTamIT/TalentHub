<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rules;

use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Explanation\RecommendationExplainer;

final class RuleSetV1
{
    public const VERSION = 'learner-rules-1.0.0';

    /** @var list<RuleDefinition> */
    private readonly array $definitions;

    public function __construct(?RecommendationExplainer $explainer = null)
    {
        $explainer ??= new RecommendationExplainer();
        $evidenceMapper = static function (array $facts, array $item): array {
            $evidence = [];
            foreach ($item['evidence_facts'] ?? [] as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                $sourceType = trim((string) ($fact['source_type'] ?? ''));
                $sourceId = trim((string) ($fact['source_id'] ?? ''));
                $safeValue = $fact['safe_value'] ?? [];
                if ($sourceType === '' || $sourceId === '' || !is_array($safeValue)) {
                    continue;
                }
                $evidence[] = new RecommendationEvidence(
                    $sourceType,
                    $sourceId,
                    is_string($fact['observed_at'] ?? null) ? $fact['observed_at'] : null,
                    trim((string) ($fact['contribution_label'] ?? 'rule_evidence')),
                    $safeValue,
                );
            }
            return $evidence;
        };

        $this->definitions = [
            new RuleDefinition(
                'technical-strength-iot',
                self::VERSION,
                ['assessment', 'skills'],
                20,
                static fn (array $facts): bool => ($facts['technical_matches'] ?? []) !== [],
                static function (array $facts) use ($explainer): array {
                    $items = [];
                    foreach ($facts['technical_matches'] as $match) {
                        $items[] = [
                            'item_type' => 'strength',
                            'title' => 'Nền tảng IoT nổi bật',
                            'summary' => $explainer->technicalStrength($match['assessment'], $match['skill']),
                            'confidence_band' => 'high',
                            'action' => ['type' => 'develop_skill', 'skill_code' => 'iot'],
                            'source_id' => $match['skill']['source_id'],
                            'evidence_facts' => [$match['assessment'], $match['skill']],
                        ];
                    }
                    return $items;
                },
                $evidenceMapper,
            ),
            new RuleDefinition(
                'eligible-technical-activity',
                self::VERSION,
                ['assessment', 'skills', 'activity'],
                30,
                static fn (array $facts): bool => ($facts['technical_matches'] ?? []) !== [] && ($facts['technical_activities'] ?? []) !== [],
                static function (array $facts) use ($explainer): array {
                    $items = [];
                    foreach ($facts['technical_matches'] as $match) {
                        foreach ($facts['technical_activities'] as $activity) {
                            $items[] = [
                                'item_type' => 'activity',
                                'title' => 'Hoạt động IoT phù hợp',
                                'summary' => $explainer->eligibleActivity($activity),
                                'confidence_band' => 'medium',
                                'action' => ['type' => 'continue_technical_activity', 'activity_source_id' => $activity['source_id']],
                                'source_id' => $activity['source_id'],
                                'evidence_facts' => [$match['assessment'], $match['skill'], $activity],
                            ];
                        }
                    }
                    return $items;
                },
                $evidenceMapper,
            ),
            new RuleDefinition(
                'communication-presentation-roadmap',
                self::VERSION,
                ['evaluation'],
                40,
                static fn (array $facts): bool => count($facts['low_presentation_evaluations'] ?? []) >= 2,
                static function (array $facts) use ($explainer): array {
                    $evaluations = $facts['low_presentation_evaluations'];
                    return [[
                        'item_type' => 'roadmap',
                        'title' => 'Lộ trình cải thiện thuyết trình',
                        'summary' => $explainer->communicationRoadmap($evaluations),
                        'confidence_band' => 'medium',
                        'action' => ['type' => 'practice_presentation', 'weeks' => 4],
                        'source_id' => $evaluations[0]['source_id'],
                        'evidence_facts' => $evaluations,
                    ]];
                },
                $evidenceMapper,
            ),
        ];
    }

    /** @return list<RuleDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
