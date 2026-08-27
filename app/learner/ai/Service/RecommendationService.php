<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use Closure;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;

final class RecommendationService
{
    /** @var Closure(string):bool */
    private readonly Closure $authorizer;

    /** @var Closure(string):list<string> */
    private readonly Closure $scopeResolver;

    /** @var Closure(string,list<string>):RecommendationInput */
    private readonly Closure $snapshotBuilder;

    /** @var Closure(RecommendationInput):DataQualityResult */
    private readonly Closure $qualityGate;

    /** @var Closure(RecommendationInput):bool */
    private readonly Closure $snapshotFreshness;

    /**
     * @param callable(string):bool $authorizer
     * @param callable(string):list<string> $scopeResolver
     * @param callable(string,list<string>):RecommendationInput $snapshotBuilder
     * @param callable(RecommendationInput):DataQualityResult $qualityGate
     * @param callable(RecommendationInput):bool $snapshotFreshness
     */
    public function __construct(
        private readonly RecommendationRepository $repository,
        private readonly RecommendationEngine $engine,
        private readonly RecommendationResultValidator $validator,
        private readonly RecommendationResponseMapper $mapper,
        callable $authorizer,
        callable $scopeResolver,
        callable $snapshotBuilder,
        callable $qualityGate,
        callable $snapshotFreshness,
        private readonly ?RecommendationEngine $modelEngine = null,
        private readonly ?RecommendationConfig $modelConfig = null,
        private readonly ?RecommendationRolloutSelector $rolloutSelector = null,
        ?AiAvailabilityPolicy $availabilityPolicy = null,
    ) {
        $this->authorizer = Closure::fromCallable($authorizer);
        $this->scopeResolver = Closure::fromCallable($scopeResolver);
        $this->snapshotBuilder = Closure::fromCallable($snapshotBuilder);
        $this->qualityGate = Closure::fromCallable($qualityGate);
        $this->snapshotFreshness = Closure::fromCallable($snapshotFreshness);
        $this->availabilityPolicy = $availabilityPolicy;
    }

    private readonly ?AiAvailabilityPolicy $availabilityPolicy;

    /** @return array<string,mixed>|null */
    public function latest(string $studentId): ?array
    {
        try {
            if (!(($this->authorizer)($studentId))) {
                return null;
            }
            $resolvedConsent = ($this->scopeResolver)($studentId);
            $scopes = $this->normalizeScopes($resolvedConsent);
            if ($resolvedConsent instanceof ConsentDecision) {
                $missingScopes = array_values(array_diff(ConsentDecision::REQUIRED_SCOPES, $scopes));
                if ($missingScopes !== []) {
                    sort($missingScopes, SORT_STRING);
                    return $this->mapper->quality(new DataQualityResult('consent_required', $missingScopes));
                }
            }
            $run = $this->repository->latestForStudent($studentId);
        } catch (\Throwable) {
            return null;
        }
        return $run === null ? null : $this->mapper->run($run);
    }

