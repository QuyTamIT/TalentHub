<?php
declare(strict_types=1);

namespace TalentHub\Modules\Student\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;
use Throwable;

final class PortfolioRepository
{
    /** @var callable(string,string,array<string,mixed>):void */
    private $notifier;

    public function __construct(private readonly PDO $pdo, ?callable $notifier = null)
    {
        $this->notifier = $notifier ?? function (string $type, string $recipientUserId, array $payload): void {
            $root = dirname(__DIR__, 4);
            require_once $root . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once $root . '/app/learner/data/Database/DatabaseNotificationRepository.php';
            require_once $root . '/app/learner/data/Service/NotificationService.php';
            $service = new \TalentHub\Learner\Data\Service\NotificationService(
                new \TalentHub\Learner\Data\Database\DatabaseNotificationRepository($this->pdo)
            );
            $service->publish($recipientUserId, $type, (string)$payload['title'], (string)$payload['message'], (string)$payload['deepLink'], (string)$payload['eventKey'], (string)$payload['studentId']);
        };
    }

    public function listForStudent(string $studentId, bool $completedOnly = false): array
    {
        $student = $this->student($studentId);
        $projectSql = "SELECT 'project' kind,p.id contextId,p.title,s.name organization,u.fullName mentorName,r.id reportId,p.status projectStatus FROM projects p JOIN schools s ON s.id=p.schoolId JOIN project_members pm ON pm.projectId=p.id AND pm.studentId=:studentId LEFT JOIN teacher_profiles tp ON tp.id=p.mentorTeacherId LEFT JOIN users u ON u.id=tp.userId LEFT JOIN project_submissions r ON r.projectId=p.id AND r.studentId=:studentId2 WHERE p.schoolId=:schoolId AND (pm.status='active' OR r.id IS NOT NULL) ORDER BY p.title";
        $project = $this->pdo->prepare($projectSql);
        $project->execute(['studentId'=>$studentId,'studentId2'=>$studentId,'schoolId'=>$student['schoolId']]);

        $internSql = "SELECT 'internship' kind,a.id contextId,ip.title,e.name organization,u.fullName mentorName,r.id reportId,a.status applicationStatus FROM internship_applications a JOIN internship_posts ip ON ip.id=a.postId JOIN enterprises e ON e.id=ip.enterpriseId LEFT JOIN internship_mentor_assignments ima ON ima.applicationId=a.id LEFT JOIN teacher_profiles tp ON tp.id=ima.mentorTeacherId LEFT JOIN users u ON u.id=tp.userId LEFT JOIN learner_internship_reports r ON r.applicationId=a.id AND r.studentId=:studentId2 WHERE a.studentId=:studentId AND (a.status='accepted' OR r.id IS NOT NULL) ORDER BY ip.title";
        $intern = $this->pdo->prepare($internSql);
        $intern->execute(['studentId'=>$studentId,'studentId2'=>$studentId]);

        $projectRows = $this->contextRows($project->fetchAll(PDO::FETCH_ASSOC) ?: [],'project');
        $internRows = $this->contextRows($intern->fetchAll(PDO::FETCH_ASSOC) ?: [],'internship');

        if ($completedOnly) {
            $projectRows = array_values(array_filter($projectRows, static function(array $item): bool {
                $status = $item['report']['status'] ?? '';
                $pStatus = $item['projectStatus'] ?? '';
                return $status === 'verified' || $pStatus === 'completed';
            }));
            $internRows = array_values(array_filter($internRows, static function(array $item): bool {
                $report = $item['report'] ?? [];
                return ($report['status'] ?? '') === 'verified' && ($report['stage'] ?? '') === 'completed';
            }));
        }

        return ['projects'=>$projectRows,'internships'=>$internRows];
    }

