<?php
declare(strict_types=1); $root=dirname(__DIR__);
putenv('APP_ENV=test');
putenv('TALENTHUB_LEARNER_SOURCE=mock');
$_ENV['APP_ENV'] = 'test';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
$_SERVER['APP_ENV'] = 'test';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';
function ar(bool $c,string $m):void{if(!$c){fwrite(STDERR,"Assertion failed: {$m}\n");exit(1);}}
foreach(['activity-detail.php','my-activities.php'] as $f) ar(is_file($root.'/app/learner/'.$f),"{$f} exists");
function render_activity(string $p,array $q=[]):string{$_GET=$q;ob_start();include $p;return(string)ob_get_clean();}
$detail=render_activity($root.'/app/learner/activity-detail.php',['id'=>'iot-lab']);
$mine=render_activity($root.'/app/learner/my-activities.php');
$catalog=render_activity($root.'/app/learner/activities.php');
$checkin=render_activity($root.'/app/learner/checkin.php',['activity'=>'iot-lab']);
foreach (['detail'=>$detail,'mine'=>$mine,'catalog'=>$catalog,'checkin'=>$checkin] as $page=>$html) ar(!preg_match('/Warning|Fatal error|Parse error/i',$html),"{$page} renders without diagnostics");
ar(str_contains($detail,'data-activity-detail-page'),'detail marker'); ar(str_contains($detail,'data-register-current'),'detail registration CTA');
ar(str_contains($mine,'data-my-activities-page'),'my activities marker'); ar(str_contains($mine,'data-my-registration-list'),'registration list');
ar(str_contains($catalog,'activity-detail.php?id='),'catalog links to detail'); ar(str_contains($catalog,'my-activities.php'),'catalog links to my activities');
ar(str_contains($catalog,'28/08/2026 · 14:00'),'catalog formats start_at from the provider contract');
ar(str_contains($checkin,'IoT Lab — Cảm biến thông minh'),'registered activity is linked to check-in');
echo "learner_activities_render_test: OK\n";
