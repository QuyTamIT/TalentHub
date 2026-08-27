<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Persistence\DatabaseAiRefreshStateRepository;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
function freshness_assert(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE learner_recommendation_runs (id TEXT PRIMARY KEY, studentId TEXT, engineType TEXT, status TEXT, createdAt TEXT, freshness_status TEXT, stale_since TEXT, last_refresh_error TEXT, next_retry_at TEXT, model_version TEXT, snapshot_hash TEXT, refresh_job_id TEXT)');
$pdo->exec('CREATE TABLE learner_ai_roadmaps (id TEXT PRIMARY KEY, studentId TEXT, runId TEXT, status TEXT, versionNumber INTEGER, freshness_status TEXT, stale_since TEXT, last_refresh_error TEXT, next_retry_at TEXT, model_version TEXT, snapshot_hash TEXT, refresh_job_id TEXT)');
$pdo->exec("INSERT INTO learner_recommendation_runs VALUES ('r1','s1','model','completed','2026-08-27 00:00:00','ready_model',NULL,NULL,NULL,'m1','h1','j1')");
$state=new DatabaseAiRefreshStateRepository($pdo);$state->failed('s1','recommendation','h2','j2','quota','2026-08-27 02:00:00');$row=$pdo->query("SELECT * FROM learner_recommendation_runs WHERE id='r1'")->fetch(PDO::FETCH_ASSOC);freshness_assert($row['freshness_status']==='stale_model'&&$row['stale_since']!==null,'failure persists stale/LKG metadata');$response=(new RecommendationResponseMapper())->run(array_merge($row,['runId'=>'r1','items'=>[],'completedAt'=>'2026-08-27 00:00:00']));freshness_assert($response['state']==='stale_model'&&$response['last_known_good']===true&&$response['next_retry_at']==='2026-08-27 02:00:00','GET mapping hydrates persisted freshness');
echo 'learner_ai_freshness_persistence_test: OK',PHP_EOL;
