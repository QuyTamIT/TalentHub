<?php
declare(strict_types=1);

namespace TalentHub\Modules\Skills\Repository;

use PDO;

/** Shared taxonomy. Group scores are never expanded into skill scores. */
final class SkillGroupRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<array{code:string,name:string,status:string}> */
    public function forAssessment(?string $assessmentId = null): array
    {
        // Retired groups remain visible only when already saved on this assessment.
        $query = $this->pdo->prepare("SELECT g.code,g.name,g.status FROM skill_groups g
            WHERE g.status='active' OR EXISTS (SELECT 1 FROM assessment_skill_group_scores s
                WHERE s.groupCode=g.code AND s.assessmentId=?)
            ORDER BY g.displayOrder,g.name");
        $query->execute([$assessmentId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,string> */
    public function assessmentScores(string $assessmentId): array
    {
        $query = $this->pdo->prepare('SELECT groupCode,score FROM assessment_skill_group_scores WHERE assessmentId=?');
        $query->execute([$assessmentId]);
        return array_map('strval', array_column($query->fetchAll(PDO::FETCH_ASSOC), 'score', 'groupCode'));
    }

    /** Published group evidence, never expanded to scores for member skills. */
    public function publishedForStudent(string $studentId): array
    {
        if (!$this->has('assessment_skill_group_scores', 'groupCode') || !$this->has('skill_groups', 'code')) return [];
        $canonical = $this->has('learner_evaluations', 'legacyAssessmentId');
        $join = $canonical ? "LEFT JOIN learner_evaluations e ON e.legacyAssessmentId=a.id AND e.studentId=a.studentId
            AND (e.status<>'draft' OR e.publishedAt IS NOT NULL)
            AND NOT EXISTS (SELECT 1 FROM learner_evaluations n WHERE n.seriesId=e.seriesId AND n.studentId=e.studentId
                AND n.revision>e.revision AND (n.status<>'draft' OR n.publishedAt IS NOT NULL))" : '';
        $guard = $canonical ? "AND (e.id IS NULL OR (e.status='published' AND e.publishedAt IS NOT NULL))" : '';
        foreach (['revokedAt', 'supersededAt'] as $column) {
            if ($canonical && $this->has('learner_evaluations', $column)) $guard .= " AND e.{$column} IS NULL";
        }
        $source = $canonical ? 'COALESCE(e.id,a.id)' : 'a.id';
        $query = $this->pdo->prepare("SELECT a.id AS assessment_id, {$source} AS source_id,
            a.publishedAt AS assessed_at, g.code AS group_code, g.name, gs.score
            FROM assessments a JOIN assessment_skill_group_scores gs ON gs.assessmentId=a.id
            JOIN skill_groups g ON g.code=gs.groupCode {$join}
            WHERE a.studentId=? AND a.status='published' AND a.publishedAt IS NOT NULL {$guard}
            ORDER BY a.publishedAt DESC,a.id DESC,g.displayOrder,g.code");
        $query->execute([$studentId]);
        $rows = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_numeric($row['score']) || (float)$row['score'] < 0 || (float)$row['score'] > 100) continue;
            $rows[] = array_merge($row, [
                'skill_id'=>'group:' . $row['group_code'], 'code'=>'group_' . $row['group_code'],
                'skill_name'=>$row['name'], 'label'=>$row['name'], 'skillName'=>$row['name'],
                'category'=>'skill_group', 'item_kind'=>'skill_group', 'score'=>(float)$row['score'],
                'max_score'=>100.0, 'maxScore'=>100.0, 'state'=>'scored', 'source_type'=>'evaluation',
                'verification_status'=>'verified', 'score_method'=>'teacher_direct',
                'evidence_summary'=>'Nhóm kỹ năng · Giảng viên chấm trực tiếp',
                'evidence_label'=>'Nhóm kỹ năng · Giảng viên chấm trực tiếp',
            ]);
        }
        return $rows;
    }

    private function has(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($this->pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['name'] === $column) return true;
            }
            return false;
        }
        $query = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $query->execute([$table,$column]);
        return $query->fetchColumn() !== false;
    }
}
