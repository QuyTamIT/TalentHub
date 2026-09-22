<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;
require_once dirname(__DIR__, 4) . '/app/learner/ai/Queue/TransactionalAiOutboxPublisher.php';

use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;
use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\BadgeAwardService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Support\Uuid;
use Throwable;

final class TeacherGradingRepository
{
    public const SKILL_CATEGORIES = [
        'technical',
        'business',
        'marketing',
        'creative',
        'soft',
        'data',
        'academic',
        'finance',
        'music',
        'arts',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?BadgeAwardService $badgeAwardService = null,
        private readonly ?NotificationService $notifications = null
    ) {}

    /** @return array<string,mixed>|null */
    public function findTeacherByUserId(string $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.schoolId, tp.isSchoolAdmin, u.fullName, s.name AS schoolName
             FROM teacher_profiles tp
             INNER JOIN users u ON u.id = tp.userId
             INNER JOIN schools s ON s.id = tp.schoolId
             WHERE tp.userId = ?
             LIMIT 1'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function activities(string $teacherId): array
    {
        $params = [];
        $scope = $this->teacherOwnedActivityWhere($teacherId, $params, 'a', 'list_');
        $detailJoin = $this->hasTable('activity_details') ? ' LEFT JOIN activity_details d ON d.activityId = a.id' : '';
        $statement = $this->pdo->prepare(
            "SELECT a.id, a.title, a.category, a.startAt, a.endAt, a.capacity, a.status,
                    COUNT(ar.id) AS registrationCount
             FROM activities a
             INNER JOIN teacher_profiles owner ON owner.id = a.createdByTeacherId AND owner.schoolId = a.schoolId
             {$detailJoin}
             LEFT JOIN activity_registrations ar
               ON ar.activityId = a.id
              AND ar.status IN ('approved', 'attended')
             WHERE {$scope}
               AND a.status = 'completed'
             GROUP BY a.id, a.title, a.category, a.startAt, a.endAt, a.capacity, a.status
             ORDER BY a.startAt DESC, a.createdAt DESC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function activityForTeacher(string $teacherId, string $activityId): ?array
    {
        $params = ['activityId' => $activityId];
        $scope = $this->teacherOwnedActivityWhere($teacherId, $params, 'a', 'one_');
        $detailJoin = $this->hasTable('activity_details') ? ' LEFT JOIN activity_details d ON d.activityId = a.id' : '';
        $statement = $this->pdo->prepare(
            "SELECT a.id, a.title, a.category, a.startAt, a.endAt, a.capacity, a.status
             FROM activities a
             {$detailJoin}
             WHERE a.id = :activityId
               AND {$scope}
               AND a.status = 'completed'
             LIMIT 1"
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function registrationsWithAssessments(string $teacherId, string $activityId, string $search = ''): array
    {
        $params = [
            'assessTeacherId' => $teacherId,
            'activityId' => $activityId,
        ];
        $scope = $this->teacherOwnedActivityWhere($teacherId, $params, 'activity', 'reg_');
        $detailJoin = $this->hasTable('activity_details') ? ' LEFT JOIN activity_details d ON d.activityId = activity.id' : '';
        $sql =
            "SELECT ar.id AS registrationId, ar.studentId, ar.status AS registrationStatus, ar.registeredAt,
                    u.fullName, u.email,
                    a.id AS assessmentId, a.version AS assessmentVersion, a.overallScore, a.comment, a.status AS assessmentStatus,
                    a.publishedAt, a.updatedAt AS assessmentUpdatedAt
             FROM activity_registrations ar
             INNER JOIN activities activity ON activity.id = ar.activityId
             {$detailJoin}
             INNER JOIN student_profiles sp ON sp.id = ar.studentId
             INNER JOIN users u ON u.id = sp.userId
             LEFT JOIN assessments a
               ON a.activityId = ar.activityId
              AND a.studentId = ar.studentId
              AND a.teacherId = :assessTeacherId
             WHERE activity.id = :activityId
               AND {$scope}
               AND ar.status IN ('approved', 'attended')";

        if ($search !== '') {
            $sql .= ' AND (u.fullName LIKE :qName OR u.email LIKE :qEmail)';
            $like = '%' . $search . '%';
            $params['qName'] = $like;
            $params['qEmail'] = $like;
        }

        $sql .= ' ORDER BY u.fullName ASC, ar.registeredAt ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeCriteria(): array
    {
        $weightColumn = $this->hasColumn('assessment_criteria', 'weight') ? ', weight' : '';
        $statement = $this->pdo->prepare(
            "SELECT id, code, name, description, minScore, maxScore, displayOrder{$weightColumn}
             FROM assessment_criteria
             WHERE status = 'active'
             ORDER BY displayOrder ASC, name ASC"
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!array_key_exists('weight', $row) || $row['weight'] === null) $row['weight'] = 1.0;
        }
        unset($row);
        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function activeSkills(?string $search = '', ?string $category = null): array
    {
        if (!$this->hasTable('skills')) {
            return [];
        }

        $sql = "SELECT id, code, name, category, status
                FROM skills
                WHERE status = 'active'";
        $parameters = [];
        $needle = trim((string) $search);
        if ($needle !== '') {
            $sql .= ' AND (name LIKE ? OR code LIKE ?)';
            $like = '%' . $needle . '%';
            $parameters[] = $like;
            $parameters[] = $like;
        }
        if (is_string($category) && $category !== '') {
            $sql .= ' AND category = ?';
            $parameters[] = $category;
        }
        $sql .= ' ORDER BY category ASC, name ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{id:string,code:string,name:string,category:string}> */
    public function skillsForActivity(string $activityId): array
    {
        if (!$this->hasTable('activity_skill_tags') || !$this->hasTable('skills')) {
            return [];
        }
        $statement = $this->pdo->prepare(
            "SELECT s.id, s.code, s.name, s.category
             FROM activity_skill_tags ast
             INNER JOIN skills s ON s.id = ast.skillId AND s.status = 'active'
             WHERE ast.activityId = ?
             ORDER BY s.category ASC, s.name ASC"
        );
        $statement->execute([$activityId]);
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'category' => (string) $row['category'],
            ];
        }
        return $rows;
    }

    /** @return array<string,string> skillId => score */
    public function assessmentSkillScores(string $assessmentId): array
    {
        if (!$this->hasTable('learner_evaluation_items') || !$this->hasTable('learner_evaluations')) {
            return [];
        }
        $statement = $this->pdo->prepare(
            "SELECT i.skillId, i.score
             FROM learner_evaluation_items i
             WHERE i.evaluationId = (
                 SELECT e.id FROM learner_evaluations e
                 WHERE e.legacyAssessmentId = ?
                 ORDER BY e.revision DESC LIMIT 1
             )
             AND i.itemKind = 'skill'
             AND i.skillId IS NOT NULL"
        );
        $statement->execute([$assessmentId]);
        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $skillId = strtolower(trim((string) ($row['skillId'] ?? '')));
            if ($skillId === '') {
                continue;
            }
            $map[$skillId] = (string) $row['score'];
        }
        return $map;
    }

    /**
     * @param list<string> $studentIds
     * @return array<string, list<array<string,mixed>>>
     */
    public function studentSkillsByStudentIds(array $studentIds): array
    {
        $ids = [];
        foreach ($studentIds as $studentId) {
            if (is_string($studentId) && $studentId !== '') {
                $ids[$studentId] = $studentId;
            }
        }
        $ids = array_values($ids);
        if ($ids === [] || !$this->hasTable('student_skills') || !$this->hasTable('skills')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT ss.studentId, ss.skillId, ss.levelScore, ss.sourceType, ss.verificationStatus, ss.verifiedAt,
                    s.code, s.name, s.category
             FROM student_skills ss
             INNER JOIN skills s ON s.id = ss.skillId
             WHERE ss.studentId IN ($placeholders)
             ORDER BY s.category ASC, s.name ASC"
        );
        $statement->execute($ids);

        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['studentId']][] = $row;
        }

        return $map;
    }

    /** @return array<string,mixed>|null */
    public function registrationForActivity(string $teacherId, string $activityId, string $studentId): ?array
    {
        return $this->registrationForTeacher($teacherId, $activityId, $studentId, false);
    }

    /** @return list<array<string,mixed>> */
    public function assessmentScores(string $teacherId, string $activityId): array
    {
        $params = [
            'assessTeacherId' => $teacherId,
            'activityId' => $activityId,
        ];
        $scope = $this->teacherOwnedActivityWhere($teacherId, $params, 'activity', 'score_');
        $detailJoin = $this->hasTable('activity_details') ? ' LEFT JOIN activity_details d ON d.activityId = activity.id' : '';
        $statement = $this->pdo->prepare(
            "SELECT a.studentId, score.criteriaId, score.score
             FROM assessments a
             INNER JOIN activities activity ON activity.id = a.activityId
             {$detailJoin}
             INNER JOIN assessment_scores score ON score.assessmentId = a.id
             WHERE a.teacherId = :assessTeacherId
               AND a.activityId = :activityId
               AND {$scope}"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function classes(string $teacherId): array
    {
        $school = $this->pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id = ? LIMIT 1');
        $school->execute([$teacherId]);
        $schoolId = $school->fetchColumn();
        if (!is_string($schoolId) || $schoolId === '') {
            return [];
        }

        $s = $this->pdo->prepare(
            "SELECT c.id, c.name AS title, c.status, COUNT(sp.id) AS studentCount,
                    CASE WHEN tca.teacherId IS NOT NULL THEN 1 ELSE 0 END AS isAssigned
             FROM classes c
             LEFT JOIN teacher_class_assignments tca
               ON tca.classId = c.id AND tca.teacherId = ? AND tca.status = 'active'
             LEFT JOIN student_profiles sp ON sp.classId = c.id
             WHERE c.schoolId = ? AND c.status = 'active'
             GROUP BY c.id, c.name, c.status, tca.teacherId
             ORDER BY isAssigned DESC, c.name ASC"
        );
        $s->execute([$teacherId, $schoolId]);

        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function projects(string $teacherId): array
    {
        $s = $this->pdo->prepare("SELECT p.id, p.title, p.status, COUNT(pm.id) AS memberCount
            FROM projects p JOIN teacher_profiles t ON t.id=p.mentorTeacherId AND t.schoolId=p.schoolId
            LEFT JOIN project_members pm ON pm.projectId=p.id AND pm.status='active' AND pm.leftAt IS NULL
            WHERE p.mentorTeacherId=? GROUP BY p.id,p.title,p.status ORDER BY p.title,p.id");
        $s->execute([$teacherId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contextForTeacher(string $teacherId, string $mode, string $contextId, bool $lock = false): ?array
    {
        TeacherAssessmentScope::column($mode);
        $sql = match ($mode) {
            'class' => "SELECT c.id,c.name AS title,c.status FROM classes c
                JOIN teacher_profiles t ON t.schoolId=c.schoolId WHERE c.id=? AND t.id=?",
            'project' => 'SELECT p.id,p.title,p.status FROM projects p JOIN teacher_profiles t ON t.id=p.mentorTeacherId AND t.schoolId=p.schoolId WHERE p.id=? AND t.id=?',
            'activity' => '',
        };
        if ($mode === 'activity') {
            $params = ['contextId' => $contextId];
            $scope = $this->teacherOwnedActivityWhere($teacherId, $params, 'a', 'ctx_');
            $detailJoin = $this->hasTable('activity_details') ? ' LEFT JOIN activity_details d ON d.activityId = a.id' : '';
            $s = $this->pdo->prepare(
                "SELECT a.id, a.title, a.status
                 FROM activities a
                 {$detailJoin}
                 WHERE a.id = :contextId
                   AND {$scope}
                   AND a.status = 'completed'
                 LIMIT 1" . $this->lockSuffix($lock)
            );
            $s->execute($params);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        }
        $s = $this->pdo->prepare($sql . ' LIMIT 1' . $this->lockSuffix($lock));
        $s->execute([$contextId, $teacherId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function studentInContext(string $teacherId, string $mode, string $contextId, string $studentId, bool $lock = false): bool
    {
        // Lock the context first, including the mentor/owner assignment, then its membership.
        if ($this->contextForTeacher($teacherId, $mode, $contextId, $lock) === null) return false;
        $sql = match ($mode) {
            'class' => 'SELECT sp.id FROM student_profiles sp
                INNER JOIN classes c ON c.id = sp.classId
                INNER JOIN teacher_profiles t ON t.id = ? AND t.schoolId = c.schoolId
                WHERE sp.classId = ? AND sp.id = ?',
            'project' => "SELECT pm.id FROM project_members pm JOIN projects p ON p.id=pm.projectId JOIN student_profiles sp ON sp.id=pm.studentId JOIN classes c ON c.id=sp.classId AND c.schoolId=p.schoolId WHERE pm.projectId=? AND pm.studentId=? AND pm.status='active' AND pm.leftAt IS NULL",
            'activity' => "SELECT ar.id FROM activity_registrations ar JOIN activities a ON a.id=ar.activityId JOIN student_profiles sp ON sp.id=ar.studentId JOIN classes c ON c.id=sp.classId AND c.schoolId=a.schoolId WHERE ar.activityId=? AND ar.studentId=? AND ar.status IN ('approved','attended')",
        };
        $s = $this->pdo->prepare($sql . ' LIMIT 1' . $this->lockSuffix($lock));
        if ($mode === 'class') {
            $s->execute([$teacherId, $contextId, $studentId]);
        } else {
            $s->execute([$contextId, $studentId]);
        }
        return $s->fetchColumn() !== false;
    }

    public function studentsForClass(string $teacherId, string $classId, string $search = ''): array
    {
        return $this->studentsForContext($teacherId, 'class', $classId, $search);
    }

    public function studentsForProject(string $teacherId, string $projectId, string $search = ''): array
    {
        return $this->studentsForContext($teacherId, 'project', $projectId, $search);
    }

    public function studentsForContext(string $teacherId, string $mode, string $contextId, string $search = ''): array
    {
        if ($this->contextForTeacher($teacherId, $mode, $contextId) === null) return [];
        $column = TeacherAssessmentScope::column($mode);
        $membership = match ($mode) {
            'class' => 'JOIN classes m ON m.id=sp.classId AND m.id=?',
            'project' => "JOIN project_members m ON m.studentId=sp.id AND m.projectId=? AND m.status='active' AND m.leftAt IS NULL",
            'activity' => "JOIN activity_registrations m ON m.studentId=sp.id AND m.activityId=? AND m.status IN ('approved','attended')",
        };
        $s = $this->pdo->prepare("SELECT sp.id AS studentId,u.fullName,u.email,
            a.id AS assessmentId,a.version AS assessmentVersion,a.overallScore,a.comment,
            a.status AS assessmentStatus,a.publishedAt,a.updatedAt AS assessmentUpdatedAt
            FROM student_profiles sp JOIN users u ON u.id=sp.userId $membership
            JOIN classes student_class ON student_class.id=sp.classId
            JOIN teacher_profiles scope_teacher ON scope_teacher.id=? AND scope_teacher.schoolId=student_class.schoolId
            LEFT JOIN assessments a ON a.studentId=sp.id AND a.teacherId=? AND a.$column=?
            WHERE (u.fullName LIKE ? OR u.email LIKE ?) ORDER BY u.fullName,sp.id");
        $s->execute([$contextId,$teacherId,$teacherId,$contextId,'%'.$search.'%','%'.$search.'%']);
        $students = $s->fetchAll(PDO::FETCH_ASSOC);
        $scores = $this->pdo->prepare("SELECT a.studentId,sc.criteriaId,sc.score,c.name,c.minScore,c.maxScore
            FROM assessments a JOIN assessment_scores sc ON sc.assessmentId=a.id
            JOIN assessment_criteria c ON c.id=sc.criteriaId WHERE a.teacherId=? AND a.$column=?
            ORDER BY c.displayOrder,c.id");
        $scores->execute([$teacherId,$contextId]);
        $map = [];
        foreach ($scores->fetchAll(PDO::FETCH_ASSOC) as $row) $map[$row['studentId']][] = $row;
        foreach ($students as &$student) {
            $student['savedCriteria'] = $map[$student['studentId']] ?? [];
            $student['criteriaScores'] = array_column($student['savedCriteria'], 'score', 'criteriaId');
        }
        return $students;
    }

    private function lockSuffix(bool $lock): string
    {
        return $lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
    }

    public function draftAssessmentForTeacherUser(string $teacherUserId, string $assessmentId): ?array
    {
        $s = $this->pdo->prepare('SELECT a.* FROM assessments a JOIN teacher_profiles t ON t.id=a.teacherId WHERE t.userId=? AND a.id=?');
        $s->execute([$teacherUserId,$assessmentId]);
        $a = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($a)) return null;
        $mode = $a['classId'] !== null ? 'class' : ($a['projectId'] !== null ? 'project' : 'activity');
        $id = (string) $a[TeacherAssessmentScope::column($mode)];
        if (!$this->studentInContext($a['teacherId'], $mode, $id, $a['studentId'])) return null;
        $a['mode'] = $mode;
        $a['contextId'] = $id;
        $s = $this->pdo->prepare('SELECT criteriaId,score FROM assessment_scores WHERE assessmentId=?');
        $s->execute([$assessmentId]);
        $a['criteria'] = array_map('strval', array_column($s->fetchAll(PDO::FETCH_ASSOC), 'score', 'criteriaId'));
        $this->requiredScoreService();
        $items = $this->pdo->prepare("SELECT i.skillId,i.label AS skillName,i.score
            FROM learner_evaluation_items i
            WHERE i.evaluationId = (SELECT e.id FROM learner_evaluations e
                WHERE e.legacyAssessmentId=? ORDER BY e.revision DESC LIMIT 1)
            AND i.itemKind='skill'");
        $items->execute([$assessmentId]);
        $a['skills'] = array_map(static fn(array $item): array => ['skillId'=>(string) $item['skillId'], 'skillName'=>(string) $item['skillName'], 'score'=>(string) $item['score']], $items->fetchAll(PDO::FETCH_ASSOC));
        if ($mode === 'activity' && $this->hasTable('assessment_skill_group_scores')) {
            $a['skillGroups'] = [];
            $groups = new \TalentHub\Modules\Skills\Repository\SkillGroupRepository($this->pdo);
            foreach ($groups->assessmentScores($assessmentId) as $code => $score) {
                $a['skillGroups'][] = ['groupCode' => $code, 'score' => $score];
            }
        }
        return $a;
    }

    /**
     * @param list<array{criteriaId:string,score:string}> $criteriaScores
     * @param list<array{skillId:string,score:string}> $skillsInput
     */
    public function saveAssessment(
        string $teacherId,
        string $studentId,
        string $activityId,
        ?string $assessmentId,
        int $expectedVersion,
        ?string $overallScore,
        ?string $comment,
        string $status,
        ?string $publishedAt,
        array $criteriaScores,
        ?string $actorUserId = null,
        ?string $requestId = null,
        string $mode = 'activity',
        array $skillsInput = [],
        ?string $scoreMethod = null,
        ?string $formulaVersion = null,
        ?string $calculationJson = null,
        ?array $groupScores = null
    ): void {
        $column = TeacherAssessmentScope::column($mode);
        $this->pdo->beginTransaction();

        try {
            if (!$this->studentInContext($teacherId, $mode, $activityId, $studentId, true)) {
                throw new TeacherGradingConflictException('Assessment scope changed during save.');
            }

            $existing = $this->assessmentForTeacher($teacherId, $studentId, $activityId, $assessmentId, $mode);
            $hasScoreMeta = $this->hasColumn('assessments', 'scoreMethod');

            if ($expectedVersion === 0) {
                if ($assessmentId !== null || $existing !== null) {
                    throw new TeacherGradingConflictException('Assessment was created by another request.');
                }

                $savedAssessmentId = Uuid::v4();
                $insertCols = "id, teacherId, studentId, $column, overallScore, comment, status, publishedAt, version";
                $insertPlaceholders = "?, ?, ?, ?, ?, ?, ?, ?, 1";
                $insertParams = [
                    $savedAssessmentId,
                    $teacherId,
                    $studentId,
                    $activityId,
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                ];
                if ($hasScoreMeta) {
                    $insertCols .= ", scoreMethod, formulaVersion, calculationJson";
                    $insertPlaceholders .= ", ?, ?, ?";
                    $insertParams[] = $scoreMethod;
                    $insertParams[] = $formulaVersion;
                    $insertParams[] = $calculationJson;
                }

                $statement = $this->pdo->prepare("INSERT INTO assessments ($insertCols) VALUES ($insertPlaceholders)");
                $statement->execute($insertParams);
                if ($statement->rowCount() !== 1) {
                    throw new TeacherGradingConflictException('Assessment creation conflicted with another request.');
                }
            } else {
                if ($assessmentId === null || $existing === null) {
                    throw new TeacherGradingConflictException('Assessment no longer matches the displayed version.');
                }

                if (($existing['status'] ?? '') === 'published' && $status === 'draft') {
                    throw new TeacherGradingConflictException('Published assessments cannot be edited as draft.');
                }

                $updateSql = "UPDATE assessments
                     SET overallScore = ?, comment = ?, status = ?, publishedAt = ?, version = version + 1";
                $updateParams = [
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                ];
                if ($hasScoreMeta) {
                    $updateSql .= ", scoreMethod = ?, formulaVersion = ?, calculationJson = ?";
                    $updateParams[] = $scoreMethod;
                    $updateParams[] = $formulaVersion;
                    $updateParams[] = $calculationJson;
                }
                $updateSql .= " WHERE id = ?
                        AND teacherId = ?
                        AND studentId = ?
                        AND $column = ?
                        AND version = ?";
                $updateParams[] = $assessmentId;
                $updateParams[] = $teacherId;
                $updateParams[] = $studentId;
                $updateParams[] = $activityId;
                $updateParams[] = $expectedVersion;

                $statement = $this->pdo->prepare($updateSql);
                $statement->execute($updateParams);
                if ($statement->rowCount() !== 1) {
                    throw new TeacherGradingConflictException('Assessment version no longer matches.');
                }

                $savedAssessmentId = $assessmentId;
            }

            $deleteScores = $this->pdo->prepare('DELETE FROM assessment_scores WHERE assessmentId=?');
            $deleteScores->execute([$savedAssessmentId]);

            $isSqlite = $this->isSqlite();
            $scoreSql = $isSqlite
                ? 'INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?) ON CONFLICT(assessmentId, criteriaId) DO UPDATE SET score = excluded.score'
                : 'INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = ?';
            $scoreStatement = $this->pdo->prepare($scoreSql);
            foreach ($criteriaScores as $criteriaScore) {
                $scoreParams = [
                    Uuid::v4(),
                    $savedAssessmentId,
                    $criteriaScore['criteriaId'],
                    $criteriaScore['score'],
                ];
                if (!$isSqlite) {
                    $scoreParams[] = $criteriaScore['score'];
                }
                $scoreStatement->execute($scoreParams);
            }

            if ($groupScores !== null) {
                if ($mode !== 'activity' || $skillsInput !== []) {
                    throw new ApiException(422, 'VALIDATION_FAILED', 'Group grading requires an Activity and no skill payload.');
                }
                $this->persistGroupScores($savedAssessmentId, $groupScores);
            }

            $this->persistTeacherSkills(
                $teacherId,
                $studentId,
                $savedAssessmentId,
                $mode,
                $activityId,
                $overallScore,
                $comment,
                $status,
                $publishedAt,
                $actorUserId,
                $skillsInput,
                $scoreMethod,
                $formulaVersion,
                $calculationJson,
                $groupScores !== null
            );

            if ($status === 'published' && $publishedAt !== null && $this->hasBadgesTable()) {
                $this->getBadgeAwardService()->evaluateAndAward($studentId, 'system');
            }

            // Never invoke the publisher's runtime DDL fallback inside this transaction.
            if ($this->hasTable('learner_ai_data_outbox')) {
                if (!TransactionalAiOutboxPublisher::publish($this->pdo,'teacher_evaluation',$savedAssessmentId,$expectedVersion+1,[$studentId],$status==='published'?'evaluation.published':'evaluation.updated',[$column=>$activityId,'status'=>$status])) {
                    throw new \RuntimeException('Could not record the assessment AI refresh event.');
                }
            }

            if ($this->hasTable('audit_logs') && is_string($actorUserId) && $actorUserId !== '' && is_string($requestId) && $requestId !== '') {
                $audit = $this->pdo->prepare(
                    'INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt)
                     VALUES (:id,:userId,:action,\'assessment\',:entityId,:requestId,NULL,:metadata,:createdAt)'
                );
                $audit->execute([
                    'id' => Uuid::v4(),
                    'userId' => $actorUserId,
                    'action' => $status === 'published' ? 'assessment.published' : 'assessment.saved_draft',
                    'entityId' => $savedAssessmentId,
                    'requestId' => $requestId,
                    'metadata' => json_encode(['mode' => $mode, 'contextId' => $activityId, 'studentId' => $studentId, 'status' => $status], JSON_THROW_ON_ERROR),
                    'createdAt' => gmdate('Y-m-d H:i:s.u'),
                ]);
            }

            if ($status === 'published' && $this->hasTable('notifications')) {
                $studentUser = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = :studentId LIMIT 1');
                $studentUser->execute(['studentId' => $studentId]);
                $studentUserId = $studentUser->fetchColumn();
                if (is_string($studentUserId) && $studentUserId !== '') {
                    $this->getNotificationService()->publish(
                        $studentUserId,
                        'teacher_assessment_published',
                        'Đánh giá mới đã được công bố',
                        'Giáo viên đã công bố kết quả đánh giá năng lực của bạn.',
                        '/app/learner/evaluation.php',
                        'teacher_assessment_published:' . $savedAssessmentId,
                        $studentId
                    );
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($exception instanceof PDOException && $this->isDuplicateKey($exception)) {
                throw new TeacherGradingConflictException('Assessment was created by another request.', 0, $exception);
            }

            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function assessmentForTeacher(string $teacherId, string $studentId, string $contextId, ?string $assessmentId, string $mode): ?array
    {
        $column = TeacherAssessmentScope::column($mode);
        $sql = "SELECT assessment.id,assessment.version,assessment.status FROM assessments assessment
            WHERE assessment.teacherId=? AND assessment.studentId=? AND assessment.$column=?";
        $parameters = [$teacherId,$studentId,$contextId];

        if ($assessmentId !== null) {
            $sql .= ' AND assessment.id = ?';
            $parameters[] = $assessmentId;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' LIMIT 1 FOR UPDATE';
        } else {
            $sql .= ' LIMIT 1';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function registrationForTeacher(string $teacherId, string $activityId, string $studentId, bool $forUpdate): ?array
    {
        $params = [
            'activityId' => $activityId,
            'studentId' => $studentId,
        ];
        $scope = $this->teacherOwnedActivityWhere($teacherId, $params, 'activity', 'own_');
        $detailJoin = $this->hasTable('activity_details') ? ' LEFT JOIN activity_details d ON d.activityId = activity.id' : '';
        $sql =
            "SELECT registration.id, registration.activityId, registration.studentId, registration.status
             FROM activity_registrations registration
             INNER JOIN activities activity ON activity.id = registration.activityId
             {$detailJoin}
             WHERE activity.id = :activityId
               AND {$scope}
               AND registration.studentId = :studentId
               AND registration.status IN ('approved', 'attended')
             LIMIT 1";
        if ($forUpdate && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function teacherOwnedActivityWhere(string $teacherId, array &$params, string $alias = 'a', string $prefix = 't_'): string
    {
        $createdParam = $prefix . 'created';
        $params[$createdParam] = $teacherId;
        $ownership = ["{$alias}.createdByTeacherId = :{$createdParam}"];

        if ($this->hasTable('activity_details')) {
            $respParam = $prefix . 'resp';
            $params[$respParam] = $teacherId;
            $ownership[] = "d.responsibleTeacherId = :{$respParam}";
        }

        $clause = '(' . implode(' OR ', $ownership) . ')';

        $school = $this->pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id = ? LIMIT 1');
        $school->execute([$teacherId]);
        $schoolId = $school->fetchColumn();
        if (is_string($schoolId) && $schoolId !== '') {
            $schoolParam = $prefix . 'school';
            $params[$schoolParam] = $schoolId;
            $clause = "({$clause} AND {$alias}.schoolId = :{$schoolParam})";
        }

        return $clause;
    }

    /** Called inside the assessment transaction, after scope/version checks. */
    private function persistGroupScores(string $assessmentId, array $groups): void
    {
        $allowed = $this->pdo->prepare("SELECT g.code FROM skill_groups g WHERE g.code=?
            AND (g.status='active' OR EXISTS (SELECT 1 FROM assessment_skill_group_scores s
                WHERE s.groupCode=g.code AND s.assessmentId=?))" . $this->lockSuffix(true));
        $seen = [];
        foreach ($groups as $group) {
            $code = $group['groupCode'] ?? null;
            $score = $group['score'] ?? null;
            if (!is_string($code) || isset($seen[$code]) || !is_string($score)
                || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $score) || (float) $score > 100) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Nhóm hoặc điểm nhóm không hợp lệ.');
            }
            $allowed->execute([$code, $assessmentId]);
            if ($allowed->fetchColumn() === false) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Nhóm kỹ năng không tồn tại hoặc không còn hoạt động.');
            }
            $seen[$code] = true;
        }
        $this->pdo->prepare('DELETE FROM assessment_skill_group_scores WHERE assessmentId=?')->execute([$assessmentId]);
        $insert = $this->pdo->prepare('INSERT INTO assessment_skill_group_scores (assessmentId,groupCode,score) VALUES (?,?,?)');
        foreach ($groups as $group) $insert->execute([$assessmentId, $group['groupCode'], $group['score']]);
    }

    private function isDuplicateKey(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * @param list<array{skillId:string,score:string}> $skillsInput
     */
    private function persistTeacherSkills(
        string $teacherId,
        string $studentId,
        string $savedAssessmentId,
        string $mode,
        string $contextId,
        ?string $overallScore,
        ?string $comment,
        string $status,
        ?string $publishedAt,
        ?string $actorUserId,
        array $skillsInput,
        ?string $scoreMethod = null,
        ?string $formulaVersion = null,
        ?string $calculationJson = null,
        bool $preserveSkills = false
    ): void {
        $scores = $this->requiredScoreService();

        $now = $this->nowExpression();
        $isSqlite = $this->isSqlite();
        $resolved = [];
        $seen = [];
        foreach ($skillsInput as $item) {
            $skillId = $this->requireActiveSkill($item);
            if (isset($seen[$skillId])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Không được chọn trùng kỹ năng trong một đánh giá.');
            }
            $seen[$skillId] = true;
            $scoreValue = $item['score'] ?? null;
            if (!is_string($scoreValue) && !is_int($scoreValue) && !is_float($scoreValue)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Điểm kỹ năng không hợp lệ.');
            }
            $score = (string) $scoreValue;
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $score) || (float) $score > 100) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Điểm kỹ năng phải nằm trong [0, 100], tối đa 2 chữ số thập phân.');
            }
            $resolved[] = [
                'skillId' => $skillId,
                'score' => $score,
            ];
        }

        $actor = $this->actorUserIdForTeacher($teacherId, $actorUserId);
        if ($actor === null) {
            throw new \RuntimeException('VERIFIED_SKILLS_ACTOR_REQUIRED: teacher user could not be resolved.');
        }
        $this->upsertLearnerSkillEvaluation(
            $teacherId,
            $studentId,
            $savedAssessmentId,
            $mode,
            $contextId,
            $overallScore,
            $comment,
            $status,
            $publishedAt,
            $actor,
            $resolved,
            $now,
            $isSqlite,
            $scoreMethod,
            $formulaVersion,
            $calculationJson,
            $preserveSkills
        );

        $scores->projectOfficialScores($studentId);
    }

    private function requiredScoreService(): EvidenceBackedScoreService
    {
        $path = dirname(__DIR__, 4) . '/app/learner/data/Service/EvidenceBackedScoreService.php';
        if (!is_readable($path)) {
            throw new \RuntimeException('VERIFIED_SKILLS_DEPENDENCY_MISSING: EvidenceBackedScoreService.php');
        }
        try {
            require_once $path;
            $service = new EvidenceBackedScoreService($this->pdo);
        } catch (Throwable $exception) {
            throw new \RuntimeException('VERIFIED_SKILLS_DEPENDENCY_FAILED: EvidenceBackedScoreService could not be loaded.', 0, $exception);
        }
        $service->assertSchemaReady(true);
        return $service;
    }

    /** @param array{skillId:string,score:string} $item */
    private function requireActiveSkill(array $item): string
    {
        $skillId = $item['skillId'] ?? null;
        if (!is_string($skillId) || !Uuid::isValid(trim($skillId))) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'skillId không hợp lệ.');
        }
        // Keep catalog status stable until the evaluation and projection have been saved.
        $statement = $this->pdo->prepare("SELECT id FROM skills WHERE id=? AND status='active' LIMIT 1" . $this->lockSuffix(true));
        $statement->execute([strtolower(trim($skillId))]);
        $found = $statement->fetchColumn();
        if (!is_string($found) || $found === '') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Kỹ năng không tồn tại trong catalog hoặc không còn active.');
        }
        return strtolower($found);
    }

    private function upsertStudentSkill(string $studentId, string $skillId, string $score, string $now, bool $isSqlite): void
    {
        if ($isSqlite) {
            $sql = "INSERT INTO student_skills
                        (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt)
                    VALUES (?, ?, ?, ?, 'teacher', 'verified', {$now}, {$now})
                    ON CONFLICT(studentId, skillId, sourceType) DO UPDATE SET
                        levelScore = excluded.levelScore,
                        sourceType = 'teacher',
                        verificationStatus = 'verified',
                        verifiedAt = {$now},
                        updatedAt = {$now}";
            $this->pdo->prepare($sql)->execute([Uuid::v4(), $studentId, $skillId, $score]);
            return;
        }

        $sql = "INSERT INTO student_skills
                    (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt)
                VALUES (?, ?, ?, ?, 'teacher', 'verified', {$now}, {$now})
                ON DUPLICATE KEY UPDATE
                    levelScore = ?,
                    sourceType = 'teacher',
                    verificationStatus = 'verified',
                    verifiedAt = {$now},
                    updatedAt = {$now}";
        $this->pdo->prepare($sql)->execute([Uuid::v4(), $studentId, $skillId, $score, $score]);
    }

    /**
     * @param list<array{skillId:string,score:string}> $resolved
     */
    private function upsertLearnerSkillEvaluation(
        string $teacherId,
        string $studentId,
        string $savedAssessmentId,
        string $mode,
        string $contextId,
        ?string $overallScore,
        ?string $comment,
        string $status,
        ?string $publishedAt,
        string $actorUserId,
        array $resolved,
        string $now,
        bool $isSqlite,
        ?string $scoreMethod = null,
        ?string $formulaVersion = null,
        ?string $calculationJson = null,
        bool $preserveSkills = false
    ): void {
        $baseEventKey = 'assessment:' . $savedAssessmentId;
        $find = $this->pdo->prepare(
            'SELECT id, seriesId, revision, status FROM learner_evaluations
             WHERE legacyAssessmentId = ? OR eventKey = ? OR eventKey LIKE ?
             ORDER BY revision DESC
             LIMIT 1'
        );
        $find->execute([$savedAssessmentId, $baseEventKey, $baseEventKey . ':%']);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        $hasMetaCols = $this->hasColumn('learner_evaluations', 'scoreMethod');

        if (is_array($existing) && (($existing['status'] ?? '') === 'published' || $status === 'published')) {
            // Publication always appends a revision, including draft-to-published.
            $updOld = $this->pdo->prepare(
                "UPDATE learner_evaluations SET supersededAt = {$now}, updatedAt = {$now} WHERE id = ?"
            );
            $updOld->execute([$existing['id']]);

            $newRevision = ((int) $existing['revision']) + 1;
            $seriesId = (string) ($existing['seriesId'] ?: Uuid::v4());
            $evaluationId = Uuid::v4();
            $eventKey = $baseEventKey . ':r' . $newRevision;

            if ($hasMetaCols) {
                $insert = $this->pdo->prepare(
                    "INSERT INTO learner_evaluations
                        (id, seriesId, revision, studentId, teacherId, legacyAssessmentId, contextType, contextId,
                         overallScore, comment, status, publishedAt, actorUserId, eventKey,
                         scoreMethod, formulaVersion, calculationJson, createdAt, updatedAt)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$now}, {$now})"
                );
                $insert->execute([
                    $evaluationId,
                    $seriesId,
                    $newRevision,
                    $studentId,
                    $teacherId,
                    $savedAssessmentId,
                    $mode,
                    $contextId,
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $actorUserId,
                    $eventKey,
                    $scoreMethod,
                    $formulaVersion,
                    $calculationJson,
                ]);
            } else {
                $insert = $this->pdo->prepare(
                    "INSERT INTO learner_evaluations
                        (id, seriesId, revision, studentId, teacherId, legacyAssessmentId, contextType, contextId,
                         overallScore, comment, status, publishedAt, actorUserId, eventKey, createdAt, updatedAt)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$now}, {$now})"
                );
                $insert->execute([
                    $evaluationId,
                    $seriesId,
                    $newRevision,
                    $studentId,
                    $teacherId,
                    $savedAssessmentId,
                    $mode,
                    $contextId,
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $actorUserId,
                    $eventKey,
                ]);
            }
        } elseif (is_array($existing)) {
            $evaluationId = (string) $existing['id'];
            if ($hasMetaCols) {
                $update = $this->pdo->prepare(
                    "UPDATE learner_evaluations
                     SET overallScore = ?, comment = ?, status = ?, publishedAt = ?,
                         actorUserId = ?, contextType = ?, contextId = ?, legacyAssessmentId = ?,
                         scoreMethod = ?, formulaVersion = ?, calculationJson = ?, updatedAt = {$now}
                     WHERE id = ?"
                );
                $update->execute([
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $actorUserId,
                    $mode,
                    $contextId,
                    $savedAssessmentId,
                    $scoreMethod,
                    $formulaVersion,
                    $calculationJson,
                    $evaluationId,
                ]);
            } else {
                $update = $this->pdo->prepare(
                    "UPDATE learner_evaluations
                     SET overallScore = ?, comment = ?, status = ?, publishedAt = ?,
                         actorUserId = ?, contextType = ?, contextId = ?, legacyAssessmentId = ?, updatedAt = {$now}
                     WHERE id = ?"
                );
                $update->execute([
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $actorUserId,
                    $mode,
                    $contextId,
                    $savedAssessmentId,
                    $evaluationId,
                ]);
            }
        } else {
            $evaluationId = Uuid::v4();
            $seriesId = Uuid::v4();
            $eventKey = $baseEventKey;
            if ($hasMetaCols) {
                $insert = $this->pdo->prepare(
                    "INSERT INTO learner_evaluations
                        (id, seriesId, revision, studentId, teacherId, legacyAssessmentId, contextType, contextId,
                         overallScore, comment, status, publishedAt, actorUserId, eventKey,
                         scoreMethod, formulaVersion, calculationJson, createdAt, updatedAt)
                     VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$now}, {$now})"
                );
                $insert->execute([
                    $evaluationId,
                    $seriesId,
                    $studentId,
                    $teacherId,
                    $savedAssessmentId,
                    $mode,
                    $contextId,
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $actorUserId,
                    $eventKey,
                    $scoreMethod,
                    $formulaVersion,
                    $calculationJson,
                ]);
            } else {
                $insert = $this->pdo->prepare(
                    "INSERT INTO learner_evaluations
                        (id, seriesId, revision, studentId, teacherId, legacyAssessmentId, contextType, contextId,
                         overallScore, comment, status, publishedAt, actorUserId, eventKey, createdAt, updatedAt)
                     VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$now}, {$now})"
                );
                $insert->execute([
                    $evaluationId,
                    $seriesId,
                    $studentId,
                    $teacherId,
                    $savedAssessmentId,
                    $mode,
                    $contextId,
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $actorUserId,
                    $eventKey,
                ]);
            }
        }

        if ($preserveSkills) {
            // Draft edits keep original item IDs and all metadata. Publication copies the
            // canonical items unchanged into the new revision, including inactive skills.
            if (is_array($existing) && $evaluationId !== (string) $existing['id']) {
                $items = $this->pdo->prepare('SELECT itemKind,itemCode,skillId,label,score,maxScore,confirmed,comment,createdAt FROM learner_evaluation_items WHERE evaluationId=?');
                $items->execute([$existing['id']]);
                $copy = $this->pdo->prepare('INSERT INTO learner_evaluation_items (id,evaluationId,itemKind,itemCode,skillId,label,score,maxScore,confirmed,comment,createdAt) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                foreach ($items->fetchAll(PDO::FETCH_NUM) as $item) {
                    $copy->execute(array_merge([Uuid::v4(), $evaluationId], $item));
                }
            }
            return;
        }

        // Replace only the current revision's skill selection; preserve published history.
        $delete = $this->pdo->prepare("DELETE FROM learner_evaluation_items WHERE evaluationId=? AND itemKind='skill'");
        $delete->execute([$evaluationId]);
        foreach ($resolved as $item) {
            $meta = $this->pdo->prepare('SELECT code, name FROM skills WHERE id = ? LIMIT 1');
            $meta->execute([$item['skillId']]);
            $skill = $meta->fetch(PDO::FETCH_ASSOC);
            $code = is_array($skill) ? substr((string) $skill['code'], 0, 100) : substr($item['skillId'], 0, 100);
            $label = is_array($skill) ? mb_substr((string) $skill['name'], 0, 160) : mb_substr($item['skillId'], 0, 160);
            if ($isSqlite) {
                $sql = "INSERT INTO learner_evaluation_items
                            (id, evaluationId, itemKind, itemCode, skillId, label, score, maxScore, confirmed, createdAt)
                        VALUES (?, ?, 'skill', ?, ?, ?, ?, 100.00, 1, {$now})
                        ON CONFLICT(evaluationId, itemKind, itemCode) DO UPDATE SET
                            skillId = excluded.skillId,
                            label = excluded.label,
                            score = excluded.score,
                            maxScore = 100.00,
                            confirmed = 1";
                $this->pdo->prepare($sql)->execute([
                    Uuid::v4(),
                    $evaluationId,
                    $code,
                    $item['skillId'],
                    $label,
                    $item['score'],
                ]);
                continue;
            }

            $sql = "INSERT INTO learner_evaluation_items
                        (id, evaluationId, itemKind, itemCode, skillId, label, score, maxScore, confirmed, createdAt)
                    VALUES (?, ?, 'skill', ?, ?, ?, ?, 100.00, 1, {$now})
                    ON DUPLICATE KEY UPDATE
                        skillId = ?,
                        label = ?,
                        score = ?,
                        maxScore = 100.00,
                        confirmed = 1";
            $this->pdo->prepare($sql)->execute([
                Uuid::v4(),
                $evaluationId,
                $code,
                $item['skillId'],
                $label,
                $item['score'],
                $item['skillId'],
                $label,
                $item['score'],
            ]);
        }
    }

    private function actorUserIdForTeacher(string $teacherId, ?string $actorUserId): ?string
    {
        $statement = $this->pdo->prepare('SELECT userId FROM teacher_profiles WHERE id = ? LIMIT 1');
        $statement->execute([$teacherId]);
        $userId = $statement->fetchColumn();
        if ($actorUserId !== null && $actorUserId !== '' && $actorUserId !== $userId) {
            throw new \RuntimeException('VERIFIED_SKILLS_ACTOR_MISMATCH: actor must be the evaluating teacher.');
        }

        return is_string($userId) && $userId !== '' ? $userId : null;
    }

    private function skillCode(string $skillName): string
    {
        $code = strtolower((string) preg_replace('/[^a-z0-9_]+/i', '_', trim($skillName)));
        $code = trim($code, '_');
        if ($code === '') {
            $code = 'skill_' . substr(hash('sha256', mb_strtolower(trim($skillName))), 0, 16);
        }

        return substr($code, 0, 100);
    }

    private function skillCategory(mixed $category): string
    {
        $value = is_string($category) ? strtolower(trim($category)) : '';

        return in_array($value, self::SKILL_CATEGORIES, true) ? $value : 'technical';
    }

    private function isUniqueViolation(PDOException $exception): bool
    {
        $vendor = (int) ($exception->errorInfo[1] ?? 0);

        return $vendor === 1062 || ($this->isSqlite() && $vendor === 19);
    }

    private function isSqlite(): bool
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function nowExpression(): string
    {
        return $this->isSqlite() ? "datetime('now')" : 'NOW(6)';
    }

    private function getBadgeAwardService(): BadgeAwardService
    {
        if ($this->badgeAwardService !== null) {
            return $this->badgeAwardService;
        }

        if (!class_exists(BadgeAwardService::class, false)) {
            $root = dirname(__DIR__, 4);
            require_once $root . '/app/learner/data/Contracts/BadgeRepository.php';
            require_once $root . '/app/learner/data/Contracts/StatisticsRepository.php';
            require_once $root . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once $root . '/app/learner/data/Domain/LevelProgression.php';
            require_once $root . '/app/learner/data/Exceptions/LearnerDataMappingException.php';
            require_once $root . '/app/learner/data/Exceptions/LearnerDataQueryException.php';
            require_once $root . '/app/learner/data/Support/KeyMapper.php';
            require_once $root . '/app/learner/data/Support/Uuid.php';
            require_once $root . '/app/learner/data/Service/BadgeRuleEngine.php';
            require_once $root . '/app/learner/data/Service/BadgeAwardService.php';
            require_once $root . '/app/learner/data/Service/NotificationService.php';
            require_once $root . '/app/learner/data/Database/AbstractDatabaseRepository.php';
            require_once $root . '/app/learner/data/Database/DatabaseBadgeRepository.php';
            require_once $root . '/app/learner/data/Database/DatabaseStatisticsRepository.php';
            require_once $root . '/app/learner/data/Database/DatabaseNotificationRepository.php';
        }

        return new BadgeAwardService(
            new DatabaseBadgeRepository($this->pdo),
            new DatabaseStatisticsRepository($this->pdo),
            new BadgeRuleEngine(),
            new NotificationService(new DatabaseNotificationRepository($this->pdo))
        );
    }

    private function hasBadgesTable(): bool
    {
        return $this->hasTable('badges');
    }

    private function hasTable(string $table): bool
    {
        if ((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1");
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function hasColumn(string $table, string $column): bool
    {
        if ($this->isSqlite()) {
            $stmt = $this->pdo->prepare("PRAGMA table_info({$table})");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1"
        );
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function getNotificationService(): NotificationService
    {
        require_once dirname(__DIR__, 4) . '/app/learner/data/bootstrap.php';
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }
}
