<?php
declare(strict_types=1);
require __DIR__ . '/teacher_competency_fixture.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Support\CompetencyScore;
use TalentHub\Support\GradeClassifier;

$checks=0;
function check(bool $condition,string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $fn,string $type,?int $status=null): void {
    try { $fn(); } catch (Throwable $e) {
        check($e instanceof $type,'Unexpected exception: '.get_class($e).' '.$e->getMessage());
        if ($status!==null) check($e instanceof ApiException && $e->status===$status,'Unexpected HTTP status');
        return;
    }
    throw new RuntimeException('Expected rejection: '.$type);
}

[$pdo,$ids,$manifest,$insert]=competencyFixture();
competencyVerify($pdo,$manifest);
$repository=new TeacherGradingRepository($pdo);
$service=new TeacherGradingService($repository);
$learner=new DatabaseAssessmentRepository($pdo);
$passport=new DatabaseTalentPassportRepository($pdo);
$readPassport=fn(string $id)=>$passport->aggregateForStudent($id)['teacher_evaluations'];
$input=static fn(string $mode)=>[
    'mode'=>$mode,'contextId'=>$ids[$mode],'studentId'=>$ids['student'],'expectedVersion'=>'0',
    'overallScore'=>'8','comment'=>'Rubric synthetic feedback','assessmentStatus'=>'draft',
    'criteria'=>[$ids['criterion']=>'0',$ids['secondCriterion']=>'1'],
];
$counts=fn()=>$pdo->query("SELECT (SELECT COUNT(*) FROM assessments) a,(SELECT COUNT(*) FROM assessment_scores) s,(SELECT COUNT(*) FROM notifications) n")->fetch(PDO::FETCH_ASSOC);

check(count($repository->classes($ids['teacher']))===1,'Only assigned same-school classes');
check(count($repository->projects($ids['teacher']))===1,'Only mentored projects');
$beforeProfiles=$pdo->query('SELECT COUNT(*) FROM teacher_profiles')->fetchColumn();
fails(fn()=>$service->pageData($ids['missingTeacherUser'],null),ApiException::class,404);
check($beforeProfiles===$pdo->query('SELECT COUNT(*) FROM teacher_profiles')->fetchColumn(),'No self-created teacher profile');
foreach (['class','project','activity'] as $mode) {
    $data=$service->pageData($ids['teacherUser'],$ids[$mode],'',$mode);
    check(count($data['students'])===2,"$mode roster");
    fails(fn()=>$service->save($ids['teacherUser'],array_replace($input($mode),['studentId'=>$ids['otherStudent']])),ApiException::class,403);
    fails(fn()=>$service->save($ids['otherTeacherUser'],$input($mode)),ApiException::class,403);
    fails(fn()=>$service->pageData($ids['otherTeacherUser'],$ids[$mode],'',$mode),ApiException::class,403);
    fails(fn()=>$service->save($ids['teacherUser'],array_replace($input($mode),['activityId'=>$ids['otherActivity']])),ApiException::class,422);
}
// Even a malformed cross-school assignment must never grant access.
$insert('teacher_class_assignments',['teacherId'=>$ids['teacher'],'classId'=>$ids['otherClass']]);
check(count($repository->classes($ids['teacher']))===1,'Cross-school assignment excluded');
fails(fn()=>$service->save($ids['teacherUser'],array_replace($input('class'),['contextId'=>$ids['otherClass'],'studentId'=>$ids['otherStudent']])),ApiException::class,403);
fails(fn()=>$service->pageData($ids['teacherUser'],$ids['unassignedClass'],'','class'),ApiException::class,403);
fails(fn()=>$service->save($ids['teacherUser'],array_replace($input('class'),['overallScore'=>'101'])),ApiException::class,422);
fails(fn()=>$service->save($ids['teacherUser'],array_replace($input('class'),['criteria'=>[$ids['criterion']=>'10.01']])),ApiException::class,422);
fails(fn()=>$service->save($ids['teacherUser'],array_replace($input('class'),['criteria'=>[$ids['secondCriterion']=>'0']])),ApiException::class,422);

