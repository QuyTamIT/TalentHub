<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use JsonException;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;

final class RecommendationResponseMapper
{
    private const CONTRACT_VERSION = 'learner-ai-customer-1.0.0';

    /** @return array<string,mixed> */
    public function quality(DataQualityResult $quality): array
    {
        return array_replace($this->unavailable(), [
            'quality_state' => $quality->state(),
            'availability_reason' => $quality->state(),
            'missing_consent_scopes' => $quality->missingConsentScopes(),
            'missing_categories' => $quality->missingCategories(),
            'completion_actions' => $quality->completionActions(),
        ]);
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    public function run(array $run, bool $recordMetrics = true): array
    {
        $engineType = (string) ($run['engineType'] ?? 'rule');
        $fallbackReason = $this->nullableString($run['fallbackReason'] ?? null);
        $status = (string) ($run['status'] ?? 'unknown');
        if ($status === 'failed') {
            return $this->providerUnavailable(
                $this->nullableString($run['safeErrorCode'] ?? null) ?? 'provider_unavailable',
                $run,
            );
        }
        $persistedFreshness=(string)($run['freshness_status']??'');
        $state = match (true) {
            $status === 'pending' => 'pending',
            $engineType === 'model' && $persistedFreshness === 'stale_model' => 'stale_model',
            $engineType === 'model' => 'ready_model',
            default => 'ready_rule',
        };
        $analysisOrigin = match ($state) {
            'ready_model', 'stale_model' => 'model',
            'ready_rule' => 'rule',
            default => null,
        };
        $items = $this->items($run['items'] ?? []);

        $response = [
            'contract_version' => self::CONTRACT_VERSION,
            'capability' => 'recommendation',
            'state' => $state,
            'freshness_status' => match ($state) {
                'ready_model', 'ready_rule' => 'fresh',
                'stale_model' => 'stale',
                'pending' => 'pending',
                default => 'unavailable',
            },
            'analysis_origin' => $analysisOrigin,
            'evidence' => $this->evidence($items),
            'run_id' => $this->nullableString($run['runId'] ?? null),
            'snapshot_id' => $this->nullableString($run['snapshotId'] ?? null),
            'status' => $status,
            'persistence_status' => $status,
            'engine_type' => $engineType,
            'rule_version' => $analysisOrigin === 'rule' ? $this->nullableString($run['ruleVersion'] ?? null) : null,
            'provider' => $this->nullableString($run['provider'] ?? null),
            'model_version' => $analysisOrigin === 'model' ? $this->nullableString($run['modelVersion'] ?? null) : null,
            'prompt_version' => $this->nullableString($run['promptVersion'] ?? null),
            'fallback_reason' => $fallbackReason,
            'generated_at' => $this->nullableString($run['completedAt'] ?? null),
            'stale_since' => $this->nullableString($run['stale_since'] ?? null),
            'last_refresh_error' => $this->nullableString($run['last_refresh_error'] ?? null),
            'next_retry_at' => $this->nullableString($run['next_retry_at'] ?? null),
            'refresh_job_id' => $this->nullableString($run['refresh_job_id'] ?? null),
            'last_known_good' => $state === 'stale_model',
            'items' => $items,
        ];
        if ($recordMetrics) $this->recordOutcome($response);
        return $response;
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    public function pending(array $pending): array
    {
        return [
            'contract_version' => self::CONTRACT_VERSION,
            'capability' => 'recommendation',
            'state' => 'pending',
            'freshness_status' => 'pending',
            'analysis_origin' => null,
            'evidence' => [],
            'generated_at' => null,
            'model_version' => null,
            'rule_version' => null,
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
        return $this->unavailable();
    }

    /** @return array<string,mixed> */
    public function staleSnapshot(): array
    {
        return $this->unavailable();
    }

    /** @return array<string,mixed> */
    public function engineFailure(): array
    {
        return $this->unavailable();
    }

    /** @param array<string,mixed>|null $pending */
    public function providerUnavailable(string $reason = 'provider_unavailable', ?array $pending = null): array
    {
        $response = $this->unavailable();
        $response['state'] = 'provider_unavailable';
        $response['availability_reason'] = $reason;
        $response['safe_error_code'] = $reason;
        if ($pending !== null) {
            $response['run_id'] = $this->nullableString($pending['runId'] ?? null);
            $response['snapshot_id'] = $this->nullableString($pending['snapshotId'] ?? null);
            $response['status'] = (string) ($pending['status'] ?? 'failed');
        }
        return $response;
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
            $action = $this->action($item['actionJson'] ?? null);
            $metadata = is_array($action['__ai_metadata'] ?? null) ? $action['__ai_metadata'] : [];
            unset($action['__ai_metadata']);
            $mapped[] = [
                'item_id' => $this->nullableString($item['itemId'] ?? null),
                'item_type' => $this->nullableString($item['itemType'] ?? null),
                'title' => $this->nullableString($item['title'] ?? null),
                'summary' => $this->nullableString($item['summary'] ?? null),
                'priority' => is_numeric($item['priority'] ?? null) ? (int) $item['priority'] : null,
                'confidence_band' => $this->nullableString($item['confidenceBand'] ?? null),
                'category' => $this->nullableString($item['category'] ?? ($metadata['category'] ?? null)),
                'catalog_id' => $this->nullableString($item['catalogId'] ?? ($item['catalog_id'] ?? ($metadata['catalog_id'] ?? null))),
                'reason' => $this->nullableString($item['reason'] ?? ($item['explanation'] ?? ($metadata['reason'] ?? null))),
                'reason_codes' => is_array($item['reasonCodes'] ?? ($item['reason_codes'] ?? ($metadata['reason_codes'] ?? null))) ? array_values($item['reasonCodes'] ?? ($item['reason_codes'] ?? $metadata['reason_codes'])) : [],
                'action' => $action,
                'evidence' => $this->normalizedEvidence($item['evidence'] ?? []),
            ];
        }
        return $mapped;
    }

    /** @return list<array<string,mixed>> */
    private function normalizedEvidence(mixed $evidence): array
    {
        if (!is_array($evidence)) return [];
        $result = [];
        foreach ($evidence as $entry) {
            if (!is_array($entry)) continue;
            $safeValue = $entry['safe_value'] ?? ($entry['safeValue'] ?? null);
            if ($safeValue === null && is_string($entry['safeValueJson'] ?? null)) {
                try { $safeValue = json_decode($entry['safeValueJson'], true, 64, JSON_THROW_ON_ERROR); }
                catch (JsonException) { $safeValue = []; }
            }
            $result[] = [
                'source_type' => $this->nullableString($entry['source_type'] ?? ($entry['sourceType'] ?? null)),
                'source_id' => $this->nullableString($entry['source_id'] ?? ($entry['sourceId'] ?? null)),
                'observed_at' => $this->nullableString($entry['observed_at'] ?? ($entry['observedAt'] ?? null)),
                'contribution_label' => $this->nullableString($entry['contribution_label'] ?? ($entry['contributionLabel'] ?? null)),
                'safe_value' => is_array($safeValue) ? $safeValue : [],
            ];
        }
        return $result;
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    public function staleModel(array $run, ?string $staleSince = null): array
    {
        $response = $this->run($run, false);
        if (($response['analysis_origin'] ?? null) !== 'model') {
            return $this->unavailable();
        }
        $response['state'] = 'stale_model';
        $response['freshness_status'] = 'stale';
        $response['last_known_good'] = true;
        $response['stale_since'] = $staleSince ?? gmdate('c');
        $this->recordOutcome($response);
        return $response;
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    public function readyRule(array $run, ?string $fallbackReason = null): array
    {
        $response = $this->run($run, false);
        if (($response['analysis_origin'] ?? null) !== 'rule') return $this->unavailable();
        $reason = $this->nullableString($fallbackReason);
        if ($reason !== null) $response['fallback_reason'] = $reason;
        $this->recordOutcome($response);
        return $response;
    }

    /** @param array<string,mixed> $response */
    private function recordOutcome(array $response): void
    {
        $generated = is_string($response['generated_at'] ?? null) ? strtotime($response['generated_at']) : false;
        AiMetricsCollector::shared()->record([
            'stale' => ($response['state'] ?? null) === 'stale_model',
            'fallback' => ($response['analysis_origin'] ?? null) === 'rule' && is_string($response['fallback_reason'] ?? null),
            'freshness_age_seconds' => $generated === false ? 0 : max(0, time() - $generated),
        ]);
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function evidence(array $items): array
    {
        $evidence = [];
        foreach ($items as $item) {
            foreach (($item['evidence'] ?? []) as $reference) {
                if (!is_array($reference)) continue;
                $key = json_encode($reference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($key)) $evidence[$key] = $reference;
            }
        }
        return array_values($evidence);
    }

    /** @return array<string,mixed> */
    private function unavailable(): array
    {
        return [
            'contract_version' => self::CONTRACT_VERSION,
            'capability' => 'recommendation',
            'state' => 'ai_unavailable',
            'freshness_status' => 'unavailable',
            'analysis_origin' => null,
            'evidence' => [],
            'generated_at' => null,
            'model_version' => null,
            'rule_version' => null,
            'items' => [],
        ];
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
