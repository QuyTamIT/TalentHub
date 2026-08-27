<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

final class TalentPassportReadModel
{
    /**
     * @param array<string,mixed> $aggregate
     * @return array<string,mixed>
     */
    public static function fromAggregate(array $aggregate): array
    {
        return [
            'student' => is_array($aggregate['student'] ?? null) ? $aggregate['student'] : [],
            'skills' => is_array($aggregate['skills'] ?? null) ? array_values($aggregate['skills']) : [],
            'experience' => [
                'confirmed_hours' => (float) ($aggregate['experience']['confirmed_hours'] ?? 0.0),
                'confirmed_entries' => is_array($aggregate['experience']['confirmed_entries'] ?? null)
                    ? array_values($aggregate['experience']['confirmed_entries'])
                    : [],
            ],
            'assessment_results' => is_array($aggregate['assessment_results'] ?? null)
                ? array_values($aggregate['assessment_results'])
                : [],
            'teacher_evaluations' => is_array($aggregate['teacher_evaluations'] ?? null)
                ? array_values($aggregate['teacher_evaluations'])
                : [],
            'activity_summary' => is_array($aggregate['activity_summary'] ?? null)
                ? $aggregate['activity_summary']
                : [],
            'certificates' => is_array($aggregate['certificates'] ?? null)
                ? array_values($aggregate['certificates'])
                : [],
            'projects' => is_array($aggregate['projects'] ?? null)
                ? array_values($aggregate['projects'])
                : [],
            'badges' => is_array($aggregate['badges'] ?? null)
                ? array_values($aggregate['badges'])
                : [],
            'ai_capability_profile' => is_array($aggregate['ai_capability_profile'] ?? null)
                ? $aggregate['ai_capability_profile']
                : null,
            'source_timestamps' => is_array($aggregate['source_timestamps'] ?? null)
                ? $aggregate['source_timestamps']
                : [],
            'capabilities' => [
                'certificates' => (bool) ($aggregate['capabilities']['certificates'] ?? false),
                'projects' => (bool) ($aggregate['capabilities']['projects'] ?? false),
                'badges' => (bool) ($aggregate['capabilities']['badges'] ?? false),
            ],
        ];
    }
}
