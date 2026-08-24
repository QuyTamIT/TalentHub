<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use JsonException;
use TalentHub\Learner\Ai\Quality\DataQualityResult;

final class RecommendationResponseMapper
{
    /** @return array<string,mixed> */
    public function quality(DataQualityResult $quality): array
    {
        return [
            'state' => $quality->state(),
            'missing_consent_scopes' => $quality->missingConsentScopes(),
            'missing_categories' => $quality->missingCategories(),
            'completion_actions' => $quality->completionActions(),
            'items' => [],
        ];
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    public function run(array $run): array
    {
        $engineType = (string) ($run['engineType'] ?? 'rule');
        $fallbackReason = $this->nullableString($run['fallbackReason'] ?? null);
        $status = (string) ($run['status'] ?? 'unknown');
        $state = match (true) {
            $status === 'pending' => 'pending',
            $status === 'failed' => 'engine_failure',
            $fallbackReason !== null => 'fallback_rule',
            $engineType === 'model' => 'ready_model',
            default => 'ready_rule',
        };

        return [
            'state' => $state,
            'run_id' => $this->nullableString($run['runId'] ?? null),
            'snapshot_id' => $this->nullableString($run['snapshotId'] ?? null),
            'status' => $status,
            'engine_type' => $engineType,
            'rule_version' => $this->nullableString($run['ruleVersion'] ?? null),
            'provider' => $this->nullableString($run['provider'] ?? null),
            'model_version' => $this->nullableString($run['modelVersion'] ?? null),
            'prompt_version' => $this->nullableString($run['promptVersion'] ?? null),
            'fallback_reason' => $fallbackReason,
            'generated_at' => $this->nullableString($run['completedAt'] ?? null),
            'items' => $this->items($run['items'] ?? []),
        ];
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    public function pending(array $pending): array
    {
        return [
            'state' => 'pending',
            'run_id' => $this->nullableString($pending['runId'] ?? null),
            'snapshot_id' => $this->nullableString($pending['snapshotId'] ?? null),
            'status' => (string) ($pending['status'] ?? 'pending'),
            'reused' => (bool) ($pending['reused'] ?? false),
            'items' => [],
        ];
    }

    /** @return array<string,mixed> */
    public function sourceUnavailable(): array
    {
        return ['state' => 'source_unavailable', 'items' => []];
    }

    /** @return array<string,mixed> */
    public function staleSnapshot(): array
    {
        return ['state' => 'stale_snapshot', 'items' => []];
    }

    /** @return array<string,mixed> */
    public function engineFailure(): array
    {
        return ['state' => 'engine_failure', 'items' => []];
    }

    /** @return array<string,mixed> */
    public function forbidden(): array
    {
        return ['state' => 'forbidden', 'items' => []];
    }

    /** @return list<array<string,mixed>> */
    private function items(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $mapped = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mapped[] = [
                'item_id' => $this->nullableString($item['itemId'] ?? null),
                'item_type' => $this->nullableString($item['itemType'] ?? null),
                'title' => $this->nullableString($item['title'] ?? null),
                'summary' => $this->nullableString($item['summary'] ?? null),
                'priority' => is_numeric($item['priority'] ?? null) ? (int) $item['priority'] : null,
                'confidence_band' => $this->nullableString($item['confidenceBand'] ?? null),
                'action' => $this->action($item['actionJson'] ?? null),
                'evidence' => is_array($item['evidence'] ?? null) ? $item['evidence'] : [],
            ];
        }
        return $mapped;
    }

    /** @return array<string,mixed> */
    private function action(mixed $encoded): array
    {
        if (is_array($encoded)) {
            return $encoded;
        }
        if (!is_string($encoded) || $encoded === '') {
            return [];
        }
        try {
            $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
