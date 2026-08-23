<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationQuery
{
    public function __construct(
        private readonly string $from,
        private readonly string $until,
        private readonly ?string $provider = null,
        private readonly ?string $modelVersion = null,
        private readonly ?string $educationBand = null,
    ) {
        if ($from === '' || $until === '' || strcmp($from, $until) >= 0) {
            throw new \InvalidArgumentException('Evaluation query requires an ordered time window.');
        }
        if ($educationBand !== null && !in_array($educationBand, ['high', 'college'], true)) {
            throw new \InvalidArgumentException('Evaluation query education band is unsupported.');
        }
    }

    public function from(): string { return $this->from; }
    public function until(): string { return $this->until; }
    public function provider(): ?string { return $this->provider; }
    public function modelVersion(): ?string { return $this->modelVersion; }
    public function educationBand(): ?string { return $this->educationBand; }
}
