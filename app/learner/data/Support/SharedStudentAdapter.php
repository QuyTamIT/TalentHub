<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\Support;

final class SharedStudentAdapter
{
    /** @return array<string,mixed> */
    public static function toView(array $profile, array $dashboard): array
    {
        $name = trim((string) ($profile['fullName'] ?? ''));
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $last = $parts === [] ? '?' : (string) end($parts);
        $initial = mb_strtoupper(mb_substr($last, 0, 1));

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'school_id' => (string) ($profile['school']['id'] ?? ''),
            'class_id' => (string) ($profile['class']['id'] ?? ''),
            'user_id' => (string) ($profile['userId'] ?? ''),
            'study_status' => (string) ($profile['studyStatus'] ?? 'unknown'),
            'name' => $name,
            'initials' => $initial,
            'class' => (string) ($profile['class']['name'] ?? ''),
            'school' => (string) ($profile['school']['name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'date_of_birth' => (string) ($profile['dateOfBirth'] ?? ''),
            'location' => '',
            'verified' => false,
            'streak_days' => 0,
            'experience_hours' => 0,
            'profile_completion' => (int) ($dashboard['metrics']['profileCompletion'] ?? 0),
        ];
    }
}
