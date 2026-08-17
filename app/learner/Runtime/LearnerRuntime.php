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

    /** @param array{source?:mixed,pdo?:mixed,student_id?:mixed} $config */
    public static function fromConfig(array $config): self
    {
        $source = strtolower(trim((string) ($config['source'] ?? 'mock')));
        if (!in_array($source, ['mock', 'database'], true)) {
            throw new LearnerDataConfigurationException('Learner runtime source must be mock or database.');
        }

        $pdo = $config['pdo'] ?? null;
        if ($source === 'database' && !$pdo instanceof PDO) {
            throw new LearnerDataConfigurationException('Learner database runtime requires a real PDO instance.');
        }
        if ($pdo !== null && !$pdo instanceof PDO) {
            throw new LearnerDataConfigurationException('Learner runtime PDO configuration must be a PDO instance.');
        }

        $studentId = trim((string) ($config['student_id'] ?? ''));
        if ($studentId === '') {
            if ($source === 'database') {
                throw new LearnerDataConfigurationException('Learner database runtime requires an explicit student_id.');
            }
            $studentId = 'student-demo-001';
        }

        return new self($source, $pdo, $studentId);
    }

    public function source(): string
    {
        return $this->source;
    }

    public function studentId(): string
    {
        return $this->studentId;
    }

    /** @return array{source:string,student_id:string} */
    public function diagnostics(): array
    {
        return ['source' => $this->source, 'student_id' => $this->studentId];
    }
}
