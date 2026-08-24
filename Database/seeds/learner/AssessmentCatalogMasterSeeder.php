<?php

declare(strict_types=1);

/**
 * Master seeder orchestrating all 12 assessment catalogs.
 *
 * Contract:
 * - Plan: docs/superpowers/plans/2026-08-17-learner-assessment-catalog-content.md (Section 4.4, 8, 11.2)
 * - DCR:  docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md (Section 7, 8, 9, 11)
 *
 * Safety:
 * - Insert-only via AbstractCatalogSeeder (no UPDATE/DELETE/TRUNCATE/DROP/REPLACE/ON DUPLICATE KEY UPDATE).
 * - Preflight of all 12 catalogs before the first write transaction.
 * - One transaction per catalog (delegated to AbstractCatalogSeeder).
 * - Default refusal on protected databases; explicit opt-in applies only to talenthub.
 * - talenthub_local is a retained read-only backup and can never be opted into.
 * - DCR PENDING is never treated as approval; no synthetic approval is read or written.
 *
 * Catalog version:
 * - learner_assessment_versions.version = '1.0.0' for all 12 catalogs in the initial seed.
 *   Evidence: tests/learner_assessment_catalog_test.php uses '1.0.0' as the canonical
 *   published version in its SQLite fixture; plan Section 14.1 illustrates roll-forward
 *   as '1.0.0' -> '1.1.0'; DCR Section 11 enforces UNIQUE(testId, version) idempotency
 *   on this version key. This is the assessment catalog version, distinct from DCR
 *   document version '1.0.0-draft'.
 */

namespace TalentHub\Learner\Seeds;

use PDO;
use RuntimeException;
use TalentHub\Database\ProtectedDatabasePolicy;
use TalentHub\Learner\Seeds\Assessment\AbstractCatalogSeeder;

require_once __DIR__ . '/Assessment/AbstractCatalogSeeder.php';

final class AssessmentCatalogMasterSeeder
{
    public const CATALOG_VERSION = AbstractCatalogSeeder::CATALOG_VERSION;
    public const MIGRATION_VERSION = '20260818000100';
    public const MIGRATION_NAME = 'create_learner_assessment_schema';
    public const PROTECTED_DATABASE = ProtectedDatabasePolicy::PRIMARY;

