<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use InvalidArgumentException;

/** SQL identifiers are selected here, never interpolated from request data. */
final class TeacherAssessmentScope
{
    public static function column(string $mode): string
    {
        return match ($mode) {
            'activity' => 'activityId',
            'class' => 'classId',
            'project' => 'projectId',
            default => throw new InvalidArgumentException('Invalid assessment context.'),
        };
    }
}
