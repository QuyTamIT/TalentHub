<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use Closure;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;

final class RoadmapService
{
    private readonly Closure $authorizer;
    private readonly Closure $consentResolver;
    private readonly Closure $snapshotBuilder;
    private readonly Closure $qualityGate;
    private readonly Closure $pendingRunCreator;
    private readonly Closure $runCompleter;
    private readonly Closure $runFailer;

    public function __construct(
        private readonly RoadmapRepository $roadmaps,
        private readonly RoadmapEngine $engine,
        callable $authorizer,
        callable $consentResolver,
        callable $snapshotBuilder,
        callable $qualityGate,
        callable $pendingRunCreator,
        callable $runCompleter,
        callable $runFailer,
        private readonly ?RoadmapEngine $modelEngine = null,
        private readonly ?RecommendationConfig $modelConfig = null,
        private readonly ?AiAvailabilityPolicy $availabilityPolicy = null,
        /** @var array<string,mixed>|null */
        private readonly ?array $rolloutEvidence = null,
    ) {
        $this->authorizer = Closure::fromCallable($authorizer);
        $this->consentResolver = Closure::fromCallable($consentResolver);
        $this->snapshotBuilder = Closure::fromCallable($snapshotBuilder);
        $this->qualityGate = Closure::fromCallable($qualityGate);
        $this->pendingRunCreator = Closure::fromCallable($pendingRunCreator);
        $this->runCompleter = Closure::fromCallable($runCompleter);
        $this->runFailer = Closure::fromCallable($runFailer);
    }