    /**
     * Fixed orchestration order:
     * Holland middle/high/college; MBTI middle/high/college; DISC middle/high/college; MI middle/high/college.
     *
     * @var list<array{test_code:string,test_name:string,test_type:string,catalog_file:string}>
     */
    public const CATALOG_ORDER = [
        ['test_code' => 'holland_middle', 'test_name' => 'Holland Middle', 'test_type' => 'holland', 'catalog_file' => 'Database/seeds/learner/Assessment/HollandCatalogMiddle.php'],
        ['test_code' => 'holland_high', 'test_name' => 'Holland High', 'test_type' => 'holland', 'catalog_file' => 'Database/seeds/learner/Assessment/HollandCatalogHigh.php'],
        ['test_code' => 'holland_college', 'test_name' => 'Holland College', 'test_type' => 'holland', 'catalog_file' => 'Database/seeds/learner/Assessment/HollandCatalogCollege.php'],
        ['test_code' => 'mbti_middle', 'test_name' => 'MBTI Middle', 'test_type' => 'mbti', 'catalog_file' => 'Database/seeds/learner/Assessment/MbtiCatalogMiddle.php'],
        ['test_code' => 'mbti_high', 'test_name' => 'MBTI High', 'test_type' => 'mbti', 'catalog_file' => 'Database/seeds/learner/Assessment/MbtiCatalogHigh.php'],
        ['test_code' => 'mbti_college', 'test_name' => 'MBTI College', 'test_type' => 'mbti', 'catalog_file' => 'Database/seeds/learner/Assessment/MbtiCatalogCollege.php'],
        ['test_code' => 'disc_middle', 'test_name' => 'DISC Middle', 'test_type' => 'disc', 'catalog_file' => 'Database/seeds/learner/Assessment/DiscCatalogMiddle.php'],
        ['test_code' => 'disc_high', 'test_name' => 'DISC High', 'test_type' => 'disc', 'catalog_file' => 'Database/seeds/learner/Assessment/DiscCatalogHigh.php'],
        ['test_code' => 'disc_college', 'test_name' => 'DISC College', 'test_type' => 'disc', 'catalog_file' => 'Database/seeds/learner/Assessment/DiscCatalogCollege.php'],
        ['test_code' => 'multiple_intelligence_middle', 'test_name' => 'Multiple Intelligence Middle', 'test_type' => 'multiple_intelligence', 'catalog_file' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogMiddle.php'],
        ['test_code' => 'multiple_intelligence_high', 'test_name' => 'Multiple Intelligence High', 'test_type' => 'multiple_intelligence', 'catalog_file' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogHigh.php'],
        ['test_code' => 'multiple_intelligence_college', 'test_name' => 'Multiple Intelligence College', 'test_type' => 'multiple_intelligence', 'catalog_file' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogCollege.php'],
    ];

    /** @var callable(string):void */
    private $logger;

    /**
     * @param string $expectedDatabase Expected DATABASE() name for this run.
     * @param bool $allowProtectedDatabase Explicit opt-in for talenthub only (default false).
     * @param callable(string):void|null $logger
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $expectedDatabase,
        private readonly bool $allowProtectedDatabase = false,
        ?callable $logger = null,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->logger = $logger ?? static function (string $line): void {
            fwrite(STDOUT, $line . PHP_EOL);
        };
    }

    /**
     * Preflight — no database writes. Must pass before any catalog transaction.
     *
     * Checks:
     * - Database name and runtime context (driver, pinned connection, protected DB gate)
     * - 8 assessment tables present
     * - Migration 20260818000100 applied and checksum matches file (no drift)
     * - MySQL session time zone +00:00
     * - Unique constraints per migration
     * - All 12 catalogs load/validate/hash successfully
     * - Scoring versions valid
     * - No UUID/code collision across the 12 in-memory catalogs
     * - No version/hash conflict against existing DB rows (read-only check)
     *
     * @return array<string,array<string,mixed>> Loaded catalogs keyed by test_code
     */
    public function preflight(): array
    {
        $this->assertDatabaseAndRuntimeContext();
        $this->assertAssessmentTablesExist();
        $this->assertMigrationAppliedAndNoDrift();
        $this->assertSessionTimeZone();
        $this->assertUniqueConstraints();

        $loaded = $this->loadAndValidateAllCatalogsInMemory();
        $this->assertNoInMemoryCollisions($loaded);
        $this->assertNoVersionHashConflict($loaded);

        // Run the same full catalog and existing-row checks used by seedCatalog()
        // for every catalog before the first write transaction is opened.
        $catalogSeeder = new AbstractCatalogSeeder($this->pdo);
        foreach (self::CATALOG_ORDER as $entry) {
            $catalogSeeder->preflightCatalog(
                $loaded[$entry['test_code']],
                $entry['test_code'],
                $entry['test_name'],
                $entry['test_type'],
                self::CATALOG_VERSION,
            );
        }

        ($this->logger)('PREFLIGHT OK — 12 catalogs validated, database=' . $this->expectedDatabase);

        return $loaded;
    }

    /**
     * Orchestrate seeding of all 12 catalogs in fixed order.
     * Preflights all 12 before the first write.
     *
     * @return array{inserted:int,no_op:int,failed:int,details:list<array{test_code:string,status:string,inserted:int,error:string}>}
     */
    public function seedAll(): array
    {
        $loaded = $this->preflight();

        $catalogSeeder = new AbstractCatalogSeeder($this->pdo, $this->logger);

        $inserted = 0;
        $noOp = 0;
        $failed = 0;
        $details = [];

        foreach (self::CATALOG_ORDER as $entry) {
            $testCode = $entry['test_code'];
            $catalog = $loaded[$testCode];
            try {
                $result = $catalogSeeder->seedCatalog(
                    $catalog,
                    $testCode,
                    $entry['test_name'],
                    $entry['test_type'],
                    self::CATALOG_VERSION,
                );
                if ($result['status'] === 'INSERTED') {
                    $inserted++;
                } else {
                    $noOp++;
                }
                $details[] = ['test_code' => $testCode, 'status' => $result['status'], 'inserted' => (int) $result['inserted'], 'error' => ''];
            } catch (\Throwable $e) {
                $failed++;
                $details[] = ['test_code' => $testCode, 'status' => 'FAILED', 'inserted' => 0, 'error' => $e->getMessage()];
                ($this->logger)('FAILED ' . $testCode . ' error=' . $e->getMessage());
            }
        }

        $summary = sprintf(
            'SUMMARY inserted=%d no_op=%d failed=%d total=%d',
            $inserted,
            $noOp,
            $failed,
            count(self::CATALOG_ORDER),
        );
        ($this->logger)($summary);

        return ['inserted' => $inserted, 'no_op' => $noOp, 'failed' => $failed, 'details' => $details];
    }

    // ---- Preflight helpers ----

    private function assertDatabaseAndRuntimeContext(): void
    {
        $expected = trim($this->expectedDatabase);
        if ($expected === '') {
            throw new RuntimeException('Preflight: expected database name is empty.');
        }

        // Protected database gate: explicit approval applies only to the active primary.
        if (ProtectedDatabasePolicy::isProtected($expected)
            && !ProtectedDatabasePolicy::allowsExplicitPrimaryWrite($expected, $this->allowProtectedDatabase)) {
            throw new RuntimeException(
                'Preflight: protected database ' . $expected
                . ' is refused; explicit approval applies only to ' . ProtectedDatabasePolicy::PRIMARY . '.',
            );
        }

        if ($this->allowProtectedDatabase && $expected !== ProtectedDatabasePolicy::PRIMARY) {
            throw new RuntimeException(
                'Preflight: protected-database approval is valid only for '
                . ProtectedDatabasePolicy::PRIMARY . '.',
            );
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') {
            throw new RuntimeException('Preflight: PDO driver must be mysql, got ' . $driver . '.');
        }

        $actual = $this->pdo->query('SELECT DATABASE()');
        if ($actual === false) {
            throw new RuntimeException('Preflight: could not determine current database.');
        }
        $actualDb = (string) $actual->fetchColumn();
        if ($actualDb !== $expected) {
            throw new RuntimeException('Preflight: PDO connection database mismatch: expected ' . $expected . ', got ' . $actualDb . '.');
        }
    }

    private function assertAssessmentTablesExist(): void
    {
        $required = [
            'talent_tests',
            'test_questions',
            'learner_assessment_versions',
            'learner_assessment_question_versions',
            'test_attempts',
            'learner_assessment_attempt_metadata',
            'learner_assessment_answers',
            'test_results',
        ];
        foreach ($required as $table) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            );
            $stmt->execute(['table' => $table]);
            if ((int) $stmt->fetchColumn() !== 1) {
                throw new RuntimeException('Preflight: required table missing: ' . $table . '.');
            }
        }
    }

    private function assertMigrationAppliedAndNoDrift(): void
    {
        $stmt = $this->pdo->prepare('SELECT checksum, name FROM schema_migrations WHERE version = :version LIMIT 1');
        $stmt->execute(['version' => self::MIGRATION_VERSION]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Preflight: migration ' . self::MIGRATION_VERSION . ' is not applied.');
        }

        $appliedChecksum = (string) $row['checksum'];
        $appliedName = (string) $row['name'];
        if ($appliedName !== self::MIGRATION_NAME) {
            throw new RuntimeException('Preflight: migration name drift: expected ' . self::MIGRATION_NAME . ', got ' . $appliedName . '.');
        }

        $migrationFile = dirname(__DIR__, 2) . '/migrations/' . self::MIGRATION_VERSION . '_' . self::MIGRATION_NAME . '.php';
        // Fallback to the canonical path used in this repo.
        if (!is_file($migrationFile)) {
            $migrationFile = dirname(__DIR__, 2) . '/migrations/20260818000100_create_learner_assessment_schema.php';
        }
        if (!is_file($migrationFile)) {
            throw new RuntimeException('Preflight: migration file not found for checksum verification.');
        }
        $contents = file_get_contents($migrationFile);
        if (!is_string($contents)) {
            throw new RuntimeException('Preflight: migration file could not be read for checksum verification.');
        }
        $canonicalChecksum = hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
        $rawChecksum = hash('sha256', $contents);
        $normalizedAppliedChecksum = strtolower($appliedChecksum);
        if (
            !hash_equals($normalizedAppliedChecksum, strtolower($canonicalChecksum))
            && !hash_equals($normalizedAppliedChecksum, strtolower($rawChecksum))
        ) {
            throw new RuntimeException('Preflight: migration checksum drift for ' . self::MIGRATION_VERSION . '.');
        }
    }

    private function assertSessionTimeZone(): void
    {
        $stmt = $this->pdo->query('SELECT @@session.time_zone');
        if ($stmt === false) {
            throw new RuntimeException('Preflight: could not read session time zone.');
        }
        $tz = (string) $stmt->fetchColumn();
        if ($tz !== '+00:00') {
            throw new RuntimeException('Preflight: MySQL session time zone must be +00:00, got ' . $tz . '.');
        }
    }

    private function assertUniqueConstraints(): void
    {
        // Verify the 5 unique keys from migration DDL exist with correct columns.
        $expected = [
            'uq_talent_tests_code' => ['talent_tests', ['code']],
            'uq_test_questions_test_code' => ['test_questions', ['testId', 'code']],
            'uq_learner_assessment_versions_test_version' => ['learner_assessment_versions', ['testId', 'version']],
            'uq_learner_assessment_question_versions_version_question' => ['learner_assessment_question_versions', ['versionId', 'questionId']],
            'uq_learner_assessment_question_versions_version_position' => ['learner_assessment_question_versions', ['versionId', 'position']],
        ];

        foreach ($expected as $indexName => [$table, $columns]) {
            $stmt = $this->pdo->prepare(
                <<<'SQL'
SELECT COLUMN_NAME
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = :table
  AND index_name = :index_name
ORDER BY seq_in_index
SQL,
            );
            $stmt->execute(['table' => $table, 'index_name' => $indexName]);
            $actualColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($actualColumns !== $columns) {
                throw new RuntimeException(
                    'Preflight: unique constraint ' . $indexName . ' mismatch on ' . $table
                    . ': expected (' . implode(', ', $columns) . '), got (' . implode(', ', (array) $actualColumns) . ').',
                );
            }
            // Verify uniqueness flag.
            $stmt2 = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name AND non_unique = 0',
            );
            $stmt2->execute(['table' => $table, 'index_name' => $indexName]);
            if ((int) $stmt2->fetchColumn() < 1) {
                throw new RuntimeException('Preflight: unique constraint ' . $indexName . ' is not unique.');
            }
        }
    }