    public function listForTeacher(string $teacherUserId): array
    {
        $teacher = $this->teacher($teacherUserId);
        $sql = "SELECT 'project' kind,p.id contextId,p.title,s.name organization,mu.fullName mentorName,su.fullName studentName,r.studentId,r.id reportId FROM project_submissions r JOIN projects p ON p.id=r.projectId JOIN schools s ON s.id=p.schoolId JOIN student_profiles sp ON sp.id=r.studentId JOIN classes c ON c.id=sp.classId JOIN users su ON su.id=sp.userId JOIN teacher_profiles mt ON mt.id=p.mentorTeacherId JOIN users mu ON mu.id=mt.userId WHERE r.status<>'draft' AND p.mentorTeacherId=:teacherId AND p.schoolId=:schoolId AND c.schoolId=:studentSchool UNION ALL SELECT 'internship',a.id,ip.title,e.name,mu.fullName,su.fullName,r.studentId,r.id FROM learner_internship_reports r JOIN internship_applications a ON a.id=r.applicationId JOIN internship_posts ip ON ip.id=a.postId JOIN enterprises e ON e.id=ip.enterpriseId JOIN student_profiles sp ON sp.id=r.studentId JOIN classes c ON c.id=sp.classId JOIN users su ON su.id=sp.userId JOIN internship_mentor_assignments ima ON ima.applicationId=a.id JOIN teacher_profiles mt ON mt.id=ima.mentorTeacherId JOIN users mu ON mu.id=mt.userId WHERE r.status<>'draft' AND ima.mentorTeacherId=:teacherId2 AND mt.schoolId=:schoolId2 AND c.schoolId=:studentSchool2";
        $stmt=$this->pdo->prepare($sql); $stmt->execute(['teacherId'=>$teacher['teacherId'],'schoolId'=>$teacher['schoolId'],'studentSchool'=>$teacher['schoolId'],'teacherId2'=>$teacher['teacherId'],'schoolId2'=>$teacher['schoolId'],'studentSchool2'=>$teacher['schoolId']]);
        $items = $this->contextRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], null);
        // Recheck hydrated rows: a learner may create a private draft after the ID query.
        $items = array_values(array_filter($items, static fn(array $item): bool => ($item['report']['status'] ?? 'draft') !== 'draft'));
        foreach ($items as &$item) {
            $item['report']['history'] = array_values(array_filter($item['report']['history'], static fn(array $event): bool => $event['status'] !== 'draft'));
        }
        unset($item);
        return $items;
    }

    public function save(string $studentId, string $kind, string $contextId, int $expectedVersion, array $input): array
    {
        $meta=$this->kind($kind); $student=$this->student($studentId); $existing=$this->find($kind,$studentId,$contextId);
        if ($existing === null) { $this->assertContext($student,$kind,$contextId,true); if ($expectedVersion!==0) $this->fail(409,'VERSION_CONFLICT','Phiên bản đã thay đổi.'); }
        else { if ($expectedVersion !== (int)$existing['version']) $this->fail(409,'VERSION_CONFLICT','Phiên bản đã thay đổi.'); if ($existing['studentId']!==$studentId) $this->fail(403,'PERMISSION_DENIED','Không có quyền sửa báo cáo.'); $this->assertContext($student,$kind,$contextId,false); }
        $newRevision=(bool)($input['newRevision']??false);
        if ($existing && in_array($existing['status'],['verified','revoked'],true) && !$newRevision) $this->fail(422,'INVALID_TRANSITION','Hãy tạo phiên bản báo cáo mới.');
        if ($existing && $existing['status']==='submitted') $this->fail(422,'INVALID_TRANSITION','Báo cáo đang chờ duyệt và không thể sửa.');
        if ($newRevision && (!$existing || !in_array($existing['status'],['verified','revoked'],true))) $this->fail(422,'INVALID_TRANSITION','Không thể tạo revision lúc này.');
        $base=$existing ?: ['notes'=>'','repositoryUrl'=>null,'demoUrl'=>null,'startDate'=>null,'endDate'=>null,'hours'=>null,'stage'=>null,'revision'=>1];
        foreach (['notes','repositoryUrl','demoUrl','startDate','endDate','hours','stage'] as $key) if (array_key_exists($key,$input)) $base[$key]=$input[$key];
        $base['notes']=trim((string)$base['notes']);
        $this->validate($kind,$base,(bool)($input['submit']??false));
        $submit=(bool)($input['submit']??false); if ($submit) $this->assertMentor($student,$kind,$contextId);
        $now=$this->now(); $id=$existing['id']??Uuid::v4(); $version=($existing?(int)$existing['version']:0)+1; $revision=$existing ? (int)$existing['revision']+($newRevision?1:0) : 1; $status=$submit?'submitted':'draft';
        if ($this->pdo->inTransaction()) $this->fail(409,'TRANSACTION_CONFLICT','Không thể ghi portfolio trong transaction đang mở.');
        try {
            $this->pdo->beginTransaction();
            $student = $this->student($studentId, true);
            $this->assertContext($student,$kind,$contextId,$submit,true);
            if ($submit) $this->assertMentor($student,$kind,$contextId,true);
            if (!$existing) {
                $sql="INSERT INTO {$meta['table']} (id,studentId,{$meta['context']},version,revision,status,notes,repositoryUrl,demoUrl,startDate,endDate,hours,stage,submittedAt,reviewedAt,reviewedByUserId,feedback,updatedAt) VALUES (:id,:studentId,:contextId,:version,:revision,:status,:notes,:repositoryUrl,:demoUrl,:startDate,:endDate,:hours,:stage,:submittedAt,NULL,NULL,NULL,:updatedAt)";
            } else {
                $sql="UPDATE {$meta['table']} SET version=:version,revision=:revision,status=:status,notes=:notes,repositoryUrl=:repositoryUrl,demoUrl=:demoUrl,startDate=:startDate,endDate=:endDate,hours=:hours,stage=:stage,submittedAt=:submittedAt,reviewedAt=NULL,reviewedByUserId=NULL,feedback=NULL,updatedAt=:updatedAt WHERE id=:id AND studentId=:studentId AND {$meta['context']}=:contextId AND version=:expectedVersion";
            }
            $params=['id'=>$id,'studentId'=>$studentId,'contextId'=>$contextId,'version'=>$version,'revision'=>$revision,'status'=>$status,'notes'=>$base['notes'],'repositoryUrl'=>$this->null($base['repositoryUrl']),'demoUrl'=>$this->null($base['demoUrl']),'startDate'=>$this->null($base['startDate']),'endDate'=>$this->null($base['endDate']),'hours'=>$base['hours']!==null&&$base['hours']!==''?(float)$base['hours']:null,'stage'=>$kind==='internship'?$this->null($base['stage']):null,'submittedAt'=>$submit?$now:($newRevision?null:($existing['submittedAt']??null)),'updatedAt'=>$now];
            if ($existing) $params['expectedVersion']=$expectedVersion;
            $stmt=$this->pdo->prepare($sql); $stmt->execute($params); if ($existing && $stmt->rowCount()!==1) $this->fail(409,'VERSION_CONFLICT','Phiên bản đã thay đổi.');
            $actor=$student['userId']; $this->history($kind,$id,$version,$status,$actor,$this->row($kind,$id));
            if ($submit) { $mentor=$this->mentor($kind,$contextId); $this->notify('portfolio_submitted',$mentor['userId'],['title'=>'Báo cáo mới cần duyệt','message'=>'Học sinh đã gửi báo cáo portfolio.','deepLink'=>'/app/teacher/portfolio-reviews.php','eventKey'=>"portfolio_submitted:{$kind}:{$id}:{$version}",'studentId'=>$studentId]); }
            $result=$this->row($kind,$id);
            $this->pdo->commit(); return $result;
        } catch (PDOException $e) { if ($this->pdo->inTransaction())$this->pdo->rollBack(); if ((string)$e->getCode()==='23000'||(string)$e->getCode()==='19')$this->fail(409,'CONFLICT','Báo cáo đã tồn tại.'); throw $e; }
        catch (Throwable $e) { if ($this->pdo->inTransaction())$this->pdo->rollBack(); if ($e instanceof ApiException) throw $e; $this->fail(500,'PORTFOLIO_WRITE_FAILED','Không thể lưu báo cáo.'); }
    }

    public function review(string $teacherUserId,string $kind,string $reportId,int $expectedVersion,string $decision,string $feedback,array $skillIds=[]): array
    {
        $meta=$this->kind($kind); $teacher=$this->teacher($teacherUserId); $report=$this->row($kind,$reportId); if ((int)$report['version']!==$expectedVersion)$this->fail(409,'VERSION_CONFLICT','Phiên bản đã thay đổi.');
        $contextId=$report['contextId']; $student=$this->student($report['studentId']); $mentor=$this->mentor($kind,$contextId);
        if ($mentor['teacherId']!==$teacher['teacherId'] || $student['schoolId']!==$teacher['schoolId'])$this->fail(403,'PERMISSION_DENIED','Giáo viên không được phân công.');
        if (!in_array($decision,['verified','changes_requested','revoked'],true))$this->fail(422,'VALIDATION_FAILED','Quyết định không hợp lệ.');
        if (($decision==='revoked' && $report['status']!=='verified') || ($decision!=='revoked' && $report['status']!=='submitted'))$this->fail(422,'INVALID_TRANSITION','Trạng thái báo cáo không hợp lệ.');
        $feedback=trim($feedback); if (mb_strlen($feedback)>2000 || (in_array($decision,['changes_requested','revoked'],true)&&$feedback===''))$this->fail(422,'VALIDATION_FAILED','Phản hồi là bắt buộc và tối đa 2000 ký tự.');
        $skillIds=array_values(array_unique(array_map('strval',$skillIds))); if(count($skillIds)>10)$this->fail(422,'VALIDATION_FAILED','Tối đa 10 kỹ năng.'); if($decision!=='verified'&&$skillIds)$this->fail(422,'VALIDATION_FAILED','Chỉ báo cáo verified được gắn kỹ năng.');
        if($decision==='verified'){ $this->assertContext($student,$kind,$contextId,true); foreach($skillIds as $skillId){$s=$this->pdo->prepare("SELECT 1 FROM skills WHERE id=:id AND status='active'");$s->execute(['id'=>$skillId]);if(!$s->fetchColumn())$this->fail(422,'VALIDATION_FAILED','Kỹ năng không hoạt động.');} }
        $now=$this->now(); $version=$expectedVersion+1;
        if ($this->pdo->inTransaction()) $this->fail(409,'TRANSACTION_CONFLICT','Không thể ghi portfolio trong transaction đang mở.');
        try {
            $this->pdo->beginTransaction();
            // Same lock order as learner save: owner -> context -> mentor -> report.
            $student = $this->student($report['studentId'], true);
            $this->assertContext($student, $kind, $contextId, $decision === 'verified', true);
            $currentMentor = $this->mentor($kind, $contextId, true);
            $teacher = $this->teacher($teacherUserId, true);
            if ($currentMentor['teacherId'] !== $teacher['teacherId']
                || $currentMentor['schoolId'] !== $student['schoolId']
                || $teacher['schoolId'] !== $student['schoolId']) {
                $this->fail(403, 'PERMISSION_DENIED', 'Giáo viên không được phân công.');
            }
            $stmt = $this->pdo->prepare("UPDATE {$meta['table']} SET version=:version,status=:status,reviewedAt=:reviewedAt,reviewedByUserId=:reviewer,feedback=:feedback,updatedAt=:updatedAt WHERE id=:id AND version=:expectedVersion");
            $stmt->execute(['version'=>$version,'status'=>$decision,'reviewedAt'=>$now,'reviewer'=>$teacherUserId,'feedback'=>$this->null($feedback),'updatedAt'=>$now,'id'=>$reportId,'expectedVersion'=>$expectedVersion]);
            if ($stmt->rowCount() !== 1) $this->fail(409, 'VERSION_CONFLICT', 'Phiên bản đã thay đổi.');
            $this->pdo->prepare('DELETE FROM learner_portfolio_skills WHERE kind=:kind AND reportId=:id')->execute(['kind'=>$kind,'id'=>$reportId]);
            if ($decision === 'verified') {
                foreach ($skillIds as $skillId) {
                    $this->pdo->prepare('INSERT INTO learner_portfolio_skills(kind,reportId,skillId) VALUES (:kind,:id,:skill)')->execute(['kind'=>$kind,'id'=>$reportId,'skill'=>$skillId]);
                }
            }
            $snapshot = $this->row($kind, $reportId);
            $snapshot['skillIds'] = $skillIds;
            $this->history($kind, $reportId, $version, $decision, $teacherUserId, $snapshot);
            $this->notify('portfolio_reviewed', $student['userId'], [
                'title'=>'Báo cáo portfolio đã được phản hồi',
                'message'=>'Giáo viên đã cập nhật trạng thái báo cáo.',
                'deepLink'=>'/app/learner/profile.php',
                'eventKey'=>"portfolio_reviewed:{$kind}:{$reportId}:{$version}",
                'studentId'=>$report['studentId'],
            ]);
            $result = $this->row($kind, $reportId);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof ApiException) throw $e;
            $this->fail(500, 'PORTFOLIO_WRITE_FAILED', 'Không thể duyệt báo cáo.');
        }
    }

    public function verifiedForStudent(string $studentId): array
    {
        $student = $this->student($studentId);
        $projects = $this->pdo->prepare(<<<'SQL'
SELECT r.projectId contextId,p.title,r.notes,r.reviewedAt,r.feedback,u.fullName reviewerName,r.id reportId
FROM project_submissions r
JOIN projects p ON p.id=r.projectId
JOIN project_members pm ON pm.projectId=p.id AND pm.studentId=r.studentId AND pm.status='active'
JOIN users u ON u.id=r.reviewedByUserId
WHERE r.studentId=:studentId AND r.status='verified' AND p.schoolId=:schoolId
SQL);
        $projects->execute(['studentId'=>$studentId,'schoolId'=>$student['schoolId']]);
        $intern = $this->pdo->prepare(<<<'SQL'
SELECT r.applicationId contextId,ip.title,e.name organization,r.startDate,r.endDate,r.hours,r.stage,r.reviewedAt,r.feedback,u.fullName reviewerName,r.id reportId
FROM learner_internship_reports r
JOIN internship_applications a ON a.id=r.applicationId AND a.studentId=r.studentId AND a.status='accepted'
JOIN internship_posts ip ON ip.id=a.postId
JOIN enterprises e ON e.id=ip.enterpriseId
JOIN users u ON u.id=r.reviewedByUserId
WHERE r.studentId=:studentId AND r.status='verified'
SQL);
        $intern->execute(['studentId'=>$studentId]);
        $skills = $this->pdo->prepare(<<<'SQL'
SELECT s.name,ps.skillId,ps.kind,ps.reportId,eligible.reviewedAt,u.fullName reviewerName
FROM learner_portfolio_skills ps
JOIN skills s ON s.id=ps.skillId AND s.status='active'
JOIN (
  SELECT 'project' kind,r.id,r.reviewedAt,r.reviewedByUserId
  FROM project_submissions r
  JOIN projects p ON p.id=r.projectId
  JOIN project_members pm ON pm.projectId=p.id AND pm.studentId=r.studentId AND pm.status='active'
  WHERE r.studentId=:projectStudent AND r.status='verified' AND p.schoolId=:schoolId
  UNION ALL
  SELECT 'internship' kind,r.id,r.reviewedAt,r.reviewedByUserId
  FROM learner_internship_reports r
  JOIN internship_applications a ON a.id=r.applicationId AND a.studentId=r.studentId AND a.status='accepted'
  WHERE r.studentId=:internStudent AND r.status='verified'
) eligible ON eligible.id=ps.reportId AND eligible.kind=ps.kind
JOIN users u ON u.id=eligible.reviewedByUserId
SQL);
        $skills->execute(['projectStudent'=>$studentId,'schoolId'=>$student['schoolId'],'internStudent'=>$studentId]);
        $strip = static fn(array $rows): array => array_map(static function(array $row): array {
            unset($row['reportId']);
            if (isset($row['hours'])) $row['hours'] = (float)$row['hours'];
            return $row;
        }, $rows);
        return [
            'projects'=>$strip($projects->fetchAll(PDO::FETCH_ASSOC) ?: []),
            'internships'=>$strip($intern->fetchAll(PDO::FETCH_ASSOC) ?: []),
            'skills'=>$skills->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    private function contextRows(array $rows,?string $forcedKind): array { return array_map(function(array $r)use($forcedKind){$kind=$forcedKind??$r['kind'];$out=['kind'=>$kind,'contextId'=>$r['contextId'],'title'=>$r['title'],'organization'=>$r['organization'],'mentorName'=>$r['mentorName']??null];foreach(['studentName','studentId','projectStatus','applicationStatus']as$k)if(array_key_exists($k,$r))$out[$k]=$r[$k];$out['report']=$r['reportId']?$this->row($kind,$r['reportId']):null;return$out;},$rows); }
    private function row(string $kind,string $id): array { $m=$this->kind($kind);$s=$this->pdo->prepare("SELECT *,{$m['context']} contextId FROM {$m['table']} WHERE id=:id");$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)$this->fail(404,'RESOURCE_NOT_FOUND','Không tìm thấy báo cáo.');foreach(['version','revision']as$k)$r[$k]=(int)$r[$k];if($r['hours']!==null)$r['hours']=(float)$r['hours'];unset($r[$m['context']]);$skills=$this->pdo->prepare('SELECT s.id,s.name FROM learner_portfolio_skills ps JOIN skills s ON s.id=ps.skillId WHERE ps.kind=:kind AND ps.reportId=:id ORDER BY s.name,s.id');$skills->execute(['kind'=>$kind,'id'=>$id]);$r['skills']=$skills->fetchAll(PDO::FETCH_ASSOC)?:[];$h=$this->pdo->prepare('SELECT version,status,actorUserId,createdAt,snapshotJson FROM learner_portfolio_history WHERE kind=:kind AND reportId=:id ORDER BY version');$h->execute(['kind'=>$kind,'id'=>$id]);$r['history']=array_map(static function($x){$snapshot=json_decode($x['snapshotJson'],true)?:[];return array_merge($snapshot,['version'=>(int)$x['version'],'status'=>$x['status'],'actorUserId'=>$x['actorUserId'],'createdAt'=>$x['createdAt']]);},$h->fetchAll(PDO::FETCH_ASSOC)?:[]);return$r; }
    private function find(string $kind,string $studentId,string $contextId): ?array { $m=$this->kind($kind);$s=$this->pdo->prepare("SELECT *,{$m['context']} contextId FROM {$m['table']} WHERE studentId=:studentId AND {$m['context']}=:contextId");$s->execute(compact('studentId','contextId'));$r=$s->fetch(PDO::FETCH_ASSOC);return$r?:null; }
    private function student(string $id, bool $lock=false): array { $suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$s=$this->pdo->prepare("SELECT sp.id,sp.userId,u.status,c.schoolId FROM student_profiles sp JOIN users u ON u.id=sp.userId JOIN roles r ON r.id=u.roleId AND r.code='student' JOIN classes c ON c.id=sp.classId WHERE sp.id=:id{$suffix}");$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)$this->fail(404,'RESOURCE_NOT_FOUND','Không tìm thấy học sinh.');if($r['status']!=='active')$this->fail(403,'PERMISSION_DENIED','Tài khoản học sinh không hoạt động.');return$r; }
    private function teacher(string $userId, bool $lock=false): array { $suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$s=$this->pdo->prepare("SELECT tp.id teacherId,tp.schoolId,u.status FROM teacher_profiles tp JOIN users u ON u.id=tp.userId JOIN roles r ON r.id=u.roleId AND r.code='teacher' WHERE tp.userId=:id{$suffix}");$s->execute(['id'=>$userId]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r||$r['status']!=='active')$this->fail(403,'PERMISSION_DENIED','Giáo viên không hoạt động.');return$r; }
    private function assertContext(array $student,string $kind,string $contextId,bool $current,bool $lock=false): void { $suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';if($kind==='project'){$s=$this->pdo->prepare("SELECT p.schoolId,pm.status FROM projects p JOIN project_members pm ON pm.projectId=p.id AND pm.studentId=:studentId WHERE p.id=:id{$suffix}");$s->execute(['studentId'=>$student['id'],'id'=>$contextId]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)$this->fail(403,'PERMISSION_DENIED','Không thuộc dự án.');if($r['schoolId']!==$student['schoolId'])$this->fail(403,'PERMISSION_DENIED','Dự án khác trường.');if($current&&$r['status']!=='active')$this->fail(422,'INVALID_CONTEXT','Thành viên dự án không hoạt động.');}else{$s=$this->pdo->prepare("SELECT status,studentId FROM internship_applications WHERE id=:id{$suffix}");$s->execute(['id'=>$contextId]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r||$r['studentId']!==$student['id'])$this->fail(403,'PERMISSION_DENIED','Không thuộc đơn thực tập.');if($current&&$r['status']!=='accepted')$this->fail(422,'INVALID_CONTEXT','Đơn thực tập chưa được chấp nhận.');} }
    private function mentor(string $kind,string $contextId,bool $lock=false): array { $suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$sql=($kind==='project'?'SELECT tp.id teacherId,tp.userId,tp.schoolId FROM projects p JOIN teacher_profiles tp ON tp.id=p.mentorTeacherId WHERE p.id=:id':'SELECT tp.id teacherId,tp.userId,tp.schoolId FROM internship_mentor_assignments ima JOIN teacher_profiles tp ON tp.id=ima.mentorTeacherId WHERE ima.applicationId=:id').$suffix;$s=$this->pdo->prepare($sql);$s->execute(['id'=>$contextId]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)$this->fail(403,'PERMISSION_DENIED','Không có giáo viên hướng dẫn hiện tại.');return$r; }
    private function assertMentor(array $student,string $kind,string $contextId,bool $lock=false): void { try{$m=$this->mentor($kind,$contextId,$lock);}catch(ApiException){$this->fail(422,'MENTOR_REQUIRED','Cần phân công giáo viên hướng dẫn trước khi gửi.');}if($m['schoolId']!==$student['schoolId'])$this->fail(403,'PERMISSION_DENIED','Giáo viên hướng dẫn khác trường.'); }
    private function validate(string $kind,array $v,bool $submit): void
    {
        foreach (['notes','repositoryUrl','demoUrl','startDate','endDate','hours','stage'] as $field) {
            if (isset($v[$field]) && !is_scalar($v[$field])) $this->fail(422,'VALIDATION_FAILED',"{$field} không hợp lệ.");
        }
        if (mb_strlen($v['notes'])>4000) $this->fail(422,'VALIDATION_FAILED','Ghi chú tối đa 4000 ký tự.');
        if ($submit&&$v['notes']==='') $this->fail(422,'VALIDATION_FAILED','Ghi chú là bắt buộc khi gửi.');
        foreach (['repositoryUrl','demoUrl'] as $key) {
            $url=$this->null($v[$key]);
            if ($url===null) continue;
            if (strlen($url)>1000||preg_match('/[\x00-\x1F\x7F]/',$url)||!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true)||parse_url($url,PHP_URL_HOST)===null||parse_url($url,PHP_URL_USER)!==null) $this->fail(422,'VALIDATION_FAILED','URL không hợp lệ.');
        }
        if ($kind==='project') return;
        $stage=(string)($v['stage']??'');
        if (!in_array($stage,['active','completed'],true)) $this->fail(422,'VALIDATION_FAILED','Giai đoạn thực tập không hợp lệ.');
        $rawStart=$this->null($v['startDate']??null); $rawEnd=$this->null($v['endDate']??null);
        $start=$this->date($rawStart); $end=$this->date($rawEnd);
        if (($rawStart!==null&&$start===null)||($rawEnd!==null&&$end===null)) $this->fail(422,'VALIDATION_FAILED','Ngày thực tập không hợp lệ.');
        $hours=$v['hours']??null; $hoursValue=null;
        if ($hours!==null&&$hours!=='') {
            $hoursText=(string)$hours;
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/',$hoursText)||!is_numeric($hoursText)||!is_finite((float)$hoursText)||(float)$hoursText<0||(float)$hoursText>10000) $this->fail(422,'VALIDATION_FAILED','Số giờ thực tập không hợp lệ.');
            $hoursValue=(float)$hoursText;
        }
        if ($start&&$end&&$end<$start) $this->fail(422,'VALIDATION_FAILED','Ngày kết thúc phải sau ngày bắt đầu.');
        if ($stage==='active'&&$rawEnd!==null) $this->fail(422,'VALIDATION_FAILED','Thực tập đang hoạt động không có ngày kết thúc.');
        if ($submit&&(!$start||$start>$this->today())) $this->fail(422,'VALIDATION_FAILED','Ngày bắt đầu không hợp lệ.');
        if ($stage==='completed'&&$submit&&(!$end||$end>$this->today()||$hoursValue===null||$hoursValue<=0)) $this->fail(422,'VALIDATION_FAILED','Thông tin hoàn thành thực tập không hợp lệ.');
    }
    private function history(string $kind,string $reportId,int $version,string $status,string $actor,array $snapshot): void { unset($snapshot['history']);$s=$this->pdo->prepare('INSERT INTO learner_portfolio_history(id,kind,reportId,version,status,actorUserId,snapshotJson,createdAt) VALUES (:id,:kind,:reportId,:version,:status,:actor,:snapshot,:createdAt)');$s->execute(['id'=>Uuid::v4(),'kind'=>$kind,'reportId'=>$reportId,'version'=>$version,'status'=>$status,'actor'=>$actor,'snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'createdAt'=>$this->now()]); }
    private function notify(string $type,string $recipient,array $payload): void { ($this->notifier)($type,$recipient,$payload); }
    private function kind(string $kind): array { return match($kind){'project'=>['table'=>'project_submissions','context'=>'projectId'],'internship'=>['table'=>'learner_internship_reports','context'=>'applicationId'],default=>$this->fail(422,'VALIDATION_FAILED','Loại báo cáo không hợp lệ.')}; }
    private function now(): string { return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'); }
    private function today(): string { return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d'); }
    private function date(mixed $v): ?string { $v=$this->null($v);if($v===null||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('UTC'));return$d&&$d->format('Y-m-d')===$v?$v:null; }
    private function null(mixed $v): ?string { if($v===null)return null;$v=trim((string)$v);return$v===''?null:$v; }
    private function fail(int $status,string $code,string $message): never { throw new ApiException($status,$code,$message); }
}