    /** @return array<string,mixed>|null */
    public function latest(string $studentId): ?array
    {
        try {
            if (!(($this->authorizer)($studentId))) return null;
            $roadmap = $this->roadmaps->latestForStudent($studentId);
            if ($roadmap !== null) return $this->readyWithHistory($studentId, $roadmap);
            $pending = $this->roadmaps->latestPendingForStudent($studentId);
            return $pending === null ? null : $this->pending($pending);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /** @return array<string,mixed>|null */
    public function version(string $studentId, int $version): ?array
    {
        try {
            if (!(($this->authorizer)($studentId))) return null;
            $roadmap = $this->roadmaps->versionForStudent($studentId, $version);
            return $roadmap === null ? null : $this->readyWithHistory($studentId, $roadmap);
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    /** @return array<string,mixed> */
    public function generate(
        string $studentId,
        string $requestId,
        string $idempotencyKey,
        bool $forceRefresh = false,
        ?callable $leaseGuard = null,
        bool $propagateProviderRetry = false,
    ): array
    {
        try {
            if (!(($this->authorizer)($studentId))) return ['state'=>'forbidden'];
        } catch (\Throwable) {
            return ['state'=>'forbidden'];
        }

        try {
            $decision = ($this->consentResolver)($studentId);
            $scopes = $this->scopes($decision);
            $input = ($this->snapshotBuilder)($studentId, $scopes);
            if (!$input instanceof RecommendationInput) throw new \RuntimeException('Roadmap snapshot is unavailable.');
            $input = $this->withPreferenceSignals($input, $this->roadmaps->feedbackSignalsForStudent($studentId));
            $quality = ($this->qualityGate)($input);
            if (!$quality instanceof DataQualityResult) throw new \RuntimeException('Roadmap quality state is unavailable.');
        } catch (\Throwable) {
            return $this->unavailable();
        }
        try {
            $active = $this->roadmaps->latestForStudent($studentId);
        } catch (\Throwable) {
            $active = null;
        }
        $availability = $this->availabilityPolicy !== null && $this->modelConfig !== null
            ? $this->availabilityPolicy->decide(
                $studentId,
                $this->modelConfig,
                $scopes,
                $quality->state() === 'ready',
                ($active['analysis_origin'] ?? null) === 'model',
                true,
                ['assessment'],
                $this->rolloutEvidence,
            )
            : null;
        if ($quality->state() !== 'ready') {
            if ($active !== null && $availability?->canServeStaleModel()) {
                return $this->retained($studentId, $active, 'snapshot_stale');
            }
            return $this->quality($quality);
        }

        if (!$forceRefresh && $active !== null && is_string($active['input_hash'] ?? null) && hash_equals($active['input_hash'], $input->contentHash())
            && ($availability === null || $availability->canServeActiveModel())) {
            $response = $this->readyWithHistory($studentId, $active); $response['reused'] = true; return $response;
        }
        if ($availability !== null && ($active['analysis_origin'] ?? null) === 'model'
            && !$availability->canServeActiveModel() && !$availability->canServeStaleModel()) {
            return $this->unavailable($availability->reason());
        }

        $context = new RecommendationContext(
            $scopes, $requestId, 'roadmap-' . hash('sha256', $idempotencyKey), $studentId,
            $decision instanceof ConsentDecision ? $decision->decisionHash() : null,
            $decision instanceof ConsentDecision ? $decision->policyVersion() : null,
            $propagateProviderRetry,
        );
        try {
            $this->guardLease($leaseGuard);
            $pending = ($this->pendingRunCreator)($studentId, $input, $context);
            if (!is_array($pending)) throw new \RuntimeException('Roadmap pending run is unavailable.');
        } catch (\Throwable) {
            return $active === null ? $this->unavailable() : $this->retained($studentId, $active, 'persistence_unavailable');
        }
        if (($pending['reused'] ?? false) === true) {
            return $this->pending($pending);
        }

        try {
            $selectedEngine = $availability?->canShowModel() === true && $this->modelEngine !== null
                ? $this->modelEngine
                : $this->engine;
            $analysis = $selectedEngine->generate($input, $context);
            $this->guardLease($leaseGuard);
            ($this->runCompleter)($studentId, (string) ($pending['runId'] ?? ''), $analysis);
            if ($analysis->origin() === 'rule_fallback' && $active !== null) {
                return $this->retained($studentId, $active, $analysis->fallbackReason() ?? 'provider_unavailable');
            }
            $this->guardLease($leaseGuard);
            $saved = $this->roadmaps->saveCompleted(
                $studentId,
                (string) ($pending['runId'] ?? ''),
                $analysis,
                $this->providerAudit($input, $analysis),
            );
            return $this->readyWithHistory($studentId, $saved);
        } catch (\Throwable $exception) {
            try { $this->guardLease($leaseGuard);($this->runFailer)($studentId, (string) ($pending['runId'] ?? ''), $exception instanceof ProviderRetryAfterException?'rate_limited':'roadmap_engine_failure'); } catch (\Throwable) {}
            if($propagateProviderRetry&&$exception instanceof ProviderRetryAfterException)throw $exception;
            return $active === null ? $this->unavailable() : $this->retained($studentId, $active, 'engine_failure');
        }
    }

    /** Internal worker response that retains the persisted model-input hash for provenance checks. */
    public function generateForProfile(
        string $studentId,
        string $requestId,
        string $idempotencyKey,
        bool $forceRefresh = false,
        ?callable $leaseGuard = null,
        bool $propagateProviderRetry = false,
    ): array {
        $result = $this->generate($studentId, $requestId, $idempotencyKey, $forceRefresh, $leaseGuard, $propagateProviderRetry);
        if (($result['state'] ?? null) !== 'ready_model') return $result;
        $persisted = $this->roadmaps->latestForStudent($studentId);
        if (is_array($persisted) && ($persisted['roadmap_id'] ?? null) === ($result['roadmap_id'] ?? null) && is_string($persisted['input_hash'] ?? null)) {
            $result['input_hash'] = $persisted['input_hash'];
        }
        return $result;
    }

    /** Hash of the exact canonical input used by roadmap/profile model generation. */
    public function inputHash(string $studentId): string
    {
        if (!(($this->authorizer)($studentId))) throw new \RuntimeException('Roadmap input is forbidden.');
        $decision = ($this->consentResolver)($studentId);
        $input = ($this->snapshotBuilder)($studentId, $this->scopes($decision));
        if (!$input instanceof RecommendationInput) throw new \RuntimeException('Roadmap snapshot is unavailable.');
        return $this->withPreferenceSignals($input, $this->roadmaps->feedbackSignalsForStudent($studentId))->contentHash();
    }

    /** @return array<string,mixed> */
    public function updateTask(string $studentId, string $taskId, string $status, string $requestId): array
    {
        try {
            if (!(($this->authorizer)($studentId))) return ['state'=>'forbidden'];
            $event = $this->roadmaps->appendTaskEvent($studentId, $taskId, $status, $requestId);
            return [
                'state' => 'task_updated',
                'event_id' => $event['event_id'] ?? null,
                'task_id' => $event['task_id'] ?? null,
                'status' => $event['status'] ?? null,
                'occurred_at' => $event['occurred_at'] ?? null,
                'reused' => ($event['reused'] ?? false) === true,
            ];
        } catch (\InvalidArgumentException|\RuntimeException) {
            return ['state'=>'invalid_task_transition'];
        } catch (\Throwable) {
            return ['state'=>'source_unavailable'];
        }
    }

    /** @return array<string,mixed> */
    public function feedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array
    {
        try {
            if (!(($this->authorizer)($studentId))) return ['state'=>'forbidden'];
            $result = $this->roadmaps->appendRoadmapFeedback($studentId, $roadmapId, $verdict, $reasonCode, $requestId);
            AiMetricsCollector::shared()->record(['recommendation_feedback' => $verdict]);
            return $result;
        } catch (\InvalidArgumentException|\RuntimeException) {
            return ['state'=>'invalid_feedback'];
        } catch (\Throwable) {
            return ['state'=>'source_unavailable'];
        }
    }

    /** @param mixed $decision @return list<string> */
    private function scopes(mixed $decision): array
    {
        $scopes = $decision instanceof ConsentDecision ? $decision->allowedScopes() : $decision;
        if (!is_array($scopes)) throw new \RuntimeException('Roadmap consent is unavailable.');
        $result = [];
        foreach ($scopes as $scope) if (is_string($scope) && in_array($scope, ['activity','assessment','evaluation','skills'], true)) $result[$scope]=true;
        $result = array_keys($result); sort($result, SORT_STRING); return $result;
    }

    /** @return array<string,mixed> */
    private function quality(DataQualityResult $quality): array
    {
        return array_replace($this->unavailable($quality->state()), [
            'quality_state'=>$quality->state(),
            'missing_consent_scopes'=>$quality->missingConsentScopes(),
            'missing_categories'=>$quality->missingCategories(),
            'completion_actions'=>$quality->completionActions(),
        ]);
    }

    /** @param array<string,mixed> $roadmap @return array<string,mixed> */
    private function ready(array $roadmap): array
    {
        $engine = is_array($roadmap['engine'] ?? null) ? $roadmap['engine'] : [];
        $analysisOrigin = ($roadmap['analysis_origin'] ?? null) === 'model' ? 'model' : 'rule';
        $publicEngine = $analysisOrigin === 'model'
            ? array_filter([
                'provider' => $engine['provider'] ?? null,
                'model_version' => $engine['model_version'] ?? null,
                'prompt_version' => $engine['prompt_version'] ?? null,
            ], static fn (mixed $value): bool => is_string($value) && $value !== '')
            : array_filter([
                'rule_version' => $engine['rule_version'] ?? null,
                'fallback_reason' => $engine['fallback_reason'] ?? null,
            ], static fn (mixed $value): bool => is_string($value) && $value !== '');

        $response = [];
        foreach ([
            'roadmap_id', 'version', 'contract_version', 'status', 'analysis_origin',
            'executive_summary', 'confidence_band', 'primary_direction',
            'confidence', 'talent_map', 'strengths', 'improvements', 'potential_paths', 'trend_signals', 'growth_hypotheses',
            'alternative_directions', 'insights', 'evidence_summary',
            'generated_at', 'phases', 'progress', 'reused',
            'stale_since', 'last_refresh_error', 'next_retry_at', 'refresh_job_id',
        ] as $field) {
            if (array_key_exists($field, $roadmap)) $response[$field] = $roadmap[$field];
        }
        $response['engine'] = $publicEngine;
        $response['contract_version'] = (string) ($roadmap['contract_version'] ?? RoadmapAnalysis::CONTRACT_VERSION);
        $response['capability'] = 'roadmap';
        $response['analysis_origin'] = $analysisOrigin;
        $persistedFreshness=(string)($roadmap['freshness_status']??'');
        $response['state'] = $analysisOrigin === 'model' ? ($persistedFreshness==='stale_model'?'stale_model':'ready_model') : 'ready_rule';
        $response['freshness_status'] = $response['state']==='stale_model'?'stale':'fresh';
        $response['last_known_good']=$response['state']==='stale_model';
        $response['model_version'] = $analysisOrigin === 'model' ? ($publicEngine['model_version'] ?? null) : null;
        $response['rule_version'] = $analysisOrigin === 'rule' ? ($publicEngine['rule_version'] ?? null) : null;
        $response['evidence'] = is_array($roadmap['evidence'] ?? null)
            ? $roadmap['evidence']
            : (is_array($roadmap['evidence_summary'] ?? null) ? $roadmap['evidence_summary'] : []);
        AiMetricsCollector::shared()->record([
            'stale' => $response['state'] === 'stale_model',
            'fallback' => $analysisOrigin === 'rule' && isset($publicEngine['fallback_reason']),
            'freshness_age_seconds' => isset($roadmap['generated_at']) && is_string($roadmap['generated_at'])
                ? max(0, time() - (strtotime($roadmap['generated_at']) ?: time())) : 0,
        ]);
        return $response;
    }

    /** @param array<string,mixed> $roadmap @return array<string,mixed> */
    private function readyWithHistory(string $studentId, array $roadmap): array
    {
        $response = $this->ready($roadmap);
        $history = $this->roadmaps->historyForStudent($studentId);
        $response['version_history'] = $history;
        foreach ($history as $entry) {
            if (($entry['roadmap_id'] ?? null) === ($roadmap['roadmap_id'] ?? null)) {
                $response['changed_sections_from_previous'] = $entry['changed_sections'] ?? [];
                break;
            }
        }
        return $response;
    }

    /** @param list<array{verdict:string,reason_code:string,count:int}> $signals */
    private function withPreferenceSignals(RecommendationInput $input, array $signals): RecommendationInput
    {
        if ($signals === []) return $input;
        $payload = $input->payload();
        $payload['preference_signals'] = array_slice($signals, 0, 8);
        return new RecommendationInput($payload, $input->sourceUpdatedAt(), $input->qualityFlags(), $input->evidenceReferences());
    }

    /** @param array<string,mixed> $active @return array<string,mixed> */
    private function retained(string $studentId, array $active, string $reason): array
    {
        $response = $this->readyWithHistory($studentId, $active);
        $response['refresh_state'] = 'fallback_not_applied';
        $response['fallback_reason'] = $reason;
        if (($response['analysis_origin'] ?? null) === 'model') {
            $response['state'] = 'stale_model';
            $response['freshness_status'] = 'stale';
            $response['last_known_good'] = true;
            $response['stale_since'] = is_string($active['stale_since'] ?? null)
                ? $active['stale_since']
                : gmdate('c');
        }
        return $response;
    }

    private function guardLease(?callable $leaseGuard):void
    {
        if($leaseGuard!==null&&!$leaseGuard())throw new \RuntimeException('refresh_lease_lost');
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    private function pending(array $pending): array
    {
        return [
            'contract_version' => RoadmapAnalysis::CONTRACT_VERSION,
            'capability' => 'roadmap',
            'state' => 'pending',
            'freshness_status' => 'pending',
            'analysis_origin' => null,
            'evidence' => [],
            'generated_at' => null,
            'model_version' => null,
            'rule_version' => null,
            'run_id' => $pending['runId'] ?? $pending['run_id'] ?? null,
            'snapshot_id' => $pending['snapshotId'] ?? $pending['snapshot_id'] ?? null,
            'started_at' => $pending['started_at'] ?? null,
            'reused' => ($pending['reused'] ?? false) === true,
        ];
    }

    /** @return array<string,mixed> */
    private function unavailable(?string $reason = null): array
    {
        $response = [
            'contract_version' => RoadmapAnalysis::CONTRACT_VERSION,
            'capability' => 'roadmap',
            'state' => 'ai_unavailable',
            'freshness_status' => 'unavailable',
            'analysis_origin' => null,
            'evidence' => [],
            'generated_at' => null,
            'model_version' => null,
            'rule_version' => null,
        ];
        if ($reason !== null) $response['availability_reason'] = $reason;
        return $response;
    }

    /** @return array<string,mixed> */
    private function providerAudit(RecommendationInput $input, RoadmapAnalysis $analysis): array
    {
        $map = [];
        foreach ($input->evidenceReferences() as $index => $reference) {
            $map[sprintf('evidence-%03d', $index + 1)] = ['source_type'=>$reference['source_type'],'source_id'=>$reference['source_id']];
        }
        $audit = ['evidence_reference_map'=>$map,'input_hash'=>$input->contentHash()];
        if ($analysis->origin() === 'model') {
            $audit['provider_request_id']=$analysis->providerRequestId();
            $audit['response_hash']=$analysis->responseHash();
        }
        return $audit;
    }
}
