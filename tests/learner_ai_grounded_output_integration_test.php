<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Matching\{CareerRoleBenchmark,JobMatchScorer,JobMatchAnalysisValidator,LearnerOpportunityProfile,OpportunityCandidate,OpportunityMatchValidator,SkillGapResolver};
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;

$errors=[];
$assert=static function(bool $ok,string $why)use(&$errors):void{if(!$ok)$errors[]=$why;};
$reject=static function(callable $fn,string $why)use($assert):void{try{$fn();$assert(false,$why);}catch(InvalidArgumentException){}};
$input=new RecommendationInput(['skills'=>[['code'=>'sql','level_score'=>45]],'assessments'=>[]],[],[],[
    ['source_type'=>'skill','source_id'=>'sql-1','observed_at'=>null,'safe_value'=>['code'=>'sql','level_score'=>45]],
]);
$profile=LearnerOpportunityProfile::fromInput($input);
$role=new CareerRoleBenchmark('data_analyst','Data Analyst','data',[
    ['code'=>'sql','label'=>'SQL','minimum_score'=>75,'weight'=>100.0,'required'=>true],
],[]);
$make=static fn(string $id,string $type):OpportunityCandidate=>OpportunityCandidate::fromEvidence([
    'source_type'=>'opportunity','source_id'=>$id,'safe_value'=>[
        'catalog_id'=>$id,'item_type'=>$type,'title'=>'Phân tích dữ liệu','provider_name'=>'Đơn vị kiểm thử',
        'status'=>'active','url'=>'/app/learner/opportunity.php?id='.$id,
        'required_skills'=>[['code'=>'sql','minimum_score'=>75,'label'=>'SQL']],
    ],
]);
$job=$make('job-1','internship');$other=$make('job-2','internship');
$match=(new JobMatchScorer())->score($profile,$job,$role);$gap=(new SkillGapResolver())->resolve($match);
$validator=new JobMatchAnalysisValidator();
$item=['catalog_id'=>'job-1','analysis'=>'Vị trí này hiện chưa phù hợp vì kỹ năng còn cần củng cố. SQL hiện đạt 45 điểm, thấp hơn ngưỡng yêu cầu 75 điểm. Hãy luyện truy vấn lọc và tổng hợp dữ liệu trước khi tăng độ khó. Bạn có thể lưu kết quả truy vấn để nhờ giáo viên góp ý.','evidence_ref_ids'=>['skill:sql-1','opportunity:job-1']];
try{$validator->validate([$item],[$job],['job-1'=>$match],['job-1'=>$gap],$profile);}catch(Throwable $e){$assert(false,'Correct grounded job rejected: '.$e->getMessage());}
$bad=$item;$bad['analysis']=str_replace('45 điểm, thấp hơn','95 điểm, vượt',$item['analysis']);
$reject(fn()=>$validator->validate([$bad],[$job],['job-1'=>$match],['job-1'=>$gap],$profile),'Job validator accepted wrong SQL score.');
$bad=$item;$bad['evidence_ref_ids']=['opportunity:job-2'];
$reject(fn()=>$validator->validate([$bad],[$job,$other],['job-1'=>$match],['job-1'=>$gap],$profile),'Job validator accepted another job evidence.');
$bad=$item;$bad['evidence_ref_ids']=['opportunity:job-1'];
$reject(fn()=>$validator->validate([$bad],[$job],['job-1'=>$match],['job-1'=>$gap],$profile),'A job comparison must cite learner evidence as well as catalog evidence.');
$project=$make('project-1','project');
$projectItem=['catalog_id'=>'project-1','gemini_score'=>90,'why_fit'=>$item['analysis'],'fit_reasons'=>['SQL là kỹ năng cần được luyện qua bài tập.'],'gap_reasons'=>['SQL hiện đạt 45 điểm, thấp hơn yêu cầu 75 điểm.'],'skills_to_develop'=>['Luyện truy vấn tổng hợp dữ liệu SQL.'],'matched_skill_codes'=>[],'missing_skill_codes'=>['sql'],'expected_outcome_codes'=>[],'evidence_ref_ids'=>['skill:sql-1','opportunity:project-1']];
$bad=$projectItem;$bad['evidence_ref_ids']=['opportunity:project-1'];
$reject(fn()=>(new OpportunityMatchValidator())->validate([$bad],[$project],$profile,'recommendation'),'A project comparison must cite learner evidence.');
$projectItem['gap_reasons']=['Bạn đã hoàn thành 12 dự án doanh nghiệp.'];
$reject(fn()=>(new OpportunityMatchValidator())->validate([$projectItem],[$project],$profile,'recommendation'),'Project auxiliary prose accepted invented biography.');

