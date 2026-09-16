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
}
