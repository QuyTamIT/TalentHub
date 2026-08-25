<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

final class PostAssessmentAiTrigger
{
    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array{required:bool,state:string}
     */
    public static function metadata(array $before, array $after): array
    {
        $requiredCount = (int) ($after['required_count'] ?? 0);
        $shouldAnalyze = ($before['required'] ?? false) === true
            && ($after['required'] ?? false) === true
            && ($before['status'] ?? null) === 'accepted'
            && ($after['status'] ?? null) === 'completed'
            && $requiredCount === 4
            && (int) ($before['completed_count'] ?? -1) === $requiredCount - 1
            && (int) ($after['completed_count'] ?? -1) === $requiredCount;

        return $shouldAnalyze
            ? ['required' => true, 'state' => 'not_generated']
            : ['required' => false, 'state' => 'not_required'];
    }
}
