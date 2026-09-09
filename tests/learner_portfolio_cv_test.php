<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/learner/data/ReadModel/PassportCvViewModel.php';
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;
$data=['projects'=>[['id'=>'p','title'=>'Project','status'=>'active','member_status'=>'active']], 'internships'=>[['application_id'=>'a','title'=>'Intern','status'=>'accepted']],
'verified_portfolio'=>['projects'=>[['contextId'=>'p','title'=>'Project','notes'=>'Reviewed contribution','reviewedAt'=>'2026-09-01']],
'internships'=>[['contextId'=>'a','title'=>'Intern','organization'=>'Company','stage'=>'completed','startDate'=>'2026-07-01','endDate'=>'2026-08-31','hours'=>160,'reviewedAt'=>'2026-09-01']],
'skills'=>[['name'=>'PHP','kind'=>'internship','reviewedAt'=>'2026-09-01']]]];
$cv=PassportCvViewModel::build($data,'now');
if(count($cv['internships'])!==1||!str_contains($cv['internships'][0]['status_label'],'Hoàn thành'))throw new RuntimeException('Verified internship must replace accepted entry');
if(!str_contains($cv['projects'][0]['status_label'],'nghiệm thu'))throw new RuntimeException('Personal acceptance missing');
if(($cv['skills'][0]['name']??'')!=='PHP')throw new RuntimeException('Verified internship skill missing');
echo "[PASS] Verified reports feed project, internship and skill CV without duplicate accepted placement\n";
$data['verified_portfolio']=['projects'=>[],'internships'=>[],'skills'=>[]];
$cv=PassportCvViewModel::build($data,'now');
if($cv['skills']!==[]||str_contains($cv['projects'][0]['status_label'],'nghiệm thu')||str_contains($cv['internships'][0]['status_label'],'Hoàn thành'))throw new RuntimeException('Revoked verification retained');
echo "[PASS] Next snapshot without verification removes completion and skill claims\n";
