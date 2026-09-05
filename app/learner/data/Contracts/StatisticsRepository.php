<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

use DateTimeImmutable;

interface StatisticsRepository
{
    /**
     * @return array{
     *     confirmed_experience_hours: float,
     *     attended_activity_count: int,
     *     submitted_assessment_type_count: int,
     *     published_teacher_evaluation_count: int
     * }
     */
    public function lifetimeFacts(string $studentId): array;

    /**
     * @return array{
     *     hours: float,
     *     activities: int,
     *     assessments: int,
     *     evaluations: int,
     *     badges: int,
     *     experience_buckets: list<array{date: string, label: string, hours: float}>,
     *     category_distribution: list<array{category: string, hours: float}>
     * }
     */
    public function periodStatistics(string $studentId, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
