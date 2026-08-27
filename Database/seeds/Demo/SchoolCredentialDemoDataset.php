<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

final class SchoolCredentialDemoDataset
{
    /** @return array{badges:list<array<string,mixed>>,certificates:list<array<string,mixed>>} */
    public static function forSchool(string $schoolId, string $schoolName): array
    {
        $badgeDefinitions = [
            ['profile_complete', 'Hồ sơ năng lực hoàn chỉnh', 'assessment', 'Hoàn thành đủ bốn bài đánh giá năng lực.', ['holland' => [], 'multiple_intelligence' => [], 'disc' => [], 'mbti' => [], 'skills' => []]],
            ['analytical_thinker', 'Nhà tư duy phân tích', 'assessment', 'Phát triển tư duy logic và khả năng nghiên cứu.', ['holland' => ['I'], 'multiple_intelligence' => ['logical'], 'disc' => ['C'], 'mbti' => ['INTJ', 'INTP', 'ISTJ'], 'skills' => ['problem_solving', 'data_analysis', 'research']]],
            ['creative_ideator', 'Nhà sáng tạo ý tưởng', 'creative', 'Khuyến khích biến ý tưởng thành sản phẩm sáng tạo.', ['holland' => ['A'], 'multiple_intelligence' => ['spatial', 'linguistic'], 'disc' => ['I'], 'mbti' => ['ENFP', 'INFP', 'ENTP'], 'skills' => ['creative_design', 'communication']]],
            ['community_connector', 'Người kết nối cộng đồng', 'community', 'Ghi nhận năng lực giao tiếp và hợp tác.', ['holland' => ['S', 'E'], 'multiple_intelligence' => ['interpersonal', 'linguistic'], 'disc' => ['I', 'S'], 'mbti' => ['ENFJ', 'ESFJ', 'INFJ'], 'skills' => ['communication', 'teamwork', 'leadership']]],
            ['reliable_organizer', 'Người tổ chức đáng tin cậy', 'leadership', 'Thể hiện sự cẩn trọng, bền bỉ và có trách nhiệm.', ['holland' => ['C'], 'multiple_intelligence' => ['logical'], 'disc' => ['C', 'S'], 'mbti' => ['ISTJ', 'ISFJ', 'ESTJ'], 'skills' => ['teamwork', 'problem_solving']]],
            ['project_pioneer', 'Tiên phong dự án thực tế', 'experience', 'Sẵn sàng đưa năng lực vào dự án thực tế.', ['holland' => ['R', 'I', 'E'], 'multiple_intelligence' => ['logical', 'spatial'], 'disc' => ['D'], 'mbti' => ['ENTJ', 'ESTP', 'ISTP'], 'skills' => ['python', 'leadership', 'entrepreneurship']]],
        ];

        $badges = [];
        foreach ($badgeDefinitions as [$suffix, $name, $category, $description, $profile]) {
            $badges[] = [
                'id' => self::uuid($schoolId, 'badge', $suffix),
                'code' => self::code($schoolId, 'badge', $suffix),
                'code_suffix' => $suffix,
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'icon_key' => $suffix === 'profile_complete' ? 'clipboard' : 'award',
                'level' => $suffix === 'project_pioneer' ? 2 : 1,
                'recommendation_profile' => $profile,
                'recommendation_enabled' => $suffix !== 'profile_complete',
                'rule' => $suffix === 'profile_complete'
                    ? ['id' => self::uuid($schoolId, 'badge-rule', $suffix), 'fact' => 'submitted_assessment_type_count', 'value' => 4]
                    : null,
            ];
        }

        $certificateDefinitions = [
            ['problem_solving_foundation', 'Nền tảng tư duy và giải quyết vấn đề', 'Hoàn thành nền tảng tư duy và giải quyết vấn đề.', ['holland' => ['I'], 'multiple_intelligence' => ['logical'], 'disc' => ['C'], 'mbti' => ['INTJ', 'ISTJ'], 'skills' => ['problem_solving', 'research']], ['submitted_assessment_type_count' => 4, 'confirmed_experience_hours' => 1]],
            ['teamwork_communication', 'Kỹ năng giao tiếp và làm việc nhóm', 'Thực hành giao tiếp, phối hợp và trình bày trong môi trường học tập.', ['holland' => ['S', 'E'], 'multiple_intelligence' => ['interpersonal', 'linguistic'], 'disc' => ['I', 'S'], 'mbti' => ['ENFJ', 'ESFJ'], 'skills' => ['communication', 'teamwork']], ['submitted_assessment_type_count' => 4, 'attended_activity_count' => 1]],
            ['applied_project', 'Thực hành dự án ứng dụng', 'Áp dụng năng lực vào một dự án thực tế được nhà trường xác nhận.', ['holland' => ['R', 'I'], 'multiple_intelligence' => ['logical', 'spatial'], 'disc' => ['D', 'C'], 'mbti' => ['ISTP', 'ENTJ'], 'skills' => ['python', 'problem_solving']], ['submitted_assessment_type_count' => 4, 'confirmed_experience_hours' => 10]],
            ['career_readiness', 'Sẵn sàng định hướng nghề nghiệp', 'Xây dựng hồ sơ và kế hoạch phát triển nghề nghiệp cá nhân.', ['holland' => ['E', 'C'], 'multiple_intelligence' => ['linguistic', 'interpersonal'], 'disc' => ['D', 'I'], 'mbti' => ['ENTJ', 'ENFJ'], 'skills' => ['leadership', 'communication']], ['submitted_assessment_type_count' => 4, 'published_teacher_evaluation_count' => 1]],
        ];

        $certificates = [];
        foreach ($certificateDefinitions as [$suffix, $name, $description, $profile, $criteria]) {
            $certificates[] = [
                'id' => self::uuid($schoolId, 'certificate', $suffix),
                'code' => self::code($schoolId, 'certificate', $suffix),
                'name' => $name,
                'description' => $description,
                'issuer_name' => $schoolName,
                'icon_key' => 'certificate',
                'eligibility_criteria' => $criteria,
                'recommendation_profile' => $profile,
                'recommendation_enabled' => true,
                'suffix' => $suffix,
            ];
        }

        return ['badges' => $badges, 'certificates' => $certificates];
    }

    public static function uuid(string $schoolId, string $kind, string $suffix): string
    {
        $hex = substr(hash('sha256', "talenthub-school-credential-v1\0{$schoolId}\0{$kind}\0{$suffix}"), 0, 32);
        $hex[12] = '4';
        $hex[16] = '8';
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    private static function code(string $schoolId, string $kind, string $suffix): string
    {
        return 'school_' . substr(str_replace('-', '', $schoolId), 0, 8) . '_' . $kind . '_' . $suffix;
    }
}
