<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;

final class DatabaseRecommendationRepository implements RecommendationRepository
{
    private const PENDING_RULE_VERSION = 'repository-pending-1.0.0';

    /** @var \Closure():string */
    private readonly \Closure $clock;

    /** @param null|callable():string $clock */
    public function __construct(private readonly PDO $pdo, ?callable $clock = null)
    {
        $this->clock = $clock === null
            ? static fn (): string => gmdate('Y-m-d\TH:i:s.uP')
            : \Closure::fromCallable($clock);
    }

    /** @return array<string,mixed> */
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array
    {
        $studentId = $this->required($studentId, 'Student id is required.');
        $idempotencyKey = $this->required($context->idempotencyKey() ?? '', 'Recommendation idempotency key is required.');
        if (strlen($idempotencyKey) > 100) {
            throw new \InvalidArgumentException('Recommendation idempotency key is too long.');
        }
        $this->assertContextScopesMatchInput($input, $context);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return $this->transaction(function () use ($studentId, $input, $context, $idempotencyKey): array {
                    $existing = $this->runByIdempotency($studentId, $idempotencyKey);
                    if ($existing !== null) {
                        return $this->pendingRunResponse($existing, true);
                    }

                    $snapshotId = $this->findSnapshotId($studentId, $input->contentHash());
                    if ($snapshotId === null) {
                        $snapshotId = self::uuid();
                        $createdSnapshot = false;
                        try {
                            $this->insertSnapshot($snapshotId, $studentId, $input, $context->allowedScopes());
                            $createdSnapshot = true;
                        } catch (PDOException $exception) {
                            // A concurrent request may have committed this exact learner/hash after our read.
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
                        'INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, ruleVersion, provider, modelVersion, promptVersion, fallbackReason, safeErrorCode, startedAt, completedAt, createdAt) VALUES (:id, :studentId, :snapshotId, :idempotencyKey, :engineType, :status, :ruleVersion, NULL, NULL, NULL, NULL, NULL, :startedAt, NULL, :createdAt)'
                    );
                    $insert->execute([
                        'id' => $runId,
                        'studentId' => $studentId,
                        'snapshotId' => $snapshotId,
                        'idempotencyKey' => $idempotencyKey,
                        'engineType' => 'rule',
                        'status' => 'pending',
                        'ruleVersion' => self::PENDING_RULE_VERSION,
                        'startedAt' => $now,
                        'createdAt' => $now,
                    ]);
                    $this->insertAuditEvent($runId, $studentId, $context->requestId() ?? self::uuid(), 'run_created', ['engine_type' => 'rule'], 'pending');

                    return [
                        'runId' => $runId,
                        'snapshotId' => $snapshotId,
                        'studentId' => $studentId,
                        'idempotencyKey' => $idempotencyKey,
                        'status' => 'pending',
                        'reused' => false,
                    ];
                });
            } catch (PDOException $exception) {
                $existing = $this->runByIdempotency($studentId, $idempotencyKey);
                if ($existing !== null) {
                    return $this->pendingRunResponse($existing, true);
                }
                if ($attempt === 0 && $this->findSnapshotId($studentId, $input->contentHash()) !== null) {
                    // Roll back and retry so snapshot reuse also works under transaction snapshot isolation.
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Recommendation snapshot retry was exhausted');
    }

    /** @return array<string,mixed> */
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array
    {
        $studentId = $this->required($studentId, 'Student id is required.');
        $runId = $this->required($runId, 'Recommendation run id is required.');

        return $this->transaction(function () use ($studentId, $runId, $result): array {
            $run = $this->ownedRun($studentId, $runId);
            if ($run['status'] !== 'pending') {
                throw new RuntimeException('Recommendation run is not pending');
            }
            $now = $this->now();

            foreach ($result->items() as $item) {
                $itemId = self::uuid();
                $this->insertItem($itemId, $runId, $item, $now);
                foreach ($item->evidence() as $evidence) {
                    $snapshotEvidence = $this->snapshotEvidence($studentId, (string) $run['snapshotId'], $evidence);
                    if ($snapshotEvidence === null) {
                        throw new RuntimeException('Recommendation evidence is not part of run snapshot');
                    }
                    $this->insertEvidence($itemId, $snapshotEvidence, $evidence->contributionLabel(), $now);
                }
            }

            $status = $result->fallbackReason() === null ? 'completed' : 'fallback';
            $update = $this->pdo->prepare(
                'UPDATE learner_recommendation_runs SET engineType = :engineType, status = :status, ruleVersion = :ruleVersion, provider = :provider, modelVersion = :modelVersion, promptVersion = :promptVersion, fallbackReason = :fallbackReason, safeErrorCode = NULL, completedAt = :completedAt WHERE id = :runId AND studentId = :studentId AND status = :pendingStatus'
            );
            $update->execute([
                'engineType' => $result->engineType(),
                'status' => $status,
                'ruleVersion' => $result->ruleVersion(),
                'provider' => $result->provider(),
                'modelVersion' => $result->modelVersion(),
                'promptVersion' => $result->promptVersion(),
                'fallbackReason' => $result->fallbackReason(),
                'completedAt' => $now,
                'runId' => $runId,
                'studentId' => $studentId,
                'pendingStatus' => 'pending',
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Recommendation run is not pending');
            }
            $this->insertAuditEvent($runId, $studentId, self::uuid(), 'run_completed', $result->engineMetadata(), $status);

            $completed = $this->runForStudent($studentId, $runId);
            if ($completed === null) {
                throw new RuntimeException('Recommendation run not found for learner');
            }
            return $completed;
        });
    }

    /** @return array<string,mixed> */
    public function completeRoadmapRun(string $studentId, string $runId, RoadmapAnalysis $analysis): array
    {
        $studentId = $this->required($studentId, 'Student id is required.');
        $runId = $this->required($runId, 'Recommendation run id is required.');

        return $this->transaction(function () use ($studentId, $runId, $analysis): array {
            $run = $this->ownedRun($studentId, $runId);
            if ($run['status'] !== 'pending') {
                throw new RuntimeException('Recommendation run is not pending');
            }
            $metadata = $analysis->engineMetadata();
            $isModel = $analysis->origin() === 'model';
            $status = $isModel ? 'completed' : 'fallback';
            $update = $this->pdo->prepare(
                'UPDATE learner_recommendation_runs SET engineType = :engineType, status = :status, ruleVersion = :ruleVersion, provider = :provider, modelVersion = :modelVersion, promptVersion = :promptVersion, fallbackReason = :fallbackReason, safeErrorCode = NULL, completedAt = :completedAt WHERE id = :runId AND studentId = :studentId AND status = :pendingStatus'
            );
            $update->execute([
                'engineType' => $isModel ? 'model' : 'rule',
                'status' => $status,
                'ruleVersion' => $isModel ? null : $metadata['rule_version'],
                'provider' => $isModel ? $metadata['provider'] : null,
                'modelVersion' => $isModel ? $metadata['model_version'] : null,
                'promptVersion' => $isModel ? $metadata['prompt_version'] : null,
                'fallbackReason' => $isModel ? null : $analysis->fallbackReason(),
                'completedAt' => $this->now(),
                'runId' => $runId,
                'studentId' => $studentId,
                'pendingStatus' => 'pending',
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Recommendation run is not pending');
            }
            $this->insertAuditEvent($runId, $studentId, self::uuid(), 'roadmap_run_completed', $metadata, $status);
            $completed = $this->runForStudent($studentId, $runId);
            if ($completed === null) {
                throw new RuntimeException('Recommendation run not found for learner');
            }
            return $completed;
        });
    }

    public function failRun(string $studentId, string $runId, string $safeErrorCode): void
    {
        $studentId = $this->required($studentId, 'Student id is required.');
        $runId = $this->required($runId, 'Recommendation run id is required.');
        $safeErrorCode = $this->required($safeErrorCode, 'Recommendation safe error code is required.');
        if (strlen($safeErrorCode) > 100) {
            throw new \InvalidArgumentException('Recommendation safe error code is too long.');
        }

        $this->transaction(function () use ($studentId, $runId, $safeErrorCode): void {
            $run = $this->ownedRun($studentId, $runId);
            if ($run['status'] !== 'pending') {
                throw new RuntimeException('Recommendation run is not pending');
            }
            $update = $this->pdo->prepare(
                'UPDATE learner_recommendation_runs SET status = :status, safeErrorCode = :safeErrorCode, completedAt = :completedAt WHERE id = :runId AND studentId = :studentId AND status = :pendingStatus'
            );
            $update->execute([
                'status' => 'failed',
                'safeErrorCode' => $safeErrorCode,
                'completedAt' => $this->now(),
                'runId' => $runId,
                'studentId' => $studentId,
                'pendingStatus' => 'pending',
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Recommendation run is not pending');
            }
            $this->insertAuditEvent($runId, $studentId, self::uuid(), 'run_failed', ['safe_error_code' => $safeErrorCode], 'failed');
        });
    }

    /** @return array<string,mixed>|null */
    public function latestForStudent(string $studentId): ?array
    {
        $studentId = $this->required($studentId, 'Student id is required.');
        $statement = $this->pdo->prepare(
            "SELECT id FROM learner_recommendation_runs WHERE studentId = :studentId AND idempotencyKey NOT LIKE 'shadow-%' ORDER BY createdAt DESC, id DESC LIMIT 1"
        );
        $statement->execute(['studentId' => $studentId]);
        $runId = $statement->fetchColumn();
        return $runId === false ? null : $this->runForStudent($studentId, (string) $runId);
    }

    /** @return array<string,mixed> */
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array
    {
        $studentId = $this->required($studentId, 'Student id is required.');
        $itemId = $this->required($itemId, 'Recommendation item id is required.');
        $verdict = $this->required($verdict, 'Recommendation feedback verdict is required.');
        $reasonCode = $this->required($reasonCode, 'Recommendation feedback reason is required.');
        $safeComment = $safeComment === null ? null : trim($safeComment);
        if ($safeComment === '') {
            $safeComment = null;
        }
        if ($safeComment !== null && strlen($safeComment) > 500) {
            throw new \InvalidArgumentException('Recommendation feedback comment is too long.');
        }

        return $this->transaction(function () use ($studentId, $itemId, $verdict, $reasonCode, $safeComment): array {
            $owned = $this->pdo->prepare(
                'SELECT items.id FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE items.id = :itemId AND runs.studentId = :studentId'
            );
            $owned->execute(['itemId' => $itemId, 'studentId' => $studentId]);
            if ($owned->fetchColumn() === false) {
                throw new RuntimeException('Recommendation item not found for learner');
            }
            $feedbackId = self::uuid();
            $createdAt = $this->now();
            $insert = $this->pdo->prepare(
                'INSERT INTO learner_recommendation_feedback (id, studentId, itemId, verdict, reasonCode, safeComment, createdAt) VALUES (:id, :studentId, :itemId, :verdict, :reasonCode, :safeComment, :createdAt)'
            );
            $insert->execute([
                'id' => $feedbackId,
                'studentId' => $studentId,
                'itemId' => $itemId,
                'verdict' => $verdict,
                'reasonCode' => $reasonCode,
                'safeComment' => $safeComment,
                'createdAt' => $createdAt,
            ]);
            return [
                'feedbackId' => $feedbackId,
                'studentId' => $studentId,
                'itemId' => $itemId,
                'verdict' => $verdict,
                'reasonCode' => $reasonCode,
                'safeComment' => $safeComment,
                'createdAt' => $createdAt,
            ];
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
        foreach ($input->evidenceReferences() as $reference) {
            $sourceType = $this->required((string) ($reference['source_type'] ?? ''), 'Recommendation snapshot evidence source type is required.');
            $sourceId = $this->required((string) ($reference['source_id'] ?? ''), 'Recommendation snapshot evidence source id is required.');
            $observedAt = $reference['observed_at'] ?? null;
            if ($observedAt !== null && !is_string($observedAt)) {
                throw new \InvalidArgumentException('Recommendation snapshot evidence observation time is invalid.');
            }
            $safeValue = $reference['safe_value'] ?? null;
            if (!is_array($safeValue)) {
                throw new \InvalidArgumentException('Recommendation snapshot evidence safe value is required.');
            }
            $insert->execute([
                'id' => self::uuid(),
                'snapshotId' => $snapshotId,
                'sourceType' => $sourceType,
                'sourceId' => $sourceId,
                'observedAt' => $this->databaseTimestamp($observedAt),
                'safeValueJson' => self::json($safeValue),
                'createdAt' => $this->now(),
            ]);
        }
    }

    private function insertItem(string $itemId, string $runId, RecommendationItem $item, string $createdAt): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus, createdAt) VALUES (:id, :runId, :itemType, :title, :summary, :priority, :confidenceBand, :actionJson, :lifecycleStatus, :createdAt)'
        );
        $insert->execute([
            'id' => $itemId,
            'runId' => $runId,
            'itemType' => $item->itemType(),
            'title' => $item->title(),
            'summary' => $item->summary(),
            'priority' => $item->priority(),
            'confidenceBand' => $item->confidenceBand(),
            'actionJson' => $item->actionJson(),
            'lifecycleStatus' => 'active',
            'createdAt' => $createdAt,
        ]);
    }

    /** @param array{id:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string} $snapshotEvidence */
    private function insertEvidence(string $itemId, array $snapshotEvidence, string $contributionLabel, string $createdAt): void
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
            'contributionLabel' => $contributionLabel,
            'safeValueJson' => $snapshotEvidence['safeValueJson'],
            'createdAt' => $createdAt,
        ]);
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
            'SELECT id, snapshotId, studentId, idempotencyKey, status FROM learner_recommendation_runs WHERE studentId = :studentId AND idempotencyKey = :idempotencyKey'
        );
        $statement->execute(['studentId' => $studentId, 'idempotencyKey' => $idempotencyKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    private function ownedRun(string $studentId, string $runId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, snapshotId, studentId, status FROM learner_recommendation_runs WHERE id = :runId AND studentId = :studentId'
        );
        $statement->execute(['runId' => $runId, 'studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Recommendation run not found for learner');
        }
        return $row;
    }

    /** @return array{id:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string}|null */
    private function snapshotEvidence(string $studentId, string $snapshotId, RecommendationEvidence $evidence): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT evidence.id, evidence.sourceType, evidence.sourceId, evidence.observedAt, evidence.safeValueJson FROM learner_recommendation_snapshot_evidence AS evidence INNER JOIN learner_recommendation_input_snapshots AS snapshots ON snapshots.id = evidence.snapshotId WHERE evidence.snapshotId = :snapshotId AND snapshots.studentId = :studentId AND evidence.sourceType = :sourceType AND evidence.sourceId = :sourceId'
        );
        $statement->execute([
            'snapshotId' => $snapshotId,
            'studentId' => $studentId,
            'sourceType' => $evidence->sourceType(),
            'sourceId' => $evidence->sourceId(),
        ]);
        $snapshotEvidence = $statement->fetch(PDO::FETCH_ASSOC);
        return $snapshotEvidence === false ? null : $snapshotEvidence;
    }

