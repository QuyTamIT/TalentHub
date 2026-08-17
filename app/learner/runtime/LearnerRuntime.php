<?php

declare(strict_types=1);

namespace TalentHub\Learner\Runtime;

use PDO;
use TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException;

final class LearnerRuntime
{
    private function __construct(private readonly string $source, private readonly ?PDO $pdo, private readonly string $studentId)
    {
    }

    public static function fromConfig(array $config): self
    {
        $source = strtolower(trim((string) ($config['source'] ?? '')));
        if (!in_array($source, ['mock', 'database'], true)) {
            throw new LearnerDataConfigurationException('Learner runtime source must be explicitly mock or database.');
        }

        $pdo = $config['pdo'] ?? null;
        if ($source === 'database' && !$pdo instanceof PDO) {
            throw new LearnerDataConfigurationException('Learner database runtime requires a real PDO instance; mock fallback is disabled.');
        }

        $studentId = trim((string) ($config['student_id'] ?? ''));
        if ($source === 'database' && $studentId === '') {
            throw new LearnerDataConfigurationException('Learner database runtime requires an explicit student_id configuration.');
        }

        return new self($source, $pdo instanceof PDO ? $pdo : null, $studentId !== '' ? $studentId : 'student-demo-001');
    }

    public function source(): string
    {
        return $this->source;
    }

    public function pdo(): ?PDO
    {
        return $this->pdo;
    }

    public function studentId(): string
    {
        return $this->studentId;
    }

    public function diagnostics(): array
    {
        return [
            'source' => $this->source,
            'pdo_configured' => $this->pdo instanceof PDO,
            'student_id_configured' => $this->studentId !== '',
        ];
    }
}
