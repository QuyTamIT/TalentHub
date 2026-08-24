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
$configuration=['visible_percent'=>0,'pilot_paused'=>true,'shadow_execution_authorized'=>false,'provider_calls'=>0];
$independentReview=false;
$manifestPath=is_string($options['manifest']??null)?trim((string)$options['manifest']):'';
if($manifestPath!==''&&is_file($manifestPath)&&is_readable($manifestPath)){
    $decoded=json_decode((string)file_get_contents($manifestPath),true);
    if(is_array($decoded)){
        $allowedMetrics=['status','reason','roadmap_contract_validity','vietnamese_language_rate','evidence_coverage','activity_grounding_rate','unsupported_claim_rate','unsafe_output_rate','fallback_rate','latency_p50_ms','latency_p95_ms','sample_size'];
        $candidate=is_array($decoded['metrics']??null)?$decoded['metrics']:[];
        $metrics=array_intersect_key($candidate,array_flip($allowedMetrics));
        $metrics['status']=($metrics['status']??null)==='measured'?'measured':'blocked';
        $cohorts=is_array($decoded['cohorts']??null)?$decoded['cohorts']:$cohorts;
        $independentReview=($decoded['independent_review_approved']??false)===true;
        $configuration['provider_calls']=max(0,(int)($decoded['provider_calls']??0));
        $configuration['shadow_execution_authorized']=($decoded['shadow_execution_authorized']??false)===true;
    }
}
$gate=(new EvaluationGateService())->decide($metrics,$cohorts,$independentReview);
$report=(new EvaluationReportGenerator())->generate($gate,$metrics,[
    ...$configuration,
]);
$report['review_bundle']=isset($options['review-bundle']);
$format=strtolower((string)($options['format']??'text'));
if($format==='json')echo json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
else echo "Phase 12 evaluation: {$report['decision']}; eligible=false; provider calls={$configuration['provider_calls']}".PHP_EOL;
exit(0);
