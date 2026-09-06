<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;

/**
 * Immutable, database-owned career role benchmark.
 *
 * Every value originates from the three benchmark tables; the repository
 * guarantees that skill codes exist in the canonical skills registry, that
 * weights are positive and that both weight groups are normalized to sum
 * 100. Nothing here is inferred or invented.
 */
final class CareerRoleBenchmark
{
    /**
     * @param list<array{code:string,label:string,minimum_score:int,weight:float,required:bool}> $skillRequirements
     * @param list<array{family:string,dimension:string,target:float,weight:float}> $assessmentSignals
     */
    public function __construct(
        private readonly string $code,
        private readonly string $title,
        private readonly string $category,
        private readonly array $skillRequirements,
        private readonly array $assessmentSignals,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]{1,99}\z/', $code) !== 1) {
            throw new InvalidArgumentException('Career role benchmark requires a lowercase snake_case role code.');
        }
        if ($title === '' || $category === '') {
            throw new InvalidArgumentException('Career role benchmark requires a non-empty title and category.');
        }
        $skillWeightSum = 0.0;
        foreach ($this->skillRequirements as $requirement) {
            if ($requirement['weight'] <= 0.0) {
                throw new InvalidArgumentException('Career role benchmark skill weights must be positive.');
            }
            $skillWeightSum += $requirement['weight'];
        }
        $signalWeightSum = 0.0;
        foreach ($this->assessmentSignals as $signal) {
            if ($signal['weight'] <= 0.0) {
                throw new InvalidArgumentException('Career role benchmark signal weights must be positive.');
            }
            $signalWeightSum += $signal['weight'];
        }
        if ($skillWeightSum > 0.0 && abs($skillWeightSum - 100.0) > 0.05) {
            throw new InvalidArgumentException('Career role benchmark skill weights must sum to 100.');
        }
        if ($signalWeightSum > 0.0 && abs($signalWeightSum - 100.0) > 0.05) {
            throw new InvalidArgumentException('Career role benchmark signal weights must sum to 100.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function category(): string
    {
        return $this->category;
    }

    /** @return list<array{code:string,label:string,minimum_score:int,weight:float,required:bool}> */
    public function skillRequirements(): array
    {
        return $this->skillRequirements;
    }

    /** @return list<array{family:string,dimension:string,target:float,weight:float}> */
    public function assessmentSignals(): array
    {
        return $this->assessmentSignals;
    }

    /** @return float the (normalized) total skill weight of the role */
    public function skillWeightSum(): float
    {
        $sum = 0.0;
        foreach ($this->skillRequirements as $requirement) {
            $sum += $requirement['weight'];
        }
        return $sum;
    }

    public function hasSkill(string $code): bool
    {
        return $this->skill($code) !== null;
    }

    /** @return array{code:string,label:string,minimum_score:int,weight:float,required:bool}|null */
    public function skill(string $code): ?array
    {
        foreach ($this->skillRequirements as $requirement) {
            if ($requirement['code'] === $code) {
                return $requirement;
            }
        }
        return null;
    }

    /** @return string the signal key, e.g. "holland:I" */
    public static function signalKey(string $family, string $dimension): string
    {
        return $family . ':' . $dimension;
    }
}
