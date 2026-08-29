<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\OpportunityMatch;

/**
 * Database-backed opportunity match repository. Every run this repository
 * reads or writes is scoped to capability = 'opportunity_match' so generic
 * recommendation and roadmap runs on the shared table are never mixed in.
 */
final class DatabaseOpportunityMatchRepository implements OpportunityMatchRepository
{
    private const CAPABILITY = 'opportunity_match';

    private const PENDING_RULE_VERSION = 'opportunity-match-pending-1.0.0';

    /** @var \Closure():string */
    private readonly \Closure $clock;

    /** @param null|callable():string $clock */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $providerVersion,
        private readonly string $modelVersion,
        private readonly string $promptVersion,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): string => gmdate('Y-m-d\TH:i:s.uP')
            : \Closure::fromCallable($clock);
    }

    /** @param list<string> $activeCatalogIds @return array<string,mixed>|null */
    public function latestValid(string $studentId, array $activeCatalogIds): ?array
    {
        $active = [];
        foreach ($activeCatalogIds as $catalogId) {
            if (is_string($catalogId) && $catalogId !== '') {
                $active[$catalogId] = true;
            }
        }
        $statement = $this->pdo->prepare(
            "SELECT id FROM learner_recommendation_runs AS runs
             WHERE runs.studentId = :studentId
               AND runs.capability = :capability
               AND runs.status = 'completed'
             ORDER BY runs.createdAt DESC, runs.id DESC"
        );
        $statement->execute(['studentId' => $studentId, 'capability' => self::CAPABILITY]);
        $candidateRunIds = array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'id');

        foreach ($candidateRunIds as $runId) {
            $run = $this->runForStudent($studentId, (string) $runId);
            if ($run === null) {
                continue;
            }
            $runState = (string) ($run['analysis']['state'] ?? ($run['items'] === [] ? '' : 'ready_model'));
            $valid = count($run['items']) >= 0 && count($run['items']) <= 3;
            if ($run['items'] === [] && $runState !== 'no_fit_model') {
                $valid = false;
            }
            if ($run['items'] !== [] && !in_array($runState, ['ready_model', 'partial_model', 'low_fit_model'], true)) {
                $valid = false;
            }
            if (count($run['items']) !== 3 && !in_array($runState, ['partial_model', 'low_fit_model'], true)) {
                $valid = false;
            }
            $catalogIds = [];
            $ranks = [];
            foreach ($run['items'] as $item) {
                $catalogId = (string) ($item['catalogId'] ?? '');
                $rank = filter_var($item['rankPosition'] ?? null, FILTER_VALIDATE_INT);
                $structuredScore = filter_var($item['structuredScore'] ?? null, FILTER_VALIDATE_INT);
                $geminiScore = filter_var($item['geminiScore'] ?? null, FILTER_VALIDATE_INT);
                $matchScore = filter_var($item['matchScore'] ?? null, FILTER_VALIDATE_INT);
                if ($catalogId === ''
                    || !isset($active[$catalogId])
                    || isset($catalogIds[$catalogId])
                    || $rank === false
                    || $structuredScore === false || $structuredScore < 0 || $structuredScore > 100
                    || $geminiScore === false || $geminiScore < 0 || $geminiScore > 100
                    || $matchScore === false || $matchScore < 0 || $matchScore > 100) {
                    $valid = false;
                    break;
                }
                $catalogIds[$catalogId] = true;
                $ranks[] = $rank;
            }
            sort($ranks);
            $expectedRanks = $run['items'] === [] ? [] : range(1, count($run['items']));
            $valid = $valid && $ranks === $expectedRanks;
            if ($run['items'] === [] && !is_array($run['analysis'] ?? null)) {
                $valid = false;
            }
            if ($valid) {
                return $run;
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array
    {
        $studentId = $this->required($studentId, 'Opportunity match student id is required.');
        $idempotencyKey = $this->required((string) ($context->idempotencyKey() ?? ''), 'Opportunity match idempotency key is required.');
        if (strlen($idempotencyKey) > 100) {
            throw new \InvalidArgumentException('Opportunity match idempotency key is too long.');
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return $this->transaction(function () use ($studentId, $input, $context, $idempotencyKey): array {
                    $existing = $this->runByIdempotency($studentId, $idempotencyKey);
                    if ($existing !== null) {
                        $existing['reused'] = true;
                        return $existing;
                    }

                    $snapshotId = $this->findSnapshotId($studentId, $input->contentHash());
                    if ($snapshotId === null) {
                        $snapshotId = self::uuid();
                        $createdSnapshot = false;
                        try {
                            $this->insertSnapshot($snapshotId, $studentId, $input, $context->allowedScopes());
                            $createdSnapshot = true;
                        } catch (PDOException $exception) {
                            $snapshotId = $this->findSnapshotId($studentId, $input->contentHash());
                            if ($snapshotId === null) {
                                throw $exception;
                            }
                        }
                        if ($createdSnapshot) {
                            $this->insertSnapshotEvidence($snapshotId, $input);
                        }
                    }

                    $runId = self::uuid();
                    $now = $this->now();
                    $insert = $this->pdo->prepare(
                        "INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, ruleVersion, provider, modelVersion, promptVersion, fallbackReason, safeErrorCode, capability, startedAt, completedAt, createdAt) VALUES (:id, :studentId, :snapshotId, :idempotencyKey, 'rule', 'pending', :ruleVersion, NULL, NULL, NULL, NULL, NULL, :capability, :startedAt, NULL, :createdAt)"
                    );
                    $insert->execute([
                        'id' => $runId,
                        'studentId' => $studentId,
                        'snapshotId' => $snapshotId,
                        'idempotencyKey' => $idempotencyKey,
                        'ruleVersion' => self::PENDING_RULE_VERSION,
                        'capability' => self::CAPABILITY,
                        'startedAt' => $now,
                        'createdAt' => $now,
                    ]);
                    $this->insertAuditEvent($runId, $studentId, $context->requestId() ?? self::uuid(), 'opportunity_match_run_created', ['capability' => self::CAPABILITY], 'pending');

                    return $this->pendingRunResponse($runId, $snapshotId, $studentId, $idempotencyKey, false);
                });
            } catch (PDOException $exception) {
                $existing = $this->runByIdempotency($studentId, $idempotencyKey);
                if ($existing !== null) {
                    $existing['reused'] = true;
                    return $existing;
                }
                if ($attempt === 0 && $this->findSnapshotId($studentId, $input->contentHash()) !== null) {
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Opportunity match snapshot retry was exhausted');
    }

    /** @param list<OpportunityMatch> $matches @param array<string,mixed> $analysis @return array<string,mixed> */
    public function completeRun(string $studentId, string $runId, array $matches, array $analysis = [], string $state = 'ready_model'): array
    {
        $studentId = $this->required($studentId, 'Opportunity match student id is required.');
        $runId = $this->required($runId, 'Opportunity match run id is required.');
        if (count($matches) > 3) {
            throw new \InvalidArgumentException('Opportunity match completion accepts at most three items.');
        }
        if (!in_array($state, ['ready_model', 'partial_model', 'low_fit_model', 'no_fit_model'], true)) {
            throw new \InvalidArgumentException('Opportunity match completion state is not supported.');
        }
        if ($matches === [] && ($state !== 'no_fit_model' || $analysis === [])) {
            throw new \InvalidArgumentException('A no-fit opportunity run requires run-level analysis.');
        }

        return $this->transaction(function () use ($studentId, $runId, $matches, $analysis, $state): array {
            $run = $this->ownedRun($studentId, $runId);
            if ($run['status'] !== 'pending') {
                throw new RuntimeException('Opportunity match run is not pending');
            }
            $snapshotId = (string) ($run['snapshotId'] ?? '');
            $now = $this->now();

            foreach ($matches as $rank => $match) {
                if (!$match instanceof OpportunityMatch) {
                    throw new \InvalidArgumentException('Opportunity match completion expects OpportunityMatch items.');
                }
                $itemId = self::uuid();
                $this->insertItem($itemId, $runId, $match, $rank + 1, $now);
                foreach ($match->evidenceRefs() as $evidenceRef) {
                    $snapshotEvidence = $this->snapshotEvidence($snapshotId, $evidenceRef);
                    if ($snapshotEvidence === null) {
                        throw new RuntimeException('Opportunity match evidence is not part of the run snapshot: ' . $evidenceRef);
                    }
                    $this->insertEvidence($itemId, $snapshotEvidence, $now);
                }
            }

            $this->supersedePreviousRuns($studentId, $runId);

            $update = $this->pdo->prepare(
                "UPDATE learner_recommendation_runs SET engineType = 'model', status = 'completed', ruleVersion = NULL, provider = :provider, modelVersion = :modelVersion, promptVersion = :promptVersion, fallbackReason = NULL, safeErrorCode = NULL, analysisJson = :analysisJson, completedAt = :completedAt WHERE id = :runId AND studentId = :studentId AND status = 'pending' AND capability = :capability"
            );
            $update->execute([
                'provider' => $this->providerVersion,
                'modelVersion' => $this->modelVersion,
                'promptVersion' => $this->promptVersion,
                'analysisJson' => self::json(array_merge($analysis, ['state' => $state])),
                'completedAt' => $now,
                'runId' => $runId,
                'studentId' => $studentId,
                'capability' => self::CAPABILITY,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Opportunity match run is not pending');
            }
            $this->insertAuditEvent($runId, $studentId, self::uuid(), 'opportunity_match_completed', [
                'provider' => $this->providerVersion,
                'model_version' => $this->modelVersion,
                'prompt_version' => $this->promptVersion,
                'response_hash' => self::responseHash($matches, $analysis, $state),
            ], 'completed');

            $completed = $this->runForStudent($studentId, $runId);
            if ($completed === null) {
                throw new RuntimeException('Opportunity match run not found for learner');
            }
            return $completed;
        });
    }

    public function failRun(string $studentId, string $runId, string $safeCode): void
    {
        $studentId = $this->required($studentId, 'Opportunity match student id is required.');
        $runId = $this->required($runId, 'Opportunity match run id is required.');
        $safeCode = $this->required($safeCode, 'Opportunity match safe error code is required.');
        if (strlen($safeCode) > 100) {
            throw new \InvalidArgumentException('Opportunity match safe error code is too long.');
        }

        $this->transaction(function () use ($studentId, $runId, $safeCode): void {
            $run = $this->ownedRun($studentId, $runId);
            if ($run['status'] !== 'pending') {
                throw new RuntimeException('Opportunity match run is not pending');
            }
            $update = $this->pdo->prepare(
                "UPDATE learner_recommendation_runs SET status = 'failed', safeErrorCode = :safeErrorCode, completedAt = :completedAt WHERE id = :runId AND studentId = :studentId AND status = 'pending' AND capability = :capability"
            );
            $update->execute([
                'safeErrorCode' => $safeCode,
                'completedAt' => $this->now(),
                'runId' => $runId,
                'studentId' => $studentId,
                'capability' => self::CAPABILITY,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Opportunity match run is not pending');
            }
            $this->insertAuditEvent($runId, $studentId, self::uuid(), 'opportunity_match_failed', ['safe_error_code' => $safeCode], 'failed');
        });
    }

    private function insertSnapshot(string $snapshotId, string $studentId, RecommendationInput $input, array $allowedScopes): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO learner_recommendation_input_snapshots (id, studentId, schemaVersion, contentHash, consentScopesJson, qualityFlagsJson, payloadJson, sourceUpdatedAt, createdAt) VALUES (:id, :studentId, :schemaVersion, :contentHash, :consentScopesJson, :qualityFlagsJson, :payloadJson, :sourceUpdatedAt, :createdAt)'
        );
        $insert->execute([
            'id' => $snapshotId,
            'studentId' => $studentId,
            'schemaVersion' => $input->schemaVersion(),
            'contentHash' => $input->contentHash(),
            'consentScopesJson' => self::json($allowedScopes),
            'qualityFlagsJson' => self::json($input->qualityFlags()),
            'payloadJson' => self::json($input->payload()),
            'sourceUpdatedAt' => self::json($input->sourceUpdatedAt()),
            'createdAt' => $this->now(),
        ]);
    }

    private function insertSnapshotEvidence(string $snapshotId, RecommendationInput $input): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO learner_recommendation_snapshot_evidence (id, snapshotId, sourceType, sourceId, observedAt, safeValueJson, createdAt) VALUES (:id, :snapshotId, :sourceType, :sourceId, :observedAt, :safeValueJson, :createdAt)'
        );
        $normalized = [];
        foreach ($input->evidenceReferences() as $reference) {
            $sourceType = $this->required((string) ($reference['source_type'] ?? ''), 'Opportunity match snapshot evidence source type is required.');
            $sourceId = $this->required((string) ($reference['source_id'] ?? ''), 'Opportunity match snapshot evidence source id is required.');
            $safeValue = $reference['safe_value'] ?? null;
            if (!is_array($safeValue)) {
                throw new \InvalidArgumentException('Opportunity match snapshot evidence safe value is required.');
            }
            $persistableType = self::persistableSourceType($sourceType);
            $storageKey = $persistableType . ':' . $sourceId;
            $safeValueJson = self::json($safeValue);
            $observedAt = $this->databaseTimestamp($reference['observed_at'] ?? null);
            if (isset($normalized[$storageKey])) {
                $existing = $normalized[$storageKey];
                if ($existing['logicalType'] === $sourceType) {
                    if ($existing['safeValueJson'] === $safeValueJson && $existing['observedAt'] === $observedAt) {
                        continue;
                    }
                    throw new RuntimeException('Opportunity match snapshot contains conflicting duplicate evidence: ' . $sourceType . ':' . $sourceId);
                }
                $logicalPair = [$existing['logicalType'], $sourceType];
                sort($logicalPair, SORT_STRING);
                if ($logicalPair !== ['catalog', 'opportunity']) {
                    throw new RuntimeException('Opportunity match evidence normalization collision: ' . $storageKey);
                }
                if ($sourceType === 'opportunity') {
                    $normalized[$storageKey] = [
                        'logicalType' => $sourceType,
                        'sourceType' => $persistableType,
                        'sourceId' => $sourceId,
                        'observedAt' => $observedAt,
                        'safeValueJson' => $safeValueJson,
                    ];
                }
                continue;
            }
            $normalized[$storageKey] = [
                'logicalType' => $sourceType,
                'sourceType' => $persistableType,
                'sourceId' => $sourceId,
                'observedAt' => $observedAt,
                'safeValueJson' => $safeValueJson,
            ];
        }
        ksort($normalized, SORT_STRING);
        foreach ($normalized as $reference) {
            $insert->execute([
                'id' => self::uuid(),
                'snapshotId' => $snapshotId,
                'sourceType' => $reference['sourceType'],
                'sourceId' => $reference['sourceId'],
                'observedAt' => $reference['observedAt'],
                'safeValueJson' => $reference['safeValueJson'],
                'createdAt' => $this->now(),
            ]);
        }
    }

    private function insertItem(string $itemId, string $runId, OpportunityMatch $match, int $rank, string $createdAt): void
    {
        $candidate = $match->candidate();
        $score = $match->score();
        if ($score === null) {
            throw new \InvalidArgumentException('Opportunity match item requires an attached score before persistence.');
        }
        $candidatePayload = $candidate->providerPayload();
        $summary = is_string($candidatePayload['summary'] ?? null) && $candidatePayload['summary'] !== ''
            ? $candidatePayload['summary']
            : $candidate->title();
        $breakdown = $score->breakdown();
        $analysis = [
            'analysis_kind' => $match->analysisKind(),
            'why_fit' => $match->whyFit(),
            'why_not_fit_yet' => $match->whyNotFitYet(),
            'matched_skill_codes' => $match->matchedSkillCodes(),
            'missing_skill_codes' => $match->missingSkillCodes(),
            'missing_conditions' => $match->missingConditions(),
            'improvement_steps' => $match->improvementSteps(),
            'expected_outcome_codes' => $match->expectedOutcomeCodes(),
            'breakdown' => $breakdown,
            'evidence_ref_ids' => $match->evidenceRefs(),
        ];
        $action = [
            'catalog_id' => $candidate->catalogId(),
            'url' => $candidate->canonicalUrl(),
        ];

        $insert = $this->pdo->prepare(
            "INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus, catalogId, rankPosition, structuredScore, geminiScore, matchScore, analysisJson, createdAt) VALUES (:id, :runId, 'activity', :title, :summary, :priority, :confidenceBand, :actionJson, 'active', :catalogId, :rankPosition, :structuredScore, :geminiScore, :matchScore, :analysisJson, :createdAt)"
        );
        $insert->execute([
            'id' => $itemId,
            'runId' => $runId,
            'title' => $candidate->title(),
            'summary' => $summary,
            'priority' => $rank,
            'confidenceBand' => self::confidenceBand($score->finalScore()),
            'actionJson' => self::json($action),
            'catalogId' => $candidate->catalogId(),
            'rankPosition' => $rank,
            'structuredScore' => $score->structuredScore(),
            'geminiScore' => $match->geminiScore(),
            'matchScore' => $score->finalScore(),
            'analysisJson' => self::json($analysis),
            'createdAt' => $createdAt,
        ]);
    }

    /** @param array{id:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string} $snapshotEvidence */
    private function insertEvidence(string $itemId, array $snapshotEvidence, string $createdAt): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO learner_recommendation_evidence (id, itemId, snapshotEvidenceId, sourceType, sourceId, observedAt, contributionLabel, safeValueJson, createdAt) VALUES (:id, :itemId, :snapshotEvidenceId, :sourceType, :sourceId, :observedAt, :contributionLabel, :safeValueJson, :createdAt)'
        );
        $insert->execute([
            'id' => self::uuid(),
            'itemId' => $itemId,
            'snapshotEvidenceId' => $snapshotEvidence['id'],
            'sourceType' => $snapshotEvidence['sourceType'],
            'sourceId' => $snapshotEvidence['sourceId'],
            'observedAt' => $snapshotEvidence['observedAt'],
            'contributionLabel' => 'opportunity_match_evidence',
            'safeValueJson' => $snapshotEvidence['safeValueJson'],
            'createdAt' => $createdAt,
        ]);
    }

    private function supersedePreviousRuns(string $studentId, string $runId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE learner_recommendation_items
             SET lifecycleStatus = 'superseded'
             WHERE lifecycleStatus = 'active'
               AND runId IN (
                 SELECT runs.id
                 FROM learner_recommendation_runs AS runs
                 WHERE runs.studentId = :studentId
                   AND runs.capability = :capability
                   AND runs.id <> :runId
             )"
        );
        $statement->execute(['studentId' => $studentId, 'capability' => self::CAPABILITY, 'runId' => $runId]);
    }

    /** @param array<string,mixed> $metadata */
    private function insertAuditEvent(string $runId, string $studentId, string $requestId, string $action, array $metadata, string $status): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO learner_recommendation_audit_events (id, runId, studentId, requestId, actorType, action, engineMetadataJson, status, createdAt) VALUES (:id, :runId, :studentId, :requestId, :actorType, :action, :engineMetadataJson, :status, :createdAt)'
        );
        $insert->execute([
            'id' => self::uuid(),
            'runId' => $runId,
            'studentId' => $studentId,
            'requestId' => $requestId,
            'actorType' => 'system',
            'action' => $action,
            'engineMetadataJson' => self::json($metadata),
            'status' => $status,
            'createdAt' => $this->now(),
        ]);
    }

    /** @return array{id:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string}|null */
    private function snapshotEvidence(string $snapshotId, string $evidenceRef): ?array
    {
        $parts = explode(':', $evidenceRef, 2);
        $sourceType = count($parts) === 2 ? $parts[0] : '';
        $sourceId = count($parts) === 2 ? $parts[1] : $evidenceRef;
        $statement = $this->pdo->prepare(
            'SELECT evidence.id, evidence.sourceType, evidence.sourceId, evidence.observedAt, evidence.safeValueJson FROM learner_recommendation_snapshot_evidence AS evidence WHERE evidence.snapshotId = :snapshotId AND evidence.sourceType = :sourceType AND evidence.sourceId = :sourceId LIMIT 1'
        );
        $statement->execute(['snapshotId' => $snapshotId, 'sourceType' => self::persistableSourceType($sourceType), 'sourceId' => $sourceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * The shared recommendation evidence schema stores five stable evidence
     * families. Opportunity matching keeps the original logical refs in its
     * analysis JSON, while snapshot/evidence rows use the compatible family
     * below. Unknown types are deliberately returned unchanged so the schema
     * CHECK continues to fail closed.
     */
    private static function persistableSourceType(string $sourceType): string
    {
        return match ($sourceType) {
            'certificate', 'badge', 'achievement' => 'skill',
            'profile' => 'assessment',
            'project', 'activity', 'progress', 'checkin' => 'activity_experience',
            'mentor_evaluation', 'teacher_feedback', 'roadmap_feedback' => 'evaluation',
            'catalog' => 'opportunity',
            default => $sourceType,
        };
    }

    private function findSnapshotId(string $studentId, string $contentHash): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM learner_recommendation_input_snapshots WHERE studentId = :studentId AND contentHash = :contentHash'
        );
        $statement->execute(['studentId' => $studentId, 'contentHash' => $contentHash]);
        $snapshotId = $statement->fetchColumn();
        return $snapshotId === false ? null : (string) $snapshotId;
    }

    /** @return array<string,mixed>|null */
    private function runByIdempotency(string $studentId, string $idempotencyKey): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, snapshotId, studentId, idempotencyKey, status, safeErrorCode FROM learner_recommendation_runs WHERE studentId = :studentId AND idempotencyKey = :idempotencyKey AND capability = :capability"
        );
        $statement->execute(['studentId' => $studentId, 'idempotencyKey' => $idempotencyKey, 'capability' => self::CAPABILITY]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    private function ownedRun(string $studentId, string $runId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, snapshotId, studentId, status FROM learner_recommendation_runs WHERE id = :runId AND studentId = :studentId AND capability = :capability"
        );
        $statement->execute(['runId' => $runId, 'studentId' => $studentId, 'capability' => self::CAPABILITY]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Opportunity match run not found for learner');
        }
        return $row;
    }

    /** @return array<string,mixed>|null */
    private function runForStudent(string $studentId, string $runId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM learner_recommendation_runs WHERE id = :runId AND studentId = :studentId AND capability = :capability"
        );
        $statement->execute(['runId' => $runId, 'studentId' => $studentId, 'capability' => self::CAPABILITY]);
        $run = $statement->fetch(PDO::FETCH_ASSOC);
        if ($run === false) {
            return null;
        }

        $items = $this->pdo->prepare(
            "SELECT items.id, items.itemType, items.title, items.summary, items.priority, items.confidenceBand, items.actionJson, items.lifecycleStatus, items.catalogId, items.rankPosition, items.structuredScore, items.geminiScore, items.matchScore, items.analysisJson, items.createdAt FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE items.runId = :runId AND runs.studentId = :studentId AND runs.capability = :capability AND items.lifecycleStatus = 'active' ORDER BY items.rankPosition ASC, items.id ASC"
        );
        $items->execute(['runId' => $runId, 'studentId' => $studentId, 'capability' => self::CAPABILITY]);
        $run['runId'] = $run['id'];
        unset($run['id']);
        $run['pendingStatus'] = $run['status'];
        $run['freshness_status'] = $run['status'] === 'completed' ? 'fresh' : 'unavailable';
        $run['analysis'] = self::decodeJson($run['analysisJson'] ?? null);
        $run['items'] = [];
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $evidence = $this->pdo->prepare(
                'SELECT evidence.id, evidence.snapshotEvidenceId, evidence.sourceType, evidence.sourceId, evidence.observedAt, evidence.contributionLabel, evidence.safeValueJson, evidence.createdAt FROM learner_recommendation_evidence AS evidence INNER JOIN learner_recommendation_items AS items ON items.id = evidence.itemId INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.capability = :capability AND evidence.itemId = :itemId AND runs.studentId = :studentId ORDER BY evidence.id ASC'
            );
            $evidence->execute(['capability' => self::CAPABILITY, 'itemId' => $item['id'], 'studentId' => $studentId]);
            $item['itemId'] = $item['id'];
            unset($item['id']);
            $item['evidence'] = $evidence->fetchAll(PDO::FETCH_ASSOC);
            $run['items'][] = $item;
        }
        $run['state'] = (string) ($run['analysis']['state'] ?? ($run['items'] === [] ? 'no_fit_model' : 'ready_model'));
        return $run;
    }

    /** @return array<string,mixed> */
    private function pendingRunResponse(string $runId, string $snapshotId, string $studentId, string $idempotencyKey, bool $reused): array
    {
        return [
            'runId' => $runId,
            'snapshotId' => $snapshotId,
            'studentId' => $studentId,
            'idempotencyKey' => $idempotencyKey,
            'status' => 'pending',
            'reused' => $reused,
        ];
    }

    /** @param list<OpportunityMatch> $matches @param array<string,mixed> $analysis */
    private static function responseHash(array $matches, array $analysis = [], string $state = 'ready_model'): string
    {
        $canonical = [];
        foreach ($matches as $match) {
            $canonical[] = [
                'catalog_id' => $match->candidate()->catalogId(),
                'gemini_score' => $match->geminiScore(),
                'why_fit' => $match->whyFit(),
                'matched_skill_codes' => $match->matchedSkillCodes(),
                'missing_skill_codes' => $match->missingSkillCodes(),
                'expected_outcome_codes' => $match->expectedOutcomeCodes(),
                'evidence_ref_ids' => $match->evidenceRefs(),
            ];
        }
        return hash('sha256', self::json(['state' => $state, 'items' => $canonical, 'analysis' => $analysis]));
    }

    private static function confidenceBand(int $matchScore): string
    {
        if ($matchScore >= 80) {
            return 'high';
        }
        if ($matchScore >= 50) {
            return 'medium';
        }
        return 'low';
    }

    /** @template T @param callable():T $operation @return T */
    private function transaction(callable $operation): mixed
    {
        $startsTransaction = !$this->pdo->inTransaction();
        if ($startsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($startsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $exception) {
            if ($startsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function now(): string
    {
        $timestamp = ($this->clock)();
        if (!is_string($timestamp) || trim($timestamp) === '') {
            throw new RuntimeException('Opportunity match repository clock must return a non-empty timestamp.');
        }
        return $this->databaseTimestamp($timestamp) ?? throw new RuntimeException('Opportunity match repository clock must return a timestamp.');
    }

    private function databaseTimestamp(?string $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u');
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Opportunity match timestamp must be a valid UTC-compatible value.', 0, $exception);
        }
    }

    private function required(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private static function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Opportunity match persistence value must be JSON serializable.', 0, $exception);
        }
    }

    /** @return array<string,mixed> */
    private static function decodeJson(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }
}
