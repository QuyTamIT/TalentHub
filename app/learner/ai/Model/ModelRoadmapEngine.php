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
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
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
        if (!$this->rateLimiter->acquire($studentId)->allowed()) {
            if ($context->shouldPropagateProviderRetry()) {
                throw new ProviderRetryAfterException('rate_limited', 60);
            }
            throw new RoadmapModelUnavailable('rate_limited');
        }

        $request = $this->prompts->create($input, $context);
        try {
            $response = $this->provider->generate(
                $request,
                new BoundProviderAttemptAuthorizer($this->consentGate, $studentId, $input, $context),
            );
        } catch (\Throwable) {
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

    private function confidenceBand(RecommendationInput $input): string
    {
        $counts = $input->qualityFlags()['source_counts'] ?? [];
        $assessmentCount = is_array($counts) && is_numeric($counts['assessments'] ?? null)
            ? (int) $counts['assessments']
            : count($input->payload()['assessments'] ?? []);
        return $assessmentCount >= 4 ? 'high' : 'low';
    }
}
