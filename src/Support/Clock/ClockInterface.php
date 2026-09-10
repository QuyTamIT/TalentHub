<?php
declare(strict_types=1);

namespace TalentHub\Support\Clock;

/**
 * Injectable clock abstraction so that all domain policies, services and
 * repositories can be tested deterministically at time boundaries without
 * hard-coded `new DateTimeImmutable('now')` calls.
 *
 * Production code MUST inject SystemClock; tests inject FixedClock.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;

    /**
     * @return \DateTimeImmutable UTC now, microsecond precision.
     */
    public function nowUtc(): \DateTimeImmutable;

    /**
     * @return \DateTimeImmutable 'today' at 00:00:00 UTC (calendar boundary).
     */
    public function todayUtc(): \DateTimeImmutable;
}
