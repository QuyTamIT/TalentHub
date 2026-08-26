<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

use DateTimeImmutable;

interface BadgeRepository
{
    /**
     * @return list<array{
     *     badge: array{id: string, code: string, name: string, category: string, description: string, iconUrl: ?string, level: int, status: string},
     *     rule: array{id: string, badgeId: string, ruleType: string, thresholdCriteria: array<string,mixed>, version: int, isActive: bool}
     * }>
     */
    public function activeRules(): array;

    /**
     * Returns global rules plus rules owned by the student's school.
     *
     * @return list<array{badge:array<string,mixed>,rule:array<string,mixed>}>
     */
    public function activeRulesForStudent(string $studentId): array;

    /**
     * @return list<array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     category: string,
     *     description: string,
     *     iconUrl: ?string,
     *     level: int,
     *     studentBadgeId: string,
     *     awardedAt: string,
     *     awardedBy: string,
     *     awardContext: array<string,mixed>
     * }>
     */
    public function awardedBadges(string $studentId): array;

    public function isAwarded(string $studentId, string $badgeId): bool;

    /**
     * @param array<string,mixed> $awardContext
     */
    public function insertAward(
        string $studentId,
        string $badgeId,
        string $ruleDefinitionId,
        string $awardedBy,
        array $awardContext,
        DateTimeImmutable $awardedAt
    ): bool;

    /**
     * @return array{userId: string, fullName: string}|null
     */
    public function userForStudent(string $studentId): ?array;

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function withTransaction(callable $operation): mixed;
}
