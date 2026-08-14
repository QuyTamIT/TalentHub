<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use TalentHub\Learner\Data\Contracts\ApplicationRepository;
use TalentHub\Learner\Data\Enums\ApplicationStatus;
use TalentHub\Learner\Data\Support\MockRecordNormalizer;

final class MockApplicationRepository implements ApplicationRepository
{
    private array $applications;

    public function __construct(array $applications)
    {
        $this->applications = array_map([$this, 'normalize'], $applications);
    }

    public function forStudent(string $studentId): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        return array_values(array_filter(
            $this->applications,
            static fn (array $application): bool => ($application['student_id'] ?? '') === $canonicalStudentId
        ));
    }

    public function findByIdForStudent(string $applicationId, string $studentId): ?array
    {
        foreach ($this->forStudent($studentId) as $application) {
            if (MockRecordNormalizer::matches($application, $applicationId)) {
                return $application;
            }
        }

        return null;
    }

    private function normalize(array $application): array
    {
        $application = MockRecordNormalizer::primary($application, 'application');
        $application = MockRecordNormalizer::foreign($application, 'student_id', 'student');
        $application = MockRecordNormalizer::foreign($application, 'opportunity_id', 'opportunity');
        $application = MockRecordNormalizer::foreign($application, 'enterprise_id', 'enterprise');
        $application = MockRecordNormalizer::foreign($application, 'school_id', 'school');
        $application = MockRecordNormalizer::foreign($application, 'activity_id', 'activity');
        $application['status'] = ApplicationStatus::normalize($application['status'] ?? null)->value;

        return $application;
    }
}
