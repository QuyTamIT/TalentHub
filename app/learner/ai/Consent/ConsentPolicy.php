<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

use TalentHub\Learner\Ai\Sources\ConsentSource;

final class ConsentPolicy
{
    private const SCOPES = ['assessment', 'skills', 'activity', 'evaluation'];

    public function __construct(private readonly ConsentSource $source)
    {
    }

    /** @return list<string> */
    public function allowedScopes(string $studentId): array
    {
        /** @var array<string, array{action:string,ordering:string}> $latestByScope */
        $latestByScope = [];

        foreach ($this->source->forStudent($studentId) as $event) {
            $scope = $event['scope'] ?? null;
            $action = $event['action'] ?? null;
            $occurredAt = $event['occurred_at'] ?? null;
            $requestId = $event['request_id'] ?? null;
            if (!is_string($scope) || !in_array($scope, self::SCOPES, true)
                || !is_string($action) || !in_array($action, ['granted', 'revoked'], true)
                || !is_string($occurredAt) || !is_string($requestId)) {
                continue;
            }

            $ordering = $occurredAt . "\0" . $requestId;
            if (!isset($latestByScope[$scope]) || strcmp($ordering, $latestByScope[$scope]['ordering']) > 0) {
                $latestByScope[$scope] = ['action' => $action, 'ordering' => $ordering];
            }
        }

        $allowed = [];
        foreach ($latestByScope as $scope => $event) {
            if ($event['action'] === 'granted') {
                $allowed[] = $scope;
            }
        }
        sort($allowed, SORT_STRING);

        return $allowed;
    }
}
