<?php
declare(strict_types=1);

namespace TalentHub\Support\Clock;

/**
 * Test double - freezes time at a fixed instant so policy boundary tests
 * can assert deterministic behaviour without sleeping.
 */
final class FixedClock implements ClockInterface
{
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $today;

    public function __construct(string $iso8601 = '2026-08-30T12:00:00+00:00')
    {
        $this->now = new \DateTimeImmutable($iso8601);
        $this->today = new \DateTimeImmutable($this->now->format('Y-m-d') . 'T00:00:00+00:00');
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function nowUtc(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function todayUtc(): \DateTimeImmutable
    {
        return $this->today;
    }

    public function advance(string $duration): void
    {
        $interval = \DateInterval::createFromDateString($duration);
        if ($interval === false) {
            throw new \InvalidArgumentException(sprintf('Invalid duration: %s', $duration));
        }
        $this->now = $this->now->add($interval);
        $this->today = new \DateTimeImmutable($this->now->format('Y-m-d') . 'T00:00:00+00:00');
    }

    public function setNow(string $iso8601): void
    {
        $this->now = new \DateTimeImmutable($iso8601);
        $this->today = new \DateTimeImmutable($this->now->format('Y-m-d') . 'T00:00:00+00:00');
    }
}
