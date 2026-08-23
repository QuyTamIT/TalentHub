<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Domain;

final class LevelProgression
{
    public const CONFIG_VERSION = 'experience-hours-v1';

    public const THRESHOLDS = [
        ['name' => 'Explorer',  'number' => 1, 'minHours' => 0.0,   'maxHours' => 10.0],
        ['name' => 'Innovator', 'number' => 2, 'minHours' => 10.0,  'maxHours' => 100.0],
        ['name' => 'Expert',    'number' => 3, 'minHours' => 100.0, 'maxHours' => 200.0],
        ['name' => 'Master',    'number' => 4, 'minHours' => 200.0, 'maxHours' => null],
    ];

    /**
     * @return array{
     *     configVersion: string,
     *     name: string,
     *     number: int,
     *     currentHours: float,
     *     targetHours: float,
     *     nextLevel: string|null,
     *     nextThreshold: float|null,
     *     remainingHours: float,
     *     progressPercent: int
     * }
     */
    public static function fromHours(float $hours): array
    {
        $hours = max(0.0, $hours);

        if ($hours >= 200.0) {
            return [
                'configVersion' => self::CONFIG_VERSION,
                'name' => 'Master',
                'number' => 4,
                'currentHours' => $hours,
                'targetHours' => 200.0,
                'nextLevel' => null,
                'nextThreshold' => null,
                'remainingHours' => 0.0,
                'progressPercent' => 100,
            ];
        }

        if ($hours >= 100.0) {
            $range = 200.0 - 100.0;
            $progress = $hours - 100.0;
            $percent = (int) min(100, max(0, floor(($progress / $range) * 100)));

            return [
                'configVersion' => self::CONFIG_VERSION,
                'name' => 'Expert',
                'number' => 3,
                'currentHours' => $hours,
                'targetHours' => 200.0,
                'nextLevel' => 'Master',
                'nextThreshold' => 200.0,
                'remainingHours' => round(200.0 - $hours, 2),
                'progressPercent' => $percent,
            ];
        }

        if ($hours >= 10.0) {
            $range = 100.0 - 10.0;
            $progress = $hours - 10.0;
            $percent = (int) min(100, max(0, floor(($progress / $range) * 100)));

            return [
                'configVersion' => self::CONFIG_VERSION,
                'name' => 'Innovator',
                'number' => 2,
                'currentHours' => $hours,
                'targetHours' => 100.0,
                'nextLevel' => 'Expert',
                'nextThreshold' => 100.0,
                'remainingHours' => round(100.0 - $hours, 2),
                'progressPercent' => $percent,
            ];
        }

        $range = 10.0;
        $percent = (int) min(100, max(0, floor(($hours / $range) * 100)));

        return [
            'configVersion' => self::CONFIG_VERSION,
            'name' => 'Explorer',
            'number' => 1,
            'currentHours' => $hours,
            'targetHours' => 10.0,
            'nextLevel' => 'Innovator',
            'nextThreshold' => 10.0,
            'remainingHours' => round(10.0 - $hours, 2),
            'progressPercent' => $percent,
        ];
    }
}
