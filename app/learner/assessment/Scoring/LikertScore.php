<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Scoring;

use RuntimeException;

final class LikertScore
{
    public static function value(mixed $answer, bool $reversed = false): int
    {
        if (is_int($answer)) {
            $value = $answer;
        } elseif (is_string($answer) && preg_match('/\A[1-5]\z/', trim($answer)) === 1) {
            $value = (int) trim($answer);
        } else {
            throw new RuntimeException('Assessment answers must be integers from 1 to 5.');
        }

        if ($value < 1 || $value > 5) {
            throw new RuntimeException('Assessment answers must be integers from 1 to 5.');
        }

        return $reversed ? 6 - $value : $value;
    }

    public static function normalize(int $total, int $count): int
    {
        return $count === 0 ? 0 : (int) round((($total - $count) / ($count * 4)) * 100);
    }
}
