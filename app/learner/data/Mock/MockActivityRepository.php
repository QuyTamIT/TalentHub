<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use DateTimeImmutable;
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

    public function discoverForStudent(string $studentId, DateTimeImmutable $now): array
    {
        $canonicalStudentId = MockRecordNormalizer::lookupId('student', $studentId);
        $activeStatuses = ['pending', 'approved', 'waitlisted', 'attended'];
        $ownActiveActivityIds = [];
        $occupiedByActivity = [];
        foreach ($this->registrations as $registration) {
            $activityId = (string) ($registration['activity_id'] ?? '');
            $status = (string) ($registration['status'] ?? '');
            if (($registration['student_id'] ?? '') === $canonicalStudentId && in_array($status, $activeStatuses, true)) {
                $ownActiveActivityIds[$activityId] = true;
            }
            if (in_array($status, ['approved', 'attended'], true)) {
                $occupiedByActivity[$activityId] = ($occupiedByActivity[$activityId] ?? 0) + 1;
            }
        }

        $eligible = array_values(array_filter(
            $this->activities,
            static function (array $activity) use ($now, $ownActiveActivityIds, $occupiedByActivity): bool {
                if (($activity['status'] ?? '') !== ActivityStatus::Published->value) return false;
                $activityId = (string) ($activity['id'] ?? '');
                if (isset($ownActiveActivityIds[$activityId])) return false;
                if (($occupiedByActivity[$activityId] ?? 0) >= (int) ($activity['capacity'] ?? PHP_INT_MAX)) return false;

                foreach ([['registration_opens_at', '<='], ['registration_closes_at', '>'], ['start_at', '>']] as [$field, $operator]) {
                    $raw = trim((string) ($activity[$field] ?? ''));
                    if ($raw === '') continue;
                    try {
                        $date = new DateTimeImmutable($raw);
                    } catch (\Throwable) {
                        return false;
                    }
                    if ($operator === '<=' && $date > $now) return false;
                    if ($operator === '>' && $date <= $now) return false;
                }
                return true;
            }
        ));
        usort($eligible, static fn (array $left, array $right): int => [
            (string) ($left['start_at'] ?? ''), (string) ($left['id'] ?? ''),
        ] <=> [
            (string) ($right['start_at'] ?? ''), (string) ($right['id'] ?? ''),
        ]);
        return $eligible;
    }

    public function findForStudent(string $studentId, string $activityId): ?array
    {
        return $this->findById($activityId);
    }

    public function registrationTimelineFor(string $studentId): array
    {
        return $this->registrationsFor($studentId);
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