    /**
     * Load all 12 catalog files, validate in memory (hash, UUID, namespace, positions, etc.).
     *
     * @return array<string,array<string,mixed>>
     */
    private function loadAndValidateAllCatalogsInMemory(): array
    {
        $projectRoot = dirname(__DIR__, 3);
        $loaded = [];

        foreach (self::CATALOG_ORDER as $entry) {
            $testCode = $entry['test_code'];
            $catalogFile = $projectRoot . '/' . $entry['catalog_file'];
            if (!is_file($catalogFile)) {
                throw new RuntimeException('Preflight: catalog file missing: ' . $entry['catalog_file'] . '.');
            }
            $catalog = require $catalogFile;
            if (!is_array($catalog) || !isset($catalog['metadata'], $catalog['questions'])) {
                throw new RuntimeException('Preflight: catalog file must return [metadata, questions]: ' . $entry['catalog_file'] . '.');
            }

            // Validate via AbstractCatalogSeeder in-memory path (without DB writes).
            // Reuse its hash/fingerprint logic by constructing a throwaway seeder for validation only.
            // We also verify scoring version is in the approved set.
            $metadata = $catalog['metadata'];
            $scoringVersion = (string) ($metadata['scoring_version'] ?? '');
            $allowed = ['holland-riasec-1.0', 'mbti-education-1.0', 'disc-education-1.0', 'multiple-intelligence-1.0'];
            if (!in_array($scoringVersion, $allowed, true)) {
                throw new RuntimeException('Preflight: scoringVersion not approved for ' . $testCode . ': ' . $scoringVersion . '.');
            }

            // Trigger full in-memory validation (hash, UUID, namespace, positions, required, options).
            // Use a temporary in-memory PDO-free check by calling the static hash and manual checks below;
            // the per-catalog seeder will re-validate before transaction, but preflight must also guarantee success.
            $questions = $catalog['questions'];
            if (!is_array($questions) || count($questions) === 0) {
                throw new RuntimeException('Preflight: catalog ' . $testCode . ' has no questions.');
            }

            $declaredHash = $metadata['schema_hash'] ?? null;
            if (!is_string($declaredHash) || !preg_match('/\A[0-9a-f]{64}\z/i', $declaredHash)) {
                throw new RuntimeException('Preflight: catalog ' . $testCode . ' has invalid schema_hash.');
            }
            $computed = AbstractCatalogSeeder::computeCanonicalSchemaHash($questions);
            if (!hash_equals(strtolower($computed), strtolower($declaredHash))) {
                throw new RuntimeException('Preflight: catalog ' . $testCode . ' schema_hash mismatch.');
            }

            $loaded[$testCode] = $catalog;
        }

        return $loaded;
    }

