<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Quality;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

final class DataQualityGate
{
    private const MAX_ASSESSMENT_AGE_DAYS = 365;

    private readonly DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null, private readonly bool $allowAssessmentOnly = false)
    {
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function evaluate(RecommendationInput $input): DataQualityResult
    {
        $flags = $input->qualityFlags();
        $missingConsent = array_values(array_filter(
            $flags['missing_consent_scopes'] ?? [],
            static fn (mixed $scope): bool => is_string($scope) && $scope !== ''
        ));
        sort($missingConsent, SORT_STRING);
        if ($missingConsent !== []) {
            return new DataQualityResult(
                'consent_required',
                $missingConsent,
                [],
                array_map(static fn (string $scope): array => [
                    'type' => 'grant_consent',
                    'scope' => $scope,
                    'message' => "Cấp quyền sử dụng dữ liệu {$scope} để tạo gợi ý cá nhân hóa.",
                ], $missingConsent)
            );
        }

        $payload = $input->payload();
        $missing = [];
        $hasAssessment = $this->hasCurrentAssessment($payload['assessments'] ?? []);
        if (!$hasAssessment) {
            $missing[] = 'assessment';
        }
        if ($this->allowAssessmentOnly) {
            if ($missing === []) {
                return new DataQualityResult('ready');
            }

            return new DataQualityResult(
                'insufficient_data',
                [],
                $missing,
                array_map(fn (string $category): array => $this->completionAction($category), $missing)
            );
        }
        if (count($payload['skills'] ?? []) < 2) {
            $missing[] = 'skills';
        }
        if (count($payload['activities'] ?? []) < 1) {
            $missing[] = 'experience';
        }
        if (count($payload['evaluations'] ?? []) < 1) {
            $missing[] = 'evaluations';
        }
        if ($missing === []) {
            return new DataQualityResult('ready');
        }

        return new DataQualityResult(
            'insufficient_data',
            [],
            $missing,
            array_map(fn (string $category): array => $this->completionAction($category), $missing)
        );
    }

    /** @param mixed $assessments */
    private function hasCurrentAssessment(mixed $assessments): bool
    {
        if (!is_array($assessments)) {
            return false;
        }
        $cutoff = $this->now->setTimezone(new DateTimeZone('UTC'))->modify('-' . self::MAX_ASSESSMENT_AGE_DAYS . ' days');
        foreach ($assessments as $assessment) {
            if (!is_array($assessment) || !is_string($assessment['submitted_at'] ?? null)) {
                continue;
            }
            try {
                $submittedAt = (new DateTimeImmutable($assessment['submitted_at']))->setTimezone(new DateTimeZone('UTC'));
            } catch (\Throwable) {
                continue;
            }
            if ($submittedAt >= $cutoff && $submittedAt <= $this->now) {
                return true;
            }
        }
        return false;
    }

    /** @return array{type:string,category:string,message:string} */
    private function completionAction(string $category): array
    {
        return match ($category) {
            'assessment' => ['type' => 'complete_assessment', 'category' => 'assessment', 'message' => 'Hoàn thành một bài đánh giá còn hiệu lực để tạo gợi ý.'],
            'skills' => ['type' => 'add_skills', 'category' => 'skills', 'message' => 'Thêm ít nhất hai kỹ năng để tạo gợi ý.'],
            'experience' => ['type' => 'confirm_experience', 'category' => 'experience', 'message' => 'Hoàn tất một hoạt động đã được xác nhận để tạo gợi ý.'],
            'evaluations' => ['type' => 'request_evaluation', 'category' => 'evaluations', 'message' => 'Chờ một đánh giá giáo viên đã công bố để tạo gợi ý.'],
        };
    }
}
