<?php
declare(strict_types=1);
namespace TalentHub\Learner\Data\Database;

use PDO;
use TalentHub\Learner\Data\Support\Uuid;

/** CV-only reader: no shared AI/export contracts changed; all reads are learner-scoped. */
final class DatabasePassportCvRepository extends AbstractDatabaseRepository
{
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
            $student=(new DatabaseStudentRepository($this->pdo))->findById($studentId);
            if (!$student) throw new \RuntimeException('CV student not found');
            $result=['student'=>$student,'skills'=>[],'projects'=>[],'internships'=>[],'teacher_evaluations'=>[],'experience'=>['confirmed_entries'=>[]]];
            // Only verified teacher evidence; activity/import/self-declared rows cannot establish CV competence.
            $evidenceGuard='';
            if ($this->has('learner_skill_evidence','studentSkillId')) {
                $invalid="ev.verificationStatus <> 'verified'";
                if ($this->has('learner_skill_evidence','revokedAt')) $invalid.=' OR ev.revokedAt IS NOT NULL';
                if ($this->has('learner_skill_evidence','expiresAt')) $invalid.=' OR (ev.expiresAt IS NOT NULL AND ev.expiresAt <= CURRENT_TIMESTAMP)';
                $evidenceGuard=" AND NOT EXISTS (SELECT 1 FROM learner_skill_evidence ev WHERE ev.studentSkillId=ss.id
                    AND ev.id=(SELECT latest.id FROM learner_skill_evidence latest WHERE latest.studentSkillId=ss.id ORDER BY latest.observedAt DESC,latest.id DESC LIMIT 1)
                    AND ({$invalid}))";
            }
            $result['skills']=$this->fetchAll('cv skills', "SELECT s.name,s.status AS skillStatus,ss.verificationStatus,ss.verifiedAt,ss.sourceType
                FROM student_skills ss JOIN skills s ON s.id=ss.skillId WHERE ss.studentId=:id AND ss.sourceType='teacher'
                AND ss.verificationStatus='verified' AND ss.verifiedAt IS NOT NULL AND s.status='active' {$evidenceGuard}", ['id'=>$studentId]);
            if ($this->has('projects','title') && $this->has('project_members','status')) {
                $result['projects']=$this->fetchAll('cv projects', "SELECT p.id,p.title,p.status,p.endAt,p.updatedAt,pm.role,pm.contribution,pm.status AS memberStatus
                    FROM projects p JOIN project_members pm ON pm.projectId=p.id WHERE pm.studentId=:id AND pm.status='active'
                    ORDER BY p.updatedAt DESC,p.id", ['id'=>$studentId]);
            }
            if ($this->has('internship_applications','status')) {
                $result['internships']=$this->fetchAll('cv accepted internships', "SELECT ia.id AS applicationId,ip.title,e.name AS enterpriseName,ia.status
                    FROM internship_applications ia JOIN internship_posts ip ON ip.id=ia.postId JOIN enterprises e ON e.id=ip.enterpriseId
                    WHERE ia.studentId=:id AND ia.status='accepted' ORDER BY ia.updatedAt DESC,ia.id", ['id'=>$studentId]);
            }
            $canonical=$this->has('learner_evaluations','seriesId');
            if ($canonical) {
                $result['teacher_evaluations']=$this->fetchAll('cv evaluations', "SELECT ev.id,ev.status,ev.comment,ev.publishedAt,ev.contextType,u.fullName AS teacherName
                    FROM learner_evaluations ev JOIN teacher_profiles tp ON tp.id=ev.teacherId JOIN users u ON u.id=tp.userId
                    WHERE ev.studentId=:id AND ev.status='published' AND ev.publishedAt IS NOT NULL
                    AND ev.revision=(SELECT MAX(newer.revision) FROM learner_evaluations newer WHERE newer.seriesId=ev.seriesId)
                    ORDER BY ev.publishedAt DESC,ev.id", ['id'=>$studentId]);
            }
            $legacyExclusion=$canonical ? ' AND NOT EXISTS (SELECT 1 FROM learner_evaluations ev WHERE ev.legacyAssessmentId=a.id)' : '';
            $legacy=$this->fetchAll('cv legacy evaluations', "SELECT a.id,a.status,a.comment,a.publishedAt,a.activityId,u.fullName AS teacherName
                FROM assessments a JOIN teacher_profiles tp ON tp.id=a.teacherId JOIN users u ON u.id=tp.userId
                WHERE a.studentId=:id AND a.status='published' AND a.publishedAt IS NOT NULL {$legacyExclusion}
                ORDER BY a.publishedAt DESC,a.id", ['id'=>$studentId]);
            $result['teacher_evaluations']=array_merge($result['teacher_evaluations'],$legacy);
            usort($result['teacher_evaluations'],static fn($a,$b)=>strcmp($b['published_at'],$a['published_at']) ?: strcmp($a['id'],$b['id']));
            if ($this->has('learner_evaluation_items','skillId')) {
                $skillEvaluations=$this->fetchAll('cv evaluated skills', "SELECT s.name,s.status AS skillStatus,'verified' AS verificationStatus,ev.publishedAt AS verifiedAt,
                    'project_evaluation' AS sourceType,'Đánh giá giảng viên đã công bố' AS evidenceLabel
                    FROM learner_evaluation_items item JOIN learner_evaluations ev ON ev.id=item.evaluationId JOIN skills s ON s.id=item.skillId
                    WHERE ev.studentId=:id AND ev.status='published' AND ev.publishedAt IS NOT NULL AND ev.contextType IN ('class','project','general')
                    AND item.confirmed=1 AND s.status='active'
                    AND ev.revision=(SELECT MAX(newer.revision) FROM learner_evaluations newer WHERE newer.seriesId=ev.seriesId)", ['id'=>$studentId]);
                $result['skills']=array_merge($result['skills'],$skillEvaluations);
            }
            $result['experience']['confirmed_entries']=$this->fetchAll('cv extracurricular', "SELECT a.title AS activityTitle,el.status,el.confirmedAt
                FROM experience_logs el JOIN activities a ON a.id=el.activityId
                WHERE el.studentId=:id AND el.status='confirmed' AND el.confirmedAt IS NOT NULL ORDER BY el.confirmedAt DESC,el.id", ['id'=>$studentId]);
            if ($this->has('project_submissions','id')) {
                $result['verified_portfolio']=(new \TalentHub\Modules\Student\Repository\PortfolioRepository($this->pdo))->verifiedForStudent($studentId);
            }
            if ($ownsTransaction) $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
