<?php
declare(strict_types=1);
require_once __DIR__.'/../app/learner/ai/Matching/LearnerOpportunityProfile.php';
$method=new ReflectionMethod(TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile::class,'collectSkills');
$skills=$method->invoke(null,['skills'=>[
    ['code'=>'legacy','score'=>99,'verification_status'=>'verified'],
    ['code'=>'self','score'=>98,'state'=>'unverified'],
    ['code'=>'proof','score'=>97,'state'=>'evidence_only'],
    ['code'=>'zero','score'=>0,'state'=>'scored'],
]]);
if ($skills!==['zero'=>0]) throw new RuntimeException('Only explicit scored state may feed matching: '.json_encode($skills));
echo "learner_score_matching_test: OK\n";
