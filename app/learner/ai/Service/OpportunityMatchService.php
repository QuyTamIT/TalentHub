<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityMatch;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Model\ModelOpportunityMatchEngine;
use TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry;
use TalentHub\Learner\Ai\Persistence\OpportunityMatchRepository;

/**
 * Orchestrates the learner Top 3 opportunity matching capability. Consent,
 * snapshot/profile, candidate normalization, deterministic structured
 * scoring, Gemini analysis, retry, stale fallback and persistence are all
 * driven from here. The final 70/30 score is composed exclusively through
 * OpportunityScore::withGeminiScore()->finalScore().
 */
final class OpportunityMatchService
{
    private const MALFORMED_MARKERS = [
        'requires exactly three items',
        'validator expects structured items',
        'carries unsupported properties',
        'is missing required key',
        'must be a string',
        'must not be empty',
        'must be an array',
        'entries must be strings',
        'must cite at least one evidence reference',
        'must be an integer within 0..100',
        'is too short to be project-specific',
    ];

    private readonly DateTimeImmutable $clock;

    /** @var \Closure(string):ConsentDecision */
    private readonly \Closure $decisionResolver;

    /** @var \Closure(string):\TalentHub\Learner\Ai\Domain\RecommendationInput */
    private readonly \Closure $inputBuilder;

    /** @var \Closure(string):list<array<string,mixed>> */
    private readonly \Closure $candidateEvidenceSupplier;

    /** @var \Closure(LearnerOpportunityProfile,OpportunityCandidate):OpportunityScore */
    private readonly \Closure $scorer;

