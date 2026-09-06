<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;
require_once dirname(__DIR__) . '/Queue/TransactionalAiOutboxPublisher.php';

use Closure;
use JsonException;
use PDO;
use RuntimeException;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Domain\RoadmapEditorDraft;
use TalentHub\Learner\Ai\Domain\RoadmapPhase;
use TalentHub\Learner\Ai\Domain\RoadmapTask;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;

final class DatabaseRoadmapRepository implements RoadmapRepository
{
    /** @var Closure():string */
    private readonly Closure $clock;

    public function __construct(private readonly PDO $pdo, ?callable $clock = null)
    {
        $this->clock = $clock === null ? static fn (): string => gmdate('Y-m-d\TH:i:s.uP') : Closure::fromCallable($clock);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function saveCompleted(string $studentId, string $runId, RoadmapAnalysis $analysis, array $providerAudit): array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $runId = $this->required($runId, 'Roadmap run id is required.');
        $this->assertAudit($analysis, $providerAudit);

        return $this->transaction(function () use ($studentId, $runId, $analysis, $providerAudit): array {
            $run = $this->ownedCompletedRun($studentId, $runId);
            $this->assertRunProvenance($run, $analysis);
            $existing = $this->roadmapByRun($studentId, $runId);
            if ($existing !== null) {
                $result = $this->hydrate($studentId, (string) $existing['id']);
                $result['reused'] = true;
                return $result;
            }

            $map = $this->evidenceMap($providerAudit);
            foreach ($analysis->evidenceReferenceIds() as $referenceId) {
                $this->assertSnapshotEvidence((string) $run['snapshotId'], $referenceId, $map);
            }
            foreach ($analysis->recommendedActivitySourceIds() as $activityId) {
                $this->assertActivityTarget((string) $run['snapshotId'], $activityId);
            }
            foreach ($analysis->phases() as $phase) {
                foreach ($phase->tasks() as $task) {
                    if (($task->action()['type'] ?? null) === 'register_activity') {
                        $this->assertActivityTarget((string) $run['snapshotId'], (string) $task->action()['activity_source_id']);
                    }
                }
            }

            $version = $this->nextVersion($studentId);
            $now = $this->now();
            $supersede = $this->pdo->prepare("UPDATE learner_ai_roadmaps SET status = 'superseded', supersededAt = :supersededAt WHERE studentId = :studentId AND status = 'active'");
            $supersede->execute(['supersededAt' => $now, 'studentId' => $studentId]);
            $roadmapId = self::uuid();
            $data = $analysis->toArray();
            $summary = $this->evidenceSummary((string) $run['snapshotId']);
            $insert = $this->pdo->prepare('INSERT INTO learner_ai_roadmaps (id,studentId,runId,versionNumber,contractVersion,status,executiveSummary,primaryDirectionJson,alternativeDirectionsJson,insightsJson,confidenceBand,evidenceSummaryJson,providerRequestId,responseHash,generatedAt,supersededAt,createdAt) VALUES (:id,:studentId,:runId,:versionNumber,:contractVersion,:status,:executiveSummary,:primaryDirectionJson,:alternativeDirectionsJson,:insightsJson,:confidenceBand,:evidenceSummaryJson,:providerRequestId,:responseHash,:generatedAt,NULL,:createdAt)');
            $insert->execute([
                'id' => $roadmapId, 'studentId' => $studentId, 'runId' => $runId, 'versionNumber' => $version,
                'contractVersion' => RoadmapAnalysis::CONTRACT_VERSION, 'status' => 'active', 'executiveSummary' => $analysis->executiveSummary(),
                'primaryDirectionJson' => self::json($analysis->primaryDirection()->toArray()),
                'alternativeDirectionsJson' => self::json($data['alternative_directions']), 'insightsJson' => self::json([
                    'items' => $data['insights'],
                    '__ai_extended' => [
                        'talent_map' => $data['talent_map'],
                        'strengths' => $data['strengths'],
                        'improvements' => $data['improvements'],
                        'potential_paths' => $data['potential_paths'],
                        'trend_signals' => $data['trend_signals'],
                        'growth_hypotheses' => $data['growth_hypotheses'],
                        'confidence' => $data['confidence'],
                        'evidence' => $data['evidence'],
                    ],
                ]),
                'confidenceBand' => $analysis->confidenceBand(), 'evidenceSummaryJson' => self::json($summary),
                'providerRequestId' => $analysis->providerRequestId(), 'responseHash' => $analysis->responseHash(),
                'generatedAt' => $now, 'createdAt' => $now,
            ]);
            foreach ($analysis->phases() as $phase) $this->insertPhase($roadmapId, $phase, $now);

            $result = $this->hydrate($studentId, $roadmapId);
            $result['reused'] = false;
            return $result;
        });
    }

    public function latestForStudent(string $studentId): ?array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $statement = $this->pdo->prepare("SELECT id FROM learner_ai_roadmaps WHERE studentId = :studentId AND status = 'active' ORDER BY versionNumber DESC LIMIT 1");
        $statement->execute(['studentId' => $studentId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : $this->hydrate($studentId, (string) $id);
    }

    public function latestPendingForStudent(string $studentId): ?array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT runs.startedAt
FROM learner_recommendation_runs AS runs
INNER JOIN learner_recommendation_audit_events AS events
  ON events.runId = runs.id
 AND events.studentId = runs.studentId
 AND events.action = 'roadmap_run_created'
WHERE runs.studentId = :studentId
  AND runs.status = 'pending'
ORDER BY runs.startedAt DESC, runs.createdAt DESC
LIMIT 1
SQL);
        $statement->execute(['studentId' => $studentId]);
        $startedAt = $statement->fetchColumn();
        return $startedAt === false ? null : ['state' => 'pending', 'started_at' => (string) $startedAt];
    }

    public function historyForStudent(string $studentId): array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT roadmaps.id, roadmaps.versionNumber, roadmaps.status, roadmaps.generatedAt,
       roadmaps.executiveSummary, roadmaps.primaryDirectionJson, roadmaps.alternativeDirectionsJson,
       roadmaps.insightsJson, runs.engineType
