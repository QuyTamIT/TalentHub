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

    /**
     * Consecutive confirmed check-in days (learning streak), counted backwards
     * from the most recent confirmed day.
     *
     * @return int
     */
    public function checkinStreakDays(string $studentId, ?DateTimeImmutable $now = null): int;

    /**
     * Verified/self-declared competency skills with scores (0-100).
     *
     * @return list<array{name: string, category: string, score: float}>
     */
    public function skillCompetencies(string $studentId): array;

    /**
     * Latest completed psychometric test result per assessment family
     * (holland, mbti, disc, multiple-intelligence).
     *
     * @return array<string, array{type: string, result_code: string, summary: string, dimension_scores: array<string,float>, submitted_at: string}|null>
     */
    public function psychometricResults(string $studentId): array;

    /**
     * Latest published teacher evaluation with per-criteria scores.
     *
     * @return array{
     *     total_score: float|null,
     *     comment: string,
     *     published_at: string|null,
     *     criteria: list<array{code: string, name: string, score: float, max: float, percentage: int}>
     * }
     */
    public function latestPublishedEvaluation(string $studentId): array;

    /**
     * Project participation statistics for the student.
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     in_progress: int,
     *     leader_roles: int,
     *     featured: list<array{name: string, role: string, status: string}>
     * }
     */
    public function projectStatistics(string $studentId): array;
}
