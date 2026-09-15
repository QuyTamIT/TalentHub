<?php
declare(strict_types=1);
namespace TalentHub\Learner\Data\Service;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/ScoreViewer.php';

/** Canonical source reader. Projection rows are never proof of a score. */
final class EvidenceBackedScoreService
{
    public const FORMULA_SKILL_MEAN = 'skill-mean-1.0';
    private array $columns = [];

    public function __construct(private readonly PDO $pdo) {}

    public function forStudent(string $studentId, ScoreViewer $viewer): array
    {
        $studentId = trim($studentId);
        $this->authorize($studentId, $viewer);
        $result = $this->resolve($studentId);
        if ($viewer->isShared()) {
            $share = $viewer->sharedProfile($this->pdo);
            $result['teacher_context_assessments'] = [];
            if (!in_array('skills', $share['fields'], true)) {
                $result['skills'] = [];
                $result['summary'] = ['score'=>null, 'formula_version'=>self::FORMULA_SKILL_MEAN,
                    'scored_skills_count'=>0, 'total_skills_count'=>0, 'included_skill_ids'=>[]];
            }
            foreach ($result['skills'] as &$skill) {
                $skill['evaluator'] = null;
                $skill['source_id'] = null;
                $skill['evidence_refs'] = [];
                $skill['calculation'] = null;
            }
            unset($skill);
        }
        return $result;
    }

