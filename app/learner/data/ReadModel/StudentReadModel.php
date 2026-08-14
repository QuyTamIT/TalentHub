<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

final class StudentReadModel
{
    public static function fromRecord(array $record): array
    {
        $record['name'] ??= $record['full_name'] ?? null;
        $record['class'] ??= $record['class_name'] ?? null;
        $record['school'] ??= $record['school_name'] ?? null;

        $view = ReadModelDefaults::apply($record, [
            'id' => '',
            'student_id' => '',
            'name' => 'Học sinh TalentHub',
            'initials' => 'TH',
            'class' => 'Chưa cập nhật',
            'school' => 'Chưa cập nhật',
            'email' => '',
            'location' => 'Chưa cập nhật',
            'verified' => false,
            'streak_days' => 0,
            'experience_hours' => 0,
        ], 'student');

        if (($view['initials'] ?? '') === 'TH' && ($view['name'] ?? '') !== 'Học sinh TalentHub') {
            $parts = preg_split('/\s+/u', trim((string) $view['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $last = (string) ($parts === [] ? '' : end($parts));
            $view['initials'] = function_exists('mb_substr') ? mb_substr($last, 0, 1, 'UTF-8') : substr($last, 0, 1);
        }

        return $view;
    }
}
