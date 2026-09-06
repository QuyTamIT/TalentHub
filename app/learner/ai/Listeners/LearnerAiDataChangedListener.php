<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Listeners;
use TalentHub\Learner\Ai\Events\LearnerAiDataChanged;
use TalentHub\Learner\Ai\Service\AdaptiveRefreshCoordinator;
final class LearnerAiDataChangedListener
{
 /** @var \Closure(string):string */ private readonly \Closure $snapshotHash;
 public function __construct(private readonly AdaptiveRefreshCoordinator $coordinator,callable $snapshotHash){$this->snapshotHash=\Closure::fromCallable($snapshotHash);}
 public function __invoke(LearnerAiDataChanged $event):array{return $this->coordinator->onDataChanged($event,($this->snapshotHash)($event->studentId),['profile_analysis','recommendation','roadmap']);}
 public function flush():array{return $this->coordinator->flush();}
}
