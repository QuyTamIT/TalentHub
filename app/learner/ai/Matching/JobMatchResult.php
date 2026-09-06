<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Immutable result of the deterministic 40/35/25 job match evaluation of one
 * internship candidate against one career role benchmark. Carries every
 * detail the skill gap resolver and the phase 4 Gemini payload need.
 */
final class JobMatchResult
{
    /**
     * @param list<array{code:string,label:string,current_score:int,target_score:int,gap:int,weight:float,required:bool,is_met:bool,evidence_refs:list<string>}> $skillEvaluations
     * @param list<array{code:string,label:string}> $unbenchmarkedSkills
     */
    public function __construct(
        private readonly CareerRoleBenchmark $role,
        private readonly JobMatchScore $score,
        private readonly array $skillEvaluations,
        private readonly array $unbenchmarkedSkills,
    ) {
    }

    public function role(): CareerRoleBenchmark
    {
        return $this->role;
    }

    public function score(): JobMatchScore
    {
        return $this->score;
    }

    /** @return list<array{code:string,label:string,current_score:int,target_score:int,gap:int,weight:float,required:bool,is_met:bool,evidence_refs:list<string>}> */
    public function skillEvaluations(): array
    {
        return $this->skillEvaluations;
    }

    /** @return list<array{code:string,label:string,current_score:int,target_score:int,gap:int,weight:float,required:bool,is_met:bool,evidence_refs:list<string>}> */
    public function metSkills(): array
    {
        return array_values(array_filter($this->skillEvaluations, static fn (array $e): bool => $e['is_met']));
    }

    /** @return list<array{code:string,label:string,current_score:int,target_score:int,gap:int,weight:float,required:bool,is_met:bool,evidence_refs:list<string>}> */
    public function missingSkills(): array
    {
        return array_values(array_filter($this->skillEvaluations, static fn (array $e): bool => !$e['is_met']));
    }

    /** @return list<array{code:string,label:string}> */
    public function unbenchmarkedSkills(): array
    {
        return $this->unbenchmarkedSkills;
    }
}
