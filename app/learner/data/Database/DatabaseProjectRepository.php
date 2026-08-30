<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\ProjectRepository;
use TalentHub\Learner\Data\Support\Uuid;
use Throwable;

final class DatabaseProjectRepository extends AbstractDatabaseRepository implements ProjectRepository
{
    private const BASE_SELECT = <<<'SQL'
        SELECT
            p.id,
            p.schoolId,
            p.mentorTeacherId,
            p.title,
            p.category,
            p.description,
            p.fundingGoal,
            p.projectUrl,
            p.startAt,
            p.endAt,
            p.status,
            p.createdAt,
            p.updatedAt,
            s.name AS schoolName,
            mentor.fullName AS mentorName,
            COALESCE((
                SELECT COUNT(*)
                FROM project_members pm
                WHERE pm.projectId = p.id AND pm.status = 'active'
            ), 0) AS membersCount
        FROM student_profiles sp
        INNER JOIN classes c ON c.id = sp.classId
        INNER JOIN projects p ON p.schoolId = c.schoolId
        INNER JOIN schools s ON s.id = p.schoolId AND s.status = 'active'
        LEFT JOIN teacher_profiles tp ON tp.id = p.mentorTeacherId AND tp.schoolId = p.schoolId
        LEFT JOIN users mentor ON mentor.id = tp.userId
        WHERE sp.id = :student_id
          AND sp.studyStatus = 'active'
          AND p.status = 'in_progress'
        SQL;

    private const SPONSORSHIPS_SQL = <<<'SQL'
        SELECT
            e.id AS enterpriseId,
            e.name AS enterpriseName,
            ps.amount,
            ps.currency,
            ps.note
        FROM project_sponsorships ps
        INNER JOIN enterprises e ON e.id = ps.enterpriseId AND e.status = 'active'
        WHERE ps.projectId = :project_id AND ps.status = 'paid'
        ORDER BY ps.createdAt, ps.id
        SQL;

    public function listVisibleForStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        return $this->fetchAll(
            'listVisibleForStudent',
            self::BASE_SELECT . ' ORDER BY p.updatedAt DESC, p.id',
            ['student_id' => $studentId],
        );
    }

    public function findVisibleForStudent(string $studentId, string $projectId): ?array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $projectId = Uuid::normalizeDatabase($projectId, 'project_id');
        $project = $this->fetchOne(
            'findVisibleForStudent',
            self::BASE_SELECT . ' AND p.id = :project_id LIMIT 1',
            ['student_id' => $studentId, 'project_id' => $projectId],
        );
        if ($project === null) {
            return null;
        }

        try {
            $project['sponsorships'] = $this->fetchAll(
                'paidSponsorshipsForProject',
                self::SPONSORSHIPS_SQL,
                ['project_id' => $projectId],
            );
        } catch (Throwable) {
            $project['sponsorships'] = [];
        }

        return $project;
    }

    public function findActiveMembershipForStudent(string $studentId, string $projectId): ?array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $projectId = Uuid::normalizeDatabase($projectId, 'project_id');
        $membership = $this->fetchOne(
            'findActiveMembershipForStudent',
            <<<'SQL'
                SELECT id, projectId, studentId, role, status, joinedAt, leftAt, createdAt, updatedAt
                FROM project_members
                WHERE projectId = :project_id AND studentId = :student_id AND status = 'active'
                LIMIT 1
                SQL,
            ['student_id' => $studentId, 'project_id' => $projectId],
        );

        return is_array($membership) ? $membership : null;
    }
}
