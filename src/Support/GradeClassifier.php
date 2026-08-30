<?php

declare(strict_types=1);

namespace TalentHub\Support;

/**
 * Standardized Academic Grade & Percentile Classifier.
 *
 * Grading Scale (0 - 100):
 * - Score >= 90: Xuất sắc
 * - Score >= 80 and < 90: Giỏi
 * - Score >= 65 and < 80: Khá
 * - Score >= 50 and < 65: Trung bình
 * - Score < 50: Cần cải thiện
 *
 * Percentile Scale:
 * - Score >= 92: Top 5% Chuyên ngành
 * - Score >= 85 and < 92: Top 15% Chuyên ngành
 * - Score >= 80 and < 85: Top 20% Chuyên ngành
 * - Score >= 65 and < 80: Top 35% Chuyên ngành
 * - Score >= 50 and < 65: Top 60% Chuyên ngành
 * - Score < 50: Cần nỗ lực cải thiện
 */
final class GradeClassifier
{
    public static function getClassification(?float $score): string
    {
        if ($score === null) {
            return 'Chưa có dữ liệu';
        }

        return match (true) {
            $score >= 90.0 => 'Xuất sắc',
            $score >= 80.0 => 'Giỏi',
            $score >= 65.0 => 'Khá',
            $score >= 50.0 => 'Trung bình',
            default => 'Cần cải thiện',
        };
    }

    public static function getRankingPercentile(?float $score): string
    {
        if ($score === null) {
            return 'Chưa có dữ liệu';
        }

        return match (true) {
            $score >= 92.0 => 'Top 5% Chuyên ngành',
            $score >= 85.0 => 'Top 15% Chuyên ngành',
            $score >= 80.0 => 'Top 20% Chuyên ngành',
            $score >= 65.0 => 'Top 35% Chuyên ngành',
            $score >= 50.0 => 'Top 60% Chuyên ngành',
            default => 'Cần nỗ lực cải thiện',
        };
    }

    public static function getBadgeTone(?float $score): string
    {
        if ($score === null) {
            return 'neutral';
        }

        return match (true) {
            $score >= 90.0 => 'excellent',
            $score >= 80.0 => 'good',
            $score >= 65.0 => 'fair',
            $score >= 50.0 => 'average',
            default => 'improvement',
        };
    }
}
