<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationSubject
{
    public function __construct(
        private readonly string $studentId,
        private readonly string $subjectRef,
        private readonly string $subjectRefVersion,
        private readonly string $educationBand,
        private readonly string $ruleRunId,
        private readonly string $snapshotId,
        private readonly string $snapshotHash,
    ) {
        if ($studentId === '' || preg_match('/\A[0-9a-f]{64}\z/', $subjectRef) !== 1
            || !in_array($educationBand, ['high', 'college'], true)
            || preg_match('/\A[0-9a-f]{64}\z/', $snapshotHash) !== 1) {
            throw new \InvalidArgumentException('Evaluation subject contract is invalid.');
        }
    }
    public function studentId(): string { return $this->studentId; }
    public function subjectRef(): string { return $this->subjectRef; }
    public function subjectRefVersion(): string { return $this->subjectRefVersion; }
    public function educationBand(): string { return $this->educationBand; }
    public function ruleRunId(): string { return $this->ruleRunId; }
    public function snapshotId(): string { return $this->snapshotId; }
    public function snapshotHash(): string { return $this->snapshotHash; }
    /** @return array<string,string> */
    public function publicRow(): array { return [
        'subject_ref' => $this->subjectRef, 'subject_ref_version' => $this->subjectRefVersion,
        'education_band' => $this->educationBand, 'rule_run_id' => $this->ruleRunId,
        'snapshot_id' => $this->snapshotId, 'snapshot_hash' => $this->snapshotHash,
    ]; }
}
