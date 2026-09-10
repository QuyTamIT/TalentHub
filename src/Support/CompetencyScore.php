<?php
declare(strict_types=1);

namespace TalentHub\Support;

/** Teacher overallScore is always on the canonical 0–100 scale. */
final class CompetencyScore
{
    public static function display(?float $score): string
    {
        return $score === null ? 'Chưa có điểm' : number_format($score / 10.0, 1, ',', '');
    }
}
