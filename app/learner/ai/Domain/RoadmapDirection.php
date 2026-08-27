<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RoadmapDirection
{
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly string $rationale,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $code) !== 1) {
            throw new \InvalidArgumentException('Roadmap direction code is invalid.');
        }
        if (trim($label) === '' || trim($rationale) === '') {
            throw new \InvalidArgumentException('Roadmap direction copy is required.');
        }
    }

    public function code(): string { return $this->code; }
    public function label(): string { return $this->label; }
    public function rationale(): string { return $this->rationale; }

    /** @return array{code:string,label:string,rationale:string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => $this->label, 'rationale' => $this->rationale];
    }
}
