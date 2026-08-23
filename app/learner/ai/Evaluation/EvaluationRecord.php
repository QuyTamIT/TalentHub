<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationRecord
{
    public const FIELDS = [
        'id', 'studentId', 'subjectRef', 'subjectRefVersion', 'attemptKey', 'ruleRunId', 'modelRunId',
        'snapshotId', 'educationBand', 'cohortTags', 'provider', 'modelVersion', 'promptVersion',
        'ruleVersion', 'evaluatorVersion', 'evaluationRevision', 'supersedesEvaluationId',
        'inputSnapshotHash', 'consentPolicyVersion', 'consentDecisionHash', 'consentEvaluatedAt',
        'schemaValid', 'evidenceCoverage', 'evidenceMatched', 'evidenceRequired',
        'unsupportedClaimCount', 'unsafeOutputCount', 'resultType', 'fallbackReason',
        'providerErrorCategory', 'latencyMs', 'inputTokens', 'outputTokens', 'estimatedCost',
        'costCurrency', 'status', 'retentionClass', 'evaluatedAt', 'createdAt',
    ];

    /** @var array<string,mixed> */
    private readonly array $values;

    /** @param array<string,mixed> $values */
    public function __construct(array $values)
    {
        $unknown = array_diff(array_keys($values), self::FIELDS);
        $missing = array_diff(self::FIELDS, array_keys($values));
        if ($unknown !== [] || $missing !== []) {
            throw new \InvalidArgumentException('Evaluation record fields do not match the reviewed contract.');
        }

        foreach (['id', 'studentId', 'ruleRunId', 'snapshotId'] as $field) {
            self::uuid($values[$field], $field);
        }
        foreach (['modelRunId', 'supersedesEvaluationId'] as $field) {
            if ($values[$field] !== null) {
                self::uuid($values[$field], $field);
            }
        }
        foreach (['subjectRef', 'attemptKey', 'inputSnapshotHash', 'consentDecisionHash'] as $field) {
            if (!is_string($values[$field]) || preg_match('/\A[0-9a-f]{64}\z/', $values[$field]) !== 1) {
                throw new \InvalidArgumentException("{$field} must be a lowercase SHA-256 digest.");
            }
        }
        foreach (['subjectRefVersion', 'provider', 'modelVersion', 'promptVersion', 'ruleVersion', 'evaluatorVersion', 'consentPolicyVersion'] as $field) {
            if (!is_string($values[$field]) || trim($values[$field]) === '' || strlen($values[$field]) > 100) {
                throw new \InvalidArgumentException("{$field} must be a bounded non-empty string.");
            }
        }
        if (preg_match('/\A[A-Za-z0-9._-]{1,50}\z/', (string) $values['subjectRefVersion']) !== 1) {
            throw new \InvalidArgumentException('subjectRefVersion contains unsupported characters.');
        }
        if (!in_array($values['educationBand'], ['high', 'college'], true)) {
            throw new \InvalidArgumentException('educationBand is not approved.');
        }
        if (!is_array($values['cohortTags']) || $values['cohortTags'] !== []) {
            throw new \InvalidArgumentException('Optional cohort tags are forbidden until independently approved.');
        }

        foreach (['evidenceMatched', 'evidenceRequired', 'unsupportedClaimCount', 'unsafeOutputCount'] as $field) {
            if (!is_int($values[$field]) || $values[$field] < 0) {
                throw new \InvalidArgumentException("{$field} must be a non-negative integer.");
            }
        }
        foreach (['inputTokens', 'outputTokens'] as $field) {
            if ($values[$field] !== null && (!is_int($values[$field]) || $values[$field] < 0)) {
                throw new \InvalidArgumentException("{$field} must be null or a non-negative integer.");
            }
        }
        if (!is_bool($values['schemaValid'])) {
            throw new \InvalidArgumentException('schemaValid must be boolean.');
        }
        if (!is_float($values['evidenceCoverage']) && !is_int($values['evidenceCoverage'])) {
            throw new \InvalidArgumentException('evidenceCoverage must be numeric.');
        }
        $coverage = round((float) $values['evidenceCoverage'], 6);
        $required = $values['evidenceRequired'];
        $matched = $values['evidenceMatched'];
        $expectedCoverage = $required === 0 ? 0.0 : round($matched / $required, 6);
        if ($coverage < 0 || $coverage > 1 || $matched > $required || abs($coverage - $expectedCoverage) > 0.0000005) {
            throw new \InvalidArgumentException('Evidence coverage does not match evidence counts.');
        }

        $revision = $values['evaluationRevision'];
        if (!is_int($revision) || $revision < 1
            || ($revision === 1 && $values['supersedesEvaluationId'] !== null)
            || ($revision > 1 && $values['supersedesEvaluationId'] === null)) {
            throw new \InvalidArgumentException('Evaluation revision/supersession is invalid.');
        }
        if (!in_array($values['resultType'], ['model', 'rule_fallback', 'blocked_before_call'], true)
            || !in_array($values['status'], ['completed', 'gate_failed', 'blocked', 'fallback'], true)
            || !in_array($values['retentionClass'], ['evaluation_standard', 'incident_hold', 'legal_hold'], true)) {
            throw new \InvalidArgumentException('Evaluation lifecycle value is not allow-listed.');
        }
        self::nullableReason($values['fallbackReason'], 'fallbackReason');
        self::nullableReason($values['providerErrorCategory'], 'providerErrorCategory');
        self::numberOrNull($values['latencyMs'], 'latencyMs');
        self::numberOrNull($values['estimatedCost'], 'estimatedCost');
        if (($values['estimatedCost'] === null) !== ($values['costCurrency'] === null)
            || ($values['costCurrency'] !== null && (!is_string($values['costCurrency']) || preg_match('/\A[A-Z]{3}\z/', $values['costCurrency']) !== 1))) {
            throw new \InvalidArgumentException('Cost and currency must be both null or a valid non-negative amount/currency pair.');
        }

        if ($values['resultType'] === 'model') {
            if ($values['modelRunId'] === null || $values['status'] !== 'completed'
                || $values['fallbackReason'] !== null || $values['providerErrorCategory'] !== null) {
                throw new \InvalidArgumentException('Completed model evaluation has inconsistent terminal fields.');
            }
        } elseif ($values['resultType'] === 'rule_fallback') {
            if ($values['modelRunId'] === null || !in_array($values['status'], ['fallback', 'gate_failed'], true)
                || $values['fallbackReason'] === null) {
                throw new \InvalidArgumentException('Rule fallback evaluation has inconsistent terminal fields.');
            }
        } else {
            if ($values['modelRunId'] !== null || $values['status'] !== 'blocked' || $values['fallbackReason'] === null
                || $values['latencyMs'] !== null || $values['inputTokens'] !== null || $values['outputTokens'] !== null
                || $values['estimatedCost'] !== null || $values['costCurrency'] !== null || $matched !== 0 || $required !== 0) {
                throw new \InvalidArgumentException('Blocked-before-call evaluation contains provider result metadata.');
            }
        }
        foreach (['consentEvaluatedAt', 'evaluatedAt', 'createdAt'] as $field) {
            if (!is_string($values[$field]) || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/', $values[$field]) !== 1) {
                throw new \InvalidArgumentException("{$field} must be a UTC DATETIME(6) string.");
            }
        }
        if (strcmp($values['consentEvaluatedAt'], $values['evaluatedAt']) > 0
            || strcmp($values['evaluatedAt'], $values['createdAt']) > 0) {
            throw new \InvalidArgumentException('Evaluation timestamps are out of order.');
        }

        $values['evidenceCoverage'] = $coverage;
        $values['latencyMs'] = $values['latencyMs'] === null ? null : round((float) $values['latencyMs'], 3);
        $values['estimatedCost'] = $values['estimatedCost'] === null ? null : round((float) $values['estimatedCost'], 8);
        $this->values = $values;
    }

    /** @return array<string,mixed> */
    public function toArray(): array { return $this->values; }

    /** @return array<string,mixed> */
    public function databaseRow(): array
    {
        $row = $this->values;
        $row['cohortTagsJson'] = json_encode($row['cohortTags'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        unset($row['cohortTags']);
        $row['schemaValid'] = $row['schemaValid'] ? 1 : 0;
        return $row;
    }

    private static function uuid(mixed $value, string $field): void
    {
        if (!is_string($value) || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) !== 1) {
            throw new \InvalidArgumentException("{$field} must be a UUID.");
        }
    }

    private static function nullableReason(mixed $value, string $field): void
    {
        if ($value !== null && (!is_string($value) || preg_match('/\A[a-z0-9_]{1,100}\z/', $value) !== 1)) {
            throw new \InvalidArgumentException("{$field} is not a safe category.");
        }
    }

    private static function numberOrNull(mixed $value, string $field): void
    {
        if ($value !== null && ((!is_float($value) && !is_int($value)) || !is_finite((float) $value) || $value < 0)) {
            throw new \InvalidArgumentException("{$field} must be null or a finite non-negative number.");
        }
    }
}
