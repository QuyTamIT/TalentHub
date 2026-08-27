<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;

final class RecommendationClickService
{
    private const ACTIONS = ['view_activity', 'view_opportunity', 'register_activity', 'open_catalog_item'];

    public function __construct(
        private readonly DatabaseRecommendationRepository $repository,
        private readonly AiMetricsCollector $metrics,
    ) {
    }

    public function record(string $studentId, string $itemId, ?string $catalogId, string $actionType): bool
    {
        $studentId = trim($studentId);
        $itemId = trim($itemId);
        $catalogId = $catalogId === null ? null : trim($catalogId);
        $actionType = strtolower(trim($actionType));
        if ($studentId === '' || !$this->validOpaqueId($itemId) || ($catalogId !== null && !$this->validOpaqueId($catalogId))) {
            throw new \InvalidArgumentException('Recommendation click identifiers are invalid.');
        }
        if (!in_array($actionType, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Recommendation click action is invalid.');
        }
        if (!$this->repository->ownsClickTarget($studentId, $itemId, $catalogId)) {
            throw new \DomainException('Recommendation click target is unavailable.');
        }
        $this->metrics->record(['recommendation_click' => true, 'recommendation_action' => $actionType]);
        return true;
    }

    private function validOpaqueId(string $value): bool
    {
        return strlen($value) >= 1 && strlen($value) <= 128
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $value) === 1;
    }
}
