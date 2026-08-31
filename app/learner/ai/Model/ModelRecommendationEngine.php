<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\BoundProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Provider\StrictAiUnavailable;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Quality\StrictAiReadinessGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

final class ModelRecommendationEngine implements RecommendationEngine
{
    public function __construct(
        private readonly RecommendationProvider $provider,
        private readonly RecommendationEngine $fallback,
        private readonly PromptRegistry $prompts,
        private readonly RecommendationRateLimiter $rateLimiter,
        private readonly RecommendationConfig $config,
        private readonly RecommendationResultValidator $validator,
        private readonly ProviderConsentGate $consentGate,
    ) {
    }

    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        $studentId = trim((string) $context->studentId());
        $strictMode = $this->config->strictMode();

        if (!$this->config->enabled() || $studentId === '') {
            if ($strictMode) {
                throw new StrictAiUnavailable('model_disabled');
            }
            return $this->fallback($input, $context, 'model_disabled');
        }
        $this->assertStrictReadiness($input, $context, $studentId);
        if (!$this->rateLimiter->acquire($studentId)->allowed()) {
            if ($strictMode) {
                throw new StrictAiUnavailable('rate_limited');
            }
            if ($context->shouldPropagateProviderRetry()) {
                throw new ProviderRetryAfterException('rate_limited', 60);
            }
            return $this->fallback($input, $context, 'rate_limited');
        }
        $request = $this->prompts->create($input, $context);
        $response = $this->provider->generate(
            $request,
            new BoundProviderAttemptAuthorizer($this->consentGate, $studentId, $input, $context),
        );
        if (!$response->isSuccess()) {
            if ($context->shouldPropagateProviderRetry() && $response->errorCode() === 'rate_limited' && $response->retryAfterSeconds() !== null) {
                throw new ProviderRetryAfterException('rate_limited', $response->retryAfterSeconds());
            }
            if ($strictMode) {
                $reason = $this->normaliseFailureReason((string) $response->errorCode());
                AiMetricsCollector::shared()->record(['fallback' => false, 'provider_error' => $reason]);
                throw new StrictAiUnavailable($reason);
            }
            return $this->fallback($input, $context, (string) $response->errorCode());
        }
        try {
            $result = new RecommendationResult(
                'model',
                null,
                (string) $this->config->provider(),
                (string) $this->config->model(),
                $request->promptVersion(),
                null,
                $this->items($response->items(), $request),
            );
            $this->validator->validate($result);
            return $result;
        } catch (\Throwable) {
            if ($strictMode) {
                AiMetricsCollector::shared()->record(['fallback' => false, 'provider_error' => 'malformed_output']);
                throw new StrictAiUnavailable('provider_unavailable', 'Strict AI response failed schema or safety validation.', null);
            }
            return $this->fallback($input, $context, 'invalid_model_response');
        }
    }

    /** @param list<array<string,mixed>> $items @return list<RecommendationItem> */
    private function items(array $items, ProviderRequest $request): array
    {
        $result = [];
        foreach ($items as $item) {
            $references = $item['evidence_ref_ids'] ?? null;
            if (!is_array($references) || $references === []) {
                throw new \InvalidArgumentException('Model item evidence references are required.');
            }
            $evidence = [];
            foreach ($references as $referenceId) {
                if (!is_string($referenceId) || ($record = $request->evidence($referenceId)) === null) {
                    throw new \InvalidArgumentException('Model item cited an unavailable evidence reference.');
                }
                $evidence[] = new RecommendationEvidence(
                    $record->sourceType(),
                    $record->sourceId(),
                    $record->observedAt(),
                    'model_source',
                    $record->safeValue(),
                );
            }
            $catalogId = is_string($item['catalog_id'] ?? null) ? $item['catalog_id'] : null;
            if ($catalogId !== null) {
                $hasMatchingEvidence = false;
                foreach ($evidence as $record) {
                    if (in_array($record->sourceType(), ['opportunity', 'catalog'], true)
                        && hash_equals($catalogId, $record->sourceId())) {
                        $hasMatchingEvidence = true;
                        break;
                    }
                }
                if (!$hasMatchingEvidence) {
                    throw new \InvalidArgumentException('Model item catalog id must match its cited catalog evidence.');
                }
            }
            $result[] = new RecommendationItem(
                is_string($item['item_type'] ?? null) ? $item['item_type'] : '',
                is_string($item['title'] ?? null) ? $item['title'] : '',
                is_string($item['summary'] ?? null) ? $item['summary'] : '',
                is_int($item['priority'] ?? null) ? $item['priority'] : 0,
                is_string($item['confidence_band'] ?? null) ? $item['confidence_band'] : '',
                is_array($item['action'] ?? null) ? $item['action'] : [],
                $evidence,
                is_string($item['category'] ?? null) ? $item['category'] : null,
                $catalogId,
                is_string($item['reason'] ?? ($item['explanation'] ?? null)) ? ($item['reason'] ?? $item['explanation']) : null,
                is_array($item['reason_codes'] ?? null) ? $item['reason_codes'] : [],
            );
        }
        return $result;
    }

    private function fallback(RecommendationInput $input, RecommendationContext $context, string $reason): RecommendationResult
    {
        AiMetricsCollector::shared()->record(['fallback' => true]);
        $rule = $this->fallback->generate($input, $context);
        return new RecommendationResult(
            'rule',
            $rule->ruleVersion() ?? 'learner-rules-fallback-1.0.0',
            null,
            null,
            null,
            trim($reason) === '' ? 'provider_unavailable' : trim($reason),
            $rule->items(),
        );
    }

    private function assertStrictReadiness(RecommendationInput $input, RecommendationContext $context, string $studentId): void
    {
        if (!$this->config->strictMode()) {
            return;
        }
        $signals = [
            'snapshot_present' => true,
            'snapshot_evidence_count' => count($input->evidenceReferences()),
            'consent_ready' => true,
            'required_scopes' => $context->allowedScopes(),
            'allowed_scopes' => $context->allowedScopes(),
        ];
        if ($signals['snapshot_evidence_count'] === 0) {
            $signals['snapshot_present'] = false;
        }
        StrictAiReadinessGate::create($this->config)->assertReady(
            $studentId,
            'recommendation.generate',
            $signals,
        );
    }

    private function normaliseFailureReason(string $reason): string
    {
        $reason = strtolower(trim($reason));
        $allowed = [
            'provider_unavailable',
            'consent_required',
            'consent_missing',
            'consent_changed',
            'consent_revoked',
            'data_insufficient',
            'empty_snapshot',
            'missing_migration',
            'model_disabled',
            'rate_limited',
        ];
        if (in_array($reason, $allowed, true)) {
            return $reason;
        }
        return 'provider_unavailable';
    }
}
