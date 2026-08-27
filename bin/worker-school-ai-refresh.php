<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
use TalentHub\Database\Connection;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Provider\DatabaseCircuitBreakerStore;
use TalentHub\Modules\School\Repository\DatabaseSchoolAiInsightRepository;
use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;
use TalentHub\Modules\School\Repository\SchoolAiAggregateRepository;
use TalentHub\Modules\School\Service\SchoolAiGeminiExplainer;
use TalentHub\Modules\School\Service\SchoolAiInsightService;
use TalentHub\Modules\School\Service\SchoolAiRefreshWorker;
$pdo=(new Connection(require dirname(__DIR__).'/config/database.php'))->connect();$config=RecommendationConfig::fromEnvironment($_ENV);$policy=new AiAvailabilityPolicy();$allowed=static fn(string $id):bool=>$config->enabled()&&$config->shadowGateApproved()&&$config->visiblePercent()>0&&!$config->pilotPaused()&&trim((string)$config->pilotApprovalReference())!==''&&$policy->isAssigned($id,$config);$store=new DatabaseSchoolAiInsightRepository($pdo);$explainer=new SchoolAiGeminiExplainer($config,null,null,new CircuitBreaker(3,30,null,new DatabaseCircuitBreakerStore($pdo),'gemini-school-insight'));$service=new SchoolAiInsightService(new SchoolAiAggregateRepository($pdo),static fn(string $id):array=>['id'=>$id],$explainer,static fn(string $id):?array=>$store->latest($id),5,static fn(string $id,array $payload)=>$store->save($id,$payload,(string)($config->model()??'unknown')),$allowed,$config->model(),true);$worker=new SchoolAiRefreshWorker(new DatabaseSchoolAiRefreshJobRepository($pdo),$service);$worker->runOnce();
