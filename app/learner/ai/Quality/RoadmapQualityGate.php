<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Quality;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

final class RoadmapQualityGate
{
    private const MAX_ASSESSMENT_AGE_DAYS = 365;
    private const REQUIRED_FAMILIES = ['disc', 'holland', 'mbti', 'multiple_intelligence'];

    private readonly DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    public function evaluate(RecommendationInput $input): DataQualityResult
    {
        $missingConsent = array_values(array_filter(
            $input->qualityFlags()['missing_consent_scopes'] ?? [],
            static fn (mixed $scope): bool => $scope === 'assessment',
        ));
        if ($missingConsent !== []) {
            return new DataQualityResult('consent_required', ['assessment'], [], [[
                'type' => 'grant_consent',
                'scope' => 'assessment',
                'message' => 'Cấp quyền sử dụng kết quả đánh giá để AI xây dựng lộ trình.',
            ]]);
        }

        $families = [];
        $assessments = $input->payload()['assessments'] ?? [];
        if (is_array($assessments)) {
            foreach ($assessments as $assessment) {
                if (!is_array($assessment) || !$this->isCurrent($assessment)) {
                    continue;
                }
                $family = $this->family($assessment);
                $scores = $assessment['dimension_scores'] ?? null;
                if ($family !== null && is_array($scores) && $scores !== []) {
                    $families[$family] = true;
                }
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_FAMILIES, array_keys($families)));
        if ($missing === []) {
            return new DataQualityResult('ready');
        }

        return new DataQualityResult(
            'insufficient_data',
            [],
            $missing,
            array_map(static fn (string $family): array => [
                'type' => 'complete_assessment',
                'category' => $family,
                'message' => "Hoàn thành bài đánh giá {$family} còn hiệu lực để AI xây dựng lộ trình.",
            ], $missing),
        );
    }

    /** @param array<string,mixed> $assessment */
    private function isCurrent(array $assessment): bool
    {
        $value = $assessment['submitted_at'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        try {
            $submittedAt = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return false;
        }
        $cutoff = $this->now->modify('-' . self::MAX_ASSESSMENT_AGE_DAYS . ' days');
        return $submittedAt >= $cutoff && $submittedAt <= $this->now;
    }

    /** @param array<string,mixed> $assessment */
    private function family(array $assessment): ?string
    {
        $type = strtolower(trim((string) ($assessment['test_type'] ?? '')));
        if (in_array($type, self::REQUIRED_FAMILIES, true)) {
            return $type;
        }
        $code = strtolower(trim((string) ($assessment['test_code'] ?? '')));
        foreach (self::REQUIRED_FAMILIES as $family) {
            if ($code === $family || str_starts_with($code, $family . '_')) {
                return $family;
            }
        }
        return null;
    }
}