$publishedIds=[];
foreach (['class','project','activity'] as $mode) {
    $draft=$input($mode);
    $service->save($ids['teacherUser'],$draft);
    $row=$service->pageData($ids['teacherUser'],$ids[$mode],'Rubric studentUser',$mode)['students'][0];
    $assessment=$row['assessmentId'];
    check((float)$row['criteriaScores'][$ids['criterion']]===0.0,'Zero is stored');
    check((float)$row['overallScore']===8.0,'Overall remains independent');
    check(count($learner->publishedEvaluationsForStudent($ids['student']))===count($publishedIds),'Draft hidden from evaluation');
    check(count($readPassport($ids['student']))===count($publishedIds),'Draft hidden from passport');
    fails(fn()=>$service->save($ids['teacherUser'],$draft),TeacherGradingConflictException::class);
    $draft['assessmentId']=$assessment;$draft['expectedVersion']='1';
    $draft['criteria']=[$ids['criterion']=>'',$ids['secondCriterion']=>'1'];
    $draft['overallScore']='';
    $service->save($ids['teacherUser'],$draft);
    $row=$service->pageData($ids['teacherUser'],$ids[$mode],'Rubric studentUser',$mode)['students'][0];
    check(!array_key_exists($ids['criterion'],$row['criteriaScores']),'Cleared draft criterion stays empty');
    check($row['overallScore']===null,'Empty total remains NULL');
    fails(fn()=>$service->publish($ids['teacherUser'],$assessment,2,'rubric-test-request'),ApiException::class,422);
    fails(fn()=>$service->save($ids['teacherUser'],$draft),TeacherGradingConflictException::class);
    $draft['expectedVersion']='2';$draft['overallScore']='8';$draft['criteria']=$input($mode)['criteria'];
    $service->save($ids['teacherUser'],$draft);
    fails(fn()=>$service->publish($ids['otherTeacherUser'],$assessment,3,'rubric-test-request'),ApiException::class,404);
    // Force failure after assessment/score updates, before commit: all effects must roll back.
    $pdo->exec("CREATE TRIGGER rubric_test_fail_notification BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture rollback'");
    $before=$counts();
    fails(fn()=>$service->publish($ids['teacherUser'],$assessment,3,'rubric-test-request'),PDOException::class);
    check($before===$counts(),'Rollback restores assessment/scores/notification counts');
    $pdo->exec('DROP TRIGGER rubric_test_fail_notification');
    check($pdo->query("SELECT status FROM assessments WHERE id=".$pdo->quote($assessment))->fetchColumn()==='draft','Rollback restores draft');
    $service->publish($ids['teacherUser'],$assessment,3,'rubric-test-request');
    $publishedIds[]=$assessment;
    check(count($learner->publishedEvaluationsForStudent($ids['student']))===count($publishedIds),"$mode published visible");
    check(count($readPassport($ids['student']))===count($publishedIds),"$mode passport visible");
    check($learner->publishedEvaluationsForStudent($ids['otherStudent'])===[],'Other student cannot see evaluation');
    check($readPassport($ids['otherStudent'])===[],'Other student cannot see passport evaluation');
    $published=$learner->publishedEvaluationsForStudent($ids['student']);
    $match=array_values(array_filter($published,fn($r)=>$r['id']===$assessment))[0];
    check($match['context_title']==='Rubric '.$mode,'Context title preserved');
    if ($mode!=='activity') check($match['activity_id']===null,'Nullable activity maps safely');
    fails(fn()=>$service->publish($ids['teacherUser'],$assessment,3,'rubric-test-request'),TeacherGradingConflictException::class);
    $draft['expectedVersion']='4';$draft['overallScore']='100';
    fails(fn()=>$service->save($ids['teacherUser'],$draft),TeacherGradingConflictException::class);
    check((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE notificationType='teacher_assessment_published'")->fetchColumn()===count($publishedIds),'Publish retry never duplicates notification');
}
foreach ([0=>'0,0',8=>'0,8',80=>'8,0',100=>'10,0'] as $raw=>$expected) check(CompetencyScore::display((float)$raw)===$expected,'Canonical /10 conversion');
check(GradeClassifier::getClassification(8)==='Cần cải thiện','Classifier receives /100');
check(GradeClassifier::getClassification(80)==='Giỏi','Canonical classifier reused');
check(CompetencyScore::display(null)==='Chưa có điểm','Unknown distinct from zero');

// Forging an assessment id from another student cannot overwrite it.
$new=array_replace($input('class'),['studentId'=>$ids['secondStudent'],'assessmentId'=>$publishedIds[0],'expectedVersion'=>'4']);
$before=$counts();fails(fn()=>$service->save($ids['teacherUser'],$new),TeacherGradingConflictException::class);check($before===$counts(),'Forged assessment id changes nothing');
$pdo->prepare("UPDATE teacher_class_assignments SET status='revoked' WHERE teacherId=? AND classId=?")->execute([$ids['teacher'],$ids['class']]);
fails(fn()=>$service->save($ids['teacherUser'],$input('class')),ApiException::class,403);
$pdo->prepare("UPDATE teacher_class_assignments SET status='active' WHERE teacherId=? AND classId=?")->execute([$ids['teacher'],$ids['class']]);
$pdo->prepare("UPDATE project_members SET status='left',leftAt=NOW() WHERE projectId=? AND studentId=?")->execute([$ids['project'],$ids['secondStudent']]);
fails(fn()=>$service->save($ids['teacherUser'],array_replace($input('project'),['studentId'=>$ids['secondStudent']])),ApiException::class,403);
$pdo->prepare("UPDATE project_members SET status='active',leftAt=NULL WHERE projectId=? AND studentId=?")->execute([$ids['project'],$ids['secondStudent']]);
// Notification preference remains authoritative.
$insert('learner_notification_preferences',['studentId'=>$ids['secondStudent'],'notificationType'=>'teacher_assessment_published','inAppEnabled'=>0,'emailEnabled'=>0]);
$service->save($ids['teacherUser'],array_replace($input('class'),['studentId'=>$ids['secondStudent'],'assessmentStatus'=>'published','overallScore'=>'0']));
check((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE userId=".$pdo->quote($ids['secondStudentUser']))->fetchColumn()===0,'Notification preference respected');
// Database constraints remain active even if application validation is bypassed.
$base=['id'=>TalentHub\Support\Uuid::v4(),'teacherId'=>$ids['otherTeacher'],'studentId'=>$ids['otherStudent']];
fails(fn()=>$insert('assessments',$base),PDOException::class);
fails(fn()=>$insert('assessments',$base+['classId'=>$ids['otherClass'],'projectId'=>$ids['project']]),PDOException::class);
fails(fn()=>$insert('assessments',$base+['activityId'=>$ids['activity']]),PDOException::class);
fails(fn()=>$insert('assessments',$base+['classId'=>TalentHub\Support\Uuid::v4()]),PDOException::class);
fails(fn()=>$insert('assessments',$base+['classId'=>$ids['otherClass'],'status'=>'published','overallScore'=>80]),PDOException::class);

echo "teacher competency integration: PASS ($checks checks)\n";
