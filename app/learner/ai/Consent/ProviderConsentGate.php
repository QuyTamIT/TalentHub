<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

final class ProviderConsentGate
{
    /** @var list<string> */
    private readonly array $requiredScopes;
    /** @var list<string> */
    private readonly array $serviceScopes;

    /** @param list<string> $requiredScopes @param list<string> $serviceScopes */
    public function __construct(
        private readonly ConsentPolicy $policy,
        array $requiredScopes = ConsentDecision::REQUIRED_SCOPES,
        array $serviceScopes = [],
    )
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
        $service = [];
        foreach ($serviceScopes as $scope) {
            if (!is_string($scope) || !in_array($scope, $this->requiredScopes, true)) {
                throw new \InvalidArgumentException('Service scope must be required by this provider gate.');
            }
            $service[$scope] = true;
        }
        $serviceScopeList = array_keys($service);
        sort($serviceScopeList, SORT_STRING);
        $this->serviceScopes = $serviceScopeList;
    }

    public function authorize(string $studentId, RecommendationInput $input, RecommendationContext $context): ConsentDecision
    {
        unset($input); // The immutable input hash is bound by the caller/model attempt contract.
        $studentId = trim($studentId);
        if ($studentId === '' || !hash_equals($studentId, trim((string) $context->studentId()))) {
            throw new ProviderConsentDenied('consent_changed');
        }
        $decision = $this->policy->decision($studentId)->withServiceScopes($this->serviceScopes);
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
