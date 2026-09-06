<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

/**
 * Owns the storage contract for learner-AI evidence source types.
 *
 * New rows use the canonical family while reads remain compatible with
 * snapshots written before the evidence families were consolidated.
 */
final class EvidenceSourceTypeNormalizer
{
    /** @var array<string,list<string>> */
    private const FAMILIES = [
        'opportunity' => ['catalog'],
        'assessment' => ['profile'],
        'activity_experience' => ['project', 'activity', 'progress', 'checkin'],
        'skill' => ['certificate', 'badge', 'achievement'],
        'evaluation' => ['mentor_evaluation', 'teacher_feedback', 'roadmap_feedback'],
    ];

    public static function canonical(string $sourceType): string
    {
        $sourceType = trim($sourceType);
        foreach (self::FAMILIES as $canonical => $aliases) {
            if ($sourceType === $canonical || in_array($sourceType, $aliases, true)) {
                return $canonical;
            }
        }

        return $sourceType;
    }

    /** @return list<string> */
    public static function lookupTypes(string $sourceType): array
    {
        $canonical = self::canonical($sourceType);
        $aliases = self::FAMILIES[$canonical] ?? [];

        return array_values(array_unique([$canonical, ...$aliases]));
    }

    /**
     * Resolve two records that normalize to the same snapshot storage key.
     * Canonical evidence wins; compatible aliases use richness, recency, then
     * a lexical tie-break so source registration order cannot change a hash.
     *
     * @param array{logicalType:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string} $left
     * @param array{logicalType:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string} $right
     * @return array{logicalType:string,sourceType:string,sourceId:string,observedAt:?string,safeValueJson:string}
     */
    public static function preferSnapshotEvidence(array $left, array $right): array
    {
        if ($left['sourceType'] !== $right['sourceType'] || $left['sourceId'] !== $right['sourceId']) {
            throw new \InvalidArgumentException('Evidence candidates must share one canonical storage key.');
        }
        if (self::canonical($left['logicalType']) !== $left['sourceType']
            || self::canonical($right['logicalType']) !== $right['sourceType']) {
            throw new \RuntimeException('Evidence normalization collision: ' . $left['sourceType'] . ':' . $left['sourceId']);
        }

        if ($left['logicalType'] === $right['logicalType']) {
            if ($left['observedAt'] === $right['observedAt'] && $left['safeValueJson'] === $right['safeValueJson']) {
                return $left;
            }
            throw new \RuntimeException('Conflicting duplicate evidence: ' . $left['logicalType'] . ':' . $left['sourceId']);
        }

        $leftCanonical = $left['logicalType'] === $left['sourceType'];
        $rightCanonical = $right['logicalType'] === $right['sourceType'];
        if ($left['sourceType'] === 'opportunity' && $leftCanonical !== $rightCanonical) {
            return $rightCanonical ? $right : $left;
        }

        $leftRichness = self::richness(json_decode($left['safeValueJson'], true, 512, JSON_THROW_ON_ERROR));
        $rightRichness = self::richness(json_decode($right['safeValueJson'], true, 512, JSON_THROW_ON_ERROR));
        if ($leftRichness !== $rightRichness) {
            return $rightRichness > $leftRichness ? $right : $left;
        }
        if ($left['observedAt'] !== $right['observedAt']) {
            return strcmp($right['observedAt'] ?? '', $left['observedAt'] ?? '') > 0 ? $right : $left;
        }

        $leftTieBreak = $left['logicalType'] . "\0" . $left['safeValueJson'];
        $rightTieBreak = $right['logicalType'] . "\0" . $right['safeValueJson'];
        return strcmp($rightTieBreak, $leftTieBreak) < 0 ? $right : $left;
    }

    private static function richness(mixed $value): int
    {
        if (!is_array($value)) {
            return $value === null || $value === '' ? 0 : 1;
        }
        $score = count($value);
        foreach ($value as $child) {
            $score += self::richness($child);
        }
        return $score;
    }
}
