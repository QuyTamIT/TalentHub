<?php
declare(strict_types=1);

namespace TalentHub\Support\Clock;

final class SystemClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeZone $utc = new \DateTimeZone('UTC'))
    {
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->utc);
    }

    public function nowUtc(): \DateTimeImmutable
    {
        return $this->now();
    }

    public function todayUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', $this->utc);
    }
}
