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
    private readonly string $decisionHash;

    /** @param array<string,array{action:string,policy_version:string,occurred_at:string,request_id:string}> $latestByScope */
    public function __construct(array $latestByScope, private readonly string $evaluatedAt)
    {
        ksort($latestByScope, SORT_STRING);
        $this->latestByScope = $latestByScope;
        $allowed = [];
        $canonical = [];
        foreach (self::REQUIRED_SCOPES as $scope) {
            $event = $latestByScope[$scope] ?? null;
            if ($event !== null && $event['action'] === 'granted') {
                $allowed[] = $scope;
            }
            $canonical[$scope] = $event ?? [
                'action' => 'missing',
                'policy_version' => '',
                'occurred_at' => '',
                'request_id' => '',
            ];
        }
        $this->allowedScopes = $allowed;
        $encoded = json_encode(
            ['decision_policy' => self::POLICY_VERSION, 'scopes' => $canonical],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $this->decisionHash = hash('sha256', $encoded);
    }

    /** @return list<string> */
    public function allowedScopes(): array { return $this->allowedScopes; }
    public function policyVersion(): string { return self::POLICY_VERSION; }
    public function decisionHash(): string { return $this->decisionHash; }
    public function evaluatedAt(): string { return $this->evaluatedAt; }
    public function permitsAllRequiredScopes(): bool { return $this->allowedScopes === self::REQUIRED_SCOPES; }

    public function denialReason(): ?string
    {
        foreach (self::REQUIRED_SCOPES as $scope) {
            if (($this->latestByScope[$scope]['action'] ?? 'missing') === 'revoked') {
                return 'consent_revoked';
            }
        }
        return $this->permitsAllRequiredScopes() ? null : 'consent_missing';
    }
}
