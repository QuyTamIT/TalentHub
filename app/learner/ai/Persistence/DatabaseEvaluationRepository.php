<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use PDO;
use PDOException;
use TalentHub\Learner\Ai\Evaluation\EvaluationQuery;
use TalentHub\Learner\Ai\Evaluation\EvaluationRecord;

final class DatabaseEvaluationRepository implements EvaluationRepository
{
    private const COLUMNS = [
        'id', 'studentId', 'subjectRef', 'subjectRefVersion', 'attemptKey', 'ruleRunId', 'modelRunId',
        'snapshotId', 'educationBand', 'cohortTagsJson', 'provider', 'modelVersion', 'promptVersion',
        'ruleVersion', 'evaluatorVersion', 'evaluationRevision', 'supersedesEvaluationId',
        'inputSnapshotHash', 'consentPolicyVersion', 'consentDecisionHash', 'consentEvaluatedAt',
        'schemaValid', 'evidenceCoverage', 'evidenceMatched', 'evidenceRequired',
        'unsupportedClaimCount', 'unsafeOutputCount', 'resultType', 'fallbackReason',
        'providerErrorCategory', 'latencyMs', 'inputTokens', 'outputTokens', 'estimatedCost',
        'costCurrency', 'status', 'retentionClass', 'evaluatedAt', 'createdAt',
    ];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function append(EvaluationRecord $record): array
    {
        $values = $record->toArray();
        $existing = $this->byAttemptRevision(
            (string) $values['attemptKey'],
            (string) $values['evaluatorVersion'],
            (int) $values['evaluationRevision'],
        );
        if ($existing !== null) {
            return $this->reuseOrConflict($record, $existing);
        }
        $this->assertSupersession($record);

        $row = $record->databaseRow();
        $columns = implode(', ', self::COLUMNS);
        $placeholders = implode(', ', array_map(static fn (string $column): string => ':' . $column, self::COLUMNS));
        try {
            $statement = $this->pdo->prepare("INSERT INTO learner_ai_evaluation_runs ({$columns}) VALUES ({$placeholders})");
            $statement->execute($row);
        } catch (PDOException $exception) {
            $existing = $this->byAttemptRevision(
                (string) $values['attemptKey'],
                (string) $values['evaluatorVersion'],
                (int) $values['evaluationRevision'],
            );
            if ($existing !== null) {
                return $this->reuseOrConflict($record, $existing);
            }
            throw $exception;
        }

        return ['record' => $record->toArray(), 'reused' => false];
    }

    public function latestByModelRun(string $studentId, string $modelRunId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM learner_ai_evaluation_runs WHERE studentId = :studentId AND modelRunId = :modelRunId ORDER BY evaluationRevision DESC, evaluatedAt DESC LIMIT 1'
        );
        $statement->execute(['studentId' => trim($studentId), 'modelRunId' => trim($modelRunId)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function aggregate(EvaluationQuery $query): array
    {
        $where = [
            'e.evaluatedAt >= :fromTime',
            'e.evaluatedAt < :untilTime',
            'NOT EXISTS (SELECT 1 FROM learner_ai_evaluation_runs child WHERE child.supersedesEvaluationId = e.id)',
        ];
        $parameters = ['fromTime' => $query->from(), 'untilTime' => $query->until()];
        foreach ([
            'provider' => $query->provider(),
            'modelVersion' => $query->modelVersion(),
            'educationBand' => $query->educationBand(),
        ] as $column => $value) {
            if ($value !== null) {
                $where[] = "e.{$column} = :{$column}";
                $parameters[$column] = $value;
            }
        }
        $statement = $this->pdo->prepare(
            'SELECT e.* FROM learner_ai_evaluation_runs e WHERE ' . implode(' AND ', $where)
            . ' ORDER BY e.educationBand, e.subjectRef, e.evaluatedAt, e.id'
        );
        $statement->execute($parameters);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $safe = $this->hydrate($row);
            unset($safe['studentId']);
            $result[] = $safe;
        }
        return $result;
    }

    /** @return array<string,mixed>|null */
    private function byAttemptRevision(string $attemptKey, string $evaluatorVersion, int $revision): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM learner_ai_evaluation_runs WHERE attemptKey = :attemptKey AND evaluatorVersion = :evaluatorVersion AND evaluationRevision = :revision LIMIT 1'
        );
        $statement->execute(['attemptKey' => $attemptKey, 'evaluatorVersion' => $evaluatorVersion, 'revision' => $revision]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $existing @return array{record:array<string,mixed>,reused:bool} */
    private function reuseOrConflict(EvaluationRecord $record, array $existing): array
    {
        if ($existing !== $record->toArray()) {
            throw new \RuntimeException('Evaluation attempt revision already exists with different immutable facts.');
        }
        return ['record' => $existing, 'reused' => true];
    }

    private function assertSupersession(EvaluationRecord $record): void
    {
        $values = $record->toArray();
        if ($values['evaluationRevision'] === 1) {
            return;
        }
        $statement = $this->pdo->prepare('SELECT * FROM learner_ai_evaluation_runs WHERE id = :id AND studentId = :studentId LIMIT 1');
        $statement->execute(['id' => $values['supersedesEvaluationId'], 'studentId' => $values['studentId']]);
        $prior = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('Superseded evaluation does not exist for this owner.');
        }
        $prior = $this->hydrate($prior);
        if ($prior['attemptKey'] !== $values['attemptKey']
            || ((int) $prior['evaluationRevision']) + 1 !== $values['evaluationRevision']) {
            throw new \InvalidArgumentException('Evaluation correction must continue the same attempt chain by one revision.');
        }
        $fork = $this->pdo->prepare('SELECT id FROM learner_ai_evaluation_runs WHERE supersedesEvaluationId = :id LIMIT 1');
        $fork->execute(['id' => $values['supersedesEvaluationId']]);
        if ($fork->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Evaluation correction chain cannot fork.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array $row): array
    {
        $row['cohortTags'] = json_decode((string) $row['cohortTagsJson'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['cohortTagsJson']);
        $row['schemaValid'] = (bool) $row['schemaValid'];
        foreach (['evaluationRevision', 'evidenceMatched', 'evidenceRequired', 'unsupportedClaimCount', 'unsafeOutputCount'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        foreach (['inputTokens', 'outputTokens'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        }
        foreach (['evidenceCoverage', 'latencyMs', 'estimatedCost'] as $field) {
            $row[$field] = $row[$field] === null ? null : (float) $row[$field];
        }
        $ordered = [];
        foreach (EvaluationRecord::FIELDS as $field) {
            $ordered[$field] = $row[$field] ?? null;
        }
        return (new EvaluationRecord($ordered))->toArray();
    }
}
