<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Staging;

use PDO;
use RuntimeException;

final class LearnerAiPilotSeeder
{
    private const SCHEMA_PATTERN = '/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/';
    private const RESERVED_PREFIX = '00000000-0000-4000-8000-';

    public function __construct(private readonly PDO $pdo, private readonly string $expectedSchema)
    {
    }

    public static function reservedPrefix(): string
    {
        return self::RESERVED_PREFIX;
    }

    /** @return list<string> */
    public static function studentIds(): array
    {
        return [
            '00000000-0000-4000-8000-000000000101',
            '00000000-0000-4000-8000-000000000102',
        ];
    }

    /** @return list<string> */
    public function touchedTables(): array
    {
        $tables = [];
        foreach (self::rows() as $row) {
            $tables[$row['table']] = true;
        }
        $tables = array_keys($tables);
        sort($tables, SORT_STRING);
        return $tables;
    }

    /** @return array{declared:int,inserted:int,existing:int} */
    public function seed(): array
    {
        $this->assertDisposableConnection();
        $this->assertCanonicalMigrations();

        $rows = self::rows();
        if (count($rows) !== 61) {
            throw new RuntimeException('Pilot seed row declaration count is invalid.');
        }

        $missing = [];
        $existing = 0;
        foreach ($rows as $row) {
            $actual = $this->findById($row['table'], $row['id']);
            if ($actual === null) {
                $missing[] = $row;
                continue;
            }
            $this->assertSameRow($row, $actual);
            $existing++;
        }

        $startsTransaction = !$this->pdo->inTransaction();
        if ($startsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $inserted = 0;
            foreach ($missing as $row) {
                if ($this->insertIfMissing($row)) {
                    $inserted++;
                } else {
                    $existing++;
                }
            }
            if ($startsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($startsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'declared' => count($rows),
            'inserted' => $inserted,
            'existing' => $existing,
        ];
    }

    private function assertDisposableConnection(): void
    {
        if (preg_match(self::SCHEMA_PATTERN, $this->expectedSchema) !== 1) {
            throw new RuntimeException('Pilot seed requires an explicit disposable verification schema.');
        }

        $actual = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($actual) || $actual !== $this->expectedSchema) {
            throw new RuntimeException('Pilot seed connection is not pinned to the approved disposable schema.');
        }
    }

    private function assertCanonicalMigrations(): void
    {
        $statement = $this->pdo->prepare(
            'SELECT checksum FROM learner_forward_migrations WHERE version = :version',
        );
        foreach (self::expectedMigrationChecksums() as $version => $checksum) {
            $statement->execute(['version' => $version]);
            $actual = $statement->fetchColumn();
            if (!is_string($actual) || !hash_equals($checksum, $actual)) {
                throw new RuntimeException('Pilot seed requires the recorded canonical migration: ' . $version);
            }
        }
    }

    /** @return array<string,string> */
    private static function expectedMigrationChecksums(): array
    {
        $root = dirname(__DIR__, 4);
        $checksums = [];
        foreach ([
            '002_create_ai_input_foundation',
            '003_create_ai_input_extensions',
            '004_create_recommendation_store',
        ] as $version) {
            $path = $root . '/Database/migrations/learner/' . $version . '.php';
            $checksum = is_file($path) ? hash_file('sha256', $path) : false;
            if (!is_string($checksum)) {
                throw new RuntimeException('Pilot seed migration source is unavailable: ' . $version);
            }
            $checksums[$version] = $checksum;
        }
        return $checksums;
    }

    /** @param array{table:string,id:string,values:array<string,scalar|null>} $row */
    private function insertIfMissing(array $row): bool
    {
        $columns = array_keys($row['values']);
        $this->assertIdentifiers($row['table'], $columns);

        $select = [];
        $parameters = ['present_id' => $row['id']];
        foreach ($columns as $index => $column) {
            $placeholder = 'value_' . $index;
            $select[] = ':' . $placeholder;
            $parameters[$placeholder] = $row['values'][$column];
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $row['table']
            . ' (' . implode(', ', $columns) . ') '
            . 'SELECT ' . implode(', ', $select) . ' '
            . 'WHERE NOT EXISTS (SELECT 1 FROM ' . $row['table'] . ' WHERE id = :present_id)',
        );
        $statement->execute($parameters);
        if ($statement->rowCount() === 1) {
            return true;
        }

        $actual = $this->findById($row['table'], $row['id']);
        if ($actual === null) {
            throw new RuntimeException('Pilot seed could not insert or verify declared row: ' . $row['table'] . '.' . $row['id']);
        }
        $this->assertSameRow($row, $actual);
        return false;
    }

    /** @return array<string,mixed>|null */
    private function findById(string $table, string $id): ?array
    {
        $this->assertIdentifiers($table, []);
        $statement = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array{table:string,id:string,values:array<string,scalar|null>} $expected
     * @param array<string,mixed> $actual
     */
    private function assertSameRow(array $expected, array $actual): void
    {
        foreach ($expected['values'] as $column => $value) {
            if (!array_key_exists($column, $actual)
                || self::normalized($actual[$column]) !== self::normalized($value)) {
                throw new RuntimeException(
                    'Pilot seed reserved row conflicts with declared content: '
                    . $expected['table'] . '.' . $expected['id'] . '.' . $column,
                );
            }
        }
    }

    private static function normalized(mixed $value): string
    {
        if ($value === null) {
            return '<NULL>';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }

    /** @param list<string> $columns */
    private function assertIdentifiers(string $table, array $columns): void
    {
        foreach (array_merge([$table], $columns) as $identifier) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
                throw new RuntimeException('Pilot seed received an unsafe identifier.');
            }
        }
    }

    /** @param array<string,scalar|null> $values
     * @return array{table:string,id:string,values:array<string,scalar|null>}
     */
    private static function row(string $table, array $values): array
    {
        $id = $values['id'] ?? null;
        if (!is_string($id) || !str_starts_with($id, self::RESERVED_PREFIX)) {
            throw new RuntimeException('Pilot seed rows require reserved identifiers.');
        }
        return ['table' => $table, 'id' => $id, 'values' => $values];
    }

    /** @return list<array{table:string,id:string,values:array<string,scalar|null>}> */
    private static function rows(): array
    {
        $created = '2026-08-16 00:00:00.000000';
        $updated = '2026-08-16 00:00:00.000000';
        $roleLearner = '00000000-0000-4000-8000-000000000001';
        $roleTeacher = '00000000-0000-4000-8000-000000000002';
        $school = '00000000-0000-4000-8000-000000000010';
        $class = '00000000-0000-4000-8000-000000000011';
        $teacherUser = '00000000-0000-4000-8000-000000000020';
        $teacher = '00000000-0000-4000-8000-000000000021';
        [$studentA, $studentB] = self::studentIds();
        $activity = '00000000-0000-4000-8000-000000000030';
        $qrToken = '00000000-0000-4000-8000-000000000031';
        $criterion = '00000000-0000-4000-8000-000000000040';
        $skillIot = '00000000-0000-4000-8000-000000000050';
        $skillPython = '00000000-0000-4000-8000-000000000051';
        $test = '00000000-0000-4000-8000-000000000060';
        $questionR = '00000000-0000-4000-8000-000000000061';
        $questionI = '00000000-0000-4000-8000-000000000062';
        $questionA = '00000000-0000-4000-8000-000000000063';
        $version = '00000000-0000-4000-8000-000000000070';
        $registrationA = '00000000-0000-4000-8000-000000000131';
        $registrationB = '00000000-0000-4000-8000-000000000132';
        $checkinA = '00000000-0000-4000-8000-000000000141';
        $checkinB = '00000000-0000-4000-8000-000000000142';

        $rows = [
            self::row('roles', ['id' => $roleLearner, 'code' => 'pilot_learner', 'name' => 'Synthetic Pilot Learner', 'description' => 'Disposable AI pilot learner role.', 'isSystem' => 0, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('roles', ['id' => $roleTeacher, 'code' => 'pilot_teacher', 'name' => 'Synthetic Pilot Teacher', 'description' => 'Disposable AI pilot teacher role.', 'isSystem' => 0, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('schools', ['id' => $school, 'name' => 'Synthetic AI Pilot School', 'status' => 'active', 'logoUrl' => null, 'address' => null, 'phone' => null, 'email' => 'pilot-school@example', 'website' => null, 'level' => null, 'studentCount' => 0, 'teacherCount' => 0, 'academicYear' => '2026-2027', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('classes', ['id' => $class, 'schoolId' => $school, 'name' => 'Synthetic AI Pilot 10A', 'gradeLevel' => 10, 'academicYear' => '2026-2027', 'status' => 'active', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('users', ['id' => $teacherUser, 'roleId' => $roleTeacher, 'email' => 'pilot-teacher@example', 'passwordHash' => 'synthetic-disabled-password-hash-v1', 'fullName' => 'Synthetic Pilot Teacher', 'status' => 'active', 'lastLoginAt' => null, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('users', ['id' => $studentA, 'roleId' => $roleLearner, 'email' => 'pilot-learner-101@example', 'passwordHash' => 'synthetic-disabled-password-hash-v1', 'fullName' => 'Synthetic Pilot Learner 101', 'status' => 'active', 'lastLoginAt' => null, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('users', ['id' => $studentB, 'roleId' => $roleLearner, 'email' => 'pilot-learner-102@example', 'passwordHash' => 'synthetic-disabled-password-hash-v1', 'fullName' => 'Synthetic Pilot Learner 102', 'status' => 'active', 'lastLoginAt' => null, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('teacher_profiles', ['id' => $teacher, 'userId' => $teacherUser, 'schoolId' => $school, 'isSchoolAdmin' => 0, 'phone' => null, 'specialization' => 'Synthetic technology facilitation', 'bio' => null, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('student_profiles', ['id' => $studentA, 'userId' => $studentA, 'classId' => $class, 'dateOfBirth' => '2010-01-01', 'phone' => '0000000101', 'studyStatus' => 'active', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('student_profiles', ['id' => $studentB, 'userId' => $studentB, 'classId' => $class, 'dateOfBirth' => '2010-02-02', 'phone' => '0000000102', 'studyStatus' => 'active', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('activities', ['id' => $activity, 'schoolId' => $school, 'createdByTeacherId' => $teacher, 'title' => 'Synthetic Technical Workshop', 'category' => 'technology', 'startAt' => '2026-08-01 08:00:00.000000', 'endAt' => '2026-08-01 12:00:00.000000', 'capacity' => 20, 'status' => 'published', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('activity_registrations', ['id' => $registrationA, 'activityId' => $activity, 'studentId' => $studentA, 'status' => 'attended', 'registeredAt' => '2026-07-28 09:00:00.000000', 'updatedAt' => $updated]),
            self::row('activity_registrations', ['id' => $registrationB, 'activityId' => $activity, 'studentId' => $studentB, 'status' => 'attended', 'registeredAt' => '2026-07-28 09:00:01.000000', 'updatedAt' => $updated]),
            self::row('activity_qr_tokens', ['id' => $qrToken, 'activityId' => $activity, 'tokenHash' => hash('sha256', 'synthetic-ai-pilot-qr-v1'), 'validFrom' => '2026-08-01 07:00:00.000000', 'validUntil' => '2026-08-01 13:00:00.000000', 'status' => 'active', 'createdAt' => $created]),
            self::row('checkins', ['id' => $checkinA, 'registrationId' => $registrationA, 'qrTokenId' => $qrToken, 'status' => 'confirmed', 'checkedInAt' => '2026-08-01 09:00:00.000000', 'confirmedAt' => '2026-08-01 09:05:00.000000', 'createdAt' => $created]),
            self::row('checkins', ['id' => $checkinB, 'registrationId' => $registrationB, 'qrTokenId' => $qrToken, 'status' => 'confirmed', 'checkedInAt' => '2026-08-01 09:01:00.000000', 'confirmedAt' => '2026-08-01 09:06:00.000000', 'createdAt' => $created]),
            self::row('experience_logs', ['id' => '00000000-0000-4000-8000-000000000151', 'studentId' => $studentA, 'activityId' => $activity, 'checkinId' => $checkinA, 'hours' => '4.50', 'status' => 'confirmed', 'auditReason' => 'Synthetic pilot confirmed attendance.', 'confirmedAt' => '2026-08-01 12:00:00.000000', 'createdAt' => $created]),
            self::row('experience_logs', ['id' => '00000000-0000-4000-8000-000000000152', 'studentId' => $studentB, 'activityId' => $activity, 'checkinId' => $checkinB, 'hours' => '6.00', 'status' => 'confirmed', 'auditReason' => 'Synthetic pilot confirmed attendance.', 'confirmedAt' => '2026-08-01 12:01:00.000000', 'createdAt' => $created]),
            self::row('assessment_criteria', ['id' => $criterion, 'code' => 'presentation', 'name' => 'Presentation', 'description' => 'Synthetic presentation criterion.', 'minScore' => '0.00', 'maxScore' => '100.00', 'displayOrder' => 1, 'status' => 'active', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('assessments', ['id' => '00000000-0000-4000-8000-000000000161', 'teacherId' => $teacher, 'studentId' => $studentA, 'activityId' => $activity, 'overallScore' => '88.00', 'comment' => null, 'status' => 'published', 'publishedAt' => '2026-08-02 09:00:00.000000', 'version' => 1, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('assessments', ['id' => '00000000-0000-4000-8000-000000000162', 'teacherId' => $teacher, 'studentId' => $studentB, 'activityId' => $activity, 'overallScore' => '76.00', 'comment' => null, 'status' => 'published', 'publishedAt' => '2026-08-02 09:01:00.000000', 'version' => 1, 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('assessment_scores', ['id' => '00000000-0000-4000-8000-000000000171', 'assessmentId' => '00000000-0000-4000-8000-000000000161', 'criteriaId' => $criterion, 'score' => '55.00', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('assessment_scores', ['id' => '00000000-0000-4000-8000-000000000172', 'assessmentId' => '00000000-0000-4000-8000-000000000162', 'criteriaId' => $criterion, 'score' => '68.00', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('skills', ['id' => $skillIot, 'code' => 'iot', 'name' => 'IoT Fundamentals', 'category' => 'technology', 'status' => 'active', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('skills', ['id' => $skillPython, 'code' => 'python', 'name' => 'Python Fundamentals', 'category' => 'technology', 'status' => 'active', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('student_skills', ['id' => '00000000-0000-4000-8000-000000000201', 'studentId' => $studentA, 'skillId' => $skillIot, 'levelScore' => '86.00', 'sourceType' => 'teacher', 'verificationStatus' => 'verified', 'verifiedAt' => '2026-08-02 10:00:00.000000', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('student_skills', ['id' => '00000000-0000-4000-8000-000000000202', 'studentId' => $studentA, 'skillId' => $skillPython, 'levelScore' => '77.00', 'sourceType' => 'teacher', 'verificationStatus' => 'verified', 'verifiedAt' => '2026-08-02 10:01:00.000000', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('student_skills', ['id' => '00000000-0000-4000-8000-000000000203', 'studentId' => $studentB, 'skillId' => $skillIot, 'levelScore' => '72.00', 'sourceType' => 'teacher', 'verificationStatus' => 'verified', 'verifiedAt' => '2026-08-02 10:02:00.000000', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('student_skills', ['id' => '00000000-0000-4000-8000-000000000204', 'studentId' => $studentB, 'skillId' => $skillPython, 'levelScore' => '91.00', 'sourceType' => 'teacher', 'verificationStatus' => 'verified', 'verifiedAt' => '2026-08-02 10:03:00.000000', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('learner_skill_evidence', ['id' => '00000000-0000-4000-8000-000000000211', 'studentSkillId' => '00000000-0000-4000-8000-000000000201', 'evidenceType' => 'teacher_assessment', 'evidenceRef' => 'synthetic:assessment:101:iot', 'verificationStatus' => 'verified', 'observedAt' => '2026-08-02 10:00:00.000000', 'createdAt' => $created]),
            self::row('learner_skill_evidence', ['id' => '00000000-0000-4000-8000-000000000212', 'studentSkillId' => '00000000-0000-4000-8000-000000000202', 'evidenceType' => 'teacher_assessment', 'evidenceRef' => 'synthetic:assessment:101:python', 'verificationStatus' => 'verified', 'observedAt' => '2026-08-02 10:01:00.000000', 'createdAt' => $created]),
            self::row('learner_skill_evidence', ['id' => '00000000-0000-4000-8000-000000000213', 'studentSkillId' => '00000000-0000-4000-8000-000000000203', 'evidenceType' => 'teacher_assessment', 'evidenceRef' => 'synthetic:assessment:102:iot', 'verificationStatus' => 'verified', 'observedAt' => '2026-08-02 10:02:00.000000', 'createdAt' => $created]),
            self::row('learner_skill_evidence', ['id' => '00000000-0000-4000-8000-000000000214', 'studentSkillId' => '00000000-0000-4000-8000-000000000204', 'evidenceType' => 'teacher_assessment', 'evidenceRef' => 'synthetic:assessment:102:python', 'verificationStatus' => 'verified', 'observedAt' => '2026-08-02 10:03:00.000000', 'createdAt' => $created]),
            self::row('talent_tests', ['id' => $test, 'code' => 'holland', 'name' => 'Synthetic Interest Check', 'type' => 'interest', 'status' => 'published', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('test_questions', ['id' => $questionR, 'testId' => $test, 'code' => 'R1', 'content' => 'Synthetic realistic-interest question.', 'optionsJson' => '{"min":1,"max":5}', 'status' => 'published', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('test_questions', ['id' => $questionI, 'testId' => $test, 'code' => 'I1', 'content' => 'Synthetic investigative-interest question.', 'optionsJson' => '{"min":1,"max":5}', 'status' => 'published', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('test_questions', ['id' => $questionA, 'testId' => $test, 'code' => 'A1', 'content' => 'Synthetic artistic-interest question.', 'optionsJson' => '{"min":1,"max":5}', 'status' => 'published', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('learner_assessment_versions', ['id' => $version, 'testId' => $test, 'version' => '1.0.0', 'scoringVersion' => 'pilot-riasec-1', 'schemaHash' => hash('sha256', 'pilot-riasec-v1'), 'status' => 'published', 'publishedAt' => '2026-08-02 11:00:00.000000', 'createdAt' => $created]),
            self::row('learner_assessment_question_versions', ['id' => '00000000-0000-4000-8000-000000000071', 'versionId' => $version, 'questionId' => $questionR, 'position' => 1, 'dimensionCode' => 'R', 'required' => 1, 'createdAt' => $created]),
            self::row('learner_assessment_question_versions', ['id' => '00000000-0000-4000-8000-000000000072', 'versionId' => $version, 'questionId' => $questionI, 'position' => 2, 'dimensionCode' => 'I', 'required' => 1, 'createdAt' => $created]),
            self::row('learner_assessment_question_versions', ['id' => '00000000-0000-4000-8000-000000000073', 'versionId' => $version, 'questionId' => $questionA, 'position' => 3, 'dimensionCode' => 'A', 'required' => 1, 'createdAt' => $created]),
            self::row('test_attempts', ['id' => '00000000-0000-4000-8000-000000000221', 'testId' => $test, 'studentId' => $studentA, 'status' => 'submitted', 'startedAt' => '2026-08-03 08:00:00.000000', 'submittedAt' => '2026-08-03 08:05:00.000000', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('test_attempts', ['id' => '00000000-0000-4000-8000-000000000222', 'testId' => $test, 'studentId' => $studentB, 'status' => 'submitted', 'startedAt' => '2026-08-03 08:10:00.000000', 'submittedAt' => '2026-08-03 08:15:00.000000', 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('learner_assessment_attempt_metadata', ['id' => '00000000-0000-4000-8000-000000000231', 'attemptId' => '00000000-0000-4000-8000-000000000221', 'versionId' => $version, 'status' => 'submitted', 'expiresAt' => null, 'submittedAt' => '2026-08-03 08:05:00.000000', 'inputHash' => hash('sha256', 'pilot-101-answers-v1'), 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('learner_assessment_attempt_metadata', ['id' => '00000000-0000-4000-8000-000000000232', 'attemptId' => '00000000-0000-4000-8000-000000000222', 'versionId' => $version, 'status' => 'submitted', 'expiresAt' => null, 'submittedAt' => '2026-08-03 08:15:00.000000', 'inputHash' => hash('sha256', 'pilot-102-answers-v1'), 'createdAt' => $created, 'updatedAt' => $updated]),
            self::row('learner_assessment_answers', ['id' => '00000000-0000-4000-8000-000000000241', 'attemptId' => '00000000-0000-4000-8000-000000000221', 'questionId' => $questionR, 'answerJson' => '{"value":5}', 'answeredAt' => '2026-08-03 08:01:00.000000']),
            self::row('learner_assessment_answers', ['id' => '00000000-0000-4000-8000-000000000242', 'attemptId' => '00000000-0000-4000-8000-000000000221', 'questionId' => $questionI, 'answerJson' => '{"value":4}', 'answeredAt' => '2026-08-03 08:02:00.000000']),
            self::row('learner_assessment_answers', ['id' => '00000000-0000-4000-8000-000000000243', 'attemptId' => '00000000-0000-4000-8000-000000000221', 'questionId' => $questionA, 'answerJson' => '{"value":3}', 'answeredAt' => '2026-08-03 08:03:00.000000']),
            self::row('learner_assessment_answers', ['id' => '00000000-0000-4000-8000-000000000244', 'attemptId' => '00000000-0000-4000-8000-000000000222', 'questionId' => $questionR, 'answerJson' => '{"value":3}', 'answeredAt' => '2026-08-03 08:11:00.000000']),
            self::row('learner_assessment_answers', ['id' => '00000000-0000-4000-8000-000000000245', 'attemptId' => '00000000-0000-4000-8000-000000000222', 'questionId' => $questionI, 'answerJson' => '{"value":5}', 'answeredAt' => '2026-08-03 08:12:00.000000']),
            self::row('learner_assessment_answers', ['id' => '00000000-0000-4000-8000-000000000246', 'attemptId' => '00000000-0000-4000-8000-000000000222', 'questionId' => $questionA, 'answerJson' => '{"value":4}', 'answeredAt' => '2026-08-03 08:13:00.000000']),
            self::row('test_results', ['id' => '00000000-0000-4000-8000-000000000251', 'attemptId' => '00000000-0000-4000-8000-000000000221', 'resultCode' => 'RIA', 'summary' => 'Synthetic minimized RIA result.', 'dimensionScoresJson' => '{"R":82,"I":76,"A":64}', 'scoringVersion' => 'pilot-riasec-1', 'createdAt' => $created]),
            self::row('test_results', ['id' => '00000000-0000-4000-8000-000000000252', 'attemptId' => '00000000-0000-4000-8000-000000000222', 'resultCode' => 'IAR', 'summary' => 'Synthetic minimized IAR result.', 'dimensionScoresJson' => '{"R":74,"I":88,"A":69}', 'scoringVersion' => 'pilot-riasec-1', 'createdAt' => $created]),
        ];

        foreach ([$studentA, $studentB] as $studentIndex => $studentId) {
            foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scopeIndex => $scope) {
                $sequence = 261 + ($studentIndex * 4) + $scopeIndex;
                $rows[] = self::row('learner_ai_consent_events', [
                    'id' => self::id($sequence),
                    'studentId' => $studentId,
                    'scope' => $scope,
                    'action' => 'granted',
                    'policyVersion' => 'pilot-ai-policy-1',
                    'occurredAt' => sprintf('2026-08-04 09:00:0%d.000000', $sequence - 261),
                    'requestId' => self::id(281 + ($studentIndex * 4) + $scopeIndex),
                ]);
            }
        }

        return $rows;
    }

    private static function id(int $sequence): string
    {
        return self::RESERVED_PREFIX . str_pad((string) $sequence, 12, '0', STR_PAD_LEFT);
    }
}
