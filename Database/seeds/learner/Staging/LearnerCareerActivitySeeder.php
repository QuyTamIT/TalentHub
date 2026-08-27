<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Staging;

use PDO;
use RuntimeException;
use TalentHub\Database\ProtectedDatabasePolicy;

require_once dirname(__DIR__, 4) . '/src/Database/ProtectedDatabasePolicy.php';

final class LearnerCareerActivitySeeder
{
    private const SCHEMA_PATTERN = '/^talenthub_ai_(?:backup_verify|career_group_verify)_[A-Za-z0-9_]+$/';
    private const RESERVED_PREFIX = '00000000-0000-4000-8000-00000000030';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $expectedSchema,
        private readonly string $schoolId,
        private readonly string $teacherProfileId,
        private readonly bool $allowMainSchema = false,
    ) {
    }

    public static function reservedPrefix(): string
    {
        return self::RESERVED_PREFIX;
    }

    /** @return array{declared:int, inserted:int, existing:int} */
    public function seed(): array
    {
        $this->assertDisposableConnection();
        $this->assertParentRows();
        $this->assertActivitySchema();

        $rows = $this->declaredRows();
        $missing = [];
        $existing = 0;

        foreach ($rows as $row) {
            $actual = $this->findById('activities', $row['id']);
            if ($actual === null) {
                $missing[] = $row;
                continue;
            }
            $this->assertSameRow($row, $actual);
            $existing++;
        }

        if ($missing === []) {
            return [
                'declared' => count($rows),
                'inserted' => 0,
                'existing' => $existing,
            ];
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
        $isApprovedMainSchema = ProtectedDatabasePolicy::allowsExplicitPrimaryWrite(
            $this->expectedSchema,
            $this->allowMainSchema,
        );
        $isDisposableSchema = preg_match(self::SCHEMA_PATTERN, $this->expectedSchema) === 1;
        if (!$isApprovedMainSchema && !$isDisposableSchema) {
            throw new RuntimeException(
                'Career activity seed requires an approved disposable schema or explicit talenthub primary opt-in; '
                . 'talenthub_local is read-only.'
            );
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $actual = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($actual) || $actual !== $this->expectedSchema) {
                throw new RuntimeException('Career activity seed connection is not pinned to the approved disposable schema.');
            }
        }
    }

    private function assertParentRows(): void
    {
        // 1. Verify school exists and is active
        $statement = $this->pdo->prepare('SELECT status FROM schools WHERE id = :schoolId');
        $statement->execute(['schoolId' => $this->schoolId]);
        $schoolStatus = $statement->fetchColumn();
        if ($schoolStatus === false || $schoolStatus !== 'active') {
            throw new RuntimeException('Career activity seed requires an existing active school: ' . $this->schoolId);
        }

        // 2. Verify teacher profile exists and belongs to the school
        $statement = $this->pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id = :teacherId');
        $statement->execute(['teacherId' => $this->teacherProfileId]);
        $teacherSchoolId = $statement->fetchColumn();
        if ($teacherSchoolId === false || $teacherSchoolId !== $this->schoolId) {
            throw new RuntimeException('Career activity seed requires an existing teacher profile matching the school: ' . $this->teacherProfileId);
        }
    }

    private function assertActivitySchema(): void
    {
        $required = ['id', 'schoolId', 'createdByTeacherId', 'title', 'category', 'startAt', 'capacity', 'status'];
        $columns = $this->columnsFor('activities');
        if (array_diff($required, $columns) !== []) {
            throw new RuntimeException('Career activity seed table schema is missing required columns.');
        }
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = match ($driver) {
                'sqlite' => "PRAGMA table_info({$table})",
                'mysql' => "SHOW COLUMNS FROM `{$table}`",
                default => null,
            };
            if ($sql === null) {
                return [];
            }
            $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? $row['Field'] ?? null;
            if (is_string($name)) {
                $columns[] = $name;
            }
        }
        return $columns;
    }

    /** @param array{table:string,id:string,values:array<string,scalar|null>} $row */
    private function insertIfMissing(array $row): bool
    {
        $columns = array_keys($row['values']);
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
            throw new RuntimeException('Career activity seed could not insert or verify declared row: ' . $row['table'] . '.' . $row['id']);
        }
        $this->assertSameRow($row, $actual);
        return false;
    }

    /** @return array<string,mixed>|null */
    private function findById(string $table, string $id): ?array
    {
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
        foreach ($expected['values'] as $column => $value) {
            if (!array_key_exists($column, $actual)
                || self::normalized($actual[$column]) !== self::normalized($value)) {
                throw new RuntimeException(
                    'Career activity seed reserved row conflicts with declared content: '
                    . $expected['table'] . '.' . $expected['id'] . '.' . $column
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
        return trim((string) $value);
    }

    /** @return list<array{table:string,id:string,values:array<string,scalar|null>}> */
    public function declaredRows(): array
    {
        $created = '2026-08-18 00:00:00.000000';
        $updated = '2026-08-18 00:00:00.000000';

        $items = [
            // 1. Technical - CLB
            [
                'id' => '00000000-0000-4000-8000-000000000301',
                'title' => 'CLB Sáng tạo Robot & IoT',
                'category' => 'career_technical',
                'startAt' => '2026-09-01 08:00:00.000000',
                'endAt' => '2026-12-31 17:00:00.000000',
                'capacity' => 30,
            ],
            // 2. Technical - Workshop
            [
                'id' => '00000000-0000-4000-8000-000000000302',
                'title' => 'Workshop Lập trình Python Ứng dụng',
                'category' => 'career_technical',
                'startAt' => '2026-09-15 09:00:00.000000',
                'endAt' => '2026-09-15 17:00:00.000000',
                'capacity' => 25,
            ],
            // 3. Business - CLB
            [
                'id' => '00000000-0000-4000-8000-000000000303',
                'title' => 'CLB Nhà lãnh đạo & Khởi nghiệp Trẻ',
                'category' => 'career_business',
                'startAt' => '2026-09-01 08:00:00.000000',
                'endAt' => '2026-12-31 17:00:00.000000',
                'capacity' => 30,
            ],
            // 4. Business - Project/Workshop
            [
                'id' => '00000000-0000-4000-8000-000000000304',
                'title' => 'Dự án Mô phỏng Kinh doanh & Tài chính Cá nhân',
                'category' => 'career_business',
                'startAt' => '2026-09-20 08:30:00.000000',
                'endAt' => '2026-10-20 17:00:00.000000',
                'capacity' => 20,
            ],
            // 5. Arts - CLB
            [
                'id' => '00000000-0000-4000-8000-000000000305',
                'title' => 'CLB Mỹ thuật Sáng tạo & Thiết kế Đồ họa',
                'category' => 'career_arts',
                'startAt' => '2026-09-01 08:00:00.000000',
                'endAt' => '2026-12-31 17:00:00.000000',
                'capacity' => 25,
            ],
            // 6. Arts - Workshop
            [
                'id' => '00000000-0000-4000-8000-000000000306',
                'title' => 'Workshop Kể chuyện Thị giác & Nhiếp ảnh Số',
                'category' => 'career_arts',
                'startAt' => '2026-09-25 13:30:00.000000',
                'endAt' => '2026-09-25 17:30:00.000000',
                'capacity' => 20,
            ],
            // 7. Sports & Academic - CLB
            [
                'id' => '00000000-0000-4000-8000-000000000307',
                'title' => 'CLB Thể thao & Rèn luyện Thể chất Năng động',
                'category' => 'career_sports_academic',
                'startAt' => '2026-09-01 08:00:00.000000',
                'endAt' => '2026-12-31 17:00:00.000000',
                'capacity' => 35,
            ],
            // 8. Sports & Academic - Workshop/Project
            [
                'id' => '00000000-0000-4000-8000-000000000308',
                'title' => 'Dự án Nghiên cứu Học thuật & Tranh biện Khoa học',
                'category' => 'career_sports_academic',
                'startAt' => '2026-10-01 08:00:00.000000',
                'endAt' => '2026-11-15 17:00:00.000000',
                'capacity' => 20,
            ],
        ];

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'table' => 'activities',
                'id' => $item['id'],
                'values' => [
                    'id' => $item['id'],
                    'schoolId' => $this->schoolId,
                    'createdByTeacherId' => $this->teacherProfileId,
                    'title' => $item['title'],
                    'category' => $item['category'],
                    'startAt' => $item['startAt'],
                    'endAt' => $item['endAt'],
                    'capacity' => $item['capacity'],
                    'status' => 'published',
                    'createdAt' => $created,
                    'updatedAt' => $updated,
                ],
            ];
        }

        return $rows;
    }
}