    /**
     * @param array<string,array<string,mixed>> $loaded
     */
    private function assertNoInMemoryCollisions(array $loaded): void
    {
        $seenUuids = [];
        $seenCodes = [];
        $seenContentHashes = [];

        foreach ($loaded as $testCode => $catalog) {
            foreach ($catalog['questions'] as $q) {
                $uuid = strtolower((string) $q['id']);
                $code = (string) $q['code'];
                $content = (string) $q['content'];

                if (isset($seenUuids[$uuid])) {
                    throw new RuntimeException('Preflight: UUID collision ' . $uuid . ' between ' . $seenUuids[$uuid] . ' and ' . $code . '.');
                }
                $seenUuids[$uuid] = $code;

                if (isset($seenCodes[$code])) {
                    throw new RuntimeException('Preflight: code collision ' . $code . '.');
                }
                $seenCodes[$code] = true;

                $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $content)), 'UTF-8');
                $h = hash('sha256', $normalized);
                if (isset($seenContentHashes[$h])) {
                    throw new RuntimeException('Preflight: normalized content collision between ' . $seenContentHashes[$h] . ' and ' . $code . '.');
                }
                $seenContentHashes[$h] = $code;
            }
        }

        // Total must be 366.
        $total = array_sum(array_map(static fn (array $c): int => count($c['questions']), $loaded));
        if ($total !== 366) {
            throw new RuntimeException('Preflight: total questions must be 366, got ' . $total . '.');
        }
    }

    /**
     * Read-only check: if (testCode, version) already exists, scoringVersion and schemaHash must match.
     *
     * @param array<string,array<string,mixed>> $loaded
     */
    private function assertNoVersionHashConflict(array $loaded): void
    {
        foreach (self::CATALOG_ORDER as $entry) {
            $testCode = $entry['test_code'];
            $catalog = $loaded[$testCode];
            $declaredHash = strtolower((string) $catalog['metadata']['schema_hash']);
            $scoringVersion = (string) $catalog['metadata']['scoring_version'];

            $stmt = $this->pdo->prepare(
                <<<'SQL'
SELECT v.scoringVersion AS scoring_version, v.schemaHash AS schema_hash
FROM learner_assessment_versions v
INNER JOIN talent_tests t ON t.id = v.testId
WHERE t.code = :test_code AND v.version = :version
LIMIT 1
SQL,
            );
            $stmt->execute(['test_code' => $testCode, 'version' => self::CATALOG_VERSION]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                continue;
            }
            if (!hash_equals(strtolower((string) $row['scoring_version']), strtolower($scoringVersion))) {
                throw new RuntimeException('Preflight: version/hash conflict for ' . $testCode . ': scoringVersion mismatch.');
            }
            if (!hash_equals(strtolower((string) $row['schema_hash']), $declaredHash)) {
                throw new RuntimeException('Preflight: version/hash conflict for ' . $testCode . ': schemaHash mismatch (fail closed).');
            }
        }
    }
}

