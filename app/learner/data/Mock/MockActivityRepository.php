<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\Enums\ActivityRegistrationStatus;
use TalentHub\Learner\Data\Enums\ActivityStatus;
use TalentHub\Learner\Data\Support\MockRecordNormalizer;

final class MockActivityRepository implements ActivityRepository
{
    private array $activities;
    private array $registrations;

    public function __construct(array $activities, array $registrations)
    {
        $this->activities = array_map([$this, 'normalizeActivity'], $activities);
        $this->registrations = array_map([$this, 'normalizeRegistration'], $registrations);
    }

    public function all(): array
    {
        return $this->activities;
    }

    public function findById(string $activityId): ?array
    {
        foreach ($this->activities as $activity) {
            if (MockRecordNormalizer::matches($activity, $activityId)
                || MockRecordNormalizer::matches($activity, $activityId, 'activity_id')) {
                return $activity;
            }
        }

        return null;
    }

    public function registrationsFor(string $studentId): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        return array_values(array_filter(
            $this->registrations,
            static fn (array $registration): bool => ($registration['student_id'] ?? '') === $canonicalStudentId
        ));
    }

    private function normalizeActivity(array $activity): array
    {
        $activity = MockRecordNormalizer::primary($activity, 'activity');
        if (isset($activity['id'])) {
            $activity['legacy_activity_id'] = $activity['legacy_id'];
            $activity['activity_id'] = $activity['id'];
        }
        $activity = MockRecordNormalizer::foreign($activity, 'school_id', 'school');
        $activity['status'] = ActivityStatus::normalize($activity['status'] ?? null)->value;

        return $activity;
    }

    private function normalizeRegistration(array $registration): array
    {
        $registration = MockRecordNormalizer::primary($registration, 'activity_registration');
        $registration = MockRecordNormalizer::foreign($registration, 'student_id', 'student');
        $registration = MockRecordNormalizer::foreign($registration, 'activity_id', 'activity');
        $registration['status'] = ActivityRegistrationStatus::normalize($registration['status'] ?? null)->value;

        return $registration;
    }
}