    /**
     * @param callable(string):ConsentDecision $decisionResolver
     * @param callable(string):\TalentHub\Learner\Ai\Domain\RecommendationInput $inputBuilder
     * @param callable(string):list<array<string,mixed>> $candidateEvidenceSupplier
     * @param callable(LearnerOpportunityProfile,OpportunityCandidate):OpportunityScore $scorer
     */
    public function __construct(
        private readonly OpportunityMatchRepository $repository,
        callable $decisionResolver,
        callable $inputBuilder,
        callable $candidateEvidenceSupplier,
        callable $scorer,
        private readonly ?ModelOpportunityMatchEngine $engine,
        ?DateTimeImmutable $clock = null,
    ) {
        $this->decisionResolver = \Closure::fromCallable($decisionResolver);
        $this->inputBuilder = \Closure::fromCallable($inputBuilder);
        $this->candidateEvidenceSupplier = \Closure::fromCallable($candidateEvidenceSupplier);
        $this->scorer = \Closure::fromCallable($scorer);
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array<string,mixed> */
    public function latest(string $studentId): array
    {
        try {
            $decision = ($this->decisionResolver)($studentId);
        } catch (Throwable) {
            return $this->response('consent_required', []);
        }
        if (!$decision instanceof ConsentDecision || !$decision->permitsAllRequiredScopes()) {
            return $this->response('consent_required', []);
        }

        try {
            $profile = LearnerOpportunityProfile::fromInput(($this->inputBuilder)($studentId));
        } catch (Throwable) {
            return $this->response('insufficient_data', []);
        }
        if ($this->profileIsThin($profile)) {
            return $this->response('insufficient_data', []);
        }

        try {
            $candidates = $this->eligibleCandidates($profile, ($this->candidateEvidenceSupplier)($studentId));
        } catch (Throwable) {
            return $this->response('not_generated', []);
        }
        if (count($candidates) < 3) {
            return $this->response('catalog_insufficient', []);
        }

        $activeCatalogIds = array_map(static fn (OpportunityCandidate $candidate): string => $candidate->catalogId(), $candidates);
        $run = $this->repository->latestValid($studentId, $activeCatalogIds);
        if ($run === null) {
            return $this->response('not_generated', []);
        }
        return $this->mapReady($run);
    }

    /** @return array<string,mixed> */
    public function generate(string $studentId, string $requestId, string $idempotencyKey): array
    {
        try {
            $decision = ($this->decisionResolver)($studentId);
        } catch (Throwable) {
            return $this->response('consent_required', []);
        }
        if (!$decision instanceof ConsentDecision || !$decision->permitsAllRequiredScopes()) {
            return $this->response('consent_required', []);
        }

        try {
            $input = ($this->inputBuilder)($studentId);
            $profile = LearnerOpportunityProfile::fromInput($input);
        } catch (Throwable) {
            return $this->response('insufficient_data', []);
        }
        if ($this->profileIsThin($profile)) {
            return $this->response('insufficient_data', []);
        }

        try {
            $candidates = $this->eligibleCandidates($profile, ($this->candidateEvidenceSupplier)($studentId));
        } catch (Throwable) {
            return $this->response('catalog_insufficient', []);
        }
        if (count($candidates) < 3) {
            return $this->response('catalog_insufficient', []);
        }
        $scored = [];
        $scoredCandidates = [];
        foreach ($candidates as $candidate) {
            try {
                $scored[$candidate->catalogId()] = ($this->scorer)($profile, $candidate);
                $scoredCandidates[] = $candidate;
            } catch (\DomainException $exception) {
                if ($exception->getMessage() !== 'candidate_ineligible') {
                    return $this->response('provider_unavailable', []);
                }
            } catch (Throwable) {
                return $this->response('provider_unavailable', []);
            }
        }
        if (count($scoredCandidates) < 3) {
            return $this->response('catalog_insufficient', []);
        }
        $activeCatalogIds = array_map(static fn (OpportunityCandidate $candidate): string => $candidate->catalogId(), $scoredCandidates);
        $allowList = $this->sortAndSlice($scoredCandidates, $scored);

        if ($this->engine === null) {
            return $this->response('provider_unavailable', []);
        }

        $context = new RecommendationContext(
            $decision->allowedScopes(),
            $requestId,
            $idempotencyKey,
            $studentId,
            $decision->decisionHash(),
            $decision->policyVersion(),
        );

        try {
            $pending = $this->repository->createPendingRun($studentId, $input, $context);
        } catch (Throwable) {
            return $this->response('provider_unavailable', []);
        }
        if (($pending['reused'] ?? false) === true) {
            $cached = $this->repository->latestValid($studentId, $activeCatalogIds);
            if (($pending['status'] ?? null) === 'completed' && $cached !== null) {
                return $this->mapReady($cached);
            }
            if (($pending['status'] ?? null) === 'failed'
                && ($pending['safeErrorCode'] ?? null) === 'provider_unavailable'
                && $cached !== null) {
                return $this->mapStale($cached);
            }
            return $this->response('provider_unavailable', []);
        }

        try {
            $matches = $this->runEngine($profile, $allowList, $scored, $context);
        } catch (Throwable $exception) {
            $this->failPending($studentId, $pending, $exception);
            if (self::isProviderFailure($exception)) {
                $stale = $this->repository->latestValid($studentId, $activeCatalogIds);
                if ($stale !== null && ($stale['status'] ?? null) === 'completed') {
                    return $this->mapStale($stale);
                }
            }
            return $this->response('provider_unavailable', []);
        }

        $ranked = $this->composeFinalRanks($matches, $scored, $allowList);
        try {
            $run = $this->repository->completeRun($studentId, (string) ($pending['runId'] ?? ''), $ranked);
        } catch (Throwable $exception) {
            $this->failPending($studentId, $pending, $exception);
            return $this->response('provider_unavailable', []);
        }
        return $this->mapReady($run);
    }

    /** @param list<OpportunityCandidate> $candidates @return list<OpportunityCandidate> */
    private function sortAndSlice(array $candidates, array $scored): array
    {
        usort($candidates, static function (OpportunityCandidate $left, OpportunityCandidate $right) use ($scored): int {
            $leftScore = $scored[$left->catalogId()]->structuredScore();
            $rightScore = $scored[$right->catalogId()]->structuredScore();
            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }
            $deadlineComparison = self::compareDeadline($left->deadline(), $right->deadline());
            if ($deadlineComparison !== 0) {
                return $deadlineComparison;
            }
            return strnatcmp($left->catalogId(), $right->catalogId());
        });
        return array_slice($candidates, 0, OpportunityMatchPromptRegistry::MAX_CANDIDATES);
    }