// CLI entry point — only when executed directly, not when required as library.
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $projectRoot = dirname(__DIR__, 3);
    require_once $projectRoot . '/bin/bootstrap.php';

    $expectedDb = (string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ''));
    // Also support --database / --allow-protected-database flags.
    $allowProtected = false;
    $cliDb = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--database=')) {
            $cliDb = substr($arg, strlen('--database='));
        }
        if ($arg === '--allow-protected-database') {
            $allowProtected = true;
        }
    }
    if ($cliDb !== null && $cliDb !== '') {
        $expectedDb = $cliDb;
    }
    if (getenv('ALLOW_PROTECTED_DATABASE') === '1' || getenv('ALLOW_PROTECTED_DATABASE') === 'true') {
        $allowProtected = true;
    }

    if ($expectedDb === '') {
        fwrite(STDERR, "DB_DATABASE is not set. Pass --database=<name> or set DB_DATABASE.\n");
        exit(2);
    }

    $host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'));
    $port = (string) (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306'));
    $user = (string) (getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root'));
    $pass = (string) (getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ''));
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$expectedDb};charset={$charset}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
        exit(2);
    }

    $seeder = new AssessmentCatalogMasterSeeder($pdo, $expectedDb, $allowProtected);
    try {
        $result = $seeder->seedAll();
        exit($result['failed'] > 0 ? 1 : 0);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Preflight failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
