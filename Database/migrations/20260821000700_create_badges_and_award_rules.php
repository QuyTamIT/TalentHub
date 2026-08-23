<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create badges catalog, versioned award rules, and student badge awards';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 9 badges migration requires MySQL session time zone +00:00.');
        }

        $serverVersion = (string) $context->pdo()->query('SELECT VERSION()')?->fetchColumn();
        if (preg_match('/^(\d+)/', $serverVersion, $matches) !== 1 || (int) $matches[1] < 8) {
            throw new RuntimeException('Phase 9 badges migration requires MySQL 8 or newer.');
        }

        foreach ([
            'student_profiles',
            'users',
            'notifications',
            'learner_notification_preferences',
            'experience_logs',
            'activities',
            'activity_registrations',
            'checkins',
            'talent_tests',
            'test_attempts',
            'test_results',
            'assessments',
        ] as $table) {
            $context->assertTableExists($table);
        }

        $allTargetsExist = $this->assertExactTargetTableSet($context);
        if ($allTargetsExist) {
            $this->verifyContracts($context, false);
            $this->assertCatalogConflicts($context);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$context->tableExists('badges')) {
            $context->execute(<<<'SQL'
                CREATE TABLE badges (
                    id CHAR(36) NOT NULL,
                    code VARCHAR(64) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    category VARCHAR(64) NOT NULL,
                    description TEXT NOT NULL,
                    iconUrl VARCHAR(500) NULL,
                    level INT NOT NULL DEFAULT 1,
                    status VARCHAR(32) NOT NULL DEFAULT 'active',
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_badges_code (code),
                    CONSTRAINT chk_badges_status CHECK (status IN ('active', 'inactive', 'deprecated')),
                    CONSTRAINT chk_badges_level CHECK (level >= 1)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$context->tableExists('badge_rule_definitions')) {
            $context->execute(<<<'SQL'
                CREATE TABLE badge_rule_definitions (
                    id CHAR(36) NOT NULL,
                    badgeId CHAR(36) NOT NULL,
                    ruleType VARCHAR(64) NOT NULL DEFAULT 'threshold',
                    thresholdCriteria JSON NOT NULL,
                    version INT NOT NULL DEFAULT 1,
                    isActive TINYINT(1) NOT NULL DEFAULT 1,
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_badge_rules_badge_version (badgeId, version),
                    KEY idx_badge_rules_active (isActive, badgeId, version),
                    CONSTRAINT fk_badge_rule_definitions_badge
                        FOREIGN KEY (badgeId) REFERENCES badges (id)
                        ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT chk_badge_rules_type CHECK (ruleType IN ('threshold')),
                    CONSTRAINT chk_badge_rules_version CHECK (version >= 1),
                    CONSTRAINT chk_badge_rules_is_active CHECK (isActive IN (0, 1))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$context->tableExists('student_badges')) {
            $context->execute(<<<'SQL'
                CREATE TABLE student_badges (
                    id CHAR(36) NOT NULL,
                    studentId CHAR(36) NOT NULL,
                    badgeId CHAR(36) NOT NULL,
                    ruleDefinitionId CHAR(36) NOT NULL,
                    awardedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    awardedBy VARCHAR(64) NOT NULL DEFAULT 'system',
                    awardContext JSON NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_student_badges_award (studentId, badgeId),
                    KEY idx_student_badges_badge (badgeId),
                    KEY idx_student_badges_rule (ruleDefinitionId),
                    KEY idx_student_badges_student_awarded (studentId, awardedAt),
                    CONSTRAINT fk_student_badges_student
                        FOREIGN KEY (studentId) REFERENCES student_profiles (id)
                        ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT fk_student_badges_badge
                        FOREIGN KEY (badgeId) REFERENCES badges (id)
                        ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT fk_student_badges_rule
                        FOREIGN KEY (ruleDefinitionId) REFERENCES badge_rule_definitions (id)
                        ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT chk_student_badges_awarded_by CHECK (awardedBy IN ('system', 'teacher', 'school_admin'))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        $this->assertExactTargetTableSet($context);
        $this->assertCatalogConflicts($context);
        $this->seedCatalogAndRules($context);
        $this->verifyContracts($context, true);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only additive schema: learner badge awards must be protected.
    }

    /** @return list<array{badgeId:string,code:string,name:string,category:string,description:string,level:int,ruleId:string,criteria:string}> */
    private function canonicalCatalog(): array
    {
        return [
            [
                'badgeId' => 'a1000000-0000-4000-8000-000000000001',
                'code' => 'first_experience',
                'name' => 'Khởi đầu trải nghiệm',
                'category' => 'experience',
                'description' => 'Hoàn thành ít nhất 1 giờ trải nghiệm thực tế được xác nhận.',
                'level' => 1,
                'ruleId' => 'b1000000-0000-4000-8000-000000000001',
                'criteria' => json_encode(['fact' => 'confirmed_experience_hours', 'operator' => 'gte', 'value' => 1], JSON_THROW_ON_ERROR),
            ],
            [
                'badgeId' => 'a1000000-0000-4000-8000-000000000002',
                'code' => 'experience_10h',
                'name' => 'Hành trình tích lũy',
                'category' => 'experience',
                'description' => 'Đạt mốc 10 giờ trải nghiệm thực tế được xác nhận.',
                'level' => 2,
                'ruleId' => 'b1000000-0000-4000-8000-000000000002',
                'criteria' => json_encode(['fact' => 'confirmed_experience_hours', 'operator' => 'gte', 'value' => 10], JSON_THROW_ON_ERROR),
            ],
            [
                'badgeId' => 'a1000000-0000-4000-8000-000000000003',
                'code' => 'active_participant',
                'name' => 'Thành viên năng nổ',
                'category' => 'activity',
                'description' => 'Tham gia tích cực và hoàn thành ít nhất 3 hoạt động.',
                'level' => 1,
                'ruleId' => 'b1000000-0000-4000-8000-000000000003',
                'criteria' => json_encode(['fact' => 'attended_activity_count', 'operator' => 'gte', 'value' => 3], JSON_THROW_ON_ERROR),
            ],
            [
                'badgeId' => 'a1000000-0000-4000-8000-000000000004',
                'code' => 'assessment_explorer',
                'name' => 'Khám phá năng lực',
                'category' => 'assessment',
                'description' => 'Hoàn thành ít nhất 2 bài đánh giá năng lực thuộc các loại khác nhau.',
                'level' => 1,
                'ruleId' => 'b1000000-0000-4000-8000-000000000004',
                'criteria' => json_encode(['fact' => 'submitted_assessment_type_count', 'operator' => 'gte', 'value' => 2], JSON_THROW_ON_ERROR),
            ],
            [
                'badgeId' => 'a1000000-0000-4000-8000-000000000005',
                'code' => 'teacher_recognition',
                'name' => 'Ghi nhận từ giáo viên',
                'category' => 'evaluation',
                'description' => 'Nhận ít nhất 1 đánh giá chính thức được công bố từ giáo viên.',
                'level' => 1,
                'ruleId' => 'b1000000-0000-4000-8000-000000000005',
                'criteria' => json_encode(['fact' => 'published_teacher_evaluation_count', 'operator' => 'gte', 'value' => 1], JSON_THROW_ON_ERROR),
            ],
        ];
    }

    private function seedCatalogAndRules(MigrationContext $context): void
    {
        $catalog = $this->canonicalCatalog();

        $insertBadge = $context->pdo()->prepare(<<<'SQL'
            INSERT INTO badges (id, code, name, category, description, iconUrl, level, status)
            VALUES (:id, :code, :name, :category, :description, NULL, :level, 'active')
        SQL);

        $insertRule = $context->pdo()->prepare(<<<'SQL'
            INSERT INTO badge_rule_definitions (id, badgeId, ruleType, thresholdCriteria, version, isActive)
            VALUES (:id, :badgeId, 'threshold', :criteria, 1, 1)
        SQL);

        $findBadge = $context->pdo()->prepare('SELECT id FROM badges WHERE id = :id');
        $findRule = $context->pdo()->prepare('SELECT id FROM badge_rule_definitions WHERE id = :id');

        foreach ($catalog as $item) {
            $findBadge->execute(['id' => $item['badgeId']]);
            if ($findBadge->fetchColumn() === false) {
                $insertBadge->execute([
                    'id' => $item['badgeId'],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'level' => $item['level'],
                ]);
            }

            $findRule->execute(['id' => $item['ruleId']]);
            if ($findRule->fetchColumn() === false) {
                $insertRule->execute([
                    'id' => $item['ruleId'],
                    'badgeId' => $item['badgeId'],
                    'criteria' => $item['criteria'],
                ]);
            }
        }
    }

    private function verifyContracts(MigrationContext $context, bool $requireCatalog): void
    {
        $this->assertExistingBadgesContract($context);
        $this->assertExistingBadgeRulesContract($context);
        $this->assertExistingStudentBadgesContract($context);

        if ($requireCatalog) {
            $this->assertCatalogConflicts($context, true);
        }
    }

    private function assertExistingBadgesContract(MigrationContext $context): void
    {
        $this->assertTableOptions($context, 'badges');
        $this->assertColumns($context, 'badges', [
            'id' => ['char(36)', 'NO', null, ''],
            'code' => ['varchar(64)', 'NO', null, ''],
            'name' => ['varchar(255)', 'NO', null, ''],
            'category' => ['varchar(64)', 'NO', null, ''],
            'description' => ['text', 'NO', null, ''],
            'iconUrl' => ['varchar(500)', 'YES', null, ''],
            'level' => ['int', 'NO', '1', ''],
            'status' => ['varchar(32)', 'NO', 'active', ''],
            'createdAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED'],
            'updatedAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6)'],
        ]);
        $this->assertIndexes($context, 'badges', [
            'PRIMARY' => [true, ['id']],
            'uq_badges_code' => [true, ['code']],
        ]);
        $this->assertForeignKeys($context, 'badges', []);
        $this->assertCheckConstraintNames($context, 'badges', ['chk_badges_level', 'chk_badges_status']);
        $this->assertCheckConstraint($context, 'badges', 'chk_badges_status', 'statusin(active,inactive,deprecated)');
        $this->assertCheckConstraint($context, 'badges', 'chk_badges_level', 'level>=1');
    }

    private function assertExistingBadgeRulesContract(MigrationContext $context): void
    {
        $this->assertTableOptions($context, 'badge_rule_definitions');
        $this->assertColumns($context, 'badge_rule_definitions', [
            'id' => ['char(36)', 'NO', null, ''],
            'badgeId' => ['char(36)', 'NO', null, ''],
            'ruleType' => ['varchar(64)', 'NO', 'threshold', ''],
            'thresholdCriteria' => ['json', 'NO', null, ''],
            'version' => ['int', 'NO', '1', ''],
            'isActive' => ['tinyint(1)', 'NO', '1', ''],
            'createdAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED'],
            'updatedAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6)'],
        ]);
        $this->assertIndexes($context, 'badge_rule_definitions', [
            'PRIMARY' => [true, ['id']],
            'idx_badge_rules_active' => [false, ['isActive', 'badgeId', 'version']],
            'uq_badge_rules_badge_version' => [true, ['badgeId', 'version']],
        ]);
        $this->assertForeignKeys($context, 'badge_rule_definitions', [
            'fk_badge_rule_definitions_badge' => ['badgeId', 'badges', 'id', 'RESTRICT', 'CASCADE'],
        ]);
        $this->assertCheckConstraintNames($context, 'badge_rule_definitions', [
            'chk_badge_rules_is_active',
            'chk_badge_rules_type',
            'chk_badge_rules_version',
        ]);
        $this->assertCheckConstraint($context, 'badge_rule_definitions', 'chk_badge_rules_type', 'ruletype=threshold');
        $this->assertCheckConstraint($context, 'badge_rule_definitions', 'chk_badge_rules_version', 'version>=1');
        $this->assertCheckConstraint($context, 'badge_rule_definitions', 'chk_badge_rules_is_active', 'isactivein(0,1)');
    }

    private function assertExistingStudentBadgesContract(MigrationContext $context): void
    {
        $this->assertTableOptions($context, 'student_badges');
        $this->assertColumns($context, 'student_badges', [
            'id' => ['char(36)', 'NO', null, ''],
            'studentId' => ['char(36)', 'NO', null, ''],
            'badgeId' => ['char(36)', 'NO', null, ''],
            'ruleDefinitionId' => ['char(36)', 'NO', null, ''],
            'awardedAt' => ['datetime(6)', 'NO', 'CURRENT_TIMESTAMP(6)', 'DEFAULT_GENERATED'],
            'awardedBy' => ['varchar(64)', 'NO', 'system', ''],
            'awardContext' => ['json', 'NO', null, ''],
        ]);
        $this->assertIndexes($context, 'student_badges', [
            'PRIMARY' => [true, ['id']],
            'idx_student_badges_badge' => [false, ['badgeId']],
            'idx_student_badges_rule' => [false, ['ruleDefinitionId']],
            'idx_student_badges_student_awarded' => [false, ['studentId', 'awardedAt']],
            'uq_student_badges_award' => [true, ['studentId', 'badgeId']],
        ]);
        $this->assertForeignKeys($context, 'student_badges', [
            'fk_student_badges_badge' => ['badgeId', 'badges', 'id', 'RESTRICT', 'CASCADE'],
            'fk_student_badges_rule' => ['ruleDefinitionId', 'badge_rule_definitions', 'id', 'RESTRICT', 'CASCADE'],
            'fk_student_badges_student' => ['studentId', 'student_profiles', 'id', 'RESTRICT', 'CASCADE'],
        ]);
        $this->assertCheckConstraintNames($context, 'student_badges', ['chk_student_badges_awarded_by']);
        $this->assertCheckConstraint($context, 'student_badges', 'chk_student_badges_awarded_by', 'awardedbyin(system,teacher,school_admin)');
    }

    private function assertExactTargetTableSet(MigrationContext $context): bool
    {
        $targets = ['badges', 'badge_rule_definitions', 'student_badges'];
        $existing = array_values(array_filter($targets, static fn (string $table): bool => $context->tableExists($table)));
        if ($existing === []) {
            return false;
        }
        if (count($existing) !== count($targets)) {
            throw new RuntimeException('Phase 9 target tables are in a partial state: ' . implode(', ', $existing) . '.');
        }

        return true;
    }

    private function assertCatalogConflicts(MigrationContext $context, bool $requireAll = false): void
    {
        $badgeQuery = $context->pdo()->prepare(<<<'SQL'
            SELECT id, code, name, category, description, iconUrl, level, status
            FROM badges
            WHERE id = :id OR code = :code
            ORDER BY id
        SQL);
        $ruleQuery = $context->pdo()->prepare(<<<'SQL'
            SELECT id, badgeId, ruleType, thresholdCriteria, version, isActive
            FROM badge_rule_definitions
            WHERE id = :id OR (badgeId = :badgeId AND version = 1)
            ORDER BY id
        SQL);

        foreach ($this->canonicalCatalog() as $item) {
            $badgeQuery->execute(['id' => $item['badgeId'], 'code' => $item['code']]);
            $badges = $badgeQuery->fetchAll(PDO::FETCH_ASSOC);
            if ($requireAll && count($badges) !== 1) {
                throw new RuntimeException("Canonical badge {$item['code']} is missing after Phase 9 migration.");
            }
            if (count($badges) > 1) {
                throw new RuntimeException("Canonical badge {$item['code']} conflicts by id/code.");
            }
            if (count($badges) === 1) {
                $badge = array_change_key_case($badges[0], CASE_LOWER);
                $matches = (string) $badge['id'] === $item['badgeId']
                    && (string) $badge['code'] === $item['code']
                    && (string) $badge['name'] === $item['name']
                    && (string) $badge['category'] === $item['category']
                    && (string) $badge['description'] === $item['description']
                    && $badge['iconurl'] === null
                    && (int) $badge['level'] === $item['level']
                    && (string) $badge['status'] === 'active';
                if (!$matches) {
                    throw new RuntimeException("Canonical badge {$item['code']} has conflicting metadata.");
                }
            }

            $ruleQuery->execute(['id' => $item['ruleId'], 'badgeId' => $item['badgeId']]);
            $rules = $ruleQuery->fetchAll(PDO::FETCH_ASSOC);
            if ($requireAll && count($rules) !== 1) {
                throw new RuntimeException("Canonical rule {$item['ruleId']} is missing after Phase 9 migration.");
            }
            if (count($rules) > 1) {
                throw new RuntimeException("Canonical rule {$item['ruleId']} conflicts by id/badge/version.");
            }
            if (count($rules) === 1) {
                $rule = array_change_key_case($rules[0], CASE_LOWER);
                $actualCriteria = json_decode((string) $rule['thresholdcriteria'], true, 512, JSON_THROW_ON_ERROR);
                $expectedCriteria = json_decode($item['criteria'], true, 512, JSON_THROW_ON_ERROR);
                $matches = (string) $rule['id'] === $item['ruleId']
                    && (string) $rule['badgeid'] === $item['badgeId']
                    && (string) $rule['ruletype'] === 'threshold'
                    && $actualCriteria == $expectedCriteria
                    && (int) $rule['version'] === 1
                    && (int) $rule['isactive'] === 1;
                if (!$matches) {
                    throw new RuntimeException("Canonical rule {$item['ruleId']} has conflicting metadata.");
                }
            }
        }
    }

    private function assertTableOptions(MigrationContext $context, string $table): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT engine, table_collation
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :table AND table_type = 'BASE TABLE'
        SQL);
        $statement->execute(['table' => $table]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $row = is_array($row) ? array_change_key_case($row, CASE_LOWER) : null;
        if (!is_array($row)
            || strtoupper((string) $row['engine']) !== 'INNODB'
            || (string) $row['table_collation'] !== 'utf8mb4_unicode_ci'
        ) {
            throw new RuntimeException("{$table} has unexpected engine or collation metadata.");
        }
    }

    /** @param array<string, array{0:string,1:string,2:?string,3:string}> $expected */
    private function assertColumns(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name, column_type, is_nullable, column_default, extra
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY ordinal_position
        SQL);
        $statement->execute(['table' => $table]);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $actual[(string) $row['column_name']] = [
                strtolower((string) $row['column_type']),
                (string) $row['is_nullable'],
                $row['column_default'] === null ? null : (string) $row['column_default'],
                (string) $row['extra'],
            ];
        }
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact column metadata.");
        }
    }

    /** @param array<string, array{0:bool,1:list<string>}> $expected */
    private function assertIndexes(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT index_name, non_unique, column_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY index_name, seq_in_index
        SQL);
        $statement->execute(['table' => $table]);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $name = (string) $row['index_name'];
            if (!isset($actual[$name])) {
                $actual[$name] = [(int) $row['non_unique'] === 0, []];
            }
            $actual[$name][1][] = (string) $row['column_name'];
        }
        ksort($actual);
        ksort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact index metadata.");
        }
    }

    /** @param array<string, array{0:string,1:string,2:string,3:string,4:string}> $expected */
    private function assertForeignKeys(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT rc.constraint_name, kcu.column_name, kcu.referenced_table_name,
                   kcu.referenced_column_name, rc.delete_rule, rc.update_rule
            FROM information_schema.referential_constraints rc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_schema = rc.constraint_schema
               AND kcu.table_name = rc.table_name
               AND kcu.constraint_name = rc.constraint_name
            WHERE rc.constraint_schema = DATABASE() AND rc.table_name = :table
            ORDER BY rc.constraint_name, kcu.ordinal_position
        SQL);
        $statement->execute(['table' => $table]);
        $actual = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $actual[(string) $row['constraint_name']] = [
                (string) $row['column_name'],
                (string) $row['referenced_table_name'],
                (string) $row['referenced_column_name'],
                (string) $row['delete_rule'],
                (string) $row['update_rule'],
            ];
        }
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact foreign-key metadata.");
        }
    }

    private function assertForeignKey(MigrationContext $context, string $table, string $constraint, array $expected): void
    {
        $this->assertForeignKeys($context, $table, [$constraint => $expected]);
    }

    /** @param list<string> $expected */
    private function assertCheckConstraintNames(MigrationContext $context, string $table, array $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT tc.constraint_name
            FROM information_schema.table_constraints tc
            WHERE tc.constraint_schema = DATABASE()
              AND tc.table_name = :table
              AND tc.constraint_type = 'CHECK'
            ORDER BY tc.constraint_name
        SQL);
        $statement->execute(['table' => $table]);
        $actual = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        sort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException("{$table} has unexpected exact CHECK constraint names.");
        }
    }

    private function assertCheckConstraint(MigrationContext $context, string $table, string $constraint, string $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT cc.check_clause
            FROM information_schema.check_constraints cc
            INNER JOIN information_schema.table_constraints tc
                ON tc.constraint_schema = cc.constraint_schema
               AND tc.constraint_name = cc.constraint_name
            WHERE tc.constraint_schema = DATABASE()
              AND tc.table_name = :table
              AND tc.constraint_name = :constraint_name
              AND tc.constraint_type = 'CHECK'
        SQL);
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        $clause = $statement->fetchColumn();
        if (!is_string($clause) || $this->normalizeCheckConstraint($clause) !== $expected) {
            throw new RuntimeException("{$table}.{$constraint} has unexpected CHECK semantics.");
        }
    }

    private function normalizeCheckConstraint(string $clause): string
    {
        $normalized = strtolower($clause);
        $normalized = preg_replace('/_(?:utf8mb4|utf8mb3|cp850)/', '', $normalized) ?? $normalized;
        $normalized = str_replace(['`', "'", '\\', ' '], '', $normalized);
        while (str_starts_with($normalized, '(') && str_ends_with($normalized, ')')) {
            $normalized = substr($normalized, 1, -1);
        }

        return $normalized;
    }
};
