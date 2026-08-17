<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

use JsonException;

final class RecommendationItem
{
    private const ITEM_TYPES = ['strength', 'improvement', 'development', 'activity', 'roadmap'];
    private const CONFIDENCE_BANDS = ['low', 'medium', 'high'];

    /** @var array<string,mixed> */
    private readonly array $action;

    /** @var list<RecommendationEvidence> */
    private readonly array $evidence;

    /** @param array<string,mixed> $action
     * @param list<RecommendationEvidence> $evidence
     */
    public function __construct(
        private readonly string $itemType,
        private readonly string $title,
        private readonly string $summary,
        private readonly int $priority,
        private readonly string $confidenceBand,
        array $action,
        array $evidence,
    ) {
        if (!in_array($itemType, self::ITEM_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported recommendation item type.');
        }
        if (trim($title) === '' || trim($summary) === '') {
            throw new \InvalidArgumentException('Recommendation item title and summary are required.');
        }
        if ($priority < 1 || $priority > 100) {
            throw new \InvalidArgumentException('Recommendation item priority must be between 1 and 100.');
        }
        if (!in_array($confidenceBand, self::CONFIDENCE_BANDS, true)) {
            throw new \InvalidArgumentException('Unsupported recommendation confidence band.');
        }
        try {
            json_encode($action, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Recommendation action must be JSON serializable.', 0, $exception);
        }
        if ($evidence === []) {
            throw new \InvalidArgumentException('Recommendation items require normalized evidence.');
        }
        foreach ($evidence as $value) {
            if (!$value instanceof RecommendationEvidence) {
                throw new \InvalidArgumentException('Recommendation items require normalized evidence.');
            }
        }
        $this->action = $action;
        $this->evidence = array_values($evidence);
    }

    public function itemType(): string
    {
        return $this->itemType;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function confidenceBand(): string
    {
        return $this->confidenceBand;
    }

    /** @return array<string,mixed> */
    public function action(): array
    {
        return $this->action;
    }

    public function actionJson(): string
    {
        return json_encode($this->action, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return list<RecommendationEvidence> */
    public function evidence(): array
    {
        return $this->evidence;
    }
}
