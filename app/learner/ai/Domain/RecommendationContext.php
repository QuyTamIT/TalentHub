<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RecommendationContext
{
    /** @var list<string> */
    private readonly array $allowedScopes;

    /** @param list<string> $allowedScopes */
    public function __construct(
        array $allowedScopes,
        private readonly ?string $requestId = null,
        private readonly ?string $idempotencyKey = null,
        private readonly ?string $studentId = null,
        private readonly ?string $consentDecisionHash = null,
        private readonly ?string $consentPolicyVersion = null,
    )
    {
        $allowed = array_values(array_unique(array_filter(
            array_map(static fn (mixed $scope): string => is_string($scope) ? trim($scope) : '', $allowedScopes),
            static fn (string $scope): bool => $scope !== ''
        )));
        sort($allowed, SORT_STRING);
        $this->allowedScopes = $allowed;
    }

    /** @return list<string> */
    public function allowedScopes(): array
    {
        return $this->allowedScopes;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function studentId(): ?string
    {
        return $this->studentId;
    }

    public function consentDecisionHash(): ?string
    {
        return $this->consentDecisionHash;
    }

    public function consentPolicyVersion(): ?string
    {
        return $this->consentPolicyVersion;
    }
}
