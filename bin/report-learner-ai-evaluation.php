<?php

declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Evaluation\EvaluationGateService;
use TalentHub\Learner\Ai\Evaluation\EvaluationReportGenerator;

$options=getopt('', ['format:','dry-run','review-bundle','manifest:']);
$metrics=['status'=>'blocked','reason'=>'approved_thresholds_and_sample_missing'];
$cohorts=['high'=>['status'=>'insufficient_sample','sample_size'=>0],'college'=>['status'=>'insufficient_sample','sample_size'=>0]];
$gate=(new EvaluationGateService())->decide($metrics,$cohorts,false);
$report=(new EvaluationReportGenerator())->generate($gate,$metrics,[
    'visible_percent'=>0,'pilot_paused'=>true,'shadow_execution_authorized'=>false,'provider_calls'=>0,
]);
$report['review_bundle']=isset($options['review-bundle']);
$format=strtolower((string)($options['format']??'text'));
if($format==='json')echo json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
else echo "Phase 12 evaluation: {$report['decision']}; eligible=false; provider calls=0".PHP_EOL;
exit(0);
