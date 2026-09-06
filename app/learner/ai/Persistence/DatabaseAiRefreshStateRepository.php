<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Persistence;
use PDO;

final class DatabaseAiRefreshStateRepository
{
 public function __construct(private readonly PDO $pdo){}
 public function pending(string $studentId,string $capability,string $snapshotHash,string $jobKey):void{$this->update($studentId,$capability,['freshness_status'=>'stale_model','snapshot_hash'=>$snapshotHash,'refresh_job_id'=>$jobKey,'stale_since'=>gmdate('Y-m-d H:i:s'),'last_refresh_error'=>null,'next_retry_at'=>null]);}
 public function succeeded(string $studentId,string $capability,string $snapshotHash,string $jobKey,?string $modelVersion):void{$this->update($studentId,$capability,['freshness_status'=>'ready_model','snapshot_hash'=>$snapshotHash,'refresh_job_id'=>$jobKey,'model_version'=>$modelVersion,'stale_since'=>null,'last_refresh_error'=>null,'next_retry_at'=>null]);}
 public function failed(string $studentId,string $capability,string $snapshotHash,string $jobKey,string $errorCode,?string $nextRetryAt):void{$this->update($studentId,$capability,['freshness_status'=>'stale_model','snapshot_hash'=>$snapshotHash,'refresh_job_id'=>$jobKey,'stale_since'=>gmdate('Y-m-d H:i:s'),'last_refresh_error'=>substr($errorCode,0,100),'next_retry_at'=>$nextRetryAt]);}
 private function update(string $studentId,string $capability,array $fields):void
 {
  $table=$capability==='recommendation'?'learner_recommendation_runs':(in_array($capability,['roadmap','profile_analysis'],true)?'learner_ai_roadmaps':null);if($table===null||!$this->hasColumn($table,'freshness_status'))return;
  if($table==='learner_recommendation_runs'){$q=$this->pdo->prepare("SELECT id FROM {$table} WHERE studentId=:student AND engineType='model' AND status='completed' ORDER BY createdAt DESC LIMIT 1");}
  else{$q=$this->pdo->prepare("SELECT roadmaps.id FROM learner_ai_roadmaps roadmaps INNER JOIN learner_recommendation_runs runs ON runs.id=roadmaps.runId WHERE roadmaps.studentId=:student AND roadmaps.status='active' AND runs.engineType='model' ORDER BY roadmaps.versionNumber DESC LIMIT 1");}
  $q->execute(['student'=>$studentId]);$id=$q->fetchColumn();if(!is_string($id))return;
  $sets=[];$parameters=['id'=>$id];foreach($fields as $column=>$value){if(!$this->hasColumn($table,$column))continue;$sets[]="{$column}=:{$column}";$parameters[$column]=$value;}if($sets===[])return;$s=$this->pdo->prepare("UPDATE {$table} SET ".implode(',',$sets).' WHERE id=:id');$s->execute($parameters);
 }
 private function hasColumn(string $table,string $column):bool{if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$rows=$this->pdo->query("PRAGMA table_info({$table})");foreach($rows?->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)if(($row['name']??null)===$column)return true;return false;}$s=$this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');$s->execute(['table'=>$table,'column'=>$column]);return $s->fetchColumn()!==false;}
}