    /** Explicit readiness error, never silently turn a broken query into an empty score. */
    public function assertSchemaReady(bool $projection = false): void
    {
        $required = [
            'student_profiles' => ['id','userId','classId'],
            'classes' => ['id','schoolId','status'],
            'users' => ['id','fullName','status'],
            'teacher_profiles' => ['id','userId','schoolId'],
            'skills' => ['id','code','name','category','status'],
            'learner_evaluations' => ['id','seriesId','revision','studentId','teacherId','actorUserId','contextType','contextId','status','publishedAt'],
            'learner_evaluation_items' => ['id','evaluationId','itemKind','skillId','score','maxScore','confirmed'],
            'learner_skill_evidence' => ['id','studentId','skillId','verificationStatus','evidenceKind','sourceType','sourceId','sourceVersion','observedAt','actorUserId','expiresAt','revokedAt','supersedesId'],
            'student_skills' => ['id','studentId','skillId','levelScore','sourceType','verificationStatus'],
        ];
        if ($projection) {
            $required['student_skills'] = array_merge($required['student_skills'], ['scoreState','sourceEvaluationId','sourceEvidenceId','formulaVersion','verifiedAt','createdAt','updatedAt']);
            $required['student_profiles'][] = 'talentScore';
        }
        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->has($table, $column)) throw new RuntimeException("SCORE_SCHEMA_NOT_READY: {$table}.{$column}");
            }
        }
        if ($projection) {
            $info = $this->columns['student_skills']['levelScore'];
            $nullable = $this->sqlite() ? !(bool)$info['notnull'] : $info['IS_NULLABLE'] === 'YES';
            if (!$nullable) throw new RuntimeException('SCORE_SCHEMA_NOT_READY: student_skills.levelScore must allow NULL');
        }
    }

    /** Internal write API: no fabricated viewer and no authorization bypass flag. */
    public function projectOfficialScores(string $studentId): void
    {
        $this->assertSchemaReady(true);
        $owns = !$this->pdo->inTransaction();
        $savepoint = 'score_projection_' . bin2hex(random_bytes(6));
        if ($owns) $this->pdo->beginTransaction();
        else $this->pdo->exec("SAVEPOINT {$savepoint}");
        try {
            // Serialize all source writers for a learner; resolve only after taking the lock.
            $locked = $this->rows('SELECT id FROM student_profiles WHERE id = ?' . ($this->sqlite() ? '' : ' FOR UPDATE'), [trim($studentId)]);
            if (!$locked) throw new DomainException('Student not found.');
            $result = $this->resolve(trim($studentId));
            $existing = $this->rows('SELECT * FROM student_skills WHERE studentId = ?', [$studentId]);
            foreach ($existing as $row) {
                if ($row['id'] === $this->projectionId($studentId, $row['skillId'])) {
                    $this->execute("UPDATE student_skills SET levelScore=NULL, scoreState='missing_source', sourceEvaluationId=NULL, sourceEvidenceId=NULL, formulaVersion=NULL, verificationStatus='pending', verifiedAt=NULL WHERE id=?", [$row['id']]);
                } else {
                    // Preserve legacy value, source, verifier and timestamps for audit. Only demote read metadata.
                    $state = $row['sourceType']==='teacher' || $row['verificationStatus']==='verified' ? 'missing_source' : 'unverified';
                    // Explicit assignment prevents MySQL's ON UPDATE timestamp from rewriting source history.
                    $this->execute('UPDATE student_skills SET scoreState=?, updatedAt=updatedAt WHERE id=?', [$state,$row['id']]);
                }
            }
            foreach ($result['skills'] as $skill) {
                if (!in_array($skill['state'], ['scored','evidence_only'], true)) continue;
                $id = $this->projectionId($studentId, $skill['skill_id']);
                $row = $this->rows('SELECT id FROM student_skills WHERE id=?', [$id]);
                if (!$row) {
                    $used = array_column($this->rows('SELECT sourceType FROM student_skills WHERE studentId=? AND skillId=?', [$studentId,$skill['skill_id']]), 'sourceType');
                    // Existing schema restricts sourceType to these five values. Never overwrite a legacy slot.
                    $free = array_values(array_diff(['assessment','import','activity','teacher','self_declared'], $used));
                    if (!$free) throw new RuntimeException('SCORE_PROJECTION_SLOT_EXHAUSTED: preserve legacy rows; migration required');
                    $this->execute("INSERT INTO student_skills (id,studentId,skillId,levelScore,sourceType,verificationStatus,createdAt,updatedAt) VALUES (?,?,?,NULL,?,'pending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)", [$id,$studentId,$skill['skill_id'],$free[0]]);
                }
                $scored = $skill['state']==='scored';
                $this->execute('UPDATE student_skills SET levelScore=?, scoreState=?, sourceEvaluationId=?, sourceEvidenceId=?, formulaVersion=?, verificationStatus=?, verifiedAt=?, updatedAt=CURRENT_TIMESTAMP WHERE id=?', [
                    $skill['score'],$skill['state'],$skill['source_type']==='evaluation' ? $skill['source_id'] : null,
                    $skill['source_type']==='evidence' ? $skill['source_id'] : null,$skill['formula_version'],
                    $scored ? 'verified' : 'pending',$scored ? $skill['assessed_at'] : null,$id,
                ]);
            }
            $this->execute('UPDATE student_profiles SET talentScore=? WHERE id=?', [$result['summary']['score'],$studentId]);
            if ($owns) $this->pdo->commit();
            else $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
        } catch (Throwable $e) {
            if ($owns && $this->pdo->inTransaction()) $this->pdo->rollBack();
            elseif (!$owns) { $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}"); $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}"); }
            throw $e;
        }
    }

    private function authorize(string $studentId, ScoreViewer $viewer): void
    {
        if (!$viewer->canViewScores()) throw new DomainException('SCORE_ACCESS_DENIED');
        if ($viewer->isShared()) {
            $share = $viewer->sharedProfile($this->pdo);
            if ($share['student_id'] !== $studentId) throw new DomainException('SCORE_ACCESS_DENIED');
            return;
        }
        $this->assertSchemaReady();
        $student = $this->student($studentId);
        $user = $this->rows("SELECT id FROM users WHERE id=? AND status='active'", [$viewer->viewerId()]);
        if (!$student || !$user) throw new DomainException('SCORE_ACCESS_DENIED');
        if ($viewer->role()===ScoreViewer::ROLE_STUDENT && $student['userId']===$viewer->viewerId()) return;
        if ($viewer->schoolId() !== null && $viewer->schoolId() !== $student['schoolId']) throw new DomainException('SCORE_ACCESS_DENIED');
        if ($viewer->role()===ScoreViewer::ROLE_SCHOOL && $this->has('school_members','userId')) {
            if ($this->rows("SELECT userId FROM school_members WHERE userId=? AND schoolId=? AND status='active'", [$viewer->viewerId(),$student['schoolId']])) return;
        }
        if ($viewer->role()===ScoreViewer::ROLE_TEACHER) {
            $teacher = $this->rows('SELECT * FROM teacher_profiles WHERE userId=? AND schoolId=?', [$viewer->viewerId(),$student['schoolId']])[0] ?? null;
            if ($teacher && ((int)($teacher['isSchoolAdmin'] ?? 0)===1 || $this->teacherAssigned($teacher['id'],$student))) return;
        }
        // No enterprise access until a per-recipient consent capability is supplied by the sharing layer.
        throw new DomainException('SCORE_ACCESS_DENIED');
    }

    private function teacherAssigned(string $teacherId, array $student): bool
    {
        if ($this->has('teacher_class_assignments','teacherId') && $this->rows("SELECT teacherId FROM teacher_class_assignments WHERE teacherId=? AND classId=? AND status='active'", [$teacherId,$student['classId']])) return true;
        if ($this->has('projects','mentorTeacherId') && $this->has('project_members','studentId') && $this->rows("SELECT p.id FROM projects p JOIN project_members pm ON pm.projectId=p.id WHERE p.mentorTeacherId=? AND pm.studentId=? AND pm.status='active'", [$teacherId,$student['id']])) return true;
        return false;
    }

    private function student(string $studentId): ?array
    {
        return $this->rows("SELECT sp.*, c.schoolId FROM student_profiles sp JOIN classes c ON c.id=sp.classId WHERE sp.id=? AND c.status='active'", [$studentId])[0] ?? null;
    }

    private function resolve(string $studentId): array
    {
        $this->assertSchemaReady();
        $student = $this->student($studentId);
        if (!$student) throw new DomainException('Student not found in an active school context.');
        $evaluations = $this->evaluations($student);
        $byEvaluation = array_column($evaluations, null, 'id');
        $items = $this->rows('SELECT i.* FROM learner_evaluation_items i JOIN learner_evaluations e ON e.id=i.evaluationId WHERE e.studentId=? ORDER BY i.id DESC', [$studentId]);
        $legacy = $this->rows('SELECT * FROM student_skills WHERE studentId=? ORDER BY id', [$studentId]);
        $evidence = $this->evidence($student, $byEvaluation, $items);
        $ids = array_unique(array_merge(array_column($items,'skillId'),array_column($legacy,'skillId'),array_column($evidence,'skillId')));
        $skills = [];
        foreach ($ids as $skillId) {
            if (!$skillId) continue;
            $meta = $this->rows("SELECT * FROM skills WHERE id=? AND status='active'", [$skillId])[0] ?? null;
            if (!$meta) continue;
            $skill = [
                'skill_id'=>$skillId,'skill_name'=>$meta['name'],'name'=>$meta['name'],'code'=>$meta['code'],'category'=>$meta['category'],
                'score'=>null,'max_score'=>100.0,'state'=>'unverified','score_method'=>null,'method'=>null,'formula_version'=>null,
                'calculation'=>null,'evaluator'=>null,'assessed_at'=>null,'source_type'=>null,'source_id'=>null,'source_version'=>null,
                'evidence_count'=>0,'evidence_summary'=>'Chưa xác minh','evidence_refs'=>[],
            ];
            foreach ($legacy as $row) {
                if ($row['skillId']!==$skillId) continue;
                if ($row['sourceType']==='teacher' || $row['verificationStatus']==='verified' || !empty($row['sourceEvaluationId'])) {
                    $skill['state']='missing_source'; $skill['evidence_summary']='Thiếu liên kết đánh giá gốc';
                }
            }
            $proofs = array_values(array_filter($evidence, static fn($e)=>$e['skillId']===$skillId));
            if ($proofs) {
                $first=$proofs[0];
                $skill=array_replace($skill, ['state'=>'evidence_only','source_type'=>'evidence','source_id'=>$first['id'],
                    'source_version'=>$first['sourceVersion'],'assessed_at'=>$first['observedAt'],'evidence_count'=>count($proofs),
                    'evidence_refs'=>array_column($proofs,'id'),'evidence_summary'=>'Có minh chứng · Chưa chấm điểm']);
            }
            foreach ($evaluations as $evaluation) {
                $candidates=array_values(array_filter($items, static fn($i)=>$i['evaluationId']===$evaluation['id'] && $i['skillId']===$skillId && $i['itemKind']==='skill'));
                if (!$candidates) continue;
                // Ambiguous duplicate items cannot establish one score. Never fall back within a newer revision.
                if (count($candidates)!==1) break;
                $item=$candidates[0];
                if ((int)$item['confirmed']!==1 || !$this->validScore($item['score'],$item['maxScore'])) break;
                $skill=array_replace($skill, [
                    'score'=>round((float)$item['score']/(float)$item['maxScore']*100,2),'state'=>'scored',
                    // Evaluation-level rubric metadata describes the context total, NOT independently entered skill items.
                    'score_method'=>'teacher_direct','method'=>'teacher_direct','formula_version'=>null,'calculation'=>null,
                    'evaluator'=>$evaluation['teacherName'],'assessed_at'=>$evaluation['publishedAt'],
                    'source_type'=>'evaluation','source_id'=>$evaluation['id'],'source_version'=>(int)$evaluation['revision'],
                    'evidence_count'=>max(1,count($proofs)),'evidence_summary'=>'Giảng viên chấm trực tiếp',
                    'evidence_refs'=>array_values(array_unique(array_merge([$evaluation['id']],array_column($proofs,'id')))),
                ]);
                break;
            }
            $skills[]=$skill;
        }
        usort($skills, static fn($a,$b)=>[$a['category'],$a['name'],$a['skill_id']] <=> [$b['category'],$b['name'],$b['skill_id']]);
        $scored=array_values(array_filter($skills, static fn($s)=>$s['state']==='scored' && $s['score']!==null));
        $contexts=[];
        foreach ($evaluations as $e) {
            $contexts[]=['assessment_id'=>$e['id'],'context_type'=>$e['contextType'],'context_id'=>$e['contextId'],
                'context_label'=>$e['contextLabel'],'overall_score'=>$this->validScore($e['overallScore'] ?? null,100) ? (float)$e['overallScore'] : null,
                'score_method'=>$e['scoreMethod'] ?? null,'method'=>$e['scoreMethod'] ?? null,'formula_version'=>$e['formulaVersion'] ?? null,
                'published_at'=>$e['publishedAt'],'evaluator'=>$e['teacherName'],'comment'=>$e['comment'] ?? null];
        }
        return ['skills'=>$skills,'summary'=>['score'=>$scored ? round(array_sum(array_column($scored,'score'))/count($scored),2) : null,
            'formula_version'=>self::FORMULA_SKILL_MEAN,'scored_skills_count'=>count($scored),'total_skills_count'=>count($skills),
            'included_skill_ids'=>array_column($scored,'skill_id')],'teacher_context_assessments'=>$contexts];
    }

    private function evaluations(array $student): array
    {
        $rows=$this->rows("SELECT e.*, u.fullName AS teacherName, t.userId AS teacherUserId FROM learner_evaluations e
            JOIN teacher_profiles t ON t.id=e.teacherId AND t.schoolId=? JOIN users u ON u.id=t.userId AND u.status='active'
            WHERE e.studentId=? AND e.status='published' AND e.publishedAt IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM learner_evaluations n WHERE n.seriesId=e.seriesId AND n.studentId=e.studentId
                AND n.revision>e.revision AND (n.status<>'draft' OR n.publishedAt IS NOT NULL))
            ORDER BY e.revision DESC,e.publishedAt DESC,LOWER(e.id) DESC", [$student['schoolId'],$student['id']]);
        $valid=[];
        foreach ($rows as $e) {
            if (!empty($e['revokedAt']) || !empty($e['supersededAt']) || $e['actorUserId']!==$e['teacherUserId'] || (int)$e['revision']<1) continue;
            $context=$this->context($e,$student);
            if ($context===null) continue;
            $e['contextLabel']=$context;
            $valid[]=$e;
        }
        return $valid;
    }

    private function context(array $e, array $student): ?string
    {
        $id=$e['contextId'];
        switch ($e['contextType']) {
            case 'general': return $id===null || $id==='' ? 'Đánh giá chung' : null;
            case 'class':
                if ($id!==$student['classId']) return null;
                return $this->rows('SELECT name FROM classes WHERE id=?', [$id])[0]['name'] ?? null;
            case 'project':
                if (!$this->has('projects','mentorTeacherId') || !$this->has('project_members','studentId')) return null;
                return $this->rows("SELECT p.title FROM projects p JOIN project_members pm ON pm.projectId=p.id
                    WHERE p.id=? AND p.schoolId=? AND p.mentorTeacherId=? AND pm.studentId=? AND pm.status='active' AND pm.leftAt IS NULL", [$id,$student['schoolId'],$e['teacherId'],$student['id']])[0]['title'] ?? null;
            case 'activity':
                if (!$this->has('activities','createdByTeacherId') || !$this->has('activity_registrations','studentId')) return null;
                return $this->rows("SELECT a.title FROM activities a JOIN activity_registrations r ON r.activityId=a.id WHERE a.id=? AND a.schoolId=? AND a.createdByTeacherId=? AND r.studentId=? AND r.status IN ('approved','attended')", [$id,$student['schoolId'],$e['teacherId'],$student['id']])[0]['title'] ?? null;
            case 'project_submission':
            case 'internship_report':
                $report=$this->report($e['contextType'],$id,$student);
                return $report && $report['reviewedByUserId']===$e['teacherUserId'] ? $report['title'] : null;
            default: return null;
        }
    }

    /** Only an exact individual reviewed report and current reviewer assignment prove portfolio evidence. */
    private function report(string $type, string $id, array $student): ?array
    {
        if ($type==='project_submission') {
            if (!$this->has('project_submissions','reviewedByUserId') || !$this->has('projects','mentorTeacherId') || !$this->has('project_members','leftAt')) return null;
            return $this->rows("SELECT r.*,p.title FROM project_submissions r JOIN projects p ON p.id=r.projectId
                JOIN project_members pm ON pm.projectId=p.id AND pm.studentId=r.studentId AND pm.status='active' AND pm.leftAt IS NULL
                JOIN teacher_profiles t ON t.id=p.mentorTeacherId AND t.schoolId=p.schoolId
                JOIN users u ON u.id=t.userId AND u.status='active'
                WHERE r.id=? AND r.studentId=? AND r.status='verified' AND r.reviewedAt IS NOT NULL
                AND r.reviewedByUserId=t.userId AND p.schoolId=?", [$id,$student['id'],$student['schoolId']])[0] ?? null;
        }
        if ($type==='internship_report') {
            if (!$this->has('learner_internship_reports','reviewedByUserId') || !$this->has('internship_mentor_assignments','mentorTeacherId')) return null;
            return $this->rows("SELECT r.*,p.title FROM learner_internship_reports r JOIN internship_applications a ON a.id=r.applicationId AND a.studentId=r.studentId AND a.status='accepted'
                JOIN internship_posts p ON p.id=a.postId JOIN internship_mentor_assignments m ON m.applicationId=a.id
                JOIN teacher_profiles t ON t.id=m.mentorTeacherId JOIN users u ON u.id=t.userId AND u.status='active'
                WHERE r.id=? AND r.studentId=? AND r.status='verified' AND r.reviewedAt IS NOT NULL AND r.reviewedByUserId=t.userId AND t.schoolId=?", [$id,$student['id'],$student['schoolId']])[0] ?? null;
        }
        return null;
    }

    private function evidence(array $student, array $evaluations, array $items): array
    {
        $rows=$this->rows("SELECT e.* FROM learner_skill_evidence e WHERE e.studentId=? AND e.evidenceKind='skill'
            AND e.verificationStatus='verified' AND e.revokedAt IS NULL AND (e.expiresAt IS NULL OR e.expiresAt>CURRENT_TIMESTAMP)
            AND NOT EXISTS (SELECT 1 FROM learner_skill_evidence n WHERE n.supersedesId=e.id AND n.studentId=e.studentId AND n.skillId=e.skillId)
            ORDER BY e.observedAt DESC,LOWER(e.id) DESC", [$student['id']]);
        $valid=[];
        foreach ($rows as $e) {
            if ((int)$e['sourceVersion']<1 || !$e['observedAt']) continue;
            if (in_array($e['sourceType'], ['evaluation','learner_evaluation'], true)) {
                $evaluation=$evaluations[$e['sourceId']] ?? null;
                if (!$evaluation || (int)$evaluation['revision']!==(int)$e['sourceVersion'] || $e['actorUserId']!==$evaluation['teacherUserId']) continue;
                $linked=array_filter($items, static fn($i)=>$i['evaluationId']===$e['sourceId'] && $i['skillId']===$e['skillId'] && $i['itemKind']==='skill' && (int)$i['confirmed']===1);
                if (!$linked) continue;
            } else {
                $report=$this->report((string)$e['sourceType'],(string)$e['sourceId'],$student);
                if (!$report || (int)$report['version']!==(int)$e['sourceVersion'] || $report['reviewedByUserId']!==$e['actorUserId'] || !$this->has('learner_portfolio_skills','reportId')) continue;
                $kind=$e['sourceType']==='project_submission' ? 'project' : 'internship';
                if (!$this->rows('SELECT skillId FROM learner_portfolio_skills WHERE kind=? AND reportId=? AND skillId=?', [$kind,$e['sourceId'],$e['skillId']])) continue;
            }
            // Deliberately never read e.score: it is not a published evaluation item.
            $valid[]=$e;
        }
        return $valid;
    }

    private function validScore(mixed $score, mixed $max): bool
    {
        return is_numeric($score) && is_numeric($max) && is_finite((float)$score) && is_finite((float)$max) && (float)$max>0 && (float)$score>=0 && (float)$score<=(float)$max;
    }

    private function projectionId(string $studentId, string $skillId): string
    {
        $h=hash('sha256', 'official-score-projection-v1:'.strtolower($studentId).':'.strtolower($skillId));
        return substr($h,0,8).'-'.substr($h,8,4).'-5'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20,12);
    }

    private function sqlite(): bool { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'; }

    private function has(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            $rows=$this->sqlite() ? $this->rows("PRAGMA table_info({$table})") : $this->rows('SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?', [$table]);
            $this->columns[$table]=array_column($rows,null,$this->sqlite() ? 'name' : 'COLUMN_NAME');
        }
        return isset($this->columns[$table][$column]);
    }

    private function execute(string $sql, array $params = []): \PDOStatement
    {
        $statement=$this->pdo->prepare($sql);
        if (!$statement || !$statement->execute($params)) throw new RuntimeException('SCORE_QUERY_FAILED');
        return $statement;
    }

    private function rows(string $sql, array $params = []): array
    {
        return $this->execute($sql,$params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
