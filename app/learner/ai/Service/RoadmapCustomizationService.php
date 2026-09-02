<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use Closure;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Domain\RoadmapEditorDraft;
use TalentHub\Learner\Ai\Model\ModelRoadmapRefinementEngine;
use TalentHub\Learner\Ai\Model\RoadmapRefinementUnavailable;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;

final class RoadmapCustomizationService
{
    private readonly Closure $authorizer;
    private readonly Closure $consentResolver;
    private readonly Closure $snapshotBuilder;

    public function __construct(
        private readonly RoadmapRepository $roadmaps,
        private readonly ?ModelRoadmapRefinementEngine $engine,
        private readonly RecommendationConfig $config,
        callable $authorizer,
        callable $consentResolver,
        callable $snapshotBuilder,
    ) {
        $this->authorizer = Closure::fromCallable($authorizer);
        $this->consentResolver = Closure::fromCallable($consentResolver);
        $this->snapshotBuilder = Closure::fromCallable($snapshotBuilder);
    }

    /** @param array<string,mixed> $draft @return array<string,mixed> */
    public function refine(string $studentId, string $roadmapId, int $baseVersion, array $draft, string $requestId, string $idempotencyKey): array
    {
        try {
            if (!(($this->authorizer)($studentId))) return ['state'=>'forbidden'];
            $base = $this->activeBase($studentId, $roadmapId, $baseVersion);
            $baseDraft = $this->editableDraft($base);
            $learnerDraft = RoadmapEditorDraft::fromArray($draft, $baseDraft);
        } catch (\InvalidArgumentException) {
            return ['state'=>'invalid_draft'];
        } catch (\RuntimeException) {
            return ['state'=>'stale_base'];
        } catch (\Throwable) {
            return ['state'=>'refinement_unavailable'];
        }
        if ($this->engine === null || !$this->config->enabled()) return ['state'=>'refinement_unavailable'];

        try {
            $decision = ($this->consentResolver)($studentId);
            if (!$decision instanceof ConsentDecision) throw new \RuntimeException('Consent decision unavailable.');
            $decision = $decision->withServiceScopes(['assessment']);
            $input = ($this->snapshotBuilder)($studentId, $decision->allowedScopes());
            if (!$input instanceof RecommendationInput) throw new \RuntimeException('Roadmap snapshot unavailable.');
            $context = new RecommendationContext(
                $decision->allowedScopes(), $requestId, $idempotencyKey, $studentId,
                $decision->decisionHash(), $decision->policyVersion(),
            );
            $refined = $this->engine->refine($learnerDraft, $input, $context);
            $providerRequestId = $refined['provider_request_id'] ?? null;
            $providerRequestId = is_string($providerRequestId) && trim($providerRequestId) !== ''
                ? trim($providerRequestId)
                : null;
            return $this->roadmaps->storeRefinementPreview(
                $studentId, $roadmapId, $baseVersion, $learnerDraft, $refined['draft'], [
                    'provider'=>(string)$this->config->provider(), 'model_version'=>(string)$this->config->model(),
                    'prompt_version'=>$refined['prompt_version'], 'provider_request_id'=>$providerRequestId,
                    'response_hash'=>$refined['response_hash'],
                ],
            );
        } catch (RoadmapRefinementUnavailable $exception) {
            return ['state'=>$exception->reason() === 'invalid_refinement_contract' ? 'invalid_refinement_contract' : 'refinement_unavailable'];
        } catch (\InvalidArgumentException) {
            return ['state'=>'invalid_draft'];
        } catch (\Throwable) {
            return ['state'=>'refinement_unavailable'];
        }
    }

    /** @param array<string,mixed> $draft @return array<string,mixed> */
    public function apply(string $studentId, string $roadmapId, int $baseVersion, string $source, array $draft, ?string $refinementId, string $requestId, string $idempotencyKey): array
    {
        try {
            if (!(($this->authorizer)($studentId))) return ['state'=>'forbidden'];
            $base = $this->ownedBaseVersion($studentId, $roadmapId, $baseVersion);
            $selected = RoadmapEditorDraft::fromArray($draft, $this->editableDraft($base));
            $preview = null;
            if ($source === 'ai_refined') {
                $preview = is_string($refinementId) ? $this->roadmaps->refinementPreview($studentId, $refinementId) : null;
                if ($preview === null) return ['state'=>'invalid_refinement_contract'];
            }
            $saved = $this->roadmaps->applyCustomization($studentId, $roadmapId, $baseVersion, $source, $selected, $preview, self::requestUuid($idempotencyKey));
            return $this->publicRoadmap($studentId, $saved);
        } catch (\InvalidArgumentException) {
            return ['state'=>'invalid_draft'];
        } catch (\RuntimeException $exception) {
            return str_contains(strtolower($exception->getMessage()), 'refinement')
                ? ['state'=>'invalid_refinement_contract'] : ['state'=>'stale_base'];
        } catch (\Throwable) {
            return ['state'=>'refinement_unavailable'];
        }
    }

