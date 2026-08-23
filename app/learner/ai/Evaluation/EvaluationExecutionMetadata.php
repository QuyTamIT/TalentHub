<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationExecutionMetadata
{
    /** @param array<string,mixed> $values */
    private function __construct(private readonly array $values) {}

    /** @param array<string,mixed> $values */
    public static function completed(array $values): self
    {
        $required = ['evaluationId', 'subjectRef', 'subjectRefVersion', 'attemptKey', 'educationBand',
            'consentDecisionHash', 'consentPolicyVersion', 'consentEvaluatedAt', 'evaluatedAt'];
        foreach ($required as $field) {
            if (!isset($values[$field]) || !is_string($values[$field]) || trim($values[$field]) === '') {
                throw new \InvalidArgumentException("Evaluation execution field {$field} is required.");
            }
        }
        $values += [
            'latencyMs' => null, 'inputTokens' => null, 'outputTokens' => null,
            'estimatedCost' => null, 'costCurrency' => null, 'providerErrorCategory' => null,
            'fallbackReason' => null, 'createdAt' => $values['evaluatedAt'],
            'evaluatorVersion' => 'learner-ai-evaluator-1.0.0',
            'evaluationRevision' => 1, 'supersedesEvaluationId' => null,
            'retentionClass' => 'evaluation_standard',
        ];
        return new self($values);
    }

    public function get(string $field, mixed $default = null): mixed { return $this->values[$field] ?? $default; }
}
