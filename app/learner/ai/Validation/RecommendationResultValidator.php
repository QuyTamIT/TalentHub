<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Validation;

use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;

final class RecommendationResultValidator
{
    private const ITEM_TYPES = ['strength', 'improvement', 'development', 'activity', 'roadmap'];
    private const CONFIDENCE_BANDS = ['low', 'medium', 'high'];
    private const MAX_ITEMS = 12;
    private const MAX_ROADMAP_STEPS = 3;

    public function validate(RecommendationResult $result): void
    {
        $items = $result->items();
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
        if ($this->containsUnsupportedClaim($item->title()) || $this->containsUnsupportedClaim($item->summary())) {
            throw new \RuntimeException('Recommendation result contains an unsupported absolute claim.');
        }
        $this->validateAction($item->action());
    }

    /** @param array<string,mixed> $action */
    private function validateAction(array $action): void
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
            if (!is_string($activitySourceId)
                || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $activitySourceId) !== 1) {
                throw new \RuntimeException('Recommendation activity action activity source ID is invalid.');
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
