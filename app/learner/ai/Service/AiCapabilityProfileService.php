<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Service;

final class AiCapabilityProfileService
{
    /** @var array<string,array<string,mixed>> */ private array $profiles=[];
    /** @var array<string,list<array<string,mixed>>> */ private array $history=[];
    public function publish(string $studentId,array $profile,string $snapshotHash,string $modelVersion,string $generatedAt): array
    { foreach (['talent_map','strengths','improvements','potential_paths','trend_signals','evidence'] as $field) if (!array_key_exists($field,$profile)) throw new \InvalidArgumentException("Missing capability profile field: {$field}"); if (isset($this->profiles[$studentId])) $this->history[$studentId][]=$this->profiles[$studentId]; $profile['student_id']=$studentId; $profile['snapshot_hash']=$snapshotHash; $profile['model_version']=$modelVersion; $profile['generated_at']=$generatedAt; $profile['status']='ready_model'; $profile['version']=count($this->history[$studentId]??[])+1; $this->profiles[$studentId]=$profile; return $profile; }
    public function markStale(string $studentId,string $reason, string $staleSince): void { if (!isset($this->profiles[$studentId])) return; $this->profiles[$studentId]['status']='stale_model'; $this->profiles[$studentId]['stale_since']=$staleSince; $this->profiles[$studentId]['last_refresh_error']=$reason; }
    public function get(string $studentId): ?array { return $this->profiles[$studentId] ?? null; }
    public function rollback(string $studentId): ?array { $previous=array_pop($this->history[$studentId]); if ($previous===null) return $this->profiles[$studentId]??null; $this->profiles[$studentId]=$previous; return $previous; }
}
