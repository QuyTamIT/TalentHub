<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Ai\Sources\ConsentSource;

final class ConsentPolicy
{
    private const SCOPES = ['assessment', 'skills', 'activity', 'evaluation'];

    /** @var Closure():string */
    private readonly Closure $clock;

    /** @var list<string> */
    private readonly array $serviceScopes;

    /**
     * @param (callable():string)|null $clock
     * @param list<string> $serviceScopes
     */
    public function __construct(private readonly ConsentSource $source, ?callable $clock = null, array $serviceScopes = [])
    {
        $this->clock = $clock !== null
            ? Closure::fromCallable($clock)
            : static fn (): string => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d\\TH:i:s.uP');
        $this->serviceScopes = $serviceScopes;
    }

    public function decision(string $studentId): ConsentDecision
    {
        /** @var array<string, array{action:string,policy_version:string,occurred_at:string,request_id:string,ordering:string}> $latestByScope */
        $latestByScope = [];

        foreach ($this->source->forStudent($studentId) as $event) {
            $scope = $event['scope'] ?? null;
            $action = $event['action'] ?? null;
            $policyVersion = $event['policy_version'] ?? null;
            $occurredAt = $event['occurred_at'] ?? null;
            $requestId = $event['request_id'] ?? null;
            if (!is_string($scope) || !in_array($scope, self::SCOPES, true)
                || !is_string($action) || !in_array($action, ['granted', 'revoked'], true)
                || !is_string($policyVersion) || trim($policyVersion) === ''
                || !is_string($occurredAt) || !is_string($requestId)) {
                continue;
            }

            $ordering = $occurredAt . "\0" . $requestId;
            if (!isset($latestByScope[$scope]) || strcmp($ordering, $latestByScope[$scope]['ordering']) > 0) {
                $latestByScope[$scope] = [
                    'action' => $action,
                    'policy_version' => trim($policyVersion),
                    'occurred_at' => $occurredAt,
                    'request_id' => $requestId,
                    'ordering' => $ordering,
                ];
            }
        }

        $canonical = [];
        foreach ($latestByScope as $scope => $event) {
            unset($event['ordering']);
            $canonical[$scope] = $event;
        }

        return new ConsentDecision($canonical, ($this->clock)(), $this->serviceScopes);
    }

    /** Built-in AI access for an authenticated learner; ownership is checked by the caller. */
    public static function forLearnerService(ConsentSource $source, string $appEnvironment): self
    {
        return new self($source, null, ConsentMode::serviceScopes($appEnvironment));
    }

    /** @return list<string> */
    public function allowedScopes(string $studentId): array
    {
        return $this->decision($studentId)->allowedScopes();
    }
}
