<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\BoundProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapEditorDraft;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;

final class ModelRoadmapRefinementEngine
{
    public function __construct(
        private readonly RoadmapProvider $provider,
        private readonly RoadmapRefinementPromptRegistry $prompts,
        private readonly RecommendationRateLimiter $rateLimiter,
        private readonly RecommendationConfig $config,
        private readonly ProviderConsentGate $consentGate,
    ) {}

    /** @return array{draft:RoadmapEditorDraft,provider_request_id:?string,response_hash:string,prompt_version:string} */
    public function refine(RoadmapEditorDraft $draft, RecommendationInput $input, RecommendationContext $context): array
    {
        $studentId = trim((string) $context->studentId());
        if (!$this->config->enabled() || $studentId === '') {
            throw new RoadmapRefinementUnavailable('model_disabled');
        }
        if (!$this->rateLimiter->acquire($studentId)->allowed()) {
            throw new RoadmapRefinementUnavailable('rate_limited');
        }
        try {
            $request = $this->prompts->create($draft, $input, $context);
        } catch (\Throwable) {
            throw new RoadmapRefinementUnavailable('invalid_request');
        }

        try {
            $response = $this->provider->generate(
                $request,
                new BoundProviderAttemptAuthorizer($this->consentGate, $studentId, $input, $context),
            );
        } catch (ProviderConsentDenied $exception) {
            $reason = in_array($exception->reason(), ['consent_revoked', 'consent_missing', 'consent_changed'], true)
                ? $exception->reason()
                : 'consent_changed';
            throw new RoadmapRefinementUnavailable($reason);
        } catch (\Throwable) {
            throw new RoadmapRefinementUnavailable('provider_unavailable');
        }
        if (!$response->isSuccess()) {
            $reason = (string) $response->errorCode();
            if (!in_array($reason, ['rate_limited', 'provider_unavailable', 'provider_rejected', 'consent_revoked', 'consent_missing', 'consent_changed'], true)) {
                $reason = 'provider_unavailable';
            }
            throw new RoadmapRefinementUnavailable($reason);
        }

        try {
            $candidate = RoadmapEditorDraft::fromArray($response->payload());
            $draft->assertSameStructure($candidate);
            $responseHash = (string) $response->responseHash();
            if (preg_match('/\A[a-f0-9]{64}\z/', $responseHash) !== 1) {
                throw new \RuntimeException('Missing refinement response hash.');
            }
        } catch (\Throwable) {
            throw new RoadmapRefinementUnavailable('invalid_refinement_contract');
        }

        return [
            'draft' => $candidate,
            'provider_request_id' => $response->providerRequestId(),
            'response_hash' => $responseHash,
            'prompt_version' => $request->promptVersion(),
        ];
    }
}
