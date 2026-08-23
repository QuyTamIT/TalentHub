<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Config;

final class EvaluationConfig
{
    /** @param array<string,string> $values */
    private function __construct(private readonly array $values) {}

    /** @param array<string,string> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $keys = [
            'TALENTHUB_AI_EVALUATION_PSEUDONYM_KEY', 'TALENTHUB_AI_EVALUATION_PSEUDONYM_KEY_VERSION',
            'TALENTHUB_AI_EVALUATION_MANIFEST_SHA256', 'TALENTHUB_AI_EVALUATION_APPROVAL_REFERENCE',
            'TALENTHUB_AI_EVALUATION_MAX_CALLS', 'TALENTHUB_AI_EVALUATION_MAX_COST', 'TALENTHUB_AI_EVALUATION_MODE',
        ];
        $values = [];
        foreach ($keys as $key) {
            $value = trim((string) ($environment[$key] ?? getenv($key) ?: ''));
            if ($value === '') throw new \InvalidArgumentException("{$key} is required for Phase 12 evaluation.");
            $values[$key] = $value;
        }
        if (strlen($values['TALENTHUB_AI_EVALUATION_PSEUDONYM_KEY']) < 32) throw new \InvalidArgumentException('Evaluation pseudonym key is too short.');
        if (preg_match('/\A[0-9a-f]{64}\z/', $values['TALENTHUB_AI_EVALUATION_MANIFEST_SHA256']) !== 1) throw new \InvalidArgumentException('Evaluation manifest hash is invalid.');
        if (filter_var($values['TALENTHUB_AI_EVALUATION_MAX_CALLS'], FILTER_VALIDATE_INT) === false || (int) $values['TALENTHUB_AI_EVALUATION_MAX_CALLS'] < 1) throw new \InvalidArgumentException('Evaluation max calls is invalid.');
        if (!is_numeric($values['TALENTHUB_AI_EVALUATION_MAX_COST']) || (float) $values['TALENTHUB_AI_EVALUATION_MAX_COST'] < 0) throw new \InvalidArgumentException('Evaluation max cost is invalid.');
        if (!in_array($values['TALENTHUB_AI_EVALUATION_MODE'], ['dry-run', 'simulated', 'approved-shadow'], true)) throw new \InvalidArgumentException('Evaluation mode is invalid.');
        return new self($values);
    }
    public function manifestSha256(): string { return $this->values['TALENTHUB_AI_EVALUATION_MANIFEST_SHA256']; }
    public function approvalReference(): string { return $this->values['TALENTHUB_AI_EVALUATION_APPROVAL_REFERENCE']; }
    public function mode(): string { return $this->values['TALENTHUB_AI_EVALUATION_MODE']; }
    /** @return array<string,mixed> */
    public function diagnostics(): array { return [
        'manifest_sha256' => $this->manifestSha256(), 'approval_reference' => $this->approvalReference(),
        'max_calls' => (int) $this->values['TALENTHUB_AI_EVALUATION_MAX_CALLS'],
        'max_cost' => (float) $this->values['TALENTHUB_AI_EVALUATION_MAX_COST'], 'mode' => $this->mode(),
    ]; }
}
