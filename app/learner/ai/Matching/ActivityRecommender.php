<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;

/**
 * Deterministic activity/workshop recommender that maps missing skill gap
 * codes onto the existing canonical AI catalog.
 *
 * Reuses DatabaseCatalogSource so publish status, deadline, remaining
 * capacity and school/tenant scoping stay exactly as canonical as the group
 * matching pipeline. Items are only recommended when their required_skills
 * or learning_outcomes intersect the missing gap; records without canonical
 * skill tags are skipped, never guessed.
 *
 * Ranking (deterministic): covered gap count, covered gap weight, sooner
 * deadline, then catalog_id. At most 3 activities are returned.
 */
final class ActivityRecommender
{
    private const MAX_ACTIVITIES = 3;

    /** Catalog item types that carry schedulable skill-building activities. */
    private const ACTIVITY_ITEM_TYPES = ['workshop', 'activity', 'project', 'contest', 'skill_resource'];

    private const TYPE_LABELS = [
        'workshop' => 'workshop',
        'activity' => 'hoạt động',
        'project' => 'dự án',
        'contest' => 'cuộc thi',
        'skill_resource' => 'tài nguyên kỹ năng',
    ];

    private readonly DateTimeImmutable $clock;

    public function __construct(
        private readonly DatabaseCatalogSource $catalogSource,
        ?DateTimeImmutable $clock = null,
    ) {
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @param array<string,float> $missingSkills canonical gap code => gap weight
     * @return array{status:string, items:list<array<string,mixed>>}
     */
    public function recommend(string $studentId, array $missingSkills): array
    {
        $studentId = trim($studentId);
        $gaps = [];
        foreach ($missingSkills as $code => $weight) {
            if (!is_string($code)) {
                continue;
            }
            $code = LearnerOpportunityProfile::normalizeCode($code);
            if ($code === '' || !is_numeric($weight) || (float) $weight <= 0.0) {
                continue;
            }
            $gaps[$code] = (float) $weight;
        }

        $noItems = static fn (string $status): array => ['status' => $status, 'items' => []];
        if ($studentId === '') {
            return $noItems('no_matching_activity');
        }
        if ($gaps === []) {
            return $noItems('no_skill_gap');
        }

        $ranked = [];
        try {
            $catalogItems = $this->catalogSource->readForStudent($studentId);
        } catch (\Throwable) {
            return $noItems('no_matching_activity');
        }

        foreach ($catalogItems as $item) {
            if (!in_array((string) ($item['item_type'] ?? ''), self::ACTIVITY_ITEM_TYPES, true)) {
                continue;
            }
            $coverage = $this->coverage($item, $gaps);
            if ($coverage === []) {
                continue;
            }

            $coveredCount = count($coverage);
            $coveredWeight = 0.0;
            foreach ($coverage as $code) {
                $coveredWeight += $gaps[$code];
            }
            $deadline = $item['deadline_at'] ?? null;
            $ranked[] = [
                'item' => $item,
                'coverage' => $coverage,
                'covered_count' => $coveredCount,
                'covered_weight' => $coveredWeight,
                'deadline' => $deadline !== null ? (string) $deadline : '9999-12-31T23:59:59.000000+00:00',
            ];
        }

        if ($ranked === []) {
            return $noItems('no_matching_activity');
        }

        usort($ranked, static function (array $a, array $b): int {
            return [$b['covered_count'], $b['covered_weight'], $a['deadline'], $a['item']['catalog_id']]
                <=> [$a['covered_count'], $a['covered_weight'], $b['deadline'], $b['item']['catalog_id']];
        });

        $items = [];
        foreach (array_slice($ranked, 0, self::MAX_ACTIVITIES) as $entry) {
            $item = $entry['item'];
            $items[] = [
                'catalog_id' => (string) ($item['catalog_id'] ?? ''),
                'item_type' => (string) ($item['item_type'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'provider_name' => (string) ($item['provider_name'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'deadline_at' => $item['deadline_at'] ?? null,
                'skill_codes' => $entry['coverage'],
                'covered_gap_weight' => $entry['covered_weight'],
                'reason' => self::reasonLabel(
                    self::TYPE_LABELS[(string) $item['item_type']] ?? 'hoạt động',
                    $entry['coverage'],
                ),
            ];
        }

        return ['status' => 'ok', 'items' => $items];
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,float> $gaps
     * @return list<string> the intersecting gap codes in gap order
     */
    private function coverage(array $item, array $gaps): array
    {
        $itemCodes = [];
        foreach ((array) ($item['required_skills'] ?? []) as $skill) {
            if (is_array($skill)) {
                $itemCodes[] = LearnerOpportunityProfile::normalizeCode((string) ($skill['code'] ?? ''));
            }
        }
        foreach ((array) ($item['learning_outcomes'] ?? []) as $outcome) {
            if (is_array($outcome)) {
                $itemCodes[] = LearnerOpportunityProfile::normalizeCode((string) ($outcome['code'] ?? ''));
            }
        }
        return array_values(array_filter(array_unique(array_intersect(array_keys($gaps), $itemCodes))));
    }

    /** @param list<string> $coverage */
    private static function reasonLabel(string $typeLabel, array $coverage): string
    {
        $count = count($coverage);
        if ($count === 1) {
            return "Hoạt động {$typeLabel} này giúp bù 1 kỹ năng còn thiếu trong khoảng cách nghề nghiệp của bạn.";
        }
        return "Hoạt động {$typeLabel} này giúp bù {$count} kỹ năng còn thiếu trong khoảng cách nghề nghiệp của bạn.";
    }
}
