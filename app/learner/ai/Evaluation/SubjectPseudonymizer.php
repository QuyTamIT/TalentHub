<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class SubjectPseudonymizer
{
    public function __construct(private readonly string $secret, private readonly string $version)
    {
        if (strlen($secret) < 32 || preg_match('/\A(?:test|default|change-me)/i', $secret) === 1) {
            throw new \InvalidArgumentException('Evaluation pseudonym secret must be a non-default value of at least 32 bytes.');
        }
        if (preg_match('/\A[A-Za-z0-9._-]{1,50}\z/', $version) !== 1) {
            throw new \InvalidArgumentException('Evaluation pseudonym key version is invalid.');
        }
    }

    public function subjectRef(string $studentId): string
    {
        $studentId = trim($studentId);
        if ($studentId === '') throw new \InvalidArgumentException('Student id is required for pseudonymization.');
        return hash_hmac('sha256', $this->version . "\n" . $studentId, $this->secret);
    }
}
