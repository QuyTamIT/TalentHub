<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use TalentHub\Learner\Data\Contracts\TalentPassportRepository;

final class MockTalentPassportRepository implements TalentPassportRepository
{
    /** @param array<string,mixed> $fixture */
    public function __construct(private readonly array $fixture = [])
    {
    }

    /** @return array<string,mixed> */
    public function aggregateForStudent(string $studentId): array
    {
        if ($this->fixture !== []) {
            $data = $this->fixture;
            if (isset($data['student']) && is_array($data['student'])) {
                $data['student']['id'] = $studentId;
            }
            return $data;
        }

        return [
            'student' => ['id' => $studentId, 'full_name' => 'Demo Student'],
            'skills' => [],
            'experience' => ['confirmed_hours' => 0.0, 'confirmed_entries' => []],
            'assessment_results' => [],
            'teacher_evaluations' => [],
            'activity_summary' => ['registered_count' => 0, 'attended_count' => 0, 'confirmed_hours' => 0.0],
            'certificates' => [],
            'projects' => [],
            'badges' => [],
            'achievements' => [],
            'progress' => [],
            'checkins' => [],
            'teacher_feedback' => [],
            'mentor_evaluations' => [],
            'roadmap_feedback' => [],
            'source_timestamps' => [],
            'capabilities' => ['certificates' => false, 'projects' => false, 'badges' => false],
            'source_availability' => [
                'achievement' => ['status' => 'unavailable', 'reason' => 'canonical_source_not_available'],
                'certificate' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
                'project' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
                'badge' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
                'progress' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
                'checkin' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
                'teacher_feedback' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
                'mentor_evaluation' => ['status' => 'unavailable', 'reason' => 'canonical_source_not_available'],
                'roadmap_feedback' => ['status' => 'unavailable', 'reason' => 'schema_not_available'],
            ],
        ];
    }
}
