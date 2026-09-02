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

        $avatarUrl = !empty($profile['avatarUrl']) ? (string) $profile['avatarUrl'] : (!empty($profile['avatar_url']) ? (string) $profile['avatar_url'] : null);
        // Chỉ hiển thị giá trị thật từ DB; khi trống trả về chuỗi rỗng/0/false, tuyệt đối không dữ liệu mẫu.
        $location = trim((string) ($profile['location'] ?? ''));
        $headline = trim((string) ($profile['headline'] ?? ''));
        $bio = trim((string) ($profile['bio'] ?? ''));

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'school_id' => (string) ($profile['school']['id'] ?? ''),
            'class_id' => (string) ($profile['class']['id'] ?? ''),
            'user_id' => (string) ($profile['userId'] ?? ''),
            'study_status' => (string) ($profile['studyStatus'] ?? 'unknown'),
            'name' => $name,
            'initials' => $initial,
            'avatar_url' => $avatarUrl,
            'avatarUrl' => $avatarUrl,
            'class' => (string) ($profile['class']['name'] ?? ''),
            'school' => (string) ($profile['school']['name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'date_of_birth' => (string) ($profile['dateOfBirth'] ?? ''),
            'location' => $location,
            'headline' => $headline,
            'bio' => $bio,
            'verified' => false,
            'streak_days' => 0,
            'experience_hours' => 0,
            'profile_completion' => (int) ($dashboard['metrics']['profileCompletion'] ?? 0),
        ];
    }
}
