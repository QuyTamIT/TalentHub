<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;

final class AiRefreshDispatcher
{
    public function __construct(private readonly AiRefreshJobRepository $jobs) {}
    public function dispatch(string $studentId,string $snapshotHash,array $capabilities=['recommendation','roadmap']): array
    {
        $priority = ['roadmap' => 1, 'recommendation' => 2, 'profile_analysis' => 3];
        $normalized = [];
        foreach ($capabilities as $capability) {
            if (!is_string($capability) || !isset($priority[$capability])) {
                continue;
            }
            $normalized[$capability] = true;
        }
        $ordered = array_keys($normalized);
        usort($ordered, static fn(string $a, string $b): int => $priority[$a] <=> $priority[$b]);
        $result = [];
        foreach ($ordered as $capability) {
            $this->jobs->cancelSuperseded($studentId, $capability, $snapshotHash);
            $result[] = $this->jobs->enqueue($studentId, $snapshotHash, $capability);
        }
        return $result;
    }
}
