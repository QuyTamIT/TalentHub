<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use Closure;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

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
    ) {
        $this->authorizer = Closure::fromCallable($authorizer);
        $this->scopeResolver = Closure::fromCallable($scopeResolver);
        $this->snapshotBuilder = Closure::fromCallable($snapshotBuilder);
        $this->qualityGate = Closure::fromCallable($qualityGate);
        $this->snapshotFreshness = Closure::fromCallable($snapshotFreshness);
    }

    /** @return array<string,mixed>|null */
    public function latest(string $studentId): ?array
    {
        try {
            if (!(($this->authorizer)($studentId))) {
                return null;
            }
            $run = $this->repository->latestForStudent($studentId);
        } catch (\Throwable) {
            return null;
        }
        return $run === null ? null : $this->mapper->run($run);
    }

    /** @return array<string,mixed> */
    public function generate(string $studentId, string $requestId, string $idempotencyKey): array
    {
        try {
            if (!(($this->authorizer)($studentId))) {
                return $this->mapper->forbidden();
            }
        } catch (\Throwable) {
            return $this->mapper->forbidden();
        }

        try {
            $scopes = $this->normalizeScopes(($this->scopeResolver)($studentId));
            $input = ($this->snapshotBuilder)($studentId, $scopes);
            if (!(($this->snapshotFreshness)($input))) {
                return $this->mapper->staleSnapshot();
            }
            $quality = ($this->qualityGate)($input);
            if (!$quality instanceof DataQualityResult) {
                return $this->mapper->sourceUnavailable();
            }
        } catch (\Throwable) {
            return $this->mapper->sourceUnavailable();
        }

        if ($quality->state() !== 'ready') {
            return $this->mapper->quality($quality);
        }

        $context = new RecommendationContext($scopes, $requestId, $idempotencyKey);
        try {
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
            return $this->mapper->run($this->repository->completeRun($studentId, (string) $pending['runId'], $result));
        } catch (\Throwable) {
            try {
                $this->repository->failRun($studentId, (string) ($pending['runId'] ?? ''), 'engine_failure');
            } catch (\Throwable) {
                // The outward response remains the same safe failure state.
            }
            return $this->mapper->engineFailure();
        }
    }

    /** @param mixed $scopes @return list<string> */
    private function normalizeScopes(mixed $scopes): array
    {
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
}
