<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RecommendationResult
{
    /** @var list<RecommendationItem> */
    private readonly array $items;

    /** @param list<RecommendationItem> $items */
    public function __construct(
        private readonly string $engineType,
        private readonly ?string $ruleVersion,
        private readonly ?string $provider,
        private readonly ?string $modelVersion,
        private readonly ?string $promptVersion,
        private readonly ?string $fallbackReason,
        array $items,
    ) {
        if ($engineType === 'rule') {
            if (trim((string) $ruleVersion) === '' || $provider !== null || $modelVersion !== null || $promptVersion !== null) {
                throw new \InvalidArgumentException('Rule recommendation results require only a rule version.');
            }
        } elseif ($engineType === 'model') {
            if ($ruleVersion !== null || trim((string) $provider) === '' || trim((string) $modelVersion) === '' || trim((string) $promptVersion) === '') {
                throw new \InvalidArgumentException('Model recommendation results require provider, model, and prompt versions.');
            }
        } else {
            throw new \InvalidArgumentException('Unsupported recommendation engine type.');
        }
        foreach ($items as $item) {
            if (!$item instanceof RecommendationItem) {
                throw new \InvalidArgumentException('Recommendation result items must be recommendation items.');
            }
        }
        $this->items = array_values($items);
    }

    public function engineType(): string
    {
        return $this->engineType;
    }

    public function ruleVersion(): ?string
    {
        return $this->ruleVersion;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function modelVersion(): ?string
    {
        return $this->modelVersion;
    }

    public function promptVersion(): ?string
    {
        return $this->promptVersion;
    }

    public function fallbackReason(): ?string
    {
        return $this->fallbackReason;
    }

    /** @return list<RecommendationItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<string,string|null> */
    public function engineMetadata(): array
    {
        return [
            'engine_type' => $this->engineType,
            'rule_version' => $this->ruleVersion,
            'provider' => $this->provider,
            'model_version' => $this->modelVersion,
            'prompt_version' => $this->promptVersion,
            'fallback_reason' => $this->fallbackReason,
        ];
    }
}
