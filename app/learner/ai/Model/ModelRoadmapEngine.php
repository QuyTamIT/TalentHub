<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\BoundProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Provider\StrictAiUnavailable;
use TalentHub\Learner\Ai\Quality\StrictAiReadinessGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

final class ModelRoadmapEngine implements RoadmapEngine
{
    public function __construct(
        private readonly RoadmapProvider $provider,
        private readonly RoadmapEngine $fallback,
        private readonly RoadmapPromptRegistry $prompts,
        private readonly RecommendationRateLimiter $rateLimiter,
        private readonly RecommendationConfig $config,
        private readonly ProviderConsentGate $consentGate,
        private readonly RecommendationEvaluator $evaluator = new RecommendationEvaluator(),
    ) {}

    public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis
    {
        $studentId = trim((string) $context->studentId());
        if (!$this->config->enabled() || $studentId === '') {
            throw new RoadmapModelUnavailable('model_disabled');
        }
        $this->assertStrictReadiness($input, $context, $studentId);
        if (!$this->rateLimiter->acquire($studentId)->allowed()) {
            if ($context->shouldPropagateProviderRetry()) {
                throw new ProviderRetryAfterException('rate_limited', 60);
            }
            throw new RoadmapModelUnavailable('rate_limited');
        }

        $request = $this->prompts->create($input, $context);
<<<<<<< HEAD
        try {
            $response = $this->provider->generate(
                $request,
                new BoundProviderAttemptAuthorizer($this->consentGate, $studentId, $input, $context),
            );
        } catch (\TalentHub\Learner\Ai\Contracts\ProviderUnavailableException $e) {
            throw new RoadmapModelUnavailable($e->reason());
        } catch (\Throwable $e) {
            throw new RoadmapModelUnavailable('provider_unavailable');
        }
        if (!$response->isSuccess()) {
            if ($context->shouldPropagateProviderRetry() && $response->errorCode() === 'rate_limited' && $response->retryAfterSeconds() !== null) {
                throw new ProviderRetryAfterException('rate_limited', $response->retryAfterSeconds());
            }
            $reason = in_array((string) $response->errorCode(), RoadmapModelUnavailable::REASONS, true)
                ? (string) $response->errorCode()
                : 'provider_unavailable';
            throw new RoadmapModelUnavailable($reason);
        }

        try {
            $requestPayload = $request->payload();
            $allowedActivities = $requestPayload['allowed_activity_ids'] ?? [];
            $allowedCatalogIds = $requestPayload['allowed_catalog_ids'] ?? [];
            $validator = new RoadmapAnalysisValidator(
                $request->evidenceReferenceIds(),
                is_array($allowedActivities) ? $allowedActivities : [],
                is_array($allowedCatalogIds) ? $allowedCatalogIds : [],
            );
            $analysis = $validator->fromProviderPayload($response->payload(), [
                'origin' => 'model',
                'provider' => (string) $this->config->provider(),
                'model_version' => (string) $this->config->model(),
                'prompt_version' => $request->promptVersion(),
                'confidence_band' => $this->confidenceBand($input),
                'provider_request_id' => $response->providerRequestId(),
                'response_hash' => $response->responseHash(),
            ]);
            $validator->validate($analysis);
            if (($this->evaluator->evaluateRoadmap($analysis, $input)['valid'] ?? false) !== true) {
                throw new \RuntimeException('Roadmap safety evaluation failed.');
            }
            return $analysis;
        } catch (\Throwable) {
            throw new RoadmapModelUnavailable('invalid_model_response');
        }
    }

=======
        // Provider transport retries deal with network/5xx failures. A model
        // can also return a syntactically valid JSON document that violates a
        // safety or provenance constraint; allow one fresh provider attempt
        // for that transient case while keeping the validator fail-closed.
        $validationAttempts = max(1, min(2, $this->config->maxAttempts()));
        for ($validationAttempt = 1; $validationAttempt <= $validationAttempts; $validationAttempt++) {
            try {
                $response = $this->provider->generate(
                    $request,
                    new BoundProviderAttemptAuthorizer($this->consentGate, $studentId, $input, $context),
                );
            } catch (\TalentHub\Learner\Ai\Contracts\ProviderUnavailableException $e) {
                throw new RoadmapModelUnavailable($e->reason());
            } catch (\Throwable $e) {
                throw new RoadmapModelUnavailable('provider_unavailable');
            }
            if (!$response->isSuccess()) {
                if ($context->shouldPropagateProviderRetry() && $response->errorCode() === 'rate_limited' && $response->retryAfterSeconds() !== null) {
                    throw new ProviderRetryAfterException('rate_limited', $response->retryAfterSeconds());
                }
                $reason = in_array((string) $response->errorCode(), RoadmapModelUnavailable::REASONS, true)
                    ? (string) $response->errorCode()
                    : 'provider_unavailable';
                throw new RoadmapModelUnavailable($reason);
            }

            try {
                $requestPayload = $request->payload();
                $allowedActivities = $requestPayload['allowed_activity_ids'] ?? [];
                $allowedCatalogIds = $requestPayload['allowed_catalog_ids'] ?? [];
                $validator = new RoadmapAnalysisValidator(
                    $request->evidenceReferenceIds(),
                    is_array($allowedActivities) ? $allowedActivities : [],
                    is_array($allowedCatalogIds) ? $allowedCatalogIds : [],
                );
                $analysis = $validator->fromProviderPayload($response->payload(), [
                    'origin' => 'model',
                    'provider' => (string) $this->config->provider(),
                    'model_version' => (string) $this->config->model(),
                    'prompt_version' => $request->promptVersion(),
                    'confidence_band' => $this->confidenceBand($input),
                    'provider_request_id' => $response->providerRequestId(),
                    'response_hash' => $response->responseHash(),
                ]);
                $validator->validate($analysis);
                if (($this->evaluator->evaluateRoadmap($analysis, $input)['valid'] ?? false) !== true) {
                    throw new \RuntimeException('Roadmap safety evaluation failed.');
                }
                return $analysis;
            } catch (\Throwable) {
                if ($validationAttempt >= $validationAttempts) {
                    throw new RoadmapModelUnavailable('invalid_model_response');
                }
            }
        }

        throw new RoadmapModelUnavailable('invalid_model_response');
    }

>>>>>>> 05d98af655ad6632b478e8cd4a88f4058926f303
    private function confidenceBand(RecommendationInput $input): string
    {
        $counts = $input->qualityFlags()['source_counts'] ?? [];
        $assessmentCount = is_array($counts) && is_numeric($counts['assessments'] ?? null)
            ? (int) $counts['assessments']
            : count($input->payload()['assessments'] ?? []);
        return $assessmentCount >= 4 ? 'high' : 'low';
    }

    private function assertStrictReadiness(RecommendationInput $input, RecommendationContext $context, string $studentId): void
    {
        if (!$this->config->strictMode()) {
            return;
        }
        $evidenceCount = count($input->evidenceReferences());
        $signals = [
            'snapshot_present' => $evidenceCount > 0,
            'snapshot_evidence_count' => $evidenceCount,
            'consent_ready' => true,
            'required_scopes' => $context->allowedScopes(),
            'allowed_scopes' => $context->allowedScopes(),
        ];
        StrictAiReadinessGate::create($this->config)->assertReady(
            $studentId,
            'roadmap.generate',
            $signals,
        );
    }
}
