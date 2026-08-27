<?php
declare(strict_types=1);
use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;
require_once dirname(__DIR__).'/bin/bootstrap.php';
require_once dirname(__DIR__).'/app/learner/data/Migrations/LearnerForwardMigration.php';
require_once dirname(__DIR__).'/app/learner/data/Migrations/ForwardMigrationDefinition.php';
require_once dirname(__DIR__).'/src/Modules/School/Repository/DatabaseSchoolAiRefreshJobRepository.php';
function school_queue_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException("Assertion failed: {$message}");}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$definition=require dirname(__DIR__).'/Database/migrations/learner/013_create_school_ai_refresh_jobs.php';foreach($definition->migration->statements('sqlite') as $sql)$pdo->exec($sql);
$queue=new DatabaseSchoolAiRefreshJobRepository($pdo);$hash=str_repeat('a',64);$queue->enqueue('school-a',$hash);$queue->enqueue('school-a',$hash);school_queue_assert((int)$pdo->query('SELECT COUNT(*) FROM school_ai_refresh_jobs')->fetchColumn()===1,'queue enqueue is idempotent by school and aggregate hash');
for($attempt=1;$attempt<=3;$attempt++){$job=$queue->claim();school_queue_assert(is_array($job),'retryable job can be claimed');$queue->fail((int)$job['id']);if($attempt<3)$pdo->exec("UPDATE school_ai_refresh_jobs SET next_retry_at='2000-01-01 00:00:00'");}
school_queue_assert($pdo->query("SELECT status FROM school_ai_refresh_jobs")->fetchColumn()==='dead_letter','third failed attempt moves school insight refresh to dead-letter');
echo "school_ai_refresh_queue_test: OK\n";
