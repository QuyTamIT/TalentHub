<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

final class ReadinessResult
{
    /** @var list<array{check:string,message:string}> */
    private array $passes = [];

    /** @var list<array{check:string,message:string,blocked:bool}> */
    private array $failures = [];

    public function __construct(private readonly int $phase)
    {
    }

    public function addPass(string $check, string $message): void
    {
        $this->passes[] = ['check' => trim($check), 'message' => trim($message)];
    }

    public function addFailure(string $check, string $message, bool $blocked = false): void
    {
        $this->failures[] = [
            'check' => trim($check),
            'message' => trim($message),
            'blocked' => $blocked,
        ];
    }

    public function status(): string
    {
        if ($this->failures === []) {
            return 'READY';
        }
        foreach ($this->failures as $failure) {
            if ($failure['blocked']) {
                return 'BLOCKED';
            }
        }
        return 'NOT_READY';
    }

    public function exitCode(): int
    {
        return match ($this->status()) {
            'READY' => 0,
            'BLOCKED' => 3,
            default => 2,
        };
    }

    /** @return array{phase:int,status:string,exit_code:int,passes:list<array{check:string,message:string}>,failures:list<array{check:string,message:string,blocked:bool}>} */
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
