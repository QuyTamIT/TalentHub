<?php

declare(strict_types=1);

$failures=[];$assert=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
$endpoint=dirname(__DIR__).'/app/learner/api/v1/ai-job-matches.php';
$assert(is_file($endpoint),'AI job matching endpoint must exist.');
$source=is_file($endpoint)?(string)file_get_contents($endpoint):'';
foreach(['student_profile.read_own','student_profile.update_own','x-csrf-token','x-idempotency-key','learner.ai','allowedInput($request->json(), [])','jobMatchingService'] as $needle)$assert(str_contains($source,$needle),'Endpoint must enforce '.$needle.'.');
$assert(!str_contains($source,'studentId\']')&&!str_contains($source,'TALENTHUB_AI_API_KEY'),'Endpoint must not accept ownership identifiers or expose provider secrets.');
$context=(string)file_get_contents(dirname(__DIR__).'/app/learner/api/LearnerApiContext.php');
foreach(['DatabaseInternshipPostSource','DatabaseJobMatchRepository','ModelJobMatchEngine','JobMatchingService','CareerRoleBenchmarkRepository','ActivityRecommender'] as $needle)$assert(str_contains($context,$needle),'LearnerApiContext must wire '.$needle.'.');
if($failures!==[]){fwrite(STDERR,"FAIL\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "OK learner AI job matching API contract\n";
