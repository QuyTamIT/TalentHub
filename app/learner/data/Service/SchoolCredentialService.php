<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use TalentHub\Learner\Data\Contracts\BadgeRepository;
use TalentHub\Learner\Data\Contracts\SchoolCredentialRepository;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;

final class SchoolCredentialService
{
    public function __construct(
        private readonly SchoolCredentialRepository $credentials,
        private readonly StatisticsRepository $statistics,
        private readonly BadgeRepository $badges,
        private readonly CredentialRecommendationMatcher $matcher
    ) {}

    /** @return array<string,mixed> */
    public function forStudent(string $studentId): array
    {
        $context = $this->credentials->studentContext($studentId);
        if ($context === null) {
            return $this->emptyResult();
        }

        $assessmentProfile = $this->credentials->latestAssessmentProfile($studentId);
        $completedFamilies = array_values(array_unique(array_map(
            static fn (mixed $value): string => trim((string) $value),
            is_array($assessmentProfile['completed_families'] ?? null) ? $assessmentProfile['completed_families'] : []
        )));
        $ready = count(array_intersect(
            ['holland', 'mbti', 'disc', 'multiple_intelligence'],
            $completedFamilies
        )) === 4;
        $analysisCompleted = $ready && $this->credentials->hasCompletedRoadmap($studentId);

        $profile = $assessmentProfile;
        $profile['skills'] = $this->credentials->verifiedSkillProfile($studentId);
        $facts = $this->statistics->lifetimeFacts($studentId);
        $catalog = $this->credentials->credentialCatalog((string) $context['school_id']);

        $badgeCatalog = array_values(array_filter($catalog, static fn (array $item): bool => ($item['kind'] ?? '') === 'badge'));
        $certificateCatalog = array_values(array_filter($catalog, static fn (array $item): bool => ($item['kind'] ?? '') === 'certificate'));
        $rankedBadges = $this->rankedMap($profile, $badgeCatalog, 3);
        $rankedCertificates = $this->rankedMap($profile, $certificateCatalog, 2);

        $awardedMap = [];
        foreach ($this->badges->awardedBadges($studentId) as $award) {
            $awardedMap[(string) ($award['id'] ?? '')] = $award;
        }
        $issuedMap = [];
        foreach ($this->credentials->issuedSchoolCertificates($studentId) as $award) {
            $issuedMap[(string) ($award['catalog_id'] ?? '')] = $award;
        }

        $badgeItems = [];
        foreach ($badgeCatalog as $catalogItem) {
            $id = (string) ($catalogItem['id'] ?? '');
            $ranked = $rankedBadges[$id] ?? $catalogItem;
            $badgeItems[] = $this->present(
                $ranked,
                $facts,
                $ready,
                $analysisCompleted,
                isset($rankedBadges[$id]),
                $awardedMap[$id] ?? null,
                null,
                (string) $context['school_name']
            );
        }

        $certificateItems = [];
        foreach ($certificateCatalog as $catalogItem) {
            $id = (string) ($catalogItem['id'] ?? '');
            $ranked = $rankedCertificates[$id] ?? $catalogItem;
            $certificateItems[] = $this->present(
                $ranked,
                $facts,
                $ready,
                $analysisCompleted,
                isset($rankedCertificates[$id]),
                null,
                $issuedMap[$id] ?? null,
                (string) $context['school_name']
            );
        }

        return [
            'ready' => $ready,
            'analysis_completed' => $analysisCompleted,
            'completed_test_count' => count($completedFamilies),
            'required_test_count' => 4,
            'school' => [
                'id' => (string) $context['school_id'],
                'name' => (string) $context['school_name'],
            ],
            'featured' => $this->featured($badgeItems, $certificateItems),
            'badges' => $badgeItems,
            'certificates' => $certificateItems,
        ];
    }

