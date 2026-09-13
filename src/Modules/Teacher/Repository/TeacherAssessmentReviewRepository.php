<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

/**
 * Repository over test_results for the 4 aptitude tests, scoped to a teacher's school.
 * Lists submitted attempts (with AI grade) and promotes them to TEACHER_VERIFIED
 * (the official score used by School analytics).
 */
final class TeacherAssessmentReviewRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function teacherProfileByUserId(string $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.schoolId, tp.isSchoolAdmin, u.fullName, s.name AS schoolName
             FROM teacher_profiles tp
             INNER JOIN users u ON u.id = tp.userId
             INNER JOIN schools s ON s.id = tp.schoolId
             WHERE tp.userId = :userId
             LIMIT 1'
        );
        $statement->execute(['userId' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * List submitted aptitude-test results for a teacher's school.
     *
     * @return list<array<string,mixed>>
     */
    public function submittedResults(string $schoolId, string $search = ''): array
    {
        $parameters = ['schoolId' => $schoolId];
        $searchClause = '';
        if ($search !== '') {
            $searchClause = ' AND (LOWER(u.fullName) LIKE :searchFull OR LOWER(tt.name) LIKE :searchTest)';
            $parameters['searchFull'] = '%' . $search . '%';
            $parameters['searchTest'] = '%' . $search . '%';
        }

        $baseSql = <<<'SQL'
SELECT tr.id AS resultId,
       tr.resultCode, tr.summary, tr.gradingStatus, tr.aiSummary,
       tr.teacherScoreOverrideJson, tr.teacherSummary, tr.teacherComment,
       tr.teacherGradedAt, tr.createdAt AS gradedAt,
       tr.dimensionScoresJson,
       tt.type AS testType, tt.code AS testCode, tt.name AS testName,
       ta.submittedAt AS submittedAt,
       sp.id AS studentId, u.id AS studentUserId, u.fullName AS studentName,
       c.name AS className
FROM test_results tr
INNER JOIN test_attempts ta ON ta.id = tr.attemptId
INNER JOIN talent_tests tt ON tt.id = ta.testId
INNER JOIN student_profiles sp ON sp.id = ta.studentId
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN users u ON u.id = sp.userId
WHERE c.schoolId = :schoolId
  AND sp.studyStatus = 'active'
  AND u.status = 'active'
  AND ta.status = 'submitted'
SQL;
        $sql = $baseSql . $searchClause . '
ORDER BY ta.submittedAt DESC
LIMIT 500';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function submittedResultDetail(string $schoolId, string $resultId): ?array
    {
        $statement = $this->pdo->prepare(
            <<<'SQL'
SELECT tr.id AS resultId,
       tr.resultCode, tr.summary, tr.gradingStatus, tr.aiSummary,
       tr.teacherScoreOverrideJson, tr.teacherSummary, tr.teacherComment,
       tr.teacherGradedAt, tr.dimensionScoresJson,
       tt.type AS testType, tt.code AS testCode, tt.name AS testName,
       ta.submittedAt AS submittedAt,
       sp.id AS studentId, u.id AS studentUserId, u.fullName AS studentName,
       c.name AS className
FROM test_results tr
INNER JOIN test_attempts ta ON ta.id = tr.attemptId
INNER JOIN talent_tests tt ON tt.id = ta.testId
INNER JOIN student_profiles sp ON sp.id = ta.studentId
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN users u ON u.id = sp.userId
WHERE tr.id = :result_id
  AND c.schoolId = :school_id
  AND sp.studyStatus = 'active'
  AND ta.status = 'submitted'
LIMIT 1
SQL
        );
        $statement->execute(['result_id' => $resultId, 'school_id' => $schoolId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Promote a result to TEACHER_VERIFIED with teacher overrides + comment.
     *
     * @param array<string,int|float> $overrides dimensionCode => 0-100 score (may be empty)
     */
    public function verify(
        string $schoolId,
        string $resultId,
        array $overrides,
        ?string $teacherSummary,
        ?string $teacherComment,
        string $teacherUserId
    ): void {
        if (!Uuid::isValid($resultId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã kết quả bài làm không hợp lệ.');
        }

        $scores = [];
        foreach ($overrides as $code => $value) {
            $code = (string) $code;
            if ($code === '') {
                continue;
            }
            $numeric = (float) $value;
            if ($numeric < 0 || $numeric > 100) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Điểm chiều "' . $code . '" phải nằm trong khoảng 0-100.');
            }
            $scores[$code] = $numeric;
        }
        $overrideJson = $scores === [] ? null : json_encode($scores, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $statement = $this->pdo->prepare(
            <<<'SQL'
UPDATE test_results tr
INNER JOIN test_attempts ta ON ta.id = tr.attemptId
INNER JOIN student_profiles sp ON sp.id = ta.studentId
INNER JOIN classes c ON c.id = sp.classId
SET tr.gradingStatus = 'teacher_verified',
    tr.teacherScoreOverrideJson = :override_json,
    tr.teacherSummary = :teacher_summary,
    tr.teacherComment = :teacher_comment,
    tr.teacherGradedBy = :teacher_user_id,
    tr.teacherGradedAt = NOW(6)
WHERE tr.id = :result_id
  AND c.schoolId = :school_id
  AND sp.studyStatus = 'active'
SQL
        );
        $statement->execute([
            'override_json' => $overrideJson,
            'teacher_summary' => $teacherSummary,
            'teacher_comment' => $teacherComment,
            'teacher_user_id' => $teacherUserId,
            'result_id' => $resultId,
            'school_id' => $schoolId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new ApiException(403, 'FORBIDDEN', 'Bạn không có quyền xác nhận bài làm này hoặc bài làm không còn hợp lệ.');
        }
    }
}
