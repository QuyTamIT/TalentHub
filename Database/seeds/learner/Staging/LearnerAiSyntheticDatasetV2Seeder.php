<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Staging;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use TalentHub\Database\ProtectedDatabasePolicy;
use TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum;

require_once dirname(__DIR__, 4) . '/src/Database/ProtectedDatabasePolicy.php';

final class LearnerAiSyntheticDatasetV2Seeder
{
    public const DCR_RELATIVE_PATH = 'docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md';
    private const SCHEMA_PATTERN = '/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/';
    private const APPROVED_SCHEMA = 'talenthub_ai_backup_verify_004_20260816';
    private const APPROVED_CONTENT_HASH = 'c6e417b69a06b9bf93a5762b03850b90c79fd88b716a1b3de48cb5097cf75b6f';
    private const RESERVED_PREFIX = '00000000-0000-4000-8000-';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $expectedSchema,
        private readonly string $approvedContentHash
    ) {
    }

    public static function reservedPrefix(): string
    {
        return self::RESERVED_PREFIX;
    }

    /** @return list<string> */
    public static function studentIds(): array
    {
        return LearnerAiSyntheticDatasetV2::studentIds();
    }

    /** @return list<string> */
    public function touchedTables(): array
    {
        return LearnerAiSyntheticDatasetV2::touchedTables();
    }

    /**
     * Parse raw DCR markdown and extract metadata fields.
     *
     * @return array{
     *     status: string,
     *     target_schema: string,
     *     fingerprint: string,
     *     total_rows: int,
     *     approval_status: string,
     *     approved_by: string,
     *     approved_at: string,
     *     execution_status: 'not_executed'|'executed'
     * }
     */
    public static function parseDcr(string $markdown): array
    {
        if (preg_match('/^\*\*Status:\*\*\s*(.+)$/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing top-level Status.');
        }
        $status = trim($matches[1]);

        if (preg_match('/-\s*\*\*Authorized Target Schema:\*\*\s*`?([A-Za-z0-9_]+)`?/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Authorized Target Schema.');
        }
        $targetSchema = trim($matches[1]);

        if (preg_match('/-\s*\*\*Dataset Fingerprint \(SHA-256\):\*\*\s*`?([a-f0-9]{64})`?/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Dataset Fingerprint.');
        }
        $fingerprint = trim($matches[1]);

        if (preg_match('/-\s*\*\*Total Declared V2 Rows:\*\*\s*`?(\d+)`?/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Total Declared V2 Rows.');
        }
        $totalRows = (int) $matches[1];

        if (preg_match('/-\s*\*\*Approval Status:\*\*\s*(.+)$/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Approval Status in log.');
        }
        $approvalStatus = trim($matches[1]);

        if (preg_match('/-\s*\*\*Approved By:\*\*\s*(.+)$/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Approved By in log.');
        }
        $approvedBy = trim($matches[1]);

        if (preg_match('/-\s*\*\*Approved At:\*\*\s*(.+)$/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Approved At in log.');
        }
        $approvedAt = trim($matches[1]);

        if (preg_match('/-\s*\*\*Execution Status:\*\*\s*(.+)$/m', $markdown, $matches) !== 1) {
            throw new RuntimeException('V2 seed DCR missing Execution Status in log.');
        }
        $rawExecutionStatus = trim($matches[1]);
        $executionStatus = match (true) {
            (bool) preg_match('/not\s*executed/i', $rawExecutionStatus) => 'not_executed',
            (bool) preg_match('/executed/i', $rawExecutionStatus) => 'executed',
            default => throw new RuntimeException('V2 seed DCR has unrecognized Execution Status: ' . $rawExecutionStatus),
        };

        return [
            'status' => $status,
            'target_schema' => $targetSchema,
            'fingerprint' => $fingerprint,
            'total_rows' => $totalRows,
            'approval_status' => $approvalStatus,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
            'execution_status' => $executionStatus,
        ];
    }

    /**
     * Validate parsed DCR document against approval rules.
     *
     * @return array{
     *     status: string,
     *     target_schema: string,
     *     fingerprint: string,
     *     total_rows: int,
     *     approval_status: string,
     *     approved_by: string,
     *     approved_at: string,
     *     execution_status: 'not_executed'|'executed'
     * }
     */
    public static function validateDcr(string $markdown, string $expectedSchema, string $approvedFingerprint): array
    {
        $parsed = self::parseDcr($markdown);

        $approvedHeader = 'APPROVED — DISPOSABLE SCHEMA ONLY';
        if ($parsed['status'] !== $approvedHeader || $parsed['approval_status'] !== $approvedHeader) {
            throw new RuntimeException(
                'V2 seed requires approved DCR; top-level Status and Approval Status must be: ' . $approvedHeader
            );
        }

        if ($parsed['approved_by'] === '' || stripos($parsed['approved_by'], 'pending') !== false) {
            throw new RuntimeException('V2 seed DCR Approved By is pending or empty.');
        }

        if ($parsed['approved_at'] === '' || stripos($parsed['approved_at'], 'pending') !== false) {
            throw new RuntimeException('V2 seed DCR Approved At is pending or empty.');
        }

        if ($parsed['target_schema'] !== self::APPROVED_SCHEMA
            || $parsed['target_schema'] !== $expectedSchema
            || ProtectedDatabasePolicy::isProtected($parsed['target_schema'])) {
            throw new RuntimeException(
                'V2 seed DCR requires approved disposable schema ' . self::APPROVED_SCHEMA . '; got: ' . $parsed['target_schema']
            );
        }

        if ($parsed['fingerprint'] !== self::APPROVED_CONTENT_HASH
            || !hash_equals($approvedFingerprint, $parsed['fingerprint'])) {
            throw new RuntimeException(
                'V2 seed DCR fingerprint mismatch; expected ' . self::APPROVED_CONTENT_HASH . ', got: ' . $parsed['fingerprint']
            );
        }

        if ($parsed['total_rows'] !== 1116) {
            throw new RuntimeException(
                'V2 seed DCR row count mismatch; expected 1116, got: ' . $parsed['total_rows']
            );
        }

        return $parsed;
    }

    /**
     * @return array{
     *     declared: int,
     *     inserted: int,
     *     existing: int,
     *     students: int,
     *     complete: int,
     *     edge: int
     * }
     */
    public function seed(): array
    {
        // 1. Pure validation of dataset contract before any DB read or DCR check
        LearnerAiSyntheticDatasetV2::validate();

        // 2. Reject externally owned transaction FIRST
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('V2 seed refuses an externally owned transaction.');
        }

        // 3. Runtime DCR approval gate (fails before any DB reads if DCR is not approved)
        $dcr = $this->assertApprovedDcr();

        // 4. Assert connection target, content hash, migrations, exact columns, V1 prerequisites
        $this->assertTarget();
        $this->assertContentHash($dcr['fingerprint']);
        $this->assertCanonicalMigrations();
        $this->assertTouchedTablesAndColumns();
        $this->assertV1Prerequisites();

        $rows = LearnerAiSyntheticDatasetV2::rows();
        if (count($rows) !== 1116) {
            throw new RuntimeException('V2 seed row declaration count is invalid.');
        }

        // 5. Preflight scan of all V2 declared rows
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

        // 6. Reject partial state before beginTransaction()
        if ($existing > 0 && count($missing) > 0) {
            throw new RuntimeException(
                'V2 seed detected a partial state (' . $existing . ' existing, ' . count($missing) . ' missing); transactional V2 state must be either completely absent or completely present.'
            );
        }

        // 7. If everything already exists, return idempotent summary
        if (count($missing) === 0) {
            return [
                'declared' => count($rows),
                'inserted' => 0,
                'existing' => $existing,
                'students' => 24,
                'complete' => 18,
                'edge' => 6,
            ];
        }

        // 8. Insert-only transaction
        $this->pdo->beginTransaction();
        try {
            $inserted = 0;
            foreach ($missing as $row) {
                if ($this->insertIfMissing($row)) {
                    $inserted++;
                } else {
                    $existing++;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'declared' => count($rows),
            'inserted' => $inserted,
            'existing' => $existing,
            'students' => 24,
            'complete' => 18,
            'edge' => 6,
        ];
    }

    private function assertApprovedDcr(): array
    {
        $root = dirname(__DIR__, 4);
        $dcrPath = $root . '/' . self::DCR_RELATIVE_PATH;
        if (!is_file($dcrPath) || !is_readable($dcrPath)) {
            throw new RuntimeException('V2 seed requires approved DCR artifact at: ' . self::DCR_RELATIVE_PATH);
        }
        $content = file_get_contents($dcrPath);
        if ($content === false) {
            throw new RuntimeException('V2 seed could not read DCR artifact: ' . self::DCR_RELATIVE_PATH);
        }

        return self::validateDcr($content, $this->expectedSchema, $this->approvedContentHash);
    }

    private function assertTarget(): void
    {
        if ($this->expectedSchema !== self::APPROVED_SCHEMA
            || preg_match(self::SCHEMA_PATTERN, $this->expectedSchema) !== 1
            || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('V2 seed requires the approved disposable MySQL schema.');
        }

        $actual = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($actual) || !hash_equals($this->expectedSchema, $actual)) {
            throw new RuntimeException('V2 seed connection is not pinned to the approved disposable schema.');
        }

        $timeZone = $this->pdo->query('SELECT @@session.time_zone')->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('V2 seed requires MySQL session time zone +00:00.');
        }
    }

    private function assertContentHash(string $dcrFingerprint): void
    {
        $calculatedHash = LearnerAiSyntheticDatasetV2::contentHash();
        if (!hash_equals(self::APPROVED_CONTENT_HASH, $this->approvedContentHash)
            || !hash_equals($calculatedHash, $this->approvedContentHash)
            || !hash_equals($dcrFingerprint, $this->approvedContentHash)) {
            throw new RuntimeException('V2 seed content hash does not match approved fingerprint.');
        }
    }

    private function assertCanonicalMigrations(): void
    {
        $statement = $this->pdo->prepare(
            'SELECT checksum FROM learner_forward_migrations WHERE version = :version'
        );
        foreach (self::expectedMigrationChecksums() as $version => $checksum) {
            $statement->execute(['version' => $version]);
            $actual = $statement->fetchColumn();
            if (!is_string($actual) || !hash_equals($checksum, $actual)) {
                throw new RuntimeException('V2 seed requires the recorded canonical migration: ' . $version);
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
            if (!is_file($path)) {
                throw new RuntimeException('V2 seed migration source is unavailable: ' . $version);
            }
            $checksums[$version] = LearnerMigrationChecksum::canonical($path);
        }
        return $checksums;
    }

    private function assertTouchedTablesAndColumns(): void
    {
        $declaredColumnsByTable = [];
        foreach (LearnerAiSyntheticDatasetV2::rows() as $row) {
            $table = $row['table'];
            $this->assertIdentifier($table);
            foreach (array_keys($row['values']) as $column) {
                $this->assertIdentifier($column);
                $declaredColumnsByTable[$table][$column] = true;
            }
        }

        $statement = $this->pdo->prepare(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table ORDER BY ordinal_position'
        );

        foreach (LearnerAiSyntheticDatasetV2::touchedTables() as $table) {
            $this->assertIdentifier($table);
            $statement->execute(['schema' => $this->expectedSchema, 'table' => $table]);
            $columns = $statement->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($columns) || count($columns) === 0) {
                throw new RuntimeException('V2 seed touched table does not exist or has empty shape: ' . $table);
            }

            $actualColumns = array_map(static fn($c): string => strtolower(trim((string) $c)), $columns);
            $declaredColumns = array_map(static fn($c): string => strtolower(trim((string) $c)), array_keys($declaredColumnsByTable[$table] ?? []));

            $actualUnique = array_values(array_unique($actualColumns));
            $declaredUnique = array_values(array_unique($declaredColumns));
            sort($actualUnique, SORT_STRING);
            sort($declaredUnique, SORT_STRING);

            if ($actualUnique !== $declaredUnique) {
                $missing = array_diff($declaredUnique, $actualUnique);
                $extra = array_diff($actualUnique, $declaredUnique);
                $details = [];
                if (count($missing) > 0) {
                    $details[] = 'missing declared columns: ' . implode(', ', $missing);
                }
                if (count($extra) > 0) {
                    $details[] = 'unexpected extra columns: ' . implode(', ', $extra);
                }
                throw new RuntimeException(
                    'V2 seed table column contract mismatch for table ' . $table . ' (' . implode('; ', $details) . ').'
                );
            }
        }
    }

    private function assertV1Prerequisites(): void
    {
        $prerequisites = [
            // 1. Roles
            ['table' => 'roles', 'id' => '00000000-0000-4000-8000-000000000001', 'values' => ['id' => '00000000-0000-4000-8000-000000000001', 'code' => 'pilot_learner']],
            ['table' => 'roles', 'id' => '00000000-0000-4000-8000-000000000002', 'values' => ['id' => '00000000-0000-4000-8000-000000000002', 'code' => 'pilot_teacher']],
            // 2. School & Class
            ['table' => 'schools', 'id' => '00000000-0000-4000-8000-000000000010', 'values' => ['id' => '00000000-0000-4000-8000-000000000010', 'name' => 'Synthetic AI Pilot School', 'status' => 'active']],
            ['table' => 'classes', 'id' => '00000000-0000-4000-8000-000000000011', 'values' => ['id' => '00000000-0000-4000-8000-000000000011', 'schoolId' => '00000000-0000-4000-8000-000000000010', 'name' => 'Synthetic AI Pilot 10A', 'status' => 'active']],
            // 3. Teacher user & profile
            ['table' => 'users', 'id' => '00000000-0000-4000-8000-000000000020', 'values' => ['id' => '00000000-0000-4000-8000-000000000020', 'roleId' => '00000000-0000-4000-8000-000000000002', 'email' => 'pilot-teacher@example', 'status' => 'active']],
            ['table' => 'teacher_profiles', 'id' => '00000000-0000-4000-8000-000000000021', 'values' => ['id' => '00000000-0000-4000-8000-000000000021', 'userId' => '00000000-0000-4000-8000-000000000020', 'schoolId' => '00000000-0000-4000-8000-000000000010']],
            // 4. Learner users & profiles 101 & 102
            ['table' => 'users', 'id' => '00000000-0000-4000-8000-000000000101', 'values' => ['id' => '00000000-0000-4000-8000-000000000101', 'roleId' => '00000000-0000-4000-8000-000000000001', 'email' => 'pilot-learner-101@example', 'status' => 'active']],
            ['table' => 'student_profiles', 'id' => '00000000-0000-4000-8000-000000000101', 'values' => ['id' => '00000000-0000-4000-8000-000000000101', 'userId' => '00000000-0000-4000-8000-000000000101', 'classId' => '00000000-0000-4000-8000-000000000011', 'studyStatus' => 'active']],
            ['table' => 'users', 'id' => '00000000-0000-4000-8000-000000000102', 'values' => ['id' => '00000000-0000-4000-8000-000000000102', 'roleId' => '00000000-0000-4000-8000-000000000001', 'email' => 'pilot-learner-102@example', 'status' => 'active']],
            ['table' => 'student_profiles', 'id' => '00000000-0000-4000-8000-000000000102', 'values' => ['id' => '00000000-0000-4000-8000-000000000102', 'userId' => '00000000-0000-4000-8000-000000000102', 'classId' => '00000000-0000-4000-8000-000000000011', 'studyStatus' => 'active']],
            // 5. Holland test & R1, I1, A1 questions
            ['table' => 'talent_tests', 'id' => '00000000-0000-4000-8000-000000000060', 'values' => ['id' => '00000000-0000-4000-8000-000000000060', 'code' => 'holland', 'status' => 'published']],
            ['table' => 'test_questions', 'id' => '00000000-0000-4000-8000-000000000061', 'values' => ['id' => '00000000-0000-4000-8000-000000000061', 'testId' => '00000000-0000-4000-8000-000000000060', 'code' => 'R1', 'status' => 'published']],
            ['table' => 'test_questions', 'id' => '00000000-0000-4000-8000-000000000062', 'values' => ['id' => '00000000-0000-4000-8000-000000000062', 'testId' => '00000000-0000-4000-8000-000000000060', 'code' => 'I1', 'status' => 'published']],
            ['table' => 'test_questions', 'id' => '00000000-0000-4000-8000-000000000063', 'values' => ['id' => '00000000-0000-4000-8000-000000000063', 'testId' => '00000000-0000-4000-8000-000000000060', 'code' => 'A1', 'status' => 'published']],
            // 6. Skills IoT and Python
            ['table' => 'skills', 'id' => '00000000-0000-4000-8000-000000000050', 'values' => ['id' => '00000000-0000-4000-8000-000000000050', 'code' => 'iot', 'name' => 'IoT Fundamentals', 'category' => 'technology', 'status' => 'active']],
            ['table' => 'skills', 'id' => '00000000-0000-4000-8000-000000000051', 'values' => ['id' => '00000000-0000-4000-8000-000000000051', 'code' => 'python', 'name' => 'Python Fundamentals', 'category' => 'technology', 'status' => 'active']],
            // 7. Activity & QR token
            ['table' => 'activities', 'id' => '00000000-0000-4000-8000-000000000030', 'values' => ['id' => '00000000-0000-4000-8000-000000000030', 'schoolId' => '00000000-0000-4000-8000-000000000010', 'createdByTeacherId' => '00000000-0000-4000-8000-000000000021', 'title' => 'Synthetic Technical Workshop', 'status' => 'published']],
            ['table' => 'activity_qr_tokens', 'id' => '00000000-0000-4000-8000-000000000031', 'values' => ['id' => '00000000-0000-4000-8000-000000000031', 'activityId' => '00000000-0000-4000-8000-000000000030', 'status' => 'active']],
            // 8. Presentation criterion
            ['table' => 'assessment_criteria', 'id' => '00000000-0000-4000-8000-000000000040', 'values' => ['id' => '00000000-0000-4000-8000-000000000040', 'code' => 'presentation', 'name' => 'Presentation', 'status' => 'active']],
        ];

        foreach ($prerequisites as $prereq) {
            $actual = $this->findById($prereq['table'], $prereq['id']);
            if ($actual === null) {
                throw new RuntimeException(
                    'V2 seed missing required V1 prerequisite row: ' . $prereq['table'] . '.' . $prereq['id']
                );
            }
            $this->assertSameRow($prereq, $actual);
        }
    }

    /** @param array{table:string,id:string,values:array<string,scalar|null>} $row */
    private function insertIfMissing(array $row): bool
    {
        $columns = array_keys($row['values']);
        $this->assertIdentifier($row['table']);
        foreach ($columns as $column) {
            $this->assertIdentifier($column);
        }

        $select = [];
        $parameters = ['present_id' => $row['id']];
        foreach ($columns as $index => $column) {
            $placeholder = 'value_' . $index;
            $select[] = ':' . $placeholder;
            $parameters[$placeholder] = $row['values'][$column];
        }

        $sql = 'INSERT INTO ' . $row['table']
            . ' (' . implode(', ', $columns) . ') '
            . 'SELECT ' . implode(', ', $select) . ' '
            . 'WHERE NOT EXISTS (SELECT 1 FROM ' . $row['table'] . ' WHERE id = :present_id)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        if ($statement->rowCount() === 1) {
            return true;
        }

        $actual = $this->findById($row['table'], $row['id']);
        if ($actual === null) {
            throw new RuntimeException('V2 seed could not insert or verify declared row: ' . $row['table'] . '.' . $row['id']);
        }
        $this->assertSameRow($row, $actual);
        return false;
    }

    /** @return array<string,mixed>|null */
    private function findById(string $table, string $id): ?array
    {
        $this->assertIdentifier($table);
        $statement = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array{table:string,id:string,values:array<string,scalar|null>} $expected
     * @param array<string,mixed> $actual
     */
    private function assertSameRow(array $expected, array $actual): void
    {
        foreach ($expected['values'] as $column => $expectedValue) {
            if (!array_key_exists($column, $actual)) {
                throw new RuntimeException(
                    'V2 seed row missing column: ' . $expected['table'] . '.' . $expected['id'] . '.' . $column
                );
            }
            if (!self::valuesMatch($expectedValue, $actual[$column])) {
                throw new RuntimeException(
                    'V2 seed reserved row conflicts with declared content: '
                    . $expected['table'] . '.' . $expected['id'] . '.' . $column
                );
            }
        }
    }

    private static function valuesMatch(mixed $expected, mixed $actual): bool
    {
        if ($expected === null) {
            return $actual === null;
        }
        if ($actual === null) {
            return false;
        }

        if (is_bool($expected) || is_bool($actual)) {
            $expectedBool = ($expected === true || $expected === 1 || $expected === '1');
            $actualBool = ($actual === true || $actual === 1 || $actual === '1');
            return $expectedBool === $actualBool;
        }

        if ($expected === $actual || (string) $expected === (string) $actual) {
            return true;
        }

        $expectedStr = (string) $expected;
        $actualStr = (string) $actual;

        if ((str_starts_with($expectedStr, '{') && str_ends_with($expectedStr, '}'))
            || (str_starts_with($expectedStr, '[') && str_ends_with($expectedStr, ']'))) {
            try {
                $expectedDecoded = json_decode($expectedStr, true, 512, JSON_THROW_ON_ERROR);
                $actualDecoded = json_decode($actualStr, true, 512, JSON_THROW_ON_ERROR);
                return self::canonicalizeJsonValues($expectedDecoded) === self::canonicalizeJsonValues($actualDecoded);
            } catch (\Throwable) {
                // fall through
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/', $expectedStr) === 1) {
            $expectedDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $expectedStr, new DateTimeZone('UTC'));
            $actualDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $actualStr, new DateTimeZone('UTC'))
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $actualStr, new DateTimeZone('UTC'));
            if ($expectedDt !== false && $actualDt !== false) {
                return $expectedDt->format('Y-m-d H:i:s.u') === $actualDt->format('Y-m-d H:i:s.u');
            }
        }

        if (is_numeric($expected) && is_numeric($actual)) {
            $hasLeadingZero = preg_match('/^[+-]?0\d+/', $expectedStr) === 1 || preg_match('/^[+-]?0\d+/', $actualStr) === 1;
            if (!$hasLeadingZero && preg_match('/^-?\d+(\.\d+)?$/', $expectedStr) === 1 && preg_match('/^-?\d+(\.\d+)?$/', $actualStr) === 1) {
                return bccomp(sprintf('%.6f', (float) $expectedStr), sprintf('%.6f', (float) $actualStr), 6) === 0;
            }
        }

        return false;
    }

    private static function canonicalizeJsonValues(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalizeJsonValues(...), $value);
        }
        ksort($value, SORT_STRING);
        return array_map(self::canonicalizeJsonValues(...), $value);
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException('V2 seed received an unsafe identifier.');
        }
    }
}