    /** @param list<array<string,mixed>> $catalog @return array<string,array<string,mixed>> */
    private function rankedMap(array $profile, array $catalog, int $limit): array
    {
        $recommendable = array_values(array_filter(
            $catalog,
            static fn (array $item): bool => (bool) ($item['recommendation_enabled'] ?? false)
        ));
        $result = [];
        foreach ($this->matcher->rank($profile, $recommendable, $limit) as $item) {
            $result[(string) ($item['id'] ?? '')] = $item;
        }
        return $result;
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $facts */
    private function present(
        array $item,
        array $facts,
        bool $ready,
        bool $analysisCompleted,
        bool $isRecommended,
        ?array $badgeAward,
        ?array $certificateAward,
        string $schoolName
    ): array {
        $kind = (string) ($item['kind'] ?? 'badge');
        $criteria = is_array($item['eligibility_criteria'] ?? null) ? $item['eligibility_criteria'] : [];
        $progress = $this->criteriaProgress($criteria, $facts);
        $isAwarded = $badgeAward !== null;
        $isIssued = $certificateAward !== null && ($certificateAward['status'] ?? 'issued') !== 'revoked';

        if ($isAwarded) {
            $status = 'achieved';
        } elseif ($isIssued) {
            $status = 'issued';
        } elseif ($criteria !== [] && $progress['eligible']) {
            $status = 'eligible';
        } elseif ($ready && $analysisCompleted && $isRecommended) {
            $status = 'recommended';
        } else {
            $status = 'locked';
        }

        $matchScore = (int) ($item['match_score'] ?? 0);
        $progressPercent = in_array($status, ['achieved', 'issued', 'eligible'], true)
            ? 100
            : ($criteria !== [] ? $progress['percent'] : ($status === 'recommended' ? $matchScore : 0));

        return [
            'kind' => $kind,
            'id' => (string) ($item['id'] ?? ''),
            'code' => (string) ($item['code'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'category' => (string) ($item['category'] ?? ''),
            'issuer_name' => (string) ($item['issuer_name'] ?? $schoolName),
            'icon_key' => (string) ($item['icon_key'] ?? 'award'),
            'status' => $status,
            'status_label' => $this->statusLabel($status, $analysisCompleted, $progressPercent),
            'match_score' => $matchScore,
            'reason' => (string) ($item['reason'] ?? ($ready
                ? 'Được chọn từ bộ tiêu chí chính thức của nhà trường.'
                : 'Hoàn thành đủ bốn bài đánh giá để xem mức độ phù hợp.')),
            'current' => $progress['current'],
            'target' => $progress['target'],
            'progress_percent' => $progressPercent,
            'criteria' => $criteria,
            'awarded_at' => $badgeAward['awardedAt'] ?? null,
            'issued_at' => $certificateAward['issued_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $criteria @param array<string,mixed> $facts @return array{eligible:bool,percent:int,current:float,target:float} */
    private function criteriaProgress(array $criteria, array $facts): array
    {
        if ($criteria === []) {
            return ['eligible' => false, 'percent' => 0, 'current' => 0.0, 'target' => 0.0];
        }

        if (isset($criteria['fact'], $criteria['value'])
            && is_string($criteria['fact'])
            && is_numeric($criteria['value'])) {
            $criteria = [$criteria['fact'] => $criteria['value']];
        }

        $ratios = [];
        $firstCurrent = 0.0;
        $firstTarget = 0.0;
        foreach ($criteria as $fact => $targetValue) {
            if (!is_numeric($targetValue)) {
                continue;
            }
            $current = is_numeric($facts[$fact] ?? null) ? (float) $facts[$fact] : 0.0;
            $target = max(0.0, (float) $targetValue);
            if ($ratios === []) {
                $firstCurrent = $current;
                $firstTarget = $target;
            }
            $ratios[] = $target <= 0.0 ? 1.0 : min(1.0, max(0.0, $current / $target));
        }
        if ($ratios === []) {
            return ['eligible' => false, 'percent' => 0, 'current' => 0.0, 'target' => 0.0];
        }

        return [
            'eligible' => min($ratios) >= 1.0,
            'percent' => (int) round((array_sum($ratios) / count($ratios)) * 100),
            'current' => $firstCurrent,
            'target' => $firstTarget,
        ];
    }

    /** @param list<array<string,mixed>> $badges @param list<array<string,mixed>> $certificates @return list<array<string,mixed>> */
    private function featured(array $badges, array $certificates): array
    {
        $priority = ['achieved' => 0, 'issued' => 0, 'eligible' => 1, 'recommended' => 2, 'locked' => 3];
        $sort = static function (array $left, array $right) use ($priority): int {
            $status = ($priority[$left['status']] ?? 9) <=> ($priority[$right['status']] ?? 9);
            if ($status !== 0) {
                return $status;
            }
            return ((int) ($right['match_score'] ?? 0)) <=> ((int) ($left['match_score'] ?? 0));
        };
        usort($badges, $sort);
        usort($certificates, $sort);
        $items = array_merge(array_slice($badges, 0, 3), array_slice($certificates, 0, 2));
        usort($items, $sort);
        return $items;
    }

    private function statusLabel(string $status, bool $analysisCompleted, int $progressPercent = 0): string
    {
        if ($progressPercent > 0 && $progressPercent < 100 && !in_array($status, ['achieved', 'issued', 'eligible'], true)) {
            return 'Đang tích lũy';
        }
        return match ($status) {
            'achieved' => 'Đã đạt',
            'issued' => 'Đã cấp',
            'eligible' => 'Đủ điều kiện',
            'recommended' => $analysisCompleted ? 'AI gợi ý' : 'Phù hợp hồ sơ',
            default => 'Chưa mở khóa',
        };
    }

    /** @return array<string,mixed> */
    private function emptyResult(): array
    {
        return [
            'ready' => false,
            'analysis_completed' => false,
            'completed_test_count' => 0,
            'required_test_count' => 4,
            'school' => null,
            'featured' => [],
            'badges' => [],
            'certificates' => [],
        ];
    }
}