    /** @return array<string,mixed> */
    public function generate(string $studentId, string $requestId, string $idempotencyKey, ?callable $leaseGuard = null, bool $propagateProviderRetry = false): array
    {
        try {
            if (!(($this->authorizer)($studentId))) {
                return $this->mapper->forbidden();
            }
        } catch (\Throwable) {
            return $this->mapper->forbidden();
        }

        try {
            $resolvedConsent = ($this->scopeResolver)($studentId);
            $scopes = $this->normalizeScopes($resolvedConsent);
            $input = ($this->snapshotBuilder)($studentId, $scopes);
            $snapshotCurrent = (bool) (($this->snapshotFreshness)($input));
            $quality = ($this->qualityGate)($input);
            if (!$quality instanceof DataQualityResult) {
                return $this->mapper->sourceUnavailable();
            }
        } catch (\Throwable) {
            return $this->mapper->sourceUnavailable();
        }

        try {
            $active = $this->repository->latestForStudent($studentId);
        } catch (\Throwable) {
            $active = null;
        }
        $hasActiveModel = is_array($active)
            && ($active['engineType'] ?? null) === 'model'
            && ($active['status'] ?? null) === 'completed';
        if (!$snapshotCurrent) {
            if ($hasActiveModel && $this->modelConfig !== null) {
                $decision = $this->availability()->decide(
                    $studentId, $this->modelConfig, $scopes, false, true, false,
                );
                if ($decision->canServeStaleModel()) return $this->mapper->staleModel($active);
            }
            return $this->mapper->staleSnapshot();
        }

        if ($quality->state() !== 'ready') {
            return $this->mapper->quality($quality);
        }

        $context = new RecommendationContext(
            $scopes,
            $requestId,
            $idempotencyKey,
            $studentId,
            $resolvedConsent instanceof ConsentDecision ? $resolvedConsent->decisionHash() : null,
            $resolvedConsent instanceof ConsentDecision ? $resolvedConsent->policyVersion() : null,
            $propagateProviderRetry,
        );
        if ($hasActiveModel) {
            return $this->refreshActiveModelOrRetain($studentId, $input, $context, $active, $leaseGuard, $propagateProviderRetry);
        }
        try {
            $this->guardLease($leaseGuard);
            $pending = $this->repository->createPendingRun($studentId, $input, $context);
        } catch (\Throwable) {
            return $this->mapper->sourceUnavailable();
        }
        if (($pending['reused'] ?? false) === true) {
            return $this->mapper->pending($pending);
        }

        try {
            $result = $this->engine->generate($input, $context);
            $this->validator->validate($result);
            return $this->visibleModelOrRule($studentId, $input, $context, $result, $pending, $leaseGuard, $propagateProviderRetry);
        } catch (\Throwable $exception) {
            try {
                $this->guardLease($leaseGuard);
                $safeCode = $exception instanceof ProviderConsentDenied ? $exception->reason() : 'engine_failure';
                $this->repository->failRun($studentId, (string) ($pending['runId'] ?? ''), $safeCode);
            } catch (\Throwable) {
                // The outward response remains the same safe failure state.
            }
            if ($propagateProviderRetry && $exception instanceof ProviderRetryAfterException) throw $exception;
            if ($exception instanceof ProviderConsentDenied) {
                return $this->consentUnavailable($studentId, $exception->reason());
            }
            return $this->mapper->engineFailure();
        }
    }

