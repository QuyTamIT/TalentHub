<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

final class CredentialRecommendationMatcher
{
    private const WEIGHTS = [
        'holland' => 35,
        'multiple_intelligence' => 30,
        'skills' => 20,
        'disc' => 7,
        'mbti' => 8,
    ];

    /** @param array<string,mixed> $profile @param list<array<string,mixed>> $catalog @return list<array<string,mixed>> */
    public function rank(array $profile, array $catalog, int $limit = 5): array
    {
        $scored = [];
        foreach ($catalog as $index => $item) {
            $tagProfile = is_array($item['recommendation_profile'] ?? null) ? $item['recommendation_profile'] : [];
            $score = 0.0;
            $matched = [];
            foreach (self::WEIGHTS as $dimension => $weight) {
                $targets = $this->values($tagProfile[$dimension] ?? []);
                if ($targets === []) {
                    continue;
                }
                $actual = $this->dimensionValues($profile[$dimension] ?? []);
                $best = 0.0;
                foreach ($targets as $target) {
                    $best = max($best, (float) ($actual[$target] ?? 0));
                }
                $score += ($best / 100) * $weight;
                if ($best >= 55) {
                    $matched[] = [$dimension, $best];
                }
            }
            usort($matched, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
            $item['match_score'] = (int) min(100, max(0, round($score)));
            $item['reason'] = $this->reason($matched);
            $item['_fallback_order'] = (int) ($item['fallback_order'] ?? $index);
            $scored[] = $item;
        }

        usort($scored, static function (array $left, array $right): int {
            $scoreCompare = ((int) ($right['match_score'] ?? 0)) <=> ((int) ($left['match_score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }
            $fallbackCompare = ((int) ($left['_fallback_order'] ?? 0)) <=> ((int) ($right['_fallback_order'] ?? 0));
            return $fallbackCompare !== 0 ? $fallbackCompare : strcmp((string) ($left['code'] ?? ''), (string) ($right['code'] ?? ''));
        });

        foreach ($scored as &$item) {
            unset($item['_fallback_order']);
        }
        unset($item);
        return array_slice($scored, 0, max(0, $limit));
    }

    /** @return list<string> */
    private function values(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== ''));
    }

    /** @return array<string,float> */
    private function dimensionValues(mixed $value): array
    {
        if (is_string($value)) {
            return [$value => 100.0];
        }
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $score) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            $result[$normalizedKey] = is_numeric($score) ? max(0.0, min(100.0, (float) $score)) : 0.0;
        }
        return $result;
    }

    /** @param list<array{0:string,1:float}> $matched */
    private function reason(array $matched): string
    {
        if ($matched === []) {
            return 'Đây là lựa chọn nền tảng của trường để bạn bắt đầu khám phá.';
        }
        $labels = [
            'holland' => 'sở thích nghề nghiệp',
            'multiple_intelligence' => 'năng lực nổi trội',
            'skills' => 'kỹ năng hiện có',
            'disc' => 'phong cách làm việc',
            'mbti' => 'môi trường học tập phù hợp',
        ];
        $parts = [];
        foreach (array_slice($matched, 0, 2) as [$dimension]) {
            $parts[] = $labels[$dimension] ?? 'hồ sơ của bạn';
        }
        return 'Phù hợp với ' . implode(' và ', $parts) . ' đang nổi bật trong hồ sơ của bạn.';
    }
}
