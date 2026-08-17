<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources;

interface ConsentSource
{
    /** @return list<array{scope:string,action:string,occurred_at:string,request_id:string}> */
    public function forStudent(string $studentId): array;
}