    /** @param mixed $scopes @return list<string> */
    private function normalizeScopes(mixed $scopes): array
    {
        if ($scopes instanceof ConsentDecision) {
            $scopes = $scopes->allowedScopes();
        }
        if (!is_array($scopes)) {
            throw new \RuntimeException('Recommendation consent scopes are unavailable.');
        }
        $normalized = [];
        foreach ($scopes as $scope) {
            if (is_string($scope) && in_array($scope, ['assessment', 'skills', 'activity', 'evaluation'], true)) {
                $normalized[$scope] = true;
            }
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    private function visibleModelOrRule(string $studentId, RecommendationInput $input, RecommendationContext $ruleContext, \TalentHub\Learner\Ai\Domain\RecommendationResult $ruleResult, array $pending, ?callable $leaseGuard = null, bool $propagateProviderRetry = false): array
    {
        if ($this->modelEngine === null || $this->modelConfig === null) {
            $this->assertConsentCurrent($studentId, $ruleContext);
            $this->guardLease($leaseGuard);
            return $this->mapper->run($this->repository->completeRun($studentId, (string) $pending['runId'], $ruleResult));
        }
        $decision = $this->availability()->decide(
            $studentId,
            $this->modelConfig,
            $ruleContext->allowedScopes(),
            true,
            false,
            true,
            null,
            $this->rolloutSelector?->rolloutEvidence(),
        );
        if (!$decision->canShowModel()) {
            $this->assertConsentCurrent($studentId, $ruleContext);
            $this->guardLease($leaseGuard);
            $ruleRun = $this->repository->completeRun($studentId, (string) $pending['runId'], $ruleResult);
            return $this->mapper->readyRule($ruleRun, $decision->reason());
        }
        $modelContext = new RecommendationContext(
            $ruleContext->allowedScopes(),
            $ruleContext->requestId(),
            'model-' . hash('sha256', $input->contentHash() . ':' . (string) $ruleContext->idempotencyKey()),
            $studentId,
            $ruleContext->consentDecisionHash(),
            $ruleContext->consentPolicyVersion(),
            $ruleContext->shouldPropagateProviderRetry(),
        );
        try {
            $modelResult = $this->modelEngine->generate($input, $modelContext);
            $this->validator->validate($modelResult);
            $this->assertConsentCurrent($studentId, $modelContext);
            $this->guardLease($leaseGuard);
            $saved = $this->repository->completeRun($studentId, (string) ($pending['runId'] ?? ''), $modelResult);
            return $modelResult->engineType() === 'model'
                ? $this->mapper->run($saved)
                : $this->mapper->readyRule($saved, $modelResult->fallbackReason() ?? 'provider_unavailable');
        } catch (\Throwable $exception) {
            if ($exception instanceof ProviderConsentDenied) throw $exception;
            if ($propagateProviderRetry && $exception instanceof ProviderRetryAfterException) throw $exception;
            $this->assertConsentCurrent($studentId, $ruleContext);
            $this->guardLease($leaseGuard);
            $ruleRun = $this->repository->completeRun($studentId, (string) ($pending['runId'] ?? ''), $ruleResult);
            return $this->mapper->readyRule($ruleRun, 'provider_unavailable');
        }
    }

    /** @param array<string,mixed> $active @return array<string,mixed> */
    private function refreshActiveModelOrRetain(
        string $studentId,
        RecommendationInput $input,
        RecommendationContext $context,
        array $active,
        ?callable $leaseGuard = null,
        bool $propagateProviderRetry = false,
    ): array {
        if ($this->modelEngine === null || $this->modelConfig === null) {
            return $this->mapper->sourceUnavailable();
        }
        $decision = $this->availability()->decide(
            $studentId,
            $this->modelConfig,
            $context->allowedScopes(),
            true,
            true,
            true,
            null,
            $this->rolloutSelector?->rolloutEvidence(),
        );
        if (!$decision->canShowModel()) {
            return $this->mapper->sourceUnavailable();
        }

        try {
            $ruleResult = $this->engine->generate($input, $context);
            $this->validator->validate($ruleResult);
            $modelContext = new RecommendationContext(
                $context->allowedScopes(),
                $context->requestId(),
                'model-' . hash('sha256', $input->contentHash() . ':' . (string) $context->idempotencyKey()),
                $studentId,
                $context->consentDecisionHash(),
                $context->consentPolicyVersion(),
                $context->shouldPropagateProviderRetry(),
            );
            $modelResult = $this->modelEngine->generate($input, $modelContext);
            if ($modelResult->engineType() !== 'model') return $this->mapper->staleModel($active);
            $this->validator->validate($modelResult);
            $this->assertConsentCurrent($studentId, $modelContext);
            $this->guardLease($leaseGuard);
            $pending = $this->repository->createPendingRun($studentId, $input, $modelContext);
            if (($pending['reused'] ?? false) === true) return $this->mapper->staleModel($active);
            $this->guardLease($leaseGuard);
            $saved = $this->repository->completeRun($studentId, (string) ($pending['runId'] ?? ''), $modelResult);
            return $this->mapper->run($saved);
        } catch (\Throwable $exception) {
            if ($exception instanceof ProviderConsentDenied) return $this->consentUnavailable($studentId, $exception->reason());
            if ($propagateProviderRetry && $exception instanceof ProviderRetryAfterException) throw $exception;
            return $this->mapper->staleModel($active);
        }
    }

    private function assertConsentCurrent(string $studentId, RecommendationContext $context): void
    {
        $resolved = ($this->scopeResolver)($studentId);
        $scopes = $this->normalizeScopes($resolved);
        if ($scopes !== $context->allowedScopes()) throw new ProviderConsentDenied('consent_changed');
        if (!$resolved instanceof ConsentDecision) return;
        if (!$resolved->permitsScopes($context->allowedScopes())) {
            throw new ProviderConsentDenied($resolved->denialReason() ?? 'consent_missing');
        }
        if ($context->consentPolicyVersion() !== null && !hash_equals($context->consentPolicyVersion(), $resolved->policyVersion())) {
            throw new ProviderConsentDenied('consent_changed');
        }
        if ($context->consentDecisionHash() !== null && !hash_equals($context->consentDecisionHash(), $resolved->decisionHash())) {
            throw new ProviderConsentDenied($resolved->denialReason() ?? 'consent_changed');
        }
    }

    /** @return array<string,mixed> */
    private function consentUnavailable(string $studentId, string $reason): array
    {
        try {
            $scopes = $this->normalizeScopes(($this->scopeResolver)($studentId));
            $missing = array_values(array_diff(ConsentDecision::REQUIRED_SCOPES, $scopes));
            sort($missing, SORT_STRING);
            return $this->mapper->quality(new DataQualityResult($reason === 'consent_changed' ? 'consent_changed' : 'consent_required', $missing));
        } catch (\Throwable) {
            return $this->mapper->sourceUnavailable();
        }
    }

    private function availability(): AiAvailabilityPolicy
    {
        return $this->availabilityPolicy ?? new AiAvailabilityPolicy();
    }

    private function guardLease(?callable $leaseGuard): void
    {
        if ($leaseGuard !== null && !$leaseGuard()) throw new \RuntimeException('refresh_lease_lost');
    }
}
