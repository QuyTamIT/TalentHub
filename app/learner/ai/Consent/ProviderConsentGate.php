<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

final class ProviderConsentGate
{
    /** @var list<string> */
    private readonly array $requiredScopes;

    /** @param list<string> $requiredScopes */
    public function __construct(private readonly ConsentPolicy $policy, array $requiredScopes = ConsentDecision::REQUIRED_SCOPES)
    {
        $normalized = [];
        foreach ($requiredScopes as $scope) {
            if (!is_string($scope) || !in_array($scope, ConsentDecision::REQUIRED_SCOPES, true)) {
                throw new \InvalidArgumentException('Unknown provider consent scope.');
            }
            $normalized[$scope] = true;
        }
        $normalized = array_keys($normalized);
        sort($normalized, SORT_STRING);
        if ($normalized === []) {
            throw new \InvalidArgumentException('At least one provider consent scope is required.');
        }
        $this->requiredScopes = $normalized;
    }

    public function authorize(string $studentId, RecommendationInput $input, RecommendationContext $context): ConsentDecision
    {
        unset($input); // The immutable input hash is bound by the caller/model attempt contract.
        $studentId = trim($studentId);
        if ($studentId === '' || !hash_equals($studentId, trim((string) $context->studentId()))) {
            throw new ProviderConsentDenied('consent_changed');
        }
        $decision = $this->policy->decision($studentId);
        if (!$decision->permitsScopes($this->requiredScopes)) {
            throw new ProviderConsentDenied($decision->denialReason() ?? 'consent_missing');
        }
        if ($decision->allowedScopes() !== $context->allowedScopes()) {
            throw new ProviderConsentDenied('consent_changed');
        }
        if ($context->consentPolicyVersion() !== null
            && !hash_equals($context->consentPolicyVersion(), $decision->policyVersion())) {
            throw new ProviderConsentDenied('consent_changed');
        }
        if ($context->consentDecisionHash() !== null
            && !hash_equals($context->consentDecisionHash(), $decision->decisionHash())) {
            throw new ProviderConsentDenied('consent_changed');
        }
        return $decision;
    }
}