FROM learner_ai_roadmaps AS roadmaps
INNER JOIN learner_recommendation_runs AS runs ON runs.id = roadmaps.runId
WHERE roadmaps.studentId = :studentId
ORDER BY roadmaps.versionNumber ASC
SQL);
        $statement->execute(['studentId' => $studentId]);
        $history = [];
        $previous = null;
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $comparable = [
                'executive_summary' => (string) $row['executiveSummary'],
                'primary_direction' => self::decode((string) $row['primaryDirectionJson']),
                'alternative_directions' => self::decode((string) $row['alternativeDirectionsJson']),
                'insights' => self::decode((string) $row['insightsJson']),
                'analysis_origin' => $row['engineType'] === 'model' ? 'model' : 'rule_fallback',
                'roadmap_plan' => $this->roadmapPlanFingerprint((string) $row['id']),
            ];
            $changed = [];
            if ($previous !== null) {
                foreach ($comparable as $section => $value) {
                    if ($value !== $previous[$section]) $changed[] = $section;
                }
            }
            $history[] = [
                'roadmap_id' => (string) $row['id'],
                'version' => (int) $row['versionNumber'],
                'status' => (string) $row['status'],
                'generated_at' => (string) $row['generatedAt'],
                'analysis_origin' => $comparable['analysis_origin'],
                'changed_sections' => $changed,
            ];
            $previous = $comparable;
        }
        return array_reverse($history);
    }

    public function versionForStudent(string $studentId, int $version): ?array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        if ($version < 1) throw new \InvalidArgumentException('Roadmap version must be positive.');
        $statement = $this->pdo->prepare('SELECT id FROM learner_ai_roadmaps WHERE studentId = :studentId AND versionNumber = :versionNumber LIMIT 1');
        $statement->execute(['studentId' => $studentId, 'versionNumber' => $version]);
        $id = $statement->fetchColumn();
        return $id === false ? null : $this->hydrate($studentId, (string) $id);
    }

    public function appendTaskEvent(string $studentId, string $taskId, string $status, string $requestId): array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $taskId = $this->required($taskId, 'Roadmap task id is required.');
        $requestId = $this->required($requestId, 'Roadmap task request id is required.');
        if (strlen($requestId) > 100 || !in_array($status, ['in_progress','completed','skipped'], true)) {
            throw new \InvalidArgumentException('Roadmap task event is invalid.');
        }
        return $this->transaction(function () use ($studentId, $taskId, $status, $requestId): array {
            $existing = $this->eventByRequest($studentId, $taskId, $requestId);
            if ($existing !== null) return $this->eventResponse($existing, true);
            $owned = $this->ownedTask($studentId, $taskId);
            $current = $this->latestTaskStatus($taskId);
            $allowed = match ($current) {
                'not_started' => ['in_progress','completed','skipped'],
                'in_progress' => ['completed','skipped'],
                'skipped' => ['in_progress'],
                'completed' => ['in_progress'],
                default => [],
            };
            if (!in_array($status, $allowed, true)) throw new RuntimeException('Roadmap task status transition is invalid');
            $event = ['id' => self::uuid(), 'taskId' => $taskId, 'studentId' => $studentId, 'status' => $status, 'requestId' => $requestId, 'occurredAt' => $this->now()];
            $insert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_task_events (id,taskId,studentId,status,requestId,occurredAt,createdAt) VALUES (:id,:taskId,:studentId,:status,:requestId,:occurredAt,:createdAt)');
            $insert->execute($event + ['createdAt' => $event['occurredAt']]);
            TransactionalAiOutboxPublisher::publish($this->pdo,'roadmap_progress',$event['id'],TransactionalAiOutboxPublisher::version(),[$studentId],'roadmap.progress_updated',['task_id'=>$taskId,'status'=>$status]);
            unset($owned);
            return $this->eventResponse($event, false);
        });
    }

    public function appendRoadmapFeedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $roadmapId = $this->required($roadmapId, 'Roadmap id is required.');
        $requestId = $this->required($requestId, 'Roadmap feedback request id is required.');
        $reasons = ['useful_direction','not_relevant','too_generic','too_difficult'];
        if (!in_array($verdict, ['helpful','not_helpful'], true) || !in_array($reasonCode, $reasons, true)) {
            throw new \InvalidArgumentException('Roadmap feedback is invalid.');
        }
        return $this->transaction(function () use ($studentId, $roadmapId, $verdict, $reasonCode, $requestId): array {
            $owned = $this->pdo->prepare('SELECT runId FROM learner_ai_roadmaps WHERE id = :roadmapId AND studentId = :studentId');
            $owned->execute(['roadmapId'=>$roadmapId,'studentId'=>$studentId]);
            $runId = $owned->fetchColumn();
            if ($runId === false) throw new RuntimeException('Roadmap not found for learner');
            $existing = $this->pdo->prepare("SELECT id, createdAt FROM learner_recommendation_audit_events WHERE runId = :runId AND studentId = :studentId AND requestId = :requestId AND action = 'roadmap_feedback' LIMIT 1");
            $existing->execute(['runId'=>$runId,'studentId'=>$studentId,'requestId'=>$requestId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) return ['state'=>'feedback_saved','feedback_id'=>$row['id'],'roadmap_id'=>$roadmapId,'reused'=>true,'created_at'=>$row['createdAt']];
            $id = self::uuid(); $now = $this->now();
            $insert = $this->pdo->prepare('INSERT INTO learner_recommendation_audit_events (id,runId,studentId,requestId,actorType,action,engineMetadataJson,status,createdAt) VALUES (:id,:runId,:studentId,:requestId,:actorType,:action,:metadata,:status,:createdAt)');
            $insert->execute(['id'=>$id,'runId'=>$runId,'studentId'=>$studentId,'requestId'=>$requestId,'actorType'=>'learner','action'=>'roadmap_feedback','metadata'=>self::json(['verdict'=>$verdict,'reason_code'=>$reasonCode]),'status'=>'completed','createdAt'=>$now]);
            TransactionalAiOutboxPublisher::publish($this->pdo,'roadmap_feedback',$id,TransactionalAiOutboxPublisher::version(),[$studentId],'roadmap.feedback',['roadmap_id'=>$roadmapId,'verdict'=>$verdict,'reason_code'=>$reasonCode]);
            return ['state'=>'feedback_saved','feedback_id'=>$id,'roadmap_id'=>$roadmapId,'verdict'=>$verdict,'reason_code'=>$reasonCode,'reused'=>false,'created_at'=>$now];
        });
    }

    public function feedbackSignalsForStudent(string $studentId): array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $statement = $this->pdo->prepare("SELECT engineMetadataJson FROM learner_recommendation_audit_events WHERE studentId = :studentId AND action = 'roadmap_feedback' AND status = 'completed' ORDER BY createdAt DESC LIMIT 100");
        $statement->execute(['studentId'=>$studentId]);
        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $json) {
            if (!is_string($json)) continue;
            $metadata = self::decode($json);
            $verdict = $metadata['verdict'] ?? null; $reason = $metadata['reason_code'] ?? null;
            if (!in_array($verdict, ['helpful','not_helpful'], true) || !in_array($reason, ['useful_direction','not_relevant','too_generic','too_difficult'], true)) continue;
            $key = $verdict . ':' . $reason;
            $counts[$key] = ['verdict'=>$verdict,'reason_code'=>$reason,'count'=>(($counts[$key]['count'] ?? 0) + 1)];
        }
        ksort($counts, SORT_STRING);
        return array_values($counts);
    }

    /** @param array<string,mixed> $audit @return array<string,mixed> */
    public function storeRefinementPreview(
        string $studentId,
        string $roadmapId,
        int $baseVersion,
        RoadmapEditorDraft $learnerDraft,
        RoadmapEditorDraft $aiDraft,
        array $audit,
    ): array {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $roadmapId = $this->required($roadmapId, 'Roadmap id is required.');
        if ($baseVersion < 1) throw new \InvalidArgumentException('Roadmap base version must be positive.');
        $learnerDraft->assertSameStructure($aiDraft);
        $provider = $this->auditValue($audit, 'provider', 80);
        $modelVersion = $this->auditValue($audit, 'model_version', 128);
        $promptVersion = $this->auditValue($audit, 'prompt_version', 128);
        $providerRequestId = $this->optionalAuditValue($audit, 'provider_request_id', 128);
        // The legacy preview table is NOT NULL; an empty value represents a
        // provider that returned no optional request identifier.
        $storedProviderRequestId = $providerRequestId ?? '';
        $responseHash = $this->auditHash($audit, 'response_hash');

        return $this->transaction(function () use ($studentId, $roadmapId, $baseVersion, $learnerDraft, $aiDraft, $provider, $modelVersion, $promptVersion, $providerRequestId, $storedProviderRequestId, $responseHash): array {
            $owned = $this->pdo->prepare("SELECT 1 FROM learner_ai_roadmaps WHERE id = :roadmapId AND studentId = :studentId AND versionNumber = :baseVersion AND status = 'active'");
            $owned->execute(['roadmapId'=>$roadmapId,'studentId'=>$studentId,'baseVersion'=>$baseVersion]);
            if ($owned->fetchColumn() === false) throw new RuntimeException('Active roadmap not found for learner');

            $id = self::uuid();
            $createdAt = $this->now();
            $expiresAt = $this->databaseTimestamp((new \DateTimeImmutable(($this->clock)()))->modify('+30 minutes')->format('Y-m-d\TH:i:s.uP'));
            $insert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_refinements (id,studentId,roadmapId,baseVersion,learnerDraftHash,learnerDraftJson,aiDraftHash,aiDraftJson,provider,modelVersion,promptVersion,providerRequestId,responseHash,expiresAt,createdAt) VALUES (:id,:studentId,:roadmapId,:baseVersion,:learnerDraftHash,:learnerDraftJson,:aiDraftHash,:aiDraftJson,:provider,:modelVersion,:promptVersion,:providerRequestId,:responseHash,:expiresAt,:createdAt)');
            $insert->execute([
                'id'=>$id, 'studentId'=>$studentId, 'roadmapId'=>$roadmapId, 'baseVersion'=>$baseVersion,
                'learnerDraftHash'=>$learnerDraft->hash(), 'learnerDraftJson'=>self::json($learnerDraft->toArray()),
                'aiDraftHash'=>$aiDraft->hash(), 'aiDraftJson'=>self::json($aiDraft->toArray()),
                'provider'=>$provider, 'modelVersion'=>$modelVersion, 'promptVersion'=>$promptVersion,
                'providerRequestId'=>$storedProviderRequestId, 'responseHash'=>$responseHash,
                'expiresAt'=>$expiresAt, 'createdAt'=>$createdAt,
            ]);

            return $this->refinementResponse([
                'id'=>$id, 'roadmapId'=>$roadmapId, 'baseVersion'=>$baseVersion,
                'learnerDraftHash'=>$learnerDraft->hash(), 'learnerDraftJson'=>self::json($learnerDraft->toArray()),
                'aiDraftHash'=>$aiDraft->hash(), 'aiDraftJson'=>self::json($aiDraft->toArray()),
                'provider'=>$provider, 'modelVersion'=>$modelVersion, 'promptVersion'=>$promptVersion,
                'providerRequestId'=>$providerRequestId, 'responseHash'=>$responseHash,
                'expiresAt'=>$expiresAt, 'createdAt'=>$createdAt,
            ]);
        });
    }

    public function refinementPreview(string $studentId, string $previewId): ?array
    {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $previewId = $this->required($previewId, 'Roadmap refinement preview id is required.');
        $statement = $this->pdo->prepare('SELECT * FROM learner_ai_roadmap_refinements WHERE id = :id AND studentId = :studentId AND expiresAt > :now LIMIT 1');
        $statement->execute(['id'=>$previewId,'studentId'=>$studentId,'now'=>$this->now()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->refinementResponse($row);
    }

    /** @param array<string,mixed>|null $refinement @return array<string,mixed> */
    public function applyCustomization(
        string $studentId,
        string $roadmapId,
        int $baseVersion,
        string $source,
        RoadmapEditorDraft $draft,
        ?array $refinement,
        string $requestId,
    ): array {
        $studentId = $this->required($studentId, 'Roadmap student id is required.');
        $roadmapId = $this->required($roadmapId, 'Roadmap id is required.');
        $requestId = $this->required($requestId, 'Roadmap customization request id is required.');
        if ($baseVersion < 1 || strlen($requestId) > 100 || !in_array($source, ['learner_draft','ai_refined'], true)) {
            throw new \InvalidArgumentException('Roadmap customization request is invalid.');
        }

        return $this->transaction(function () use ($studentId, $roadmapId, $baseVersion, $source, $draft, $refinement, $requestId): array {
            $existing = $this->customizationByRequest($studentId, $requestId);
            if ($existing !== null) {
                $result = $this->hydrate($studentId, $existing);
                $result['state'] = 'roadmap_customized';
                $result['reused'] = true;
                return $result;
            }

            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $baseStatement = $this->pdo->prepare('SELECT roadmaps.*, runs.snapshotId, runs.engineType, runs.provider AS runProvider, runs.modelVersion AS runModelVersion, runs.promptVersion AS runPromptVersion, runs.analysisJson FROM learner_ai_roadmaps AS roadmaps INNER JOIN learner_recommendation_runs AS runs ON runs.id = roadmaps.runId WHERE roadmaps.id = :roadmapId AND roadmaps.studentId = :studentId AND roadmaps.versionNumber = :baseVersion AND roadmaps.status = \'active\'' . $lock);
            $baseStatement->execute(['roadmapId'=>$roadmapId,'studentId'=>$studentId,'baseVersion'=>$baseVersion]);
            $base = $baseStatement->fetch(PDO::FETCH_ASSOC);
            if ($base === false) throw new RuntimeException('Active roadmap base is stale or unavailable');
            if (($base['engineType'] ?? null) !== 'model') throw new RuntimeException('Only model roadmaps can be customized');

            $provider = (string) ($base['runProvider'] ?? '');
            $modelVersion = (string) ($base['runModelVersion'] ?? '');
            $promptVersion = (string) ($base['runPromptVersion'] ?? '');
            $providerRequestId = null;
            $responseHash = null;
            $previewId = null;
            if ($source === 'ai_refined') {
                $previewId = is_string($refinement['preview_id'] ?? null) ? $refinement['preview_id'] : '';
                $storedPreview = $previewId === '' ? null : $this->refinementPreview($studentId, $previewId);
                if ($storedPreview === null
                    || ($storedPreview['roadmap_id'] ?? null) !== $roadmapId
                    || ($storedPreview['base_version'] ?? null) !== $baseVersion
                    || !hash_equals((string) ($storedPreview['ai_draft_hash'] ?? ''), $draft->hash())) {
                    throw new RuntimeException('Roadmap refinement preview is invalid or expired');
                }
                $engine = is_array($storedPreview['engine'] ?? null) ? $storedPreview['engine'] : [];
                $provider = (string) ($engine['provider'] ?? '');
                $modelVersion = (string) ($engine['model_version'] ?? '');
                $promptVersion = (string) ($engine['prompt_version'] ?? '');
                $providerRequestId = $this->optionalStoredValue($engine['provider_request_id'] ?? null);
                $responseHash = (string) ($engine['response_hash'] ?? '');
            }
            if ($provider === '' || $modelVersion === '' || $promptVersion === '') {
                throw new RuntimeException('Roadmap customization provenance is unavailable');
            }

            $sourcePhases = $this->sourcePlan($roadmapId);
            $this->assertDraftAgainstSource($draft, $sourcePhases);
            $now = $this->now();
            $runId = self::uuid();
            $newRoadmapId = self::uuid();
            $version = $this->nextVersion($studentId);
            $idempotencyKey = 'roadmap-customization-' . substr(hash('sha256', $requestId), 0, 64);
            $runInsert = $this->pdo->prepare('INSERT INTO learner_recommendation_runs (id,studentId,snapshotId,idempotencyKey,engineType,status,ruleVersion,provider,modelVersion,promptVersion,fallbackReason,safeErrorCode,capability,analysisJson,startedAt,completedAt,createdAt) VALUES (:id,:studentId,:snapshotId,:idempotencyKey,\'model\',\'completed\',NULL,:provider,:modelVersion,:promptVersion,NULL,NULL,\'roadmap\',:analysisJson,:startedAt,:completedAt,:createdAt)');
            $runInsert->execute(['id'=>$runId,'studentId'=>$studentId,'snapshotId'=>$base['snapshotId'],'idempotencyKey'=>$idempotencyKey,'provider'=>$provider,'modelVersion'=>$modelVersion,'promptVersion'=>$promptVersion,'analysisJson'=>$base['analysisJson'],'startedAt'=>$now,'completedAt'=>$now,'createdAt'=>$now]);

            $supersede = $this->pdo->prepare("UPDATE learner_ai_roadmaps SET status = 'superseded', supersededAt = :supersededAt WHERE id = :roadmapId AND studentId = :studentId AND versionNumber = :baseVersion AND status = 'active'");
            $supersede->execute(['supersededAt'=>$now,'roadmapId'=>$roadmapId,'studentId'=>$studentId,'baseVersion'=>$baseVersion]);
            if ($supersede->rowCount() !== 1) throw new RuntimeException('Active roadmap base changed during customization');

            $roadmapInsert = $this->pdo->prepare('INSERT INTO learner_ai_roadmaps (id,studentId,runId,versionNumber,contractVersion,status,executiveSummary,primaryDirectionJson,alternativeDirectionsJson,insightsJson,confidenceBand,evidenceSummaryJson,providerRequestId,responseHash,generatedAt,supersededAt,createdAt) VALUES (:id,:studentId,:runId,:versionNumber,:contractVersion,\'active\',:executiveSummary,:primaryDirectionJson,:alternativeDirectionsJson,:insightsJson,:confidenceBand,:evidenceSummaryJson,:providerRequestId,:responseHash,:generatedAt,NULL,:createdAt)');
            $roadmapInsert->execute([
                'id'=>$newRoadmapId,'studentId'=>$studentId,'runId'=>$runId,'versionNumber'=>$version,'contractVersion'=>$base['contractVersion'],
                'executiveSummary'=>$base['executiveSummary'],'primaryDirectionJson'=>$base['primaryDirectionJson'],
                'alternativeDirectionsJson'=>$base['alternativeDirectionsJson'],'insightsJson'=>$base['insightsJson'],
                'confidenceBand'=>$base['confidenceBand'],'evidenceSummaryJson'=>$base['evidenceSummaryJson'],
                'providerRequestId'=>$providerRequestId,'responseHash'=>$responseHash,'generatedAt'=>$now,'createdAt'=>$now,
            ]);

            foreach ($draft->toArray()['phases'] as $phase) {
                $sourcePhase = $sourcePhases[$phase['phase_id']];
                $newPhaseId = self::uuid();
                $phaseInsert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_phases (id,roadmapId,position,startDay,endDay,code,title,goal,skillFocus,deliverable,effortLabel,metricLabel,evidenceJson,createdAt) VALUES (:id,:roadmapId,:position,:startDay,:endDay,:code,:title,:goal,:skillFocus,:deliverable,:effortLabel,:metricLabel,:evidenceJson,:createdAt)');
                $phaseInsert->execute(['id'=>$newPhaseId,'roadmapId'=>$newRoadmapId,'position'=>$phase['position'],'startDay'=>$phase['start_day'],'endDay'=>$phase['end_day'],'code'=>$phase['code'],'title'=>$phase['title'],'goal'=>$phase['goal'],'skillFocus'=>$phase['skill_focus'],'deliverable'=>$phase['deliverable'],'effortLabel'=>$phase['effort_label'],'metricLabel'=>$phase['metric_label'],'evidenceJson'=>$sourcePhase['evidenceJson'],'createdAt'=>$now]);
                foreach ($phase['tasks'] as $task) {
                    $sourceTask = $sourcePhase['tasks'][$task['task_id']] ?? null;
                    $isRetained = is_array($sourceTask);
                    $newTaskId = self::uuid();
                    $taskInsert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_tasks (id,phaseId,position,title,description,estimatedMinutes,actionType,targetType,targetId,evidenceJson,createdAt) VALUES (:id,:phaseId,:position,:title,:description,:estimatedMinutes,:actionType,:targetType,:targetId,:evidenceJson,:createdAt)');
                    $taskInsert->execute([
                        'id'=>$newTaskId,
                        'phaseId'=>$newPhaseId,
                        'position'=>$task['position'],
                        'title'=>$draft->storageTitle($task),
                        'description'=>$task['description'],
                        'estimatedMinutes'=>$task['estimated_minutes'],
                        'actionType'=>$isRetained ? $sourceTask['actionType'] : 'self_task',
                        'targetType'=>$isRetained ? $sourceTask['targetType'] : null,
                        'targetId'=>$isRetained ? $sourceTask['targetId'] : null,
                        'evidenceJson'=>$isRetained ? $sourceTask['evidenceJson'] : '[]',
                        'createdAt'=>$now,
                    ]);
                    $status = $isRetained ? $this->latestTaskStatus((string) $task['task_id']) : 'not_started';
                    if ($isRetained && $status !== 'not_started') {
                        $eventInsert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_task_events (id,taskId,studentId,status,requestId,occurredAt,createdAt) VALUES (:id,:taskId,:studentId,:status,:requestId,:occurredAt,:createdAt)');
                        $eventInsert->execute(['id'=>self::uuid(),'taskId'=>$newTaskId,'studentId'=>$studentId,'status'=>$status,'requestId'=>$requestId,'occurredAt'=>$now,'createdAt'=>$now]);
                    }
                }
            }

            $auditId = self::uuid();
            $auditMetadata = ['base_roadmap_id'=>$roadmapId,'base_version'=>$baseVersion,'roadmap_id'=>$newRoadmapId,'version'=>$version,'source'=>$source,'draft_hash'=>$draft->hash(),'preview_id'=>$previewId];
            $auditInsert = $this->pdo->prepare('INSERT INTO learner_recommendation_audit_events (id,runId,studentId,requestId,actorType,action,engineMetadataJson,status,createdAt) VALUES (:id,:runId,:studentId,:requestId,\'learner\',\'roadmap_customization_applied\',:metadata,\'completed\',:createdAt)');
            $auditInsert->execute(['id'=>$auditId,'runId'=>$runId,'studentId'=>$studentId,'requestId'=>$requestId,'metadata'=>self::json($auditMetadata),'createdAt'=>$now]);
            TransactionalAiOutboxPublisher::publish($this->pdo,'roadmap_customization',$auditId,TransactionalAiOutboxPublisher::version(),[$studentId],'roadmap.customized',['roadmap_id'=>$newRoadmapId,'version'=>$version,'source'=>$source]);

            $result = $this->hydrate($studentId, $newRoadmapId);
            $result['state'] = 'roadmap_customized';
            $result['reused'] = false;
            return $result;
        });
    }

    /** @param array<string,mixed> $providerAudit */
    private function assertAudit(RoadmapAnalysis $analysis, array $providerAudit): void
    {
        if ($analysis->origin() === 'model') {
            if (($providerAudit['provider_request_id'] ?? null) !== $analysis->providerRequestId()
                || ($providerAudit['response_hash'] ?? null) !== $analysis->responseHash()
                || $analysis->responseHash() === null) {
                throw new RuntimeException('Roadmap provider audit does not match model result');
            }
        } elseif (isset($providerAudit['provider_request_id']) || isset($providerAudit['response_hash'])) {
            throw new RuntimeException('Roadmap fallback cannot contain provider audit provenance');
        }
    }

    /** @param array<string,mixed> $run */
    private function assertRunProvenance(array $run, RoadmapAnalysis $analysis): void
    {
        $engine = $analysis->engineMetadata();
        if ($analysis->origin() === 'model') {
            if ($run['status'] !== 'completed' || $run['engineType'] !== 'model'
                || $run['provider'] !== $engine['provider'] || $run['modelVersion'] !== $engine['model_version'] || $run['promptVersion'] !== $engine['prompt_version']) {
                throw new RuntimeException('Roadmap model provenance does not match completed run');
            }
            return;
        }
        if ($run['status'] !== 'fallback' || $run['engineType'] !== 'rule'
            || $run['ruleVersion'] !== $engine['rule_version'] || $run['fallbackReason'] !== $analysis->fallbackReason()) {
            throw new RuntimeException('Roadmap fallback provenance does not match completed run');
        }
    }

    /** @param array<string,mixed> $providerAudit @return array<string,array{source_type:string,source_id:string}> */
    private function evidenceMap(array $providerAudit): array
    {
        $map = $providerAudit['evidence_reference_map'] ?? null;
        if (!is_array($map)) throw new RuntimeException('Roadmap evidence reference map is required');
        return $map;
    }

    /** @param array<string,array{source_type:string,source_id:string}> $map */
    private function assertSnapshotEvidence(string $snapshotId, string $referenceId, array $map): void
    {
        $record = $map[$referenceId] ?? null;
        if (!is_array($record) || !is_string($record['source_type'] ?? null) || !is_string($record['source_id'] ?? null)) {
            throw new RuntimeException('Roadmap evidence reference is not mapped to run snapshot');
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM learner_recommendation_snapshot_evidence WHERE snapshotId = :snapshotId AND sourceType = :sourceType AND sourceId = :sourceId');
        foreach (EvidenceSourceTypeNormalizer::lookupTypes($record['source_type']) as $sourceType) {
            $statement->execute(['snapshotId' => $snapshotId, 'sourceType' => $sourceType, 'sourceId' => $record['source_id']]);
            if ($statement->fetchColumn() !== false) return;
        }
        throw new RuntimeException('Roadmap evidence is not part of run snapshot');
    }

    private function assertActivityTarget(string $snapshotId, string $activityId): void
    {
        $statement = $this->pdo->prepare("SELECT safeValueJson FROM learner_recommendation_snapshot_evidence WHERE snapshotId = :snapshotId AND sourceType = 'opportunity' AND sourceId = :sourceId");
        $statement->execute(['snapshotId' => $snapshotId, 'sourceId' => $activityId]);
        $json = $statement->fetchColumn();
        if (!is_string($json)) throw new RuntimeException('Roadmap activity target is not part of run snapshot');
        $safe = self::decode($json);
        if (($safe['opportunity_type'] ?? null) !== 'activity') throw new RuntimeException('Roadmap activity target is not eligible');
    }

    private function insertPhase(string $roadmapId, RoadmapPhase $phase, string $now): void
    {
        $phaseId = self::uuid();
        $insert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_phases (id,roadmapId,position,startDay,endDay,code,title,goal,skillFocus,deliverable,effortLabel,metricLabel,evidenceJson,createdAt) VALUES (:id,:roadmapId,:position,:startDay,:endDay,:code,:title,:goal,:skillFocus,:deliverable,:effortLabel,:metricLabel,:evidenceJson,:createdAt)');
        $insert->execute(['id'=>$phaseId,'roadmapId'=>$roadmapId,'position'=>$phase->position(),'startDay'=>$phase->startDay(),'endDay'=>$phase->endDay(),'code'=>$phase->code(),'title'=>$phase->title(),'goal'=>$phase->goal(),'skillFocus'=>$phase->skillFocus(),'deliverable'=>$phase->deliverable(),'effortLabel'=>$phase->effortLabel(),'metricLabel'=>$phase->metricLabel(),'evidenceJson'=>self::json($phase->evidenceReferenceIds()),'createdAt'=>$now]);
        foreach ($phase->tasks() as $task) $this->insertTask($phaseId, $task, $now);
    }

    private function insertTask(string $phaseId, RoadmapTask $task, string $now): void
    {
        $action = $task->action();
        $targetId = ($action['type'] ?? null) === 'register_activity' ? $action['activity_source_id'] : null;
        $insert = $this->pdo->prepare('INSERT INTO learner_ai_roadmap_tasks (id,phaseId,position,title,description,estimatedMinutes,actionType,targetType,targetId,evidenceJson,createdAt) VALUES (:id,:phaseId,:position,:title,:description,:estimatedMinutes,:actionType,:targetType,:targetId,:evidenceJson,:createdAt)');
        $insert->execute(['id'=>self::uuid(),'phaseId'=>$phaseId,'position'=>$task->position(),'title'=>$task->title(),'description'=>$task->description(),'estimatedMinutes'=>$task->estimatedMinutes(),'actionType'=>$action['type'],'targetType'=>$targetId === null ? null : 'activity','targetId'=>$targetId,'evidenceJson'=>self::json($task->evidenceReferenceIds()),'createdAt'=>$now]);
    }

    /** @return array<string,mixed> */
    private function hydrate(string $studentId, string $roadmapId): array
    {
        $statement = $this->pdo->prepare('SELECT roadmaps.*, runs.engineType, runs.ruleVersion, runs.provider, runs.modelVersion, runs.promptVersion, runs.fallbackReason, snapshots.contentHash AS inputHash FROM learner_ai_roadmaps AS roadmaps INNER JOIN learner_recommendation_runs AS runs ON runs.id = roadmaps.runId INNER JOIN learner_recommendation_input_snapshots AS snapshots ON snapshots.id = runs.snapshotId WHERE roadmaps.id = :roadmapId AND roadmaps.studentId = :studentId');
        $statement->execute(['roadmapId' => $roadmapId, 'studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) throw new RuntimeException('Roadmap not found for learner');
        $phases = $this->pdo->prepare('SELECT * FROM learner_ai_roadmap_phases WHERE roadmapId = :roadmapId ORDER BY position ASC');
        $phases->execute(['roadmapId' => $roadmapId]);
        $phasesList = $phases->fetchAll(PDO::FETCH_ASSOC);
        // Load every phase task and its latest event status in one database round trip.
        $tasksStmt = $this->pdo->prepare(<<<'SQL'
SELECT t.*,
       COALESCE(
           (SELECT e.status
            FROM learner_ai_roadmap_task_events e
            WHERE e.taskId = t.id
            ORDER BY e.occurredAt DESC, e.createdAt DESC, e.id DESC
            LIMIT 1),
           'not_started'
       ) AS latest_status
FROM learner_ai_roadmap_tasks t
INNER JOIN learner_ai_roadmap_phases p ON p.id = t.phaseId
WHERE p.roadmapId = :roadmapId
ORDER BY p.position ASC, t.position ASC
SQL);
        $tasksStmt->execute(['roadmapId' => $roadmapId]);
        $allTasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);
        $hasActivityTasks = false;
        $tasksByPhase = [];
        foreach ($allTasks as $t) {
            if (($t['actionType'] ?? '') === 'register_activity') {
                $hasActivityTasks = true;
            }
            $tasksByPhase[(string)$t['phaseId']][] = $t;
        }
        $eligibleActivityIds = [];
        if ($hasActivityTasks) {
            try {
                foreach ((new DatabaseOpportunitySource($this->pdo))->forStudent($studentId) as $opportunity) {
                    if (($opportunity['opportunity_type'] ?? null) === 'activity' && is_string($opportunity['opportunity_id'] ?? null)) {
                        $eligibleActivityIds[(string) $opportunity['opportunity_id']] = true;
                    }
                }
            } catch (\Throwable) {}
        }
        $phaseData = []; $total = 0; $completed = 0;
        foreach ($phasesList as $phase) {
            $taskData = []; $phaseCompleted = 0;
            $phaseTasks = $tasksByPhase[(string)$phase['id']] ?? [];
            foreach ($phaseTasks as $task) {
                $status = (string)($task['latest_status'] ?? 'not_started');
                $total++; if ($status === 'completed') { $completed++; $phaseCompleted++; }
                $action = ['type' => $task['actionType']];
                if ($task['actionType'] === 'register_activity') {
                    $action['activity_source_id'] = $task['targetId'];
                    if (isset($eligibleActivityIds[(string) $task['targetId']])) {
                        $action['registration_path'] = '/app/learner/activity-detail.php?id=' . rawurlencode((string) $task['targetId']);
                        $action['availability'] = 'available';
                    } else {
                        $action['availability'] = 'unavailable';
                    }
                }
                $taskData[] = ['task_id'=>$task['id'],'position'=>(int)$task['position'],'title'=>$task['title'],'description'=>$task['description'],'estimated_minutes'=>(int)$task['estimatedMinutes'],'action'=>$action,'evidence_ref_ids'=>self::decode((string)$task['evidenceJson']),'status'=>$status];
            }
            $phaseData[] = ['phase_id'=>$phase['id'],'position'=>(int)$phase['position'],'start_day'=>(int)$phase['startDay'],'end_day'=>(int)$phase['endDay'],'code'=>$phase['code'],'title'=>$phase['title'],'goal'=>$phase['goal'],'skill_focus'=>$phase['skillFocus'],'deliverable'=>$phase['deliverable'],'effort_label'=>$phase['effortLabel'],'metric_label'=>$phase['metricLabel'],'evidence_ref_ids'=>self::decode((string)$phase['evidenceJson']),'tasks'=>$taskData,'progress'=>['completed_tasks'=>$phaseCompleted,'total_tasks'=>count($taskData)]];
        }
        $storedInsights = self::decode((string)$row['insightsJson']);
        $extended = is_array($storedInsights['__ai_extended'] ?? null) ? $storedInsights['__ai_extended'] : [];
        $insights = is_array($storedInsights['items'] ?? null) ? $storedInsights['items'] : $storedInsights;
        $evidence = is_array($extended['evidence'] ?? null) ? $extended['evidence'] : [];
        if ($evidence === []) {
            $evidence = [];
            foreach ($phaseData as $phase) {
                foreach (($phase['evidence_ref_ids'] ?? []) as $reference) {
                    if (is_string($reference)) $evidence[$reference] = true;
                }
                foreach (($phase['tasks'] ?? []) as $task) {
                    foreach (($task['evidence_ref_ids'] ?? []) as $reference) {
                        if (is_string($reference)) $evidence[$reference] = true;
                    }
                }
            }
            $evidence = array_keys($evidence);
            sort($evidence, SORT_STRING);
        }
        $origin = $row['engineType'] === 'model' ? 'model' : 'rule_fallback';
        return ['roadmap_id'=>$row['id'],'run_id'=>$row['runId'],'input_hash'=>$row['inputHash'],'version'=>(int)$row['versionNumber'],'contract_version'=>$row['contractVersion'],'status'=>$row['status'],'analysis_origin'=>$origin,'freshness_status'=>$row['freshness_status']??null,'stale_since'=>$row['stale_since']??null,'last_refresh_error'=>$row['last_refresh_error']??null,'next_retry_at'=>$row['next_retry_at']??null,'refresh_job_id'=>$row['refresh_job_id']??null,'executive_summary'=>$row['executiveSummary'],'confidence_band'=>$row['confidenceBand'],'confidence'=>(float)($extended['confidence'] ?? 0.0),'talent_map'=>is_array($extended['talent_map'] ?? null) ? $extended['talent_map'] : [],'strengths'=>is_array($extended['strengths'] ?? null) ? $extended['strengths'] : [],'improvements'=>is_array($extended['improvements'] ?? null) ? $extended['improvements'] : [],'potential_paths'=>is_array($extended['potential_paths'] ?? null) ? $extended['potential_paths'] : [],'trend_signals'=>is_array($extended['trend_signals'] ?? null) ? $extended['trend_signals'] : [],'growth_hypotheses'=>is_array($extended['growth_hypotheses'] ?? null) ? $extended['growth_hypotheses'] : [],'evidence'=>$evidence,'primary_direction'=>self::decode((string)$row['primaryDirectionJson']),'alternative_directions'=>self::decode((string)$row['alternativeDirectionsJson']),'insights'=>$insights,'evidence_summary'=>self::decode((string)$row['evidenceSummaryJson']),'generated_at'=>$row['generatedAt'],'engine'=>['provider'=>$row['provider'],'model_version'=>$row['modelVersion'],'prompt_version'=>$row['promptVersion'],'rule_version'=>$row['ruleVersion'],'fallback_reason'=>$row['fallbackReason']],'phases'=>$phaseData,'progress'=>['completed_tasks'=>$completed,'total_tasks'=>$total]];
    }

    /** @return array<string,mixed> */
    private function ownedCompletedRun(string $studentId, string $runId): array
    {
        $statement = $this->pdo->prepare("SELECT id,studentId,snapshotId,engineType,status,ruleVersion,provider,modelVersion,promptVersion,fallbackReason FROM learner_recommendation_runs WHERE id = :runId AND studentId = :studentId AND status IN ('completed','fallback')");
        $statement->execute(['runId'=>$runId,'studentId'=>$studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) throw new RuntimeException('Completed roadmap run not found for learner');
        return $row;
    }

    /** @return array<string,mixed>|null */
    private function roadmapByRun(string $studentId, string $runId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id FROM learner_ai_roadmaps WHERE studentId = :studentId AND runId = :runId');
        $statement->execute(['studentId'=>$studentId,'runId'=>$runId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function nextVersion(string $studentId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(versionNumber),0) + 1 FROM learner_ai_roadmaps WHERE studentId = :studentId');
        $statement->execute(['studentId'=>$studentId]);
        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    private function roadmapPlanFingerprint(string $roadmapId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT phases.position AS phasePosition, phases.startDay, phases.endDay, phases.code, phases.title,
       phases.goal, phases.skillFocus, phases.deliverable, phases.effortLabel, phases.metricLabel,
       tasks.position AS taskPosition, tasks.title AS taskTitle, tasks.description AS taskDescription,
       tasks.estimatedMinutes, tasks.actionType, tasks.targetType, tasks.targetId
FROM learner_ai_roadmap_phases AS phases
LEFT JOIN learner_ai_roadmap_tasks AS tasks ON tasks.phaseId = phases.id
WHERE phases.roadmapId = :roadmapId
ORDER BY phases.position ASC, tasks.position ASC
SQL);
        $statement->execute(['roadmapId'=>$roadmapId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,int> */
    private function evidenceSummary(string $snapshotId): array
    {
        $statement = $this->pdo->prepare('SELECT sourceType, COUNT(*) AS total FROM learner_recommendation_snapshot_evidence WHERE snapshotId = :snapshotId GROUP BY sourceType');
        $statement->execute(['snapshotId'=>$snapshotId]);
        $counts = ['assessment_count'=>0,'skill_count'=>0,'activity_count'=>0,'evaluation_count'=>0];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = match ($row['sourceType']) { 'assessment'=>'assessment_count','skill'=>'skill_count','activity_experience'=>'activity_count','evaluation'=>'evaluation_count',default=>null };
            if ($key !== null) $counts[$key] = (int) $row['total'];
        }
        return $counts;
    }

    /** @return array<string,mixed> */
    private function ownedTask(string $studentId, string $taskId): array
    {
        $statement = $this->pdo->prepare('SELECT tasks.id FROM learner_ai_roadmap_tasks AS tasks INNER JOIN learner_ai_roadmap_phases AS phases ON phases.id = tasks.phaseId INNER JOIN learner_ai_roadmaps AS roadmaps ON roadmaps.id = phases.roadmapId WHERE tasks.id = :taskId AND roadmaps.studentId = :studentId');
        $statement->execute(['taskId'=>$taskId,'studentId'=>$studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) throw new RuntimeException('Roadmap task not found for learner');
        return $row;
    }

    private function latestTaskStatus(string $taskId): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM learner_ai_roadmap_task_events WHERE taskId = :taskId ORDER BY occurredAt DESC, createdAt DESC, id DESC LIMIT 1');
        $statement->execute(['taskId'=>$taskId]);
        $status = $statement->fetchColumn();
        return $status === false ? 'not_started' : (string) $status;
    }

    /** @return array<string,mixed>|null */
    private function eventByRequest(string $studentId, string $taskId, string $requestId): ?array
    {
        $statement = $this->pdo->prepare('SELECT events.* FROM learner_ai_roadmap_task_events AS events INNER JOIN learner_ai_roadmap_tasks AS tasks ON tasks.id = events.taskId INNER JOIN learner_ai_roadmap_phases AS phases ON phases.id = tasks.phaseId INNER JOIN learner_ai_roadmaps AS roadmaps ON roadmaps.id = phases.roadmapId WHERE events.taskId = :taskId AND events.requestId = :requestId AND roadmaps.studentId = :studentId');
        $statement->execute(['taskId'=>$taskId,'requestId'=>$requestId,'studentId'=>$studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $event @return array<string,mixed> */
    private function eventResponse(array $event, bool $reused): array
    {
        return ['event_id'=>$event['id'],'task_id'=>$event['taskId'],'student_id'=>$event['studentId'],'status'=>$event['status'],'request_id'=>$event['requestId'],'occurred_at'=>$event['occurredAt'],'reused'=>$reused];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function refinementResponse(array $row): array
    {
        return [
            'state'=>'refinement_ready', 'preview_id'=>(string)$row['id'], 'roadmap_id'=>(string)$row['roadmapId'],
            'base_version'=>(int)$row['baseVersion'], 'learner_draft_hash'=>(string)$row['learnerDraftHash'],
            'learner_draft'=>self::decode((string)$row['learnerDraftJson']), 'ai_draft_hash'=>(string)$row['aiDraftHash'],
            'ai_draft'=>self::decode((string)$row['aiDraftJson']), 'expires_at'=>(string)$row['expiresAt'],
            'created_at'=>(string)$row['createdAt'], 'engine'=>[
                'provider'=>(string)$row['provider'], 'model_version'=>(string)$row['modelVersion'],
                'prompt_version'=>(string)$row['promptVersion'],
                'provider_request_id'=>$this->optionalStoredValue($row['providerRequestId'] ?? null),
                'response_hash'=>(string)$row['responseHash'],
            ],
        ];
    }

    private function customizationByRequest(string $studentId, string $requestId): ?string
    {
        $statement = $this->pdo->prepare("SELECT roadmaps.id FROM learner_recommendation_audit_events AS events INNER JOIN learner_ai_roadmaps AS roadmaps ON roadmaps.runId = events.runId AND roadmaps.studentId = events.studentId WHERE events.studentId = :studentId AND events.requestId = :requestId AND events.action = 'roadmap_customization_applied' LIMIT 1");
        $statement->execute(['studentId'=>$studentId,'requestId'=>$requestId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    /** @return array<string,array<string,mixed>> */
    private function sourcePlan(string $roadmapId): array
    {
        $phaseStatement = $this->pdo->prepare('SELECT * FROM learner_ai_roadmap_phases WHERE roadmapId = :roadmapId ORDER BY position ASC');
        $phaseStatement->execute(['roadmapId'=>$roadmapId]);
        $result = [];
        foreach ($phaseStatement->fetchAll(PDO::FETCH_ASSOC) as $phase) {
            $taskStatement = $this->pdo->prepare('SELECT * FROM learner_ai_roadmap_tasks WHERE phaseId = :phaseId ORDER BY position ASC');
            $taskStatement->execute(['phaseId'=>$phase['id']]);
            $tasks = [];
            foreach ($taskStatement->fetchAll(PDO::FETCH_ASSOC) as $task) $tasks[(string)$task['id']] = $task;
            $phase['tasks'] = $tasks;
            $result[(string)$phase['id']] = $phase;
        }
        return $result;
    }

    /** @param array<string,array<string,mixed>> $sourcePhases */
    private function assertDraftAgainstSource(RoadmapEditorDraft $draft, array $sourcePhases): void
    {
        $phases = $draft->toArray()['phases'];
        if (count($sourcePhases) !== 3) throw new RuntimeException('Roadmap base structure is invalid');
        $sourceTaskPhases = [];
        foreach ($sourcePhases as $sourcePhaseId => $sourcePhase) {
            foreach (($sourcePhase['tasks'] ?? []) as $sourceTaskId => $_sourceTask) {
                $sourceTaskPhases[$sourceTaskId] = $sourcePhaseId;
            }
        }
        foreach ($phases as $phase) {
            $source = $sourcePhases[$phase['phase_id']] ?? null;
            if (!is_array($source)
                || (int)$source['position'] !== $phase['position']
                || (int)$source['startDay'] !== $phase['start_day']
                || (int)$source['endDay'] !== $phase['end_day']
                || (string)$source['code'] !== $phase['code']) {
                throw new RuntimeException('Roadmap draft changed an immutable phase');
            }
            foreach ($phase['tasks'] as $task) {
                $ownerPhaseId = $sourceTaskPhases[$task['task_id']] ?? null;
                if ($ownerPhaseId !== null && $ownerPhaseId !== $phase['phase_id']) {
                    throw new RuntimeException('Roadmap draft moved a retained task between phases');
                }
            }
        }
    }

    /** @param array<string,mixed> $audit */
    private function auditValue(array $audit, string $key, int $maximum): string
    {
        $value = $audit[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > $maximum) {
            throw new \InvalidArgumentException('Roadmap refinement audit is invalid.');
        }
        return trim($value);
    }

    /** @param array<string,mixed> $audit */
    private function optionalAuditValue(array $audit, string $key, int $maximum): ?string
    {
        $value = $audit[$key] ?? null;
        if ($value === null) return null;
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > $maximum) {
            throw new \InvalidArgumentException('Roadmap refinement audit is invalid.');
        }
        return trim($value);
    }

    private function optionalStoredValue(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /** @param array<string,mixed> $audit */
    private function auditHash(array $audit, string $key): string
    {
        $value = $audit[$key] ?? null;
        if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Roadmap refinement audit hash is invalid.');
        }
        return $value;
    }

    /** @template T @param callable():T $operation @return T */
    private function transaction(callable $operation): mixed
    {
        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try { $result = $operation(); if ($started) $this->pdo->commit(); return $result; }
        catch (\Throwable $exception) { if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack(); throw $exception; }
    }

    private function now(): string { return $this->databaseTimestamp(($this->clock)()); }
    private function databaseTimestamp(string $value): string { return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'); }
    private function required(string $value, string $message): string { $value = trim($value); if ($value === '') throw new \InvalidArgumentException($message); return $value; }
    private static function uuid(): string { $bytes=random_bytes(16); $bytes[6]=chr((ord($bytes[6])&0x0f)|0x40); $bytes[8]=chr((ord($bytes[8])&0x3f)|0x80); $hex=bin2hex($bytes); return sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20)); }
    private static function json(mixed $value): string { return json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
    /** @return array<mixed> */ private static function decode(string $value): array { try { $decoded=json_decode($value,true,512,JSON_THROW_ON_ERROR); } catch (JsonException $e) { throw new RuntimeException('Stored roadmap JSON is invalid',0,$e); } if(!is_array($decoded)) throw new RuntimeException('Stored roadmap JSON is invalid'); return $decoded; }
}
