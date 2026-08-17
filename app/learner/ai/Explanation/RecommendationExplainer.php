<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Explanation;

final class RecommendationExplainer
{
    /** @param array<string,mixed> $assessment @param array<string,mixed> $skill */
    public function technicalStrength(array $assessment, array $skill): string
    {
        $version = trim((string) ($assessment['assessment_version'] ?? ''));
        $date = $this->date((string) ($assessment['observed_at'] ?? ''));
        $skillCode = strtoupper(trim((string) ($skill['code'] ?? 'IoT')));

        return "Dựa trên kết quả Holland phiên bản {$version} ngày {$date} và kỹ năng {$skillCode} đã xác minh.";
    }

    /** @param array<string,mixed> $activity */
    public function eligibleActivity(array $activity): string
    {
        $date = $this->date((string) ($activity['observed_at'] ?? ''));
        return "Dựa trên hoạt động kỹ thuật đã được xác nhận ngày {$date}, bạn có thể tiếp tục phát triển trải nghiệm IoT.";
    }

    /** @param list<array<string,mixed>> $evaluations */
    public function communicationRoadmap(array $evaluations): string
    {
        return 'Dựa trên các đánh giá giáo viên đã công bố có điểm thuyết trình cần cải thiện, hãy luyện trình bày theo từng tuần.';
    }

    private function date(string $value): string
    {
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('d/m/Y');
        } catch (\Throwable) {
            return 'không xác định';
        }
    }
}
