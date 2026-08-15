<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;
use PDOException;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Support\Uuid;

final class TeacherGradingRepository
{
    public function __construct(private readonly PDO $pdo) {}

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
        array $criteriaScores
    ): void {
        $this->pdo->beginTransaction();

        try {
            if ($this->registrationForTeacher($teacherId, $activityId, $studentId, true) === null) {
                throw new TeacherGradingConflictException('Assessment scope changed during save.');
            }

            $existing = $this->assessmentForTeacher($teacherId, $studentId, $activityId, $assessmentId);
            if ($expectedVersion === 0) {
                if ($assessmentId !== null || $existing !== null) {
                    throw new TeacherGradingConflictException('Assessment was created by another request.');
                }

                $savedAssessmentId = Uuid::v4();
                $statement = $this->pdo->prepare(
                    'INSERT INTO assessments
                        (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
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
                    'UPDATE assessments
                     SET overallScore = ?, comment = ?, status = ?, publishedAt = ?, version = version + 1
                     WHERE id = ?
                       AND teacherId = ?
                       AND studentId = ?
                       AND activityId = ?
                       AND version = ?'
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

            // Keep VALUES(score) for MariaDB compatibility; newer MySQL versions deprecate this syntax.
            $scoreStatement = $this->pdo->prepare(
                'INSERT INTO assessment_scores (id, assessmentId, criteriaId, score)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE score = VALUES(score)'
            );
            foreach ($criteriaScores as $criteriaScore) {
                $scoreStatement->execute([
                    Uuid::v4(),
                    $savedAssessmentId,
                    $criteriaScore['criteriaId'],
                    $criteriaScore['score'],
                ]);
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
    private function assessmentForTeacher(string $teacherId, string $studentId, string $activityId, ?string $assessmentId): ?array
    {
        $sql =
            'SELECT assessment.id, assessment.version
             FROM assessments assessment
             INNER JOIN activities activity
               ON activity.id = assessment.activityId
              AND activity.createdByTeacherId = ?
             WHERE assessment.teacherId = ?
               AND assessment.studentId = ?
               AND assessment.activityId = ?';
        $parameters = [$teacherId, $teacherId, $studentId, $activityId];

        if ($assessmentId !== null) {
            $sql .= ' AND assessment.id = ?';
            $parameters[] = $assessmentId;
        }

        $sql .= ' LIMIT 1 FOR UPDATE';
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
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute([$teacherId, $activityId, $studentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
