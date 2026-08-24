<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Support;

final class LearnerViewAdapter
{
    public static function record(array $record): array
    {
        foreach ($record as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $record[$key] = array_map(
                    static fn (mixed $item): mixed => is_array($item) ? self::record($item) : $item,
                    $value
                );
            } else {
                $record[$key] = self::record($value);
            }
        }

        if (($record['id_origin'] ?? null) === 'mock_compat') {
            foreach (array_keys($record) as $key) {
                if (!str_starts_with((string) $key, 'legacy_')) {
                    continue;
                }

                $target = substr((string) $key, strlen('legacy_'));
                $record[$target] = $record[$key];
            }
        }

        foreach (array_keys($record) as $key) {
            if (str_starts_with((string) $key, 'legacy_')) {
                unset($record[$key]);
            }
        }
        unset($record['id_origin']);

        return $record;
    }

    public static function records(array $records): array
    {
        return array_map(
            static fn (array $record): array => self::record($record),
            $records
        );
    }

    public static function student(array $student): array
    {
        $view = self::record($student);
        $name = (string) ($view['name'] ?? $view['full_name'] ?? '');
        $className = (string) ($view['class'] ?? $view['class_name'] ?? '');
        $schoolName = (string) ($view['school'] ?? $view['school_name'] ?? '');

        $view['name'] = $name;
        $view['initials'] = (string) ($view['initials'] ?? self::initials($name));
        $view['class'] = $className;
        $view['school'] = $schoolName;
        $view += [
            'email' => '',
            'location' => '',
            'verified' => false,
            'streak_days' => 0,
            'experience_hours' => 0,
        ];

        return $view;
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '';
        }

        $last = (string) end($parts);
        return function_exists('mb_substr') ? mb_substr($last, 0, 1, 'UTF-8') : substr($last, 0, 1);
    }
}
