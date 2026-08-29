<?php
declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use PDO;
use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;
use TalentHub\Modules\School\Repository\SchoolAiAggregateRepository;

final class SchoolAiRefreshCoordinator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SchoolAiAggregateRepository $aggregates,
        private readonly DatabaseSchoolAiRefreshJobRepository $jobs,
        private readonly int $minimumCohort = 5,
    ) {
    }

    /**
     * @param list<string> $studentIds
     * @return array{school_ids:list<string>,job_count:int}
     */
    public function dispatchForStudents(array $studentIds): array
    {
        $normalized = [];
        foreach ($studentIds as $id) {
            if (is_string($id)) {
                $trimmed = trim($id);
                if ($trimmed !== '') {
                    $normalized[$trimmed] = $trimmed;
                }
            }
        }
        if ($normalized === []) {
            return ['school_ids' => [], 'job_count' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT c.schoolId "
            . "FROM student_profiles sp "
            . "JOIN classes c ON c.id = sp.classId "
            . "WHERE sp.id IN ($placeholders) AND sp.studyStatus = 'active' AND c.schoolId IS NOT NULL AND c.schoolId != ''"
        );
        $stmt->execute(array_values($normalized));
        $schoolIds = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), static fn(string $id): bool => trim($id) !== ''));

        $jobCount = 0;
        foreach ($schoolIds as $schoolId) {
            try {
                $aggregate = $this->aggregates->aggregate($schoolId, $this->minimumCohort);
                $hash = $this->aggregates->aggregateHash($aggregate);

                if ($this->jobs->enqueue($schoolId, $hash) !== null) {
                    $jobCount++;
                }
            } catch (\Throwable) {
                throw new \RuntimeException('school_refresh_dispatch_failed');
            }
        }

        return [
            'school_ids' => $schoolIds,
            'job_count' => $jobCount,
        ];
    }
}