$refs=['evidence-001'];$phases=[];
foreach([[0,30,'discover'],[31,60,'practice'],[61,90,'breakthrough']] as $i=>[$start,$end,$code]){
    $tasks=[];for($j=1;$j<=3;$j++)$tasks[]=['position'=>$j,'title'=>'Luyện truy vấn dữ liệu','description'=>'Viết truy vấn SQL trên bảng dữ liệu mẫu, lưu truy vấn và đối chiếu kết quả với yêu cầu của bài tập.','estimated_minutes'=>60,'action'=>['type'=>'self_task'],'evidence_ref_ids'=>$refs];
    $phases[]=['position'=>$i+1,'start_day'=>$start,'end_day'=>$end,'code'=>$code,'title'=>'Luyện kỹ năng dữ liệu','goal'=>'Củng cố kỹ năng truy vấn dữ liệu.','skill_focus'=>'Truy vấn dữ liệu','deliverable'=>'Bộ truy vấn có kết quả đối chiếu','effort_label'=>'Thời lượng đề xuất theo từng bài tập','metric_label'=>'Số truy vấn có kết quả chính xác','evidence_ref_ids'=>$refs,'tasks'=>$tasks];
}
$direction=['code'=>'data','label'=>'Phân tích dữ liệu','rationale'=>'Hướng luyện tập đề xuất dựa trên kỹ năng hiện có.'];
$roadmap=['executive_summary'=>'Lộ trình này đề xuất luyện tập theo từng bước và đối chiếu kết quả để củng cố kỹ năng.','primary_direction'=>$direction,'alternative_directions'=>[$direction,$direction],'insights'=>[],'phases'=>$phases,'recommended_activity_source_ids'=>[],'talent_map'=>[]];
foreach(['strength','improvement','potential'] as $c)$roadmap['insights'][]=['category'=>$c,'title'=>'Hướng luyện tập','summary'=>'Dữ liệu kỹ năng là căn cứ để đề xuất bài tập phù hợp.','evidence_ref_ids'=>$refs];
foreach(['Tư duy Logic & Hệ thống','Kỹ năng Thực hành & Thao tác','Tổ chức & Điều phối'] as $field)$roadmap['talent_map'][]=['field'=>$field,'score'=>0.5,'evidence_ref_ids'=>$refs];
$meta=['origin'=>'model','provider'=>'gemini','model_version'=>'test','prompt_version'=>RoadmapPromptRegistry::VERSION,'confidence_band'=>'low'];
$prompt=(new RoadmapPromptRegistry())->create($input,new RecommendationContext(['skills'],'request','key','student'));
$instructions=implode(' ',$prompt->payload()['instructions']);
$assert(str_contains($instructions,'giảng viên') && str_contains($instructions,'chưa đủ dữ liệu'), 'Roadmap must describe educator and uncertainty contract.');
$assert(($prompt->payload()['output_schema']['properties']['phases']['items']['properties']['tasks']['items']['properties']['estimated_minutes']['maximum']??0)===120,'Prompt effort schema must match learner effort validation.');
$rv=new RoadmapAnalysisValidator($refs,[],[], $input);
try{$rv->fromProviderPayload($roadmap,$meta);}catch(Throwable $e){$assert(false,'Valid roadmap rejected: '.$e->getMessage());}
$bad=$roadmap;$bad['strengths']=[['text'=>'Bạn đã hoàn thành 12 dự án doanh nghiệp.','evidence_ref_ids'=>$refs]];
$reject(fn()=>$rv->fromProviderPayload($bad,$meta),'Roadmap extended fields accepted fabricated achievement.');
$bad=$roadmap;$bad['executive_summary']='SQL của bạn đạt 95 điểm nên có thể bắt đầu luyện tập nâng cao.';
$reject(fn()=>$rv->fromProviderPayload($bad,$meta),'Roadmap accepted wrong skill score.');
$bad=$roadmap;$bad['phases'][0]['tasks'][0]['estimated_minutes']=1440;
$reject(fn()=>$rv->fromProviderPayload($bad,$meta),'Generated roadmap accepted a 24-hour task.');
if($errors!==[]){fwrite(STDERR,"FAIL\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "learner_ai_grounded_output_integration_test: OK\n";
