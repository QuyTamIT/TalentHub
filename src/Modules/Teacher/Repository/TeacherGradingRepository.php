<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;
require_once dirname(__DIR__, 4) . '/app/learner/ai/Queue/TransactionalAiOutboxPublisher.php';

use PDO;
use PDOException;
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
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.title, a.category, a.startAt, a.endAt, a.capacity, a.status,
                    COUNT(ar.id) AS registrationCount
             FROM activities a
             INNER JOIN teacher_profiles owner ON owner.id=a.createdByTeacherId AND owner.schoolId=a.schoolId
             LEFT JOIN activity_registrations ar
               ON ar.activityId = a.id
              AND ar.status IN (\'approved\', \'attended\')
             WHERE a.createdByTeacherId = ?
             GROUP BY a.id, a.title, a.category, a.startAt, a.endAt, a.capacity, a.status
             ORDER BY a.startAt DESC, a.createdAt DESC'
        );
        $statement->execute([$teacherId]);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function activityForTeacher(string $teacherId, string $activityId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, category, startAt, endAt, capacity, status
             FROM activities
             WHERE id = ? AND createdByTeacherId = ?
             LIMIT 1'
        );
        $statement->execute([$activityId, $teacherId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function registrationsWithAssessments(string $teacherId, string $activityId, string $search = ''): array
    {
        $sql =
            'SELECT ar.id AS registrationId, ar.studentId, ar.status AS registrationStatus, ar.registeredAt,
                    u.fullName, u.email,
                    a.id AS assessmentId, a.version AS assessmentVersion, a.overallScore, a.comment, a.status AS assessmentStatus,
                    a.publishedAt, a.updatedAt AS assessmentUpdatedAt
             FROM activity_registrations ar
             INNER JOIN activities activity ON activity.id = ar.activityId
             INNER JOIN student_profiles sp ON sp.id = ar.studentId
             INNER JOIN users u ON u.id = sp.userId
             LEFT JOIN assessments a
               ON a.activityId = ar.activityId
              AND a.studentId = ar.studentId
              AND a.teacherId = ?
             WHERE activity.id = ?
               AND activity.createdByTeacherId = ?
               AND ar.status IN (\'approved\', \'attended\')';
        $parameters = [$teacherId, $activityId, $teacherId];

        if ($search !== '') {
            $sql .= ' AND (u.fullName LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            $parameters[] = $like;
            $parameters[] = $like;
        }

        $sql .= ' ORDER BY u.fullName ASC, ar.registeredAt ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeCriteria(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, name, description, minScore, maxScore, displayOrder
             FROM assessment_criteria
             WHERE status = \'active\'
             ORDER BY displayOrder ASC, name ASC'
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function registrationForActivity(string $teacherId, string $activityId, string $studentId): ?array
    {
        return $this->registrationForTeacher($teacherId, $activityId, $studentId, false);
    }

    /** @return list<array<string,mixed>> */
    public function assessmentScores(string $teacherId, string $activityId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.studentId, score.criteriaId, score.score
             FROM assessments a
             INNER JOIN activities activity
               ON activity.id = a.activityId
              AND activity.createdByTeacherId = ?
             INNER JOIN assessment_scores score ON score.assessmentId = a.id
             WHERE a.teacherId = ? AND a.activityId = ?'
        );
        $statement->execute([$teacherId, $teacherId, $activityId]);

        return $statement->fetchAll();
    }

    public function classes(string $teacherId): array
    {
        $s = $this->pdo->prepare("SELECT c.id,c.name AS title,c.status,COUNT(sp.id) AS studentCount
            FROM teacher_class_assignments tca JOIN teacher_profiles t ON t.id=tca.teacherId
            JOIN classes c ON c.id=tca.classId AND c.schoolId=t.schoolId
            LEFT JOIN student_profiles sp ON sp.classId=c.id
            WHERE t.id=? AND tca.status='active' GROUP BY c.id,c.name,c.status ORDER BY c.name,c.id");
        $s->execute([$teacherId]);
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
                JOIN teacher_class_assignments tca ON tca.classId=c.id AND tca.status='active'
                JOIN teacher_profiles t ON t.id=tca.teacherId AND t.schoolId=c.schoolId WHERE c.id=? AND t.id=?",
            'project' => 'SELECT p.id,p.title,p.status FROM projects p JOIN teacher_profiles t ON t.id=p.mentorTeacherId AND t.schoolId=p.schoolId WHERE p.id=? AND t.id=?',
            'activity' => 'SELECT a.id,a.title,a.status FROM activities a JOIN teacher_profiles t ON t.id=a.createdByTeacherId AND t.schoolId=a.schoolId WHERE a.id=? AND t.id=?',
        };
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
            'class' => 'SELECT id FROM student_profiles WHERE classId=? AND id=?',
            'project' => "SELECT pm.id FROM project_members pm JOIN projects p ON p.id=pm.projectId JOIN student_profiles sp ON sp.id=pm.studentId JOIN classes c ON c.id=sp.classId AND c.schoolId=p.schoolId WHERE pm.projectId=? AND pm.studentId=? AND pm.status='active' AND pm.leftAt IS NULL",
            'activity' => "SELECT ar.id FROM activity_registrations ar JOIN activities a ON a.id=ar.activityId JOIN student_profiles sp ON sp.id=ar.studentId JOIN classes c ON c.id=sp.classId AND c.schoolId=a.schoolId WHERE ar.activityId=? AND ar.studentId=? AND ar.status IN ('approved','attended')",
        };
        $s = $this->pdo->prepare($sql . ' LIMIT 1' . $this->lockSuffix($lock));
        $s->execute([$contextId, $studentId]);
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
        return $a;
    }

    /** @param list<array{criteriaId:string,score:string}> $criteriaScores */
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
        string $mode = 'activity'
    ): void {
        $column = TeacherAssessmentScope::column($mode);
        $this->pdo->beginTransaction();

        try {
            if (!$this->studentInContext($teacherId, $mode, $activityId, $studentId, true)) {
                throw new TeacherGradingConflictException('Assessment scope changed during save.');
            }

            $existing = $this->assessmentForTeacher($teacherId, $studentId, $activityId, $assessmentId, $mode);
            if (($existing['status'] ?? null) === 'published') {
                throw new TeacherGradingConflictException('Published assessments are immutable.');
            }
            if ($expectedVersion === 0) {
                if ($assessmentId !== null || $existing !== null) {
                    throw new TeacherGradingConflictException('Assessment was created by another request.');
                }

                $savedAssessmentId = Uuid::v4();
                $statement = $this->pdo->prepare(
                    "INSERT INTO assessments
                        (id, teacherId, studentId, $column, overallScore, comment, status, publishedAt, version)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
                );
                $statement->execute([
                    $savedAssessmentId,
                    $teacherId,
                    $studentId,
                    $activityId,
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                ]);
                if ($statement->rowCount() !== 1) {
                    throw new TeacherGradingConflictException('Assessment creation conflicted with another request.');
                }
            } else {
                if ($assessmentId === null || $existing === null) {
                    throw new TeacherGradingConflictException('Assessment no longer matches the displayed version.');
                }

                $statement = $this->pdo->prepare(
                    "UPDATE assessments
                     SET overallScore = ?, comment = ?, status = ?, publishedAt = ?, version = version + 1
                     WHERE id = ?
                       AND teacherId = ?
                       AND studentId = ?
                       AND $column = ?
                       AND status = 'draft' AND version = ?"
                );
                $statement->execute([
                    $overallScore,
                    $comment,
                    $status,
                    $publishedAt,
                    $assessmentId,
                    $teacherId,
                    $studentId,
                    $activityId,
                    $expectedVersion,
                ]);
                if ($statement->rowCount() !== 1) {
                    throw new TeacherGradingConflictException('Assessment version no longer matches.');
                }

                $savedAssessmentId = $assessmentId;
            }

            $deleteScores = $this->pdo->prepare('DELETE FROM assessment_scores WHERE assessmentId=?');
            $deleteScores->execute([$savedAssessmentId]);

            // Keep VALUES(score) for MariaDB compatibility; newer MySQL versions deprecate this syntax.
            $isSqlite = ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            $scoreSql = $isSqlite
                ? 'INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?) ON CONFLICT(assessmentId, criteriaId) DO UPDATE SET score = excluded.score'
                : 'INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)';
            $scoreStatement = $this->pdo->prepare($scoreSql);
            foreach ($criteriaScores as $criteriaScore) {
                $scoreStatement->execute([
                    Uuid::v4(),
                    $savedAssessmentId,
                    $criteriaScore['criteriaId'],
                    $criteriaScore['score'],
                ]);
            }

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
        $sql =
            'SELECT registration.id, registration.activityId, registration.studentId, registration.status
             FROM activity_registrations registration
             INNER JOIN activities activity
               ON activity.id = registration.activityId
              AND activity.createdByTeacherId = ?
             WHERE activity.id = ?
               AND registration.studentId = ?
               AND registration.status IN (\'approved\', \'attended\')
             LIMIT 1';
        if ($forUpdate && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute([$teacherId, $activityId, $studentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function isDuplicateKey(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    private function getBadgeAwardService(): BadgeAwardService
    {
        if ($this->badgeAwardService !== null) {
            return $this->badgeAwardService;
        }

        if (!class_exists('TalentHub\Learner\Data\Service\BadgeAwardService', false)) {
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

    private function getNotificationService(): NotificationService
    {
        require_once dirname(__DIR__, 4) . '/app/learner/data/bootstrap.php';
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }
}