    private function assertContextScopesMatchInput(RecommendationInput $input, RecommendationContext $context): void
    {
        $embeddedScopes = $input->qualityFlags()['allowed_scopes'] ?? null;
        if (!is_array($embeddedScopes)) {
            throw new RuntimeException('Recommendation context scopes do not match input snapshot');
        }

        $normalized = [];
        foreach ($embeddedScopes as $scope) {
            if (!is_string($scope) || trim($scope) === '') {
                throw new RuntimeException('Recommendation context scopes do not match input snapshot');
            }
            $normalized[trim($scope)] = true;
        }
        $inputScopes = array_keys($normalized);
        sort($inputScopes, SORT_STRING);
        if ($inputScopes !== $context->allowedScopes()) {
            throw new RuntimeException('Recommendation context scopes do not match input snapshot');
        }
    }

    /** @return array<string,mixed>|null */
    private function runForStudent(string $studentId, string $runId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, snapshotId, studentId, idempotencyKey, engineType, status, ruleVersion, provider, modelVersion, promptVersion, fallbackReason, safeErrorCode, startedAt, completedAt, createdAt FROM learner_recommendation_runs WHERE id = :runId AND studentId = :studentId'
        );
        $statement->execute(['runId' => $runId, 'studentId' => $studentId]);
        $run = $statement->fetch(PDO::FETCH_ASSOC);
        if ($run === false) {
            return null;
        }

