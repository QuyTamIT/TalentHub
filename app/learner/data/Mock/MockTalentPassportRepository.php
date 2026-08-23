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
            'source_timestamps' => [],
            'capabilities' => ['certificates' => false, 'projects' => false, 'badges' => false],
        ];
    }
}
