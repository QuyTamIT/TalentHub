<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Service;

use TalentHub\Learner\Ai\Events\LearnerAiDataChanged;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;

final class AdaptiveRefreshCoordinator
{
    /** @var array<string,int> */ private array $versions=[];
    /** @var array<string,int> */ private array $lastDispatch=[];
    /** @var array<string,array{event:LearnerAiDataChanged,hash:string,capabilities:array}> */ private array $pending=[];
    /** @var \Closure():int */ private readonly \Closure $clock;
    public function __construct(private readonly AiRefreshDispatcher $dispatcher, private readonly int $debounceSeconds=30, ?callable $clock=null) { $this->clock=$clock===null?static fn():int=>time():\Closure::fromCallable($clock); }
    public function onDataChanged(LearnerAiDataChanged $event, string $snapshotHash, array $capabilities = ['recommendation', 'roadmap']): array
    {
        $key = $event->studentId . ':' . $event->sourceType . ':' . $event->sourceId;
        if (($this->versions[$key] ?? 0) >= $event->sourceVersion) {
            return [];
        }
        $this->versions[$key] = $event->sourceVersion;
        $now = ($this->clock)();
        if (isset($this->lastDispatch[$event->studentId]) && $now - $this->lastDispatch[$event->studentId] < $this->debounceSeconds) {
            $existingCaps = $this->pending[$event->studentId]['capabilities'] ?? [];
            $unionedCaps = array_values(array_unique(array_merge($existingCaps, $capabilities)));
            $this->pending[$event->studentId] = [
                'event' => $event,
                'hash' => $snapshotHash,
                'capabilities' => $unionedCaps,
            ];
            return [];
        }
        // A new event after the debounce window supersedes any queued burst. Keep
        // its capabilities in the same dispatch while using the newest snapshot.
        $queuedCapabilities = $this->pending[$event->studentId]['capabilities'] ?? [];
        $capabilities = array_values(array_unique(array_merge($queuedCapabilities, $capabilities)));
        unset($this->pending[$event->studentId]);
        $this->lastDispatch[$event->studentId] = $now;
        return $this->dispatcher->dispatch($event->studentId, $snapshotHash, $capabilities);
    }

    public function flush(): array
    {
        $result = [];
        $now = ($this->clock)();
        foreach ($this->pending as $studentId => $pending) {
            if ($now - ($this->lastDispatch[$studentId] ?? 0) < $this->debounceSeconds) {
                continue;
            }
            $result = array_merge($result, $this->dispatcher->dispatch($studentId, $pending['hash'], $pending['capabilities']));
            $this->lastDispatch[$studentId] = $now;
            unset($this->pending[$studentId]);
        }
        return $result;
    }
}
