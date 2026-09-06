<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Immutable result of deterministic internship skill normalization.
 *
 * `mapped` entries are the only values safe to feed downstream scorers:
 * every code is guaranteed to exist in the canonical skills registry that
 * produced the normalizer. Unmapped display names are tracked separately
 * (original recruiter labels, no synthetic codes) for observability.
 */
final class JobSkillNormalization
{
    /**
     * @param list<array{code:string,label:string}> $mapped
     * @param list<string> $unmapped
     */
    public function __construct(
        private readonly array $mapped,
        private readonly array $unmapped,
    ) {
    }

    /** @return list<array{code:string,label:string}> */
    public function mapped(): array
    {
        return $this->mapped;
    }

    /** @return list<string> */
    public function unmapped(): array
    {
        return $this->unmapped;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(static fn (array $entry): string => $entry['code'], $this->mapped);
    }
}
