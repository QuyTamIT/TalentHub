<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

final class ConsentDecision
{
    public const POLICY_VERSION = 'learner-ai-consent-decision-v1';
    public const REQUIRED_SCOPES = ['activity', 'assessment', 'evaluation', 'skills'];

    /** @var array<string,array{action:string,policy_version:string,occurred_at:string,request_id:string}> */
    private readonly array $latestByScope;
    /** @var list<string> */
    private readonly array $allowedScopes;
    /** @var list<string> */
    private readonly array $serviceScopes;
    private readonly string $decisionHash;

    /**
     * @param array<string,array{action:string,policy_version:string,occurred_at:string,request_id:string}> $latestByScope
     * @param list<string> $serviceScopes
     */
    public function __construct(array $latestByScope, private readonly string $evaluatedAt, array $serviceScopes = [])
    {
        $normalizedServiceScopes = [];
        foreach ($serviceScopes as $scope) {
            if (!is_string($scope) || !in_array($scope, self::REQUIRED_SCOPES, true)) {
                throw new \InvalidArgumentException('Unknown service data scope.');
            }
            $normalizedServiceScopes[$scope] = true;
        }
        $serviceScopeList = array_keys($normalizedServiceScopes);
        sort($serviceScopeList, SORT_STRING);
        $this->serviceScopes = $serviceScopeList;
        ksort($latestByScope, SORT_STRING);
        $this->latestByScope = $latestByScope;
        $allowed = array_fill_keys($this->serviceScopes, true);
        $canonical = [];
        foreach (self::REQUIRED_SCOPES as $scope) {
            $event = $latestByScope[$scope] ?? null;
            if ($event !== null && $event['action'] === 'granted') {
                $allowed[$scope] = true;
            }
            $canonical[$scope] = $event ?? [
                'action' => 'missing',
                'policy_version' => '',
                'occurred_at' => '',
                'request_id' => '',
            ];
        }
        $allowedScopeList = array_keys($allowed);
        sort($allowedScopeList, SORT_STRING);
        $this->allowedScopes = $allowedScopeList;
        $encoded = json_encode(
            [
                'decision_policy' => self::POLICY_VERSION,
                'service_scopes' => $this->serviceScopes,
                'scopes' => $canonical,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $this->decisionHash = hash('sha256', $encoded);
    }

    /** @return list<string> */
    public function allowedScopes(): array { return $this->allowedScopes; }
    public function policyVersion(): string { return self::POLICY_VERSION; }
    public function decisionHash(): string { return $this->decisionHash; }
    public function evaluatedAt(): string { return $this->evaluatedAt; }

    /** @param list<string> $scopes */
    public function withServiceScopes(array $scopes): self
    {
        return new self($this->latestByScope, $this->evaluatedAt, [...$this->serviceScopes, ...$scopes]);
    }

    /** @param list<string> $requiredScopes */
    public function permitsScopes(array $requiredScopes): bool
    {
        $normalized = [];
        foreach ($requiredScopes as $scope) {
            if (!is_string($scope) || !in_array($scope, self::REQUIRED_SCOPES, true)) {
                return false;
            }
            $normalized[$scope] = true;
        }
        $normalized = array_keys($normalized);
        sort($normalized, SORT_STRING);
        return $normalized !== [] && array_diff($normalized, $this->allowedScopes) === [];
    }

    public function permitsAllRequiredScopes(): bool { return $this->permitsScopes(self::REQUIRED_SCOPES); }

    public function denialReason(): ?string
    {
        foreach (self::REQUIRED_SCOPES as $scope) {
            if (!in_array($scope, $this->serviceScopes, true)
                && ($this->latestByScope[$scope]['action'] ?? 'missing') === 'revoked') {
                return 'consent_revoked';
            }
        }
        return $this->permitsAllRequiredScopes() ? null : 'consent_missing';
    }
}
