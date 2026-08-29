<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Validation;

use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;

final class RecommendationResultValidator
{
    private const ITEM_TYPES = ['strength', 'improvement', 'development', 'activity', 'roadmap', 'group', 'community'];
    private const CONFIDENCE_BANDS = ['low', 'medium', 'high'];
    private const MAX_ITEMS = 12;
    private const MAX_ROADMAP_STEPS = 3;
    /** @var array<string,bool> */
    private readonly array $allowedCatalogIds;

    /** @param list<string> $allowedCatalogIds */
    public function __construct(array $allowedCatalogIds = [])
    {
        $normalized = [];
        foreach ($allowedCatalogIds as $catalogId) {
            if (!is_string($catalogId) || trim($catalogId) === '') {
                throw new \InvalidArgumentException('Recommendation catalog allow-list is invalid.');
            }
            $normalized[trim($catalogId)] = true;
        }
        $this->allowedCatalogIds = $normalized;
    }

    public function validate(RecommendationResult $result): void
    {
        $items = $result->items();
        if ($items === []) {
            throw new \RuntimeException('Recommendation result must contain at least one item.');
        }
        if (count($items) > self::MAX_ITEMS) {
            throw new \RuntimeException('Recommendation result has too many items.');
        }
        foreach ($items as $item) {
            $this->validateItem($item);
        }
    }

    private function validateItem(RecommendationItem $item): void
    {
        if (!in_array($item->itemType(), self::ITEM_TYPES, true)
            || $item->priority() < 1 || $item->priority() > 100
            || !in_array($item->confidenceBand(), self::CONFIDENCE_BANDS, true)
            || $item->evidence() === []) {
            throw new \RuntimeException('Recommendation result item is invalid.');
        }
        if ($item->catalogId() !== null) {
            if ($this->allowedCatalogIds !== [] && !isset($this->allowedCatalogIds[$item->catalogId()])) {
                throw new \RuntimeException('Recommendation catalog id is invalid or unavailable.');
            }
            $matchingCatalogEvidence = null;
            foreach ($item->evidence() as $evidence) {
                if (in_array($evidence->sourceType(), ['opportunity', 'catalog'], true)
                    && hash_equals($item->catalogId(), $evidence->sourceId())) {
                    $matchingCatalogEvidence = $evidence;
                    break;
                }
            }
            if ($matchingCatalogEvidence === null) {
                throw new \RuntimeException('Recommendation catalog id must match catalog evidence on the same item.');
            }
            if ($matchingCatalogEvidence->sourceType() === 'opportunity'
                && ($matchingCatalogEvidence->safeValue()['opportunity_type'] ?? null) === 'internship'
                && ($item->action()['type'] ?? null) !== 'open_catalog_item') {
                throw new \RuntimeException('Enterprise internship recommendations must use their canonical catalog action.');
            }
        }
        if ($item->reason() !== null && $this->containsUnsupportedClaim($item->reason())) {
            throw new \RuntimeException('Recommendation reason contains an unsupported absolute claim.');
        }
        if ($this->containsUnsupportedClaim($item->title()) || $this->containsUnsupportedClaim($item->summary())) {
            throw new \RuntimeException('Recommendation result contains an unsupported absolute claim.');
        }
        $this->validateAction($item->action(), $item);
    }

    /** @param array<string,mixed> $action */
    private function validateAction(array $action, RecommendationItem $item): void
    {
        $type = $action['type'] ?? null;
        if (!is_string($type)) {
            throw new \RuntimeException('Recommendation action type is required.');
        }
        $allowed = match ($type) {
            'develop_skill' => ['type', 'skill_code'],
            'continue_technical_activity' => ['type', 'activity_source_id'],
            'practice_presentation' => ['type', 'weeks', 'steps'],
            'explore_career_group' => ['type', 'career_group'],
            'register_activity' => ['type', 'career_group', 'activity_source_id'],
            'join_group', 'open_catalog_item' => ['type', 'catalog_id'],
            default => throw new \RuntimeException('Recommendation action type is unsupported.'),
        };
        foreach (array_keys($action) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \RuntimeException('Recommendation action contains unsupported data.');
            }
        }

        $validCareerGroups = ['technical', 'business', 'arts', 'sports_academic'];

        if ($type === 'develop_skill' && (!is_string($action['skill_code'] ?? null) || trim($action['skill_code']) === '')) {
            throw new \RuntimeException('Recommendation skill action is invalid.');
        }
        if ($type === 'continue_technical_activity' && (!is_string($action['activity_source_id'] ?? null) || trim($action['activity_source_id']) === '')) {
            throw new \RuntimeException('Recommendation activity action is invalid.');
        }
        if ($type === 'explore_career_group') {
            $careerGroup = $action['career_group'] ?? null;
            if (!is_string($careerGroup) || !in_array(trim($careerGroup), $validCareerGroups, true)) {
                throw new \RuntimeException('Recommendation career group action is invalid.');
            }
        }
        if ($type === 'register_activity') {
            $careerGroup = $action['career_group'] ?? null;
            $activitySourceId = $action['activity_source_id'] ?? null;
            if (!is_string($careerGroup) || !in_array(trim($careerGroup), $validCareerGroups, true)) {
                throw new \RuntimeException('Recommendation activity action career group is invalid.');
            }
            $isUuid = is_string($activitySourceId)
                && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $activitySourceId) === 1;
            $isValidatedCatalogId = is_string($activitySourceId)
                && $item->catalogId() !== null
                && hash_equals($item->catalogId(), $activitySourceId);
            if (!$isUuid && !$isValidatedCatalogId) {
                throw new \RuntimeException('Recommendation activity action activity source ID is invalid.');
            }
        }
        if (in_array($type, ['join_group', 'open_catalog_item'], true)) {
            $catalogId = $action['catalog_id'] ?? null;
            if (!is_string($catalogId) || trim($catalogId) === '') {
                throw new \RuntimeException('Recommendation group/catalog action catalog ID is required.');
            }
            if ($item->catalogId() === null || !hash_equals($item->catalogId(), $catalogId)) {
                throw new \RuntimeException('Recommendation group/catalog action catalog ID must match item catalog ID.');
            }
        }
        if ($type === 'practice_presentation') {
            if (!is_int($action['weeks'] ?? null) || $action['weeks'] < 1 || $action['weeks'] > 12) {
                throw new \RuntimeException('Recommendation presentation action is invalid.');
            }
            if (array_key_exists('steps', $action)) {
                if (!is_array($action['steps']) || count($action['steps']) > self::MAX_ROADMAP_STEPS) {
                    throw new \RuntimeException('Recommendation roadmap has too many steps.');
                }
                foreach ($action['steps'] as $step) {
                    if (!is_string($step) || trim($step) === '') {
                        throw new \RuntimeException('Recommendation roadmap step is invalid.');
                    }
                }
            }
        }
    }

    private function containsUnsupportedClaim(string $value): bool
    {
        return preg_match('/\b(guaranteed|will\s+be\s+hired|hired|admitted|admission|chắc\s+chắn|cam\s+kết|được\s+tuyển|đậu\s+đại\s+học|nhập\s+học)\b/iu', $value) === 1;
    }
}
