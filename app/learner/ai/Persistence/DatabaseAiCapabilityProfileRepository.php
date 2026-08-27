<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Persistence;
use PDO;

final class DatabaseAiCapabilityProfileRepository
{
 public function __construct(private readonly PDO $pdo){}
 public function publish(string $studentId,array $profile,string $snapshotHash,string $modelVersion,string $generatedAt):array
 {
  $this->pdo->beginTransaction();
  try {
   $this->lockStudent($studentId);
   $same=$this->pdo->prepare('SELECT * FROM learner_ai_capability_profiles WHERE student_id=:student AND snapshot_hash=:hash AND model_version=:model LIMIT 1');$same->execute(['student'=>$studentId,'hash'=>$snapshotHash,'model'=>$modelVersion]);$existing=$same->fetch(PDO::FETCH_ASSOC);if(is_array($existing)){$this->pdo->commit();return $existing;}
   $latest=$this->latestLocked($studentId);if(is_array($latest)&&strcmp((string)$latest['generated_at'],$generatedAt)>0){$this->pdo->commit();return $latest;}
   $maximum=$this->pdo->prepare('SELECT COALESCE(MAX(version_number),0) FROM learner_ai_capability_profiles WHERE student_id=:student');$maximum->execute(['student'=>$studentId]);$version=(int)$maximum->fetchColumn()+1;
   $this->pdo->prepare('UPDATE learner_ai_capability_profiles SET superseded_at=:at WHERE student_id=:student AND superseded_at IS NULL')->execute(['at'=>$generatedAt,'student'=>$studentId]);
   $encode=static fn(mixed $v):string=>json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   $s=$this->pdo->prepare("INSERT INTO learner_ai_capability_profiles (id,student_id,version_number,status,talent_map_json,strengths_json,improvements_json,potential_paths_json,trend_signals_json,evidence_json,snapshot_hash,model_version,generated_at,created_at) VALUES (:id,:student,:version,'ready_model',:talent,:strengths,:improvements,:paths,:trends,:evidence,:hash,:model,:generated,:created)");
   $s->execute(['id'=>self::uuid(),'student'=>$studentId,'version'=>$version,'talent'=>$encode($profile['talent_map']??[]),'strengths'=>$encode($profile['strengths']??[]),'improvements'=>$encode($profile['improvements']??[]),'paths'=>$encode($profile['potential_paths']??[]),'trends'=>$encode($profile['trend_signals']??[]),'evidence'=>$encode($profile['evidence']??[]),'hash'=>$snapshotHash,'model'=>$modelVersion,'generated'=>$generatedAt,'created'=>$generatedAt]);
   $this->pdo->commit();return $this->latest($studentId)??[];
  } catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 public function latest(string $studentId):?array{$s=$this->pdo->prepare('SELECT * FROM learner_ai_capability_profiles WHERE student_id=:student AND superseded_at IS NULL ORDER BY version_number DESC LIMIT 1');$s->execute(['student'=>$studentId]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
 public function markStale(string $studentId,string $staleSince):void{$s=$this->pdo->prepare("UPDATE learner_ai_capability_profiles SET status='stale_model',stale_since=:stale WHERE student_id=:student AND superseded_at IS NULL");$s->execute(['stale'=>$staleSince,'student'=>$studentId]);}
 public function markPending(string $studentId,string $snapshotHash,string $jobKey):void{$s=$this->pdo->prepare("UPDATE learner_ai_capability_profiles SET status='stale_model',stale_since=COALESCE(stale_since,:stale),pending_snapshot_hash=:hash,refresh_job_id=:job,last_refresh_error=NULL,next_retry_at=NULL WHERE student_id=:student AND superseded_at IS NULL");$s->execute(['stale'=>gmdate('Y-m-d H:i:s'),'hash'=>$snapshotHash,'job'=>$jobKey,'student'=>$studentId]);}
 public function markFailed(string $studentId,string $errorCode,?string $nextRetryAt):void{$s=$this->pdo->prepare("UPDATE learner_ai_capability_profiles SET status='stale_model',stale_since=COALESCE(stale_since,:stale),last_refresh_error=:error,next_retry_at=:retry WHERE student_id=:student AND superseded_at IS NULL");$s->execute(['stale'=>gmdate('Y-m-d H:i:s'),'error'=>substr($errorCode,0,100),'retry'=>$nextRetryAt,'student'=>$studentId]);}
 public function rollback(string $studentId,int $versionNumber,string $at):?array{$this->pdo->beginTransaction();try{$this->lockStudent($studentId);$target=$this->pdo->prepare('SELECT id FROM learner_ai_capability_profiles WHERE student_id=:student AND version_number=:version LIMIT 1');$target->execute(['student'=>$studentId,'version'=>$versionNumber]);$id=$target->fetchColumn();if(!is_string($id)){ $this->pdo->rollBack();return null;}$this->pdo->prepare('UPDATE learner_ai_capability_profiles SET superseded_at=:at WHERE student_id=:student AND superseded_at IS NULL')->execute(['at'=>$at,'student'=>$studentId]);$this->pdo->prepare("UPDATE learner_ai_capability_profiles SET superseded_at=NULL,status='ready_model',stale_since=NULL WHERE id=:id")->execute(['id'=>$id]);$this->pdo->commit();return $this->latest($studentId);}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
 private function latestLocked(string $studentId):?array{$sql='SELECT * FROM learner_ai_capability_profiles WHERE student_id=:student AND superseded_at IS NULL ORDER BY version_number DESC LIMIT 1'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');$s=$this->pdo->prepare($sql);$s->execute(['student'=>$studentId]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
 private function lockStudent(string $studentId):void{if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')return;$s=$this->pdo->prepare('SELECT id FROM student_profiles WHERE id=:student FOR UPDATE');$s->execute(['student'=>$studentId]);if($s->fetchColumn()===false)throw new \RuntimeException('Student profile is required for AI capability profile.');}
 private static function uuid():string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20,12);}
}
