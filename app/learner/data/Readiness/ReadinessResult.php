<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

final class ReadinessResult
{
    private const READY = 'READY';
    private const NOT_READY = 'NOT_READY';
    private const BLOCKED = 'BLOCKED';

    /** @var list<array{check:string,message:string}> */
    private array $passes = [];

    /** @var list<array{check:string,message:string,blocked:bool}> */
    private array $failures = [];

    public function __construct(private readonly int $phase)
    {
    }

    public function addPass(string $check, string $message): void
    {
        $this->passes[] = ['check' => $check, 'message' => $message];
    }

    public function addFailure(string $check, string $message, bool $blocked = false): void
    {
        $this->failures[] = ['check' => $check, 'message' => $message, 'blocked' => $blocked];
    }

    public function status(): string
    {
        foreach ($this->failures as $failure) {
            if ($failure['blocked']) {
                return self::BLOCKED;
            }
        }

        return $this->failures === [] ? self::READY : self::NOT_READY;
    }

    public function exitCode(): int
    {
        return match ($this->status()) {
            self::READY => 0,
            self::NOT_READY => 2,
            self::BLOCKED => 3,
        };
    }

    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'status' => $this->status(),
            'exit_code' => $this->exitCode(),
            'passes' => $this->passes,
            'failures' => $this->failures,
        ];
    }
}
