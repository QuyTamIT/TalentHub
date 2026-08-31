<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\JobMatchResult;
use TalentHub\Learner\Ai\Matching\JobMatchScorer;
use TalentHub\Learner\Ai\Matching\JobRoleResolver;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\SkillGapResolver;
use TalentHub\Learner\Ai\Model\ModelJobMatchEngine;
use TalentHub\Learner\Ai\Persistence\JobMatchRepository;

final class JobMatchingService
{
    private readonly \Closure $decisionResolver;
    private readonly \Closure $inputBuilder;
    private readonly \Closure $candidateSupplier;
    private readonly \Closure $roleSupplier;
    private readonly \Closure $activityResolver;
    private readonly DateTimeImmutable $clock;

    /**
     * @param callable(string):ConsentDecision $decisionResolver
     * @param callable(string):RecommendationInput $inputBuilder
     * @param callable(string):list<array<string,mixed>> $candidateSupplier
     * @param callable():array{status:string,roles:list<\TalentHub\Learner\Ai\Matching\CareerRoleBenchmark>} $roleSupplier
     * @param callable(string,array<string,float>):array<string,mixed> $activityResolver
     */
    public function __construct(
        private readonly JobMatchRepository $repository,
        callable $decisionResolver,
        callable $inputBuilder,
        callable $candidateSupplier,
        callable $roleSupplier,
        private readonly ?ModelJobMatchEngine $engine,
        callable $activityResolver,
        ?DateTimeImmutable $clock = null,
    ) {
        $this->decisionResolver = \Closure::fromCallable($decisionResolver);
        $this->inputBuilder = \Closure::fromCallable($inputBuilder);
        $this->candidateSupplier = \Closure::fromCallable($candidateSupplier);
        $this->roleSupplier = \Closure::fromCallable($roleSupplier);
        $this->activityResolver = \Closure::fromCallable($activityResolver);
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array<string,mixed> */
    public function latest(string $studentId): array
    {
        $prepared = $this->prepare($studentId);
        if (!isset($prepared['profile'])) return $prepared;
        $candidates = $prepared['candidates'];
        $rolesResult = ($this->roleSupplier)();
        $roles = is_array($rolesResult['roles'] ?? null) ? $rolesResult['roles'] : [];
        if (($rolesResult['status'] ?? '') !== 'ok' || $roles === []) return self::emptyResponse('benchmark_insufficient');
        $resolver = new JobRoleResolver(); $scorer = new JobMatchScorer(); $currentScores = []; $activeIds = [];
        foreach ($candidates as $candidate) {
            $resolved = $resolver->resolve($candidate, $roles); $role = $resolved['role'] ?? null;
            if (($resolved['status'] ?? '') !== 'resolved' || $role === null) continue;
            try { $score = $scorer->score($prepared['profile'], $candidate, $role)->score()->totalScore(); } catch (Throwable) { continue; }
            $activeIds[] = $candidate->catalogId(); $currentScores[$candidate->catalogId()] = $score;
        }
        $run = $this->repository->latestValid($studentId, $activeIds);
        if ($run !== null) {
            foreach ($run['items'] ?? [] as $item) {
                $id = (string) ($item['catalogId'] ?? '');
                if (!isset($currentScores[$id]) || (int) ($item['matchScore'] ?? -1) !== $currentScores[$id]) { $run = null; break; }
            }
        }
        return $run === null ? self::emptyResponse('not_generated') : $this->mapRun($run, $candidates, false);
    }

    /** @return array<string,mixed> */
    public function generate(string $studentId, string $requestId, string $idempotencyKey): array
    {
        $prepared = $this->prepare($studentId);
        if (!isset($prepared['profile'])) return $prepared;
        /** @var ConsentDecision $decision */ $decision = $prepared['decision'];
        /** @var RecommendationInput $input */ $input = $prepared['input'];
        /** @var LearnerOpportunityProfile $profile */ $profile = $prepared['profile'];
        /** @var list<OpportunityCandidate> $candidates */ $candidates = $prepared['candidates'];
        $rolesResult = ($this->roleSupplier)();
        $roles = is_array($rolesResult['roles'] ?? null) ? $rolesResult['roles'] : [];
        if (($rolesResult['status'] ?? '') !== 'ok' || $roles === []) return self::emptyResponse('benchmark_insufficient');

        $resolver = new JobRoleResolver(); $scorer = new JobMatchScorer(); $gapResolver = new SkillGapResolver();
        $matches = []; $gaps = []; $ranked = [];
        foreach ($candidates as $candidate) {
            $resolved = $resolver->resolve($candidate, $roles);
            $role = $resolved['role'] ?? null;
            if (($resolved['status'] ?? '') !== 'resolved' || $role === null) continue;
            try { $match = $scorer->score($profile, $candidate, $role); } catch (Throwable) { continue; }
            $id = $candidate->catalogId(); $matches[$id] = $match; $gaps[$id] = $gapResolver->resolve($match); $ranked[] = $candidate;
        }
        usort($ranked, static function (OpportunityCandidate $a, OpportunityCandidate $b) use ($matches): int {
            $score = $matches[$b->catalogId()]->score()->totalScore() <=> $matches[$a->catalogId()]->score()->totalScore();
            if ($score !== 0) return $score;
            $ad = $a->deadline()?->getTimestamp() ?? PHP_INT_MAX; $bd = $b->deadline()?->getTimestamp() ?? PHP_INT_MAX;
            return $ad !== $bd ? $ad <=> $bd : strcmp($a->catalogId(), $b->catalogId());
        });
        if ($ranked === []) return self::emptyResponse('benchmark_insufficient');
        $eligible = array_values(array_filter($ranked, static fn (OpportunityCandidate $candidate): bool => $matches[$candidate->catalogId()]->score()->totalScore() >= 40));
        $noMatchingJobs = $eligible === [];
        $selected = $noMatchingJobs ? [reset($ranked)] : array_slice($eligible, 0, 10);
        $activeIds = array_map(static fn (OpportunityCandidate $c): string => $c->catalogId(), $candidates);
        if ($this->engine === null) {
            try { $stale = $this->repository->latestValid($studentId, $activeIds); }
            catch (Throwable) { $stale = null; }
            return $stale === null ? self::emptyResponse('provider_unavailable') : $this->mapRun($stale, $candidates, true);
        }

        $context = new RecommendationContext($decision->allowedScopes(), $requestId, 'job-match-' . hash('sha256', $idempotencyKey), $studentId, $decision->decisionHash(), $decision->policyVersion());
        try { $pending = $this->repository->createPendingRun($studentId, $input, $context); }
        catch (Throwable) {
            try { $stale = $this->repository->latestValid($studentId, $activeIds); }
            catch (Throwable) { $stale = null; }
            return $stale === null ? self::emptyResponse('provider_unavailable') : $this->mapRun($stale, $candidates, true);
        }
        if (($pending['reused'] ?? false) === true) {
            $cached = $this->repository->latestValid($studentId, $activeIds);
            return $cached === null ? self::emptyResponse('provider_unavailable') : $this->mapRun($cached, $candidates, false);
        }

        $analyses = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $analyses = $this->engine->generate($profile, $selected, $matches, $gaps, $context);
                $expected = array_map(static fn (OpportunityCandidate $c): string => $c->catalogId(), $selected);
                $actual = array_map(static fn ($a): string => $a->catalogId(), $analyses); sort($expected); sort($actual);
                if ($actual !== $expected) throw new \InvalidArgumentException('Gemini must analyze every eligible job exactly once.');
                break;
            } catch (Throwable) {}
        }
        if ($analyses === null) {
            try { $this->repository->failRun($studentId, (string) $pending['runId'], 'provider_unavailable'); } catch (Throwable) {}
            $stale = $this->repository->latestValid($studentId, $activeIds);
            return $stale === null ? self::emptyResponse('provider_unavailable') : $this->mapRun($stale, $candidates, true);
        }

