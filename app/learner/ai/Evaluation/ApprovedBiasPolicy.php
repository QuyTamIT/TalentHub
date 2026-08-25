<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class ApprovedBiasPolicy
{
    /** @param list<string> $bands */
    public function __construct(private readonly string $version, private readonly int $minimumSampleSize, private readonly array $bands)
    {
        if ($version === '' || $minimumSampleSize < 2 || array_diff($bands, ['high', 'college']) !== []) {
            throw new \InvalidArgumentException('Bias policy is invalid.');
        }
    }
    public function version(): string { return $this->version; }
    public function minimumSampleSize(): int { return $this->minimumSampleSize; }
    /** @return list<string> */ public function bands(): array { return $this->bands; }
}
