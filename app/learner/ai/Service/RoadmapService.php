<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use Closure;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Quality\DataQualityResult;

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
            if ($roadmap !== null) return $this->ready($roadmap);
            return $this->roadmaps->latestPendingForStudent($studentId);
        } catch (\Throwable) {
            return ['state' => 'source_unavailable'];
        }
    }

    /** @return array<string,mixed> */
    public function generate(string $studentId, string $requestId, string $idempotencyKey): array
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
            $quality = ($this->qualityGate)($input);
            if (!$quality instanceof DataQualityResult) throw new \RuntimeException('Roadmap quality state is unavailable.');
        } catch (\Throwable) {
            return ['state'=>'source_unavailable'];
        }
        if ($quality->state() !== 'ready') return $this->quality($quality);

        try {
            $active = $this->roadmaps->latestForStudent($studentId);
        } catch (\Throwable) {
            $active = null;
        }
        if ($active !== null && is_string($active['input_hash'] ?? null) && hash_equals($active['input_hash'], $input->contentHash())) {
            $response = $this->ready($active); $response['reused'] = true; return $response;
        }

        $context = new RecommendationContext(
            $scopes, $requestId, 'roadmap-' . hash('sha256', $idempotencyKey), $studentId,
            $decision instanceof ConsentDecision ? $decision->decisionHash() : null,
            $decision instanceof ConsentDecision ? $decision->policyVersion() : null,
        );
        try {
            $pending = ($this->pendingRunCreator)($studentId, $input, $context);
            if (!is_array($pending)) throw new \RuntimeException('Roadmap pending run is unavailable.');
        } catch (\Throwable) {
            return $active === null ? ['state'=>'source_unavailable'] : $this->retained($active, 'persistence_unavailable');
        }
        if (($pending['reused'] ?? false) === true) {
            return ['state'=>'pending','run_id'=>$pending['runId']??null,'snapshot_id'=>$pending['snapshotId']??null,'reused'=>true];
        }

        try {
            $analysis = $this->engine->generate($input, $context);
            ($this->runCompleter)($studentId, (string) ($pending['runId'] ?? ''), $analysis);
            if ($analysis->origin() === 'rule_fallback' && $active !== null) {
                return $this->retained($active, $analysis->fallbackReason() ?? 'provider_unavailable');
            }
            $saved = $this->roadmaps->saveCompleted(
                $studentId,
                (string) ($pending['runId'] ?? ''),
                $analysis,
                $this->providerAudit($input, $analysis),
            );
            return $this->ready($saved);
        } catch (\Throwable) {
            try { ($this->runFailer)($studentId, (string) ($pending['runId'] ?? ''), 'roadmap_engine_failure'); } catch (\Throwable) {}
            return $active === null ? ['state'=>'engine_failure'] : $this->retained($active, 'engine_failure');
        }
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
        return ['state'=>$quality->state(),'missing_consent_scopes'=>$quality->missingConsentScopes(),'missing_categories'=>$quality->missingCategories(),'completion_actions'=>$quality->completionActions()];
    }

    /** @param array<string,mixed> $roadmap @return array<string,mixed> */
    private function ready(array $roadmap): array
    {
        $engine = is_array($roadmap['engine'] ?? null) ? $roadmap['engine'] : [];
        $publicEngine = ($roadmap['analysis_origin'] ?? null) === 'model'
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
            'alternative_directions', 'insights', 'evidence_summary',
            'generated_at', 'phases', 'progress', 'reused',
        ] as $field) {
            if (array_key_exists($field, $roadmap)) $response[$field] = $roadmap[$field];
        }
        $response['engine'] = $publicEngine;
        $response['state'] = ($roadmap['analysis_origin'] ?? null) === 'model' ? 'ready_model' : 'fallback_rule';
        return $response;
    }

    /** @param array<string,mixed> $active @return array<string,mixed> */
    private function retained(array $active, string $reason): array
    {
        $response = $this->ready($active);
        $response['refresh_state'] = 'fallback_not_applied';
        $response['fallback_reason'] = $reason;
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