    /** @return array<string,mixed> */
    private function activeBase(string $studentId, string $roadmapId, int $version): array
    {
        $base = $this->ownedBaseVersion($studentId, $roadmapId, $version);
        if (($base['status'] ?? null) !== 'active') throw new \RuntimeException('Roadmap base is stale.');
        return $base;
    }

    /** @return array<string,mixed> */
    private function ownedBaseVersion(string $studentId, string $roadmapId, int $version): array
    {
        $base = $this->roadmaps->versionForStudent($studentId, $version);
        if (!is_array($base) || ($base['roadmap_id'] ?? null) !== $roadmapId || ($base['version'] ?? null) !== $version
            || ($base['analysis_origin'] ?? null) !== 'model') {
            throw new \RuntimeException('Roadmap base is stale.');
        }
        return $base;
    }

    /** @param array<string,mixed> $roadmap @return array{phases:list<array<string,mixed>>} */
    private function editableDraft(array $roadmap): array
    {
        $phases = [];
        foreach (($roadmap['phases'] ?? []) as $phase) {
            if (!is_array($phase)) continue;
            $tasks = [];
            foreach (($phase['tasks'] ?? []) as $task) {
                if (!is_array($task)) continue;
                $title = (string)($task['title'] ?? '');
                $milestone = (int)($phase['end_day'] ?? 0);
                if (preg_match('/\(Mốc\s+(\d+)\s+ngày\)\s*$/iu', $title, $match) === 1) {
                    $milestone = (int)$match[1];
                    $title = trim((string)preg_replace('/\s*\(Mốc\s+\d+\s+ngày\)\s*$/iu', '', $title));
                }
                $tasks[] = ['task_id'=>$task['task_id'],'position'=>$task['position'],'title'=>$title,'description'=>$task['description'],'milestone_day'=>$milestone,'estimated_minutes'=>$task['estimated_minutes']];
            }
            $phases[] = ['phase_id'=>$phase['phase_id'],'position'=>$phase['position'],'start_day'=>$phase['start_day'],'end_day'=>$phase['end_day'],'code'=>$phase['code'],'title'=>$phase['title'],'goal'=>$phase['goal'],'skill_focus'=>$phase['skill_focus'],'deliverable'=>$phase['deliverable'],'effort_label'=>$phase['effort_label'],'metric_label'=>$phase['metric_label'],'tasks'=>$tasks];
        }
        return ['phases'=>$phases];
    }

    /** @param array<string,mixed> $roadmap @return array<string,mixed> */
    private function publicRoadmap(string $studentId, array $roadmap): array
    {
        $response = [];
        foreach (['roadmap_id','version','contract_version','status','analysis_origin','executive_summary','confidence_band','confidence','talent_map','strengths','improvements','potential_paths','trend_signals','growth_hypotheses','primary_direction','alternative_directions','insights','evidence_summary','generated_at','phases','progress','reused'] as $field) {
            if (array_key_exists($field, $roadmap)) $response[$field] = $roadmap[$field];
        }
        $response['state'] = 'roadmap_customized';
        $response['capability'] = 'roadmap';
        $response['freshness_status'] = 'fresh';
        $response['last_known_good'] = false;
        $response['engine'] = array_filter([
            'provider'=>$roadmap['engine']['provider'] ?? null,
            'model_version'=>$roadmap['engine']['model_version'] ?? null,
            'prompt_version'=>$roadmap['engine']['prompt_version'] ?? null,
        ], static fn(mixed $value): bool => is_string($value) && $value !== '');
        $response['version_history'] = $this->roadmaps->historyForStudent($studentId);
        $response['contract_version'] = (string)($roadmap['contract_version'] ?? RoadmapAnalysis::CONTRACT_VERSION);
        return $response;
    }

    private static function requestUuid(string $idempotencyKey): string
    {
        $hex = hash('sha256', 'roadmap-customization:' . $idempotencyKey);
        return sprintf('%s-%s-4%s-8%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,13,3),substr($hex,17,3),substr($hex,20,12));
    }
}
