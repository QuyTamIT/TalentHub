<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

use JsonException;

final class RecommendationEvidence
{
    private const SOURCE_TYPES = ['skill', 'assessment', 'activity_experience', 'evaluation', 'opportunity'];

    /** @var array<string,mixed> */
    private readonly array $safeValue;

    /** @param array<string,mixed> $safeValue */
    public function __construct(
        private readonly string $sourceType,
        private readonly string $sourceId,
        private readonly ?string $observedAt,
        private readonly string $contributionLabel,
        array $safeValue,
    ) {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported recommendation evidence source type.');
        }
        if (trim($sourceId) === '' || trim($contributionLabel) === '') {
            throw new \InvalidArgumentException('Recommendation evidence source and contribution are required.');
        }
        if ($observedAt !== null && trim($observedAt) === '') {
            throw new \InvalidArgumentException('Recommendation evidence observation time must be null or non-empty.');
        }
        self::encode($safeValue);
        $this->safeValue = $safeValue;
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function observedAt(): ?string
    {
        return $this->observedAt;
    }

    public function contributionLabel(): string
    {
        return $this->contributionLabel;
    }

    /** @return array<string,mixed> */
    public function safeValue(): array
    {
        return $this->safeValue;
    }

    public function safeValueJson(): string
    {
        return self::encode($this->safeValue);
    }

    /** @param array<string,mixed> $value */
    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Recommendation evidence safe value must be JSON serializable.', 0, $exception);
        }
    }
}