    private static function compareDeadline(?DateTimeImmutable $left, ?DateTimeImmutable $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return 1;
        }
        if ($right === null) {
            return -1;
        }
        return $left <=> $right;
    }

    /**
     * @param list<array<string,mixed>> $rawMatches
     * @param array<string,OpportunityScore> $scored
     * @param list<OpportunityCandidate> $allowList
     * @return list<OpportunityMatch>
     */
    private function composeFinalRanks(array $rawMatches, array $scored, array $allowList): array
    {
        $composed = [];
        foreach ($rawMatches as $match) {
            if (!$match instanceof OpportunityMatch) {
                continue;
            }
            $catalogId = $match->candidate()->catalogId();
            $structured = $scored[$catalogId] ?? null;
            if ($structured === null) {
                continue;
            }
            $composed[] = $match->withScore($structured->withGeminiScore($match->geminiScore()));
        }

        $preGeminiOrder = [];
        foreach ($allowList as $index => $candidate) {
            $preGeminiOrder[$candidate->catalogId()] = $index;
        }
        usort($composed, static function (OpportunityMatch $left, OpportunityMatch $right) use ($preGeminiOrder): int {
            $leftFinal = $left->score()->finalScore();
            $rightFinal = $right->score()->finalScore();
            if ($leftFinal !== $rightFinal) {
                return $rightFinal <=> $leftFinal;
            }
            return $preGeminiOrder[$left->candidate()->catalogId()] <=> $preGeminiOrder[$right->candidate()->catalogId()];
        });

        return array_values($composed);
    }

    /** @param array<string,mixed> $pending */
    private function failPending(string $studentId, array $pending, Throwable $exception): void
    {
        try {
            $safeCode = self::isProviderFailure($exception) ? 'provider_unavailable' : 'engine_failure';
            $this->repository->failRun($studentId, (string) ($pending['runId'] ?? ''), $safeCode);
        } catch (Throwable) {
            // The outward response remains the safe provider_unavailable state.
        }
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function mapReady(array $run): array
    {
        return [
            'state' => 'ready_model',
            'items' => $this->mapItems($run['items'] ?? []),
        ];
    }

    /** @param array<string,mixed> $run @return array<string,mixed> */
    private function mapStale(array $run): array
    {
        return [
            'state' => 'stale_model',
            'items' => $this->mapItems($run['items'] ?? []),
        ];
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function mapItems(array $items): array
    {
        $mapped = [];
        foreach ($items as $item) {
            $analysis = $this->decodeJson($item['analysisJson'] ?? null);
            $action = $this->decodeJson($item['actionJson'] ?? null);
            $mapped[] = [
                'catalog_id' => (string) ($item['catalogId'] ?? ''),
                'rank' => isset($item['rankPosition']) ? (int) $item['rankPosition'] : null,
                'match_score' => isset($item['matchScore']) ? (int) $item['matchScore'] : null,
                'structured_score' => isset($item['structuredScore']) ? (int) $item['structuredScore'] : null,
                'gemini_score' => isset($item['geminiScore']) ? (int) $item['geminiScore'] : null,
                'why_fit' => is_array($analysis) ? (string) ($analysis['why_fit'] ?? '') : '',
                'matched_skills' => is_array($analysis) && is_array($analysis['matched_skill_codes'] ?? null) ? array_values($analysis['matched_skill_codes']) : [],
                'missing_skills' => is_array($analysis) && is_array($analysis['missing_skill_codes'] ?? null) ? array_values($analysis['missing_skill_codes']) : [],
                'expected_outcomes' => is_array($analysis) && is_array($analysis['expected_outcome_codes'] ?? null) ? array_values($analysis['expected_outcome_codes']) : [],
                'evidence' => is_array($analysis) && is_array($analysis['evidence_ref_ids'] ?? null) ? array_values($analysis['evidence_ref_ids']) : [],
                'title' => (string) ($item['title'] ?? ''),
                'summary' => (string) ($item['summary'] ?? ''),
                'canonical_url' => is_array($action) ? (string) ($action['url'] ?? '') : '',
            ];
        }
        return $mapped;
    }

    /** @return array<string,mixed> */
    private function response(string $state, array $items): array
    {
        return ['state' => $state, 'items' => $items];
    }

    /** @param list<array<string,mixed>> $evidence @return list<OpportunityCandidate> */
    private function eligibleCandidates(LearnerOpportunityProfile $profile, array $evidence): array
    {
        $now = $this->clock;
        $candidates = [];
        foreach ($evidence as $entry) {
            try {
                $candidate = OpportunityCandidate::fromEvidence(is_array($entry) ? $entry : []);
            } catch (Throwable) {
                continue;
            }
            if ($candidate->isEligibleFor($profile, $now)) {
                $candidates[] = $candidate;
            }
        }
        return $candidates;
    }

    private static function isProviderFailure(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'Opportunity match provider returned a failure');
    }

    private static function isMalformedOutput(Throwable $exception): bool
    {
        $message = $exception->getMessage();
        foreach (self::MALFORMED_MARKERS as $marker) {
            if (str_contains($message, $marker)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,OpportunityScore> $scored @param list<OpportunityCandidate> $allowList @return list<OpportunityMatch> */
    private function runEngine(LearnerOpportunityProfile $profile, array $allowList, array $scored, RecommendationContext $context): array
    {
        $attempts = 0;
        while (true) {
            try {
                return $this->engine->generate($profile, $allowList, $scored, $context);
            } catch (Throwable $exception) {
                if ($attempts === 0 && self::isMalformedOutput($exception)) {
                    $attempts++;
                    continue;
                }
                throw $exception;
            }
        }
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $encoded): array
    {
        if (!is_string($encoded) || $encoded === '') {
            return [];
        }
        try {
            $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function profileIsThin(LearnerOpportunityProfile $profile): bool
    {
        return $profile->skills() === [] && $profile->assessmentDimensions() === [];
    }
}