        $analysisById = []; foreach ($analyses as $analysis) $analysisById[$analysis->catalogId()] = $analysis;
        $records = [];
        foreach ($selected as $candidate) {
            $id = $candidate->catalogId(); $missingWeights = [];
            foreach ($gaps[$id]['skills_missing'] ?? [] as $skill) $missingWeights[(string) $skill['code']] = (float) ($skill['weight'] ?? 0.0);
            try { $activities = ($this->activityResolver)($studentId, $missingWeights); } catch (Throwable) { $activities = ['status' => 'no_matching_activity', 'items' => []]; }
            $records[] = ['candidate' => $candidate, 'match' => $matches[$id], 'analysis' => $analysisById[$id], 'skill_gap' => $gaps[$id], 'activities' => is_array($activities['items'] ?? null) ? $activities['items'] : []];
        }
        try { $run = $this->repository->completeRun($studentId, (string) $pending['runId'], $records, ['state' => $noMatchingJobs ? 'no_matching_jobs' : 'ready_model']); }
        catch (Throwable) {
            try { $this->repository->failRun($studentId, (string) $pending['runId'], 'persistence_failure'); } catch (Throwable) {}
            try { $stale = $this->repository->latestValid($studentId, $activeIds); }
            catch (Throwable) { $stale = null; }
            return $stale === null ? self::emptyResponse('provider_unavailable') : $this->mapRun($stale, $candidates, true);
        }
        return $this->mapRun($run, $candidates, false);
    }

    /** @return array<string,mixed> */
    private function prepare(string $studentId): array
    {
        try { $decision = ($this->decisionResolver)($studentId); }
        catch (Throwable) { return self::emptyResponse('consent_required'); }
        if (!$decision instanceof ConsentDecision || !$decision->permitsAllRequiredScopes()) return self::emptyResponse('consent_required');
        try { $input = ($this->inputBuilder)($studentId); $profile = LearnerOpportunityProfile::fromInput($input); }
        catch (Throwable) { return self::emptyResponse('insufficient_data'); }
        if ($profile->skills() === []) return self::emptyResponse('insufficient_data');
        try { $raw = ($this->candidateSupplier)($studentId); } catch (Throwable) { return self::emptyResponse('catalog_insufficient'); }
        $candidates = [];
        foreach ($raw as $evidence) {
            try { $candidate = OpportunityCandidate::fromEvidence($evidence); if ($candidate->isEligibleFor($profile, $this->clock)) $candidates[] = $candidate; } catch (Throwable) {}
        }
        if ($candidates === []) return self::emptyResponse('catalog_insufficient');
        return compact('decision', 'input', 'profile', 'candidates');
    }

    /** @param list<OpportunityCandidate> $candidates @return array<string,mixed> */
    private function mapRun(array $run, array $candidates, bool $stale): array
    {
        $candidateMap = [];
        foreach ($candidates as $candidate) $candidateMap[$candidate->catalogId()] = $candidate;
        $groups = []; $topGap = null;
        foreach ($run['items'] ?? [] as $item) {
            $id = (string) ($item['catalogId'] ?? '');
            $candidate = $candidateMap[$id] ?? null;
            if (!$candidate) continue;
            $meta = is_array($item['analysis'] ?? null) ? $item['analysis'] : [];
            $ai = is_array($meta['analysis'] ?? null) ? $meta['analysis'] : [];
            $strengthSummary = self::strengthSummary($meta);
            $enterprise = $candidate->enterpriseId() !== '' ? $candidate->enterpriseId() : 'provider:' . hash('sha256', $candidate->providerName());
            if (($run['state'] ?? '') === 'no_matching_jobs') {
                $gap = is_array($meta['skill_gap'] ?? null) ? $meta['skill_gap'] : [];
                $gap['recommended_activities'] = is_array($meta['recommended_activities'] ?? null) ? $meta['recommended_activities'] : [];
                $nearMatch = array_merge([
                    'enterprise_id' => $candidate->enterpriseId(), 'enterprise_name' => $candidate->providerName(),
                    'catalog_id' => $id, 'title' => $candidate->title(), 'url' => $candidate->canonicalUrl(),
                    'match_score' => (int) $item['matchScore'], 'match_tier' => 'low_fit',
                    'score_breakdown' => $meta['score_breakdown'] ?? [], 'analysis' => $ai['analysis'] ?? '',
                    'strength_skill_codes' => $ai['strength_skill_codes'] ?? [], 'gap_skill_codes' => $ai['gap_skill_codes'] ?? [],
                    'gap_explanations' => $ai['gap_explanations'] ?? [], 'evidence_ref_ids' => $ai['evidence_ref_ids'] ?? [],
                ], $strengthSummary);
                return ['state'=>'no_matching_jobs','analysis_origin'=>'gemini','score_origin'=>'deterministic_40_35_25','enterprise_groups'=>[],'near_match'=>$nearMatch,'skill_gap'=>$gap,'run_id'=>$run['runId']??null,'freshness_status'=>$stale?'stale':'fresh'];
            }
            if (!isset($groups[$enterprise])) $groups[$enterprise] = ['enterprise_id'=>$candidate->enterpriseId(),'enterprise_name'=>$candidate->providerName(),'positions'=>[]];
            $groups[$enterprise]['positions'][] = array_merge([
                'catalog_id'=>$id,'title'=>$candidate->title(),'url'=>$candidate->canonicalUrl(),
                'match_score'=>(int)$item['matchScore'],'match_tier'=>$meta['score_breakdown']['tier']??'developing_fit',
                'score_breakdown'=>$meta['score_breakdown']??[],'analysis'=>$ai['analysis']??'',
                'strength_skill_codes'=>$ai['strength_skill_codes']??[],'gap_skill_codes'=>$ai['gap_skill_codes']??[],
                'gap_explanations'=>$ai['gap_explanations']??[],'evidence_ref_ids'=>$ai['evidence_ref_ids']??[],
            ], $strengthSummary);
            if ($topGap === null) {
                $topGap = is_array($meta['skill_gap'] ?? null) ? $meta['skill_gap'] : [];
                $topGap['recommended_activities'] = is_array($meta['recommended_activities'] ?? null) ? $meta['recommended_activities'] : [];
            }
        }
        if ($groups === []) return self::emptyResponse('not_generated');
        return ['state'=>$stale?'stale_model':'ready_model','analysis_origin'=>'gemini','score_origin'=>'deterministic_40_35_25','enterprise_groups'=>array_values($groups),'skill_gap'=>$topGap??[],'run_id'=>$run['runId']??null,'freshness_status'=>$stale?'stale':'fresh'];
    }

    /** @return array{strength_details:list<array{code:string,label:string,current_score:int,target_score:int}>,met_skill_count:int,benchmark_skill_count:int} */
    private static function strengthSummary(array $meta): array
    {
        $gap = is_array($meta['skill_gap'] ?? null) ? $meta['skill_gap'] : [];
        $metRows = is_array($gap['skills_met'] ?? null) ? array_slice($gap['skills_met'], 0, 20) : [];
        $missingRows = is_array($gap['skills_missing'] ?? null) ? array_slice($gap['skills_missing'], 0, 20) : [];
        $details = []; $benchmarkCodes = [];
        foreach ($metRows as $row) {
            if (!is_array($row)) continue;
            $code = is_string($row['code'] ?? null) ? trim($row['code']) : '';
            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $current = filter_var($row['current_score'] ?? null, FILTER_VALIDATE_INT);
            $target = filter_var($row['target_score'] ?? null, FILTER_VALIDATE_INT);
            if ($code === '' || preg_match('/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/', $code) !== 1 || $label === '' || mb_strlen($label, 'UTF-8') > 100
                || $current === false || $target === false || $current < 0 || $current > 100 || $target < 0 || $target > 100 || $current < $target
                || isset($benchmarkCodes[$code])) continue;
            $benchmarkCodes[$code] = true;
            $details[] = ['code'=>$code,'label'=>$label,'current_score'=>$current,'target_score'=>$target];
        }
        foreach ($missingRows as $row) {
            if (!is_array($row)) continue;
            $code = is_string($row['code'] ?? null) ? trim($row['code']) : '';
            if ($code !== '' && preg_match('/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/', $code) === 1) $benchmarkCodes[$code] = true;
        }
        return ['strength_details'=>$details,'met_skill_count'=>count($details),'benchmark_skill_count'=>count($benchmarkCodes)];
    }

    /** @return array<string,mixed> */
    private static function emptyResponse(string $state): array{return ['state'=>$state,'analysis_origin'=>null,'score_origin'=>'deterministic_40_35_25','enterprise_groups'=>[],'skill_gap'=>[]];}
}