        $items = $this->pdo->prepare(
            'SELECT items.id, items.itemType, items.title, items.summary, items.priority, items.confidenceBand, items.actionJson, items.lifecycleStatus, items.createdAt FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE items.runId = :runId AND runs.studentId = :studentId ORDER BY items.priority DESC, items.id ASC'
        );
        $items->execute(['runId' => $runId, 'studentId' => $studentId]);
        $run['runId'] = $run['id'];
        $run['snapshotId'] = $run['snapshotId'];
        unset($run['id']);
        $run['items'] = [];
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $evidence = $this->pdo->prepare(
                'SELECT evidence.id, evidence.snapshotEvidenceId, evidence.sourceType, evidence.sourceId, evidence.observedAt, evidence.contributionLabel, evidence.safeValueJson, evidence.createdAt FROM learner_recommendation_evidence AS evidence INNER JOIN learner_recommendation_items AS items ON items.id = evidence.itemId INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE evidence.itemId = :itemId AND runs.studentId = :studentId ORDER BY evidence.id ASC'
            );
            $evidence->execute(['itemId' => $item['id'], 'studentId' => $studentId]);
            $item['itemId'] = $item['id'];
            unset($item['id']);
            $item['evidence'] = $evidence->fetchAll(PDO::FETCH_ASSOC);
            $run['items'][] = $item;
        }
        return $run;
    }

    /** @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function pendingRunResponse(array $run, bool $reused): array
    {
        return [
            'runId' => (string) $run['id'],
            'snapshotId' => (string) $run['snapshotId'],
            'studentId' => (string) $run['studentId'],
            'idempotencyKey' => (string) $run['idempotencyKey'],
            'status' => (string) $run['status'],
            'reused' => $reused,
        ];
    }

    /** @template T
     * @param callable():T $operation
     * @return T
     */
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
            throw new RuntimeException('Recommendation repository clock must return a non-empty timestamp.');
        }
        return $this->databaseTimestamp($timestamp) ?? throw new RuntimeException('Recommendation repository clock must return a timestamp.');
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
            throw new \InvalidArgumentException('Recommendation timestamp must be a valid UTC-compatible value.', 0, $exception);
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
            throw new \InvalidArgumentException('Recommendation persistence value must be JSON serializable.', 0, $exception);
        }
    }
}
