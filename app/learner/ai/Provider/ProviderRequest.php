<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use TalentHub\Learner\Ai\Domain\RecommendationEvidence;

final class ProviderRequest
{
    /** @var array<string,mixed> */
    private readonly array $payload;
    /** @var array<string,RecommendationEvidence> */
    private readonly array $evidenceByReference;

    /** @param array<string,mixed> $payload @param array<string,RecommendationEvidence> $evidenceByReference */
    public function __construct(private readonly string $promptVersion, array $payload, array $evidenceByReference)
    {
        if (trim($promptVersion) === '' || $evidenceByReference === []) {
            throw new \InvalidArgumentException('Provider request requires a prompt version and evidence references.');
        }
        foreach ($evidenceByReference as $id => $evidence) {
            if (!is_string($id) || !$evidence instanceof RecommendationEvidence) {
                throw new \InvalidArgumentException('Provider evidence references are invalid.');
            }
        }
        $this->payload = $payload;
        $this->evidenceByReference = $evidenceByReference;
    }

    public function promptVersion(): string { return $this->promptVersion; }
    /** @return array<string,mixed> */
    public function payload(): array { return $this->payload; }
    /** @return list<string> */
    public function evidenceReferenceIds(): array { return array_keys($this->evidenceByReference); }
    public function evidence(string $referenceId): ?RecommendationEvidence { return $this->evidenceByReference[$referenceId] ?? null; }
}
