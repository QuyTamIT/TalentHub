<?php
declare(strict_types=1);
namespace TalentHub\Learner\Data\Database;

use PDO;
use TalentHub\Learner\Data\Support\Uuid;

require_once __DIR__ . '/../Service/EvidenceBackedScoreService.php';

/** CV-only reader: no shared AI/export contracts changed; all reads are learner-scoped. */
final class DatabasePassportCvRepository extends AbstractDatabaseRepository
{
    public function __construct(PDO $pdo, private readonly ?\TalentHub\Learner\Data\Service\ScoreViewer $scoreViewer = null)
    {
        parent::__construct($pdo);
    }

    private function has(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
            foreach ($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['name']===$column) return true;
            return false;
        }
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $stmt->execute([$table,$column]); return (int)$stmt->fetchColumn()===1;
    }

    public function forStudent(string $studentId): array
    {
        $studentId=Uuid::normalizeDatabase($studentId,'student_id');
        $ownsTransaction=!$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $viewer = $this->scoreViewer ?? \TalentHub\Learner\Data\Service\ScoreViewer::fromSession();
            $official=(new \TalentHub\Learner\Data\Service\EvidenceBackedScoreService($this->pdo))->forStudent(
                $studentId, $viewer
            );
            $student=(new DatabaseStudentRepository($this->pdo))->findById($studentId);
            if (!$student) throw new \RuntimeException('CV student not found');
            if ($this->has('student_profile_details', 'studentId')) {
                $details=$this->fetchOne('cv student details', "SELECT location, bio, avatarUrl, headline FROM student_profile_details WHERE studentId = :id LIMIT 1", ['id'=>$studentId]);
                if ($details) {
                    $student['location'] = $details['location'] ?? null;
                    $student['headline'] = $details['headline'] ?? null;
                    $avatar = $details['avatar_url'] ?? $details['avatarUrl'] ?? null;
                    $student['avatarUrl'] = $avatar;
                    $student['avatar_url'] = $avatar;
                    $student['bio'] = $details['bio'] ?? null;
                }
            }
            $result=['student'=>$student,'skills'=>[],'projects'=>[],'internships'=>[],'teacher_evaluations'=>[],'assessment_results'=>[],'experience'=>['confirmed_entries'=>[],'summary'=>['total_hours'=>0.0,'total_activities'=>0]],'badges'=>[],'certificates'=>[]];
            foreach ($official['skills'] as $skill) {
                if ($skill['state'] !== 'scored' || $skill['score'] === null) continue;
                $result['skills'][]=array_merge($skill, [
                    'level_score'=>$skill['score'], 'score_state'=>'scored', 'skill_status'=>'active',
                    'verification_status'=>'verified', 'verified_at'=>$skill['assessed_at'],
                ]);
            }
            if ($this->has('projects','title') && $this->has('project_members','status')) {
                $descCol = $this->has('projects', 'description') ? 'p.description' : "''";
                $catCol = $this->has('projects', 'category') ? 'p.category' : "'general'";
                $result['projects']=$this->fetchAll('cv projects', "SELECT p.id,p.title, {$catCol} AS category, {$descCol} AS description, p.status,p.endAt,p.updatedAt,pm.role,pm.contribution,pm.status AS memberStatus
                    FROM projects p JOIN project_members pm ON pm.projectId=p.id WHERE pm.studentId=:id AND pm.status='active'
                    ORDER BY p.updatedAt DESC,p.id", ['id'=>$studentId]);
            }
            if (empty($result['projects']) && $this->has('projects', 'schoolId') && $this->has('student_profiles', 'classId')) {
                $descCol = $this->has('projects', 'description') ? 'p.description' : "''";
                $catCol = $this->has('projects', 'category') ? 'p.category' : "'general'";
                $mentorJoin = ($this->has('projects', 'mentorTeacherId') && $this->has('teacher_profiles', 'userId'))
                    ? "LEFT JOIN teacher_profiles tp ON tp.id = p.mentorTeacherId LEFT JOIN users mentor ON mentor.id = tp.userId"
                    : "";
                $mentorSelect = ($this->has('projects', 'mentorTeacherId') && $this->has('teacher_profiles', 'userId'))
                    ? "mentor.fullName AS mentorName,"
                    : "NULL AS mentorName,";
                $result['projects']=$this->fetchAll('cv school projects', "SELECT p.id, p.title, {$catCol} AS category, {$descCol} AS description, p.status, p.endAt, p.updatedAt,
                    {$mentorSelect} NULL AS role, {$descCol} AS contribution, 'active' AS memberStatus
                    FROM student_profiles sp
                    INNER JOIN classes c ON c.id = sp.classId
                    INNER JOIN projects p ON p.schoolId = c.schoolId
                    {$mentorJoin}
                    WHERE sp.id = :id AND sp.studyStatus = 'active' AND p.status IN ('in_progress', 'completed')
                    ORDER BY p.updatedAt DESC, p.id", ['id'=>$studentId]);
            }
            if ($this->has('test_attempts', 'studentId') && $this->has('talent_tests', 'type') && $this->has('test_results', 'resultCode')) {
                $result['assessment_results']=$this->fetchAll('cv assessments', "SELECT tt.name AS testName, tt.type AS testType, tr.resultCode, tr.summary
                    FROM test_attempts ta
                    INNER JOIN talent_tests tt ON tt.id = ta.testId
                    INNER JOIN test_results tr ON tr.attemptId = ta.id
                    WHERE ta.studentId = :id AND ta.status = 'submitted'
                    ORDER BY ta.submittedAt DESC, ta.id DESC", ['id'=>$studentId]);
            }
            if ($this->has('internship_applications','status')) {
                $result['internships']=$this->fetchAll('cv accepted internships', "SELECT ia.id AS applicationId,ip.title,e.name AS enterpriseName,ia.status
                    FROM internship_applications ia JOIN internship_posts ip ON ip.id=ia.postId JOIN enterprises e ON e.id=ip.enterpriseId
                    WHERE ia.studentId=:id AND ia.status='accepted' ORDER BY ia.updatedAt DESC,ia.id", ['id'=>$studentId]);
            }
            foreach ($official['teacher_context_assessments'] as $context) {
                $result['teacher_evaluations'][]=array_merge($context, [
                    'id'=>$context['assessment_id'], 'status'=>'published', 'teacher_name'=>$context['evaluator'],
                ]);
            }
            usort($result['teacher_evaluations'],static fn($a,$b)=>strcmp($b['published_at'],$a['published_at']) ?: strcmp($b['id'],$a['id']));
            $result['experience']['confirmed_entries']=$this->fetchAll('cv extracurricular', "SELECT a.title AS activityTitle,el.status,el.confirmedAt
                FROM experience_logs el JOIN activities a ON a.id=el.activityId
                WHERE el.studentId=:id AND el.status='confirmed' AND el.confirmedAt IS NOT NULL ORDER BY el.confirmedAt DESC,el.id", ['id'=>$studentId]);
            if ($this->has('badges','id') && $this->has('student_badges','studentId')) {
                $timeCol = $this->has('student_badges','awardedAt') ? 'sb.awardedAt' : ($this->has('student_badges','earnedAt') ? 'sb.earnedAt' : 'sb.id');
                $result['badges']=$this->fetchAll('cv badges', "SELECT b.name, b.description, {$timeCol} AS earnedAt
                    FROM badges b INNER JOIN student_badges sb ON sb.badgeId=b.id
                    WHERE sb.studentId=:id ORDER BY {$timeCol} DESC,b.id LIMIT 3", ['id'=>$studentId]);
            }
            if ($this->has('certificates','studentId') && $this->has('certificates','verificationStatus')) {
                $result['certificates']=$this->fetchAll('cv certificates', "SELECT id,title,issuingOrganization,issueDate,verificationStatus,createdAt
                    FROM certificates WHERE studentId=:id ORDER BY issueDate DESC, createdAt DESC, id", ['id'=>$studentId]);
            }
            if ($this->has('experience_logs','hours')) {
                $summaryRow=$this->fetchOne('cv experience summary', "SELECT COALESCE(SUM(hours),0) AS totalHours, COUNT(DISTINCT activityId) AS totalActivities
                    FROM experience_logs WHERE studentId=:id AND status='confirmed'", ['id'=>$studentId]);
                if ($summaryRow) {
                    $result['experience']['summary']=[
                        'total_hours'=>(float)($summaryRow['totalHours']??0),
                        'total_activities'=>(int)($summaryRow['totalActivities']??0),
                    ];
                }
            }
            if ($this->has('project_submissions','id')) {
                $result['verified_portfolio']=(new \TalentHub\Modules\Student\Repository\PortfolioRepository($this->pdo))->verifiedForStudent($studentId);
            }
            if ($viewer->isShared()) {
                $fields = $viewer->sharedProfile($this->pdo)['fields'];
                $result = $this->filterSharedFields($result, $fields);
            }
            if ($ownsTransaction) $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Keep consent filtering at the data boundary, including portfolio-derived CV sections. */
    private function filterSharedFields(array $result, array $fields): array
    {
        $studentKeys = [
            'fullName'=>['full_name','fullName','avatar_url','avatarUrl'], 'headline'=>['headline'],
            'bio'=>['bio'], 'location'=>['location'], 'school'=>['school_name','school'],
            'class'=>['class_name','class'], 'email'=>['email'], 'phone'=>['phone'],
        ];
        foreach ($studentKeys as $field=>$keys) {
            if (in_array($field, $fields, true)) continue;
            foreach ($keys as $key) $result['student'][$key] = '';
        }
        if (!in_array('skills', $fields, true)) {
            $result['skills'] = [];
            $result['verified_portfolio']['skills'] = [];
        }
        if (!in_array('projects', $fields, true)) {
            $result['projects'] = [];
            $result['verified_portfolio']['projects'] = [];
        }
        if (!in_array('experience', $fields, true)) {
            $result['internships'] = [];
            $result['verified_portfolio']['internships'] = [];
            $result['experience'] = ['confirmed_entries'=>[], 'summary'=>['total_hours'=>0.0,'total_activities'=>0]];
        }
        if (!in_array('certificates', $fields, true)) { $result['badges'] = []; $result['certificates'] = []; }
        $result['teacher_evaluations'] = [];
        $result['assessment_results'] = [];
        foreach ($result['projects'] as &$project) {
            unset($project['mentorName'], $project['mentor_name']);
        }
        unset($project);
        if (isset($result['verified_portfolio'])) {
            $result['verified_portfolio'] += ['projects'=>[], 'internships'=>[], 'skills'=>[]];
        }
        return $result;
    }
}
