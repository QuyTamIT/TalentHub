<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Assessment;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;

/**
 * Shared insert-only seeder for one assessment catalog.
 *
 * Contract derived from:
 * - docs/superpowers/plans/2026-08-17-learner-assessment-catalog-content.md (Section 2.7, 4.4, 11.2)
 * - docs/superpowers/dcr/2026-08-18-learner-assessment-catalog-seed-dcr.md (Section 7, 8, 11)
 * - tests/learner_catalog_content_validator.php (canonical hash)
 *
 * Safety:
 * - No UPDATE / DELETE / TRUNCATE / DROP / REPLACE / ON DUPLICATE KEY UPDATE.
 * - Validate entire catalog in memory before opening any transaction.
 * - One transaction per catalog; rollback on pre-commit failure.
 * - Idempotent on exact re-run; fail closed on any hash/identity mismatch.
 */
final class AbstractCatalogSeeder
{
    public const CATALOG_VERSION = '1.0.0';
    public const CATALOG_STATUS_PUBLISHED = 'published';

    /** @var callable(string):void|null */
    private $logger;

    /**
     * @param callable(string):void|null $logger Logger receives START/INSERTED/NO_OP/FAILED lines.
     */
    public function __construct(
        private readonly PDO $pdo,
        ?callable $logger = null,
    ) {
        $this->logger = $logger;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Seed (or verify idempotent NO-OP for) one catalog.
     *
     * @param array<string,mixed> $catalog Dataset returned by the catalog PHP file.
     * @param string $testCode Banded code, e.g. holland_middle.
     * @param string $testName Human name, e.g. Holland Middle.
     * @param string $testType Framework type, e.g. holland.
     * @param string $catalogVersion learner_assessment_versions.version (must be CATALOG_VERSION).
     * @return array{status:string,inserted:int,reason:string}
     *               status: INSERTED | NO_OP
     */
    public function seedCatalog(
        array $catalog,
        string $testCode,
        string $testName,
        string $testType,
        string $catalogVersion = self::CATALOG_VERSION,
    ): array {
        if ($catalogVersion !== self::CATALOG_VERSION) {
            throw new RuntimeException(
                "Catalog version mismatch for {$testCode}: expected " . self::CATALOG_VERSION . ", got {$catalogVersion}."
            );
        }

        // ---- In-memory validation before any DB interaction that opens a transaction ----
        $validated = self::validateCatalogInMemory($catalog, $testCode, $testType, $catalogVersion);
        $canonicalHash = $validated['canonical_hash'];
        $scoringVersion = $validated['scoring_version'];
        /** @var list<array<string,mixed>> $questions */
        $questions = $validated['questions'];
        /** @var array<string,string> $perQuestionFingerprints code => fingerprint */
        $perQuestionFingerprints = $validated['per_question_fingerprints'];
        /** @var array<string,string> $uuidByCode */
        $uuidByCode = $validated['uuid_by_code'];

        $this->log("START {$testCode} version={$catalogVersion} hash={$canonicalHash}");

        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Catalog seeder refuses an externally owned transaction.');
        }

        // The test definition is shared by all versions and must remain publishable.
        $this->assertExistingTalentTestCompatible($testCode, $testType);

        // ---- Idempotency pre-check (outside transaction, read-only) ----
        $existingVersion = $this->findExistingVersion($testCode, $catalogVersion);
        if ($existingVersion !== null) {
            $this->assertIdempotentMatch(
                $testCode,
                $catalogVersion,
                $scoringVersion,
                $canonicalHash,
                $questions,
                $perQuestionFingerprints,
                $uuidByCode,
                $existingVersion,
            );
            $this->log("NO_OP {$testCode} version={$catalogVersion} reason=idempotent_match");
            return ['status' => 'NO_OP', 'inserted' => 0, 'reason' => 'idempotent_match'];
        }

        // New version — fail closed on any UUID/code collision before writing.
        $this->assertNoIdentityCollision($questions, $uuidByCode, $perQuestionFingerprints);

        // ---- Insert-only transaction ----
        $this->pdo->beginTransaction();
        try {
            $testId = $this->insertTalentTest($testCode, $testName, $testType);
            $this->insertTestQuestions($testId, $questions);
            $versionId = $this->insertAssessmentVersion($testId, $catalogVersion, $scoringVersion, $canonicalHash);
            $this->insertQuestionVersionBindings($versionId, $questions);

            $this->assertPostInsertIntegrity($testId, $versionId, count($questions), $canonicalHash);

            $this->pdo->commit();
            $this->log("INSERTED {$testCode} version={$catalogVersion} rows=" . count($questions));
            return ['status' => 'INSERTED', 'inserted' => count($questions), 'reason' => 'inserted'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->log("FAILED {$testCode} version={$catalogVersion} error=" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Run every read-only check required for one catalog before any write transaction.
     * The master seeder calls this for all catalogs during preflight.
     *
     * @param array<string,mixed> $catalog
     */
    public function preflightCatalog(
        array $catalog,
        string $testCode,
        string $testName,
        string $testType,
        string $catalogVersion = self::CATALOG_VERSION,
    ): void {
        if ($catalogVersion !== self::CATALOG_VERSION) {
            throw new RuntimeException(
                "Catalog version mismatch for {$testCode}: expected " . self::CATALOG_VERSION . ", got {$catalogVersion}."
            );
        }

        $validated = self::validateCatalogInMemory($catalog, $testCode, $testType, $catalogVersion);
        $this->assertExistingTalentTestCompatible($testCode, $testType);

        $existingVersion = $this->findExistingVersion($testCode, $catalogVersion);
        if ($existingVersion !== null) {
            $this->assertIdempotentMatch(
                $testCode,
                $catalogVersion,
                $validated['scoring_version'],
                $validated['canonical_hash'],
                $validated['questions'],
                $validated['per_question_fingerprints'],
                $validated['uuid_by_code'],
                $existingVersion,
            );
            return;
        }

        $this->assertNoIdentityCollision(
            $validated['questions'],
            $validated['uuid_by_code'],
            $validated['per_question_fingerprints'],
        );
    }

    /**
     * Validate entire catalog in memory (schema hash, UUID, code namespace, positions, required/options, dimensions).
     *
     * @return array{
     *   canonical_hash:string,
     *   scoring_version:string,
     *   questions:list<array<string,mixed>>,
     *   per_question_fingerprints:array<string,string>,
     *   uuid_by_code:array<string,string>
     * }
     */
    public static function validateCatalogInMemory(array $catalog, string $testCode, string $testType, string $catalogVersion): array
    {
        if (!isset($catalog['metadata']) || !is_array($catalog['metadata'])) {
            throw new RuntimeException("Catalog {$testCode}: missing metadata.");
        }
        if (!isset($catalog['questions']) || !is_array($catalog['questions'])) {
            throw new RuntimeException("Catalog {$testCode}: missing questions.");
        }
        $metadata = $catalog['metadata'];
        /** @var list<array<string,mixed>> $questions */
        $questions = $catalog['questions'];

        $framework = (string) ($metadata['framework'] ?? '');
        $educationBand = (string) ($metadata['education_band'] ?? '');
        $scoringVersion = (string) ($metadata['scoring_version'] ?? '');
        $declaredHash = $metadata['schema_hash'] ?? null;
        $stableNamespace = (string) ($metadata['stable_code_namespace'] ?? '');

        if ($framework === '' || $educationBand === '' || $scoringVersion === '') {
            throw new RuntimeException("Catalog {$testCode}: metadata framework/band/scoring_version missing.");
        }

        $expectedNamespace = "{$framework}_{$educationBand}_";
        if ($stableNamespace !== $expectedNamespace) {
            throw new RuntimeException("Catalog {$testCode}: stable_code_namespace mismatch.");
        }

        $expectedTestType = $framework;
        if ($testType !== $expectedTestType) {
            throw new RuntimeException("Catalog {$testCode}: testType {$testType} does not match framework {$framework}.");
        }

        if ($testCode !== "{$framework}_{$educationBand}") {
            throw new RuntimeException("Catalog {$testCode}: testCode does not match framework_band.");
        }

        $reviewState = (string) ($metadata['review_state'] ?? '');
        if ($reviewState !== 'published') {
            throw new RuntimeException("Catalog {$testCode}: review_state must be published before seeding.");
        }
        $reviewEvents = $metadata['review_events'] ?? null;
        if (!is_array($reviewEvents)) {
            throw new RuntimeException("Catalog {$testCode}: review_events must be a list before seeding.");
        }
        $requiredCheckpoints = [
            'content_review',
            'educational_review',
            'bias_review',
            'scoring_review',
            'product_owner_approval',
            'codex_schema_review',
        ];
        $recordedCheckpoints = [];
        foreach ($reviewEvents as $event) {
            if (!is_array($event) || !isset($event['checkpoint'], $event['reviewer'], $event['approved_at_utc'])) {
                throw new RuntimeException("Catalog {$testCode}: every review event needs checkpoint, reviewer, and approved_at_utc.");
            }
            if (trim((string) $event['reviewer']) === '') {
                throw new RuntimeException("Catalog {$testCode}: review event reviewer cannot be empty.");
            }
            if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z\z/', (string) $event['approved_at_utc']) !== 1) {
                throw new RuntimeException("Catalog {$testCode}: review event approved_at_utc must be a UTC Z timestamp.");
            }
            $recordedCheckpoints[] = (string) $event['checkpoint'];
        }
        $missingCheckpoints = array_values(array_diff($requiredCheckpoints, array_unique($recordedCheckpoints)));
        if ($missingCheckpoints !== []) {
            throw new RuntimeException(
                "Catalog {$testCode}: missing review checkpoints: " . implode(', ', $missingCheckpoints) . '.'
            );
        }

        // Validate each question shape before hashing.
        $seenCodes = [];
        $seenIds = [];
        $seenPositions = [];
        $uuidByCode = [];
        $optionsJsonByCode = [];

        $allowedScoringVersions = [
            'holland-riasec-1.0',
            'mbti-education-1.0',
            'disc-education-1.0',
            'multiple-intelligence-1.0',
        ];
        if (!in_array($scoringVersion, $allowedScoringVersions, true)) {
            throw new RuntimeException("Catalog {$testCode}: scoringVersion {$scoringVersion} not approved.");
        }

        foreach ($questions as $idx => $q) {
            if (!is_array($q)) {
                throw new RuntimeException("Catalog {$testCode}: question at index {$idx} must be array.");
            }
            foreach (['id', 'code', 'position', 'dimension_code', 'required', 'content', 'options'] as $k) {
                if (!array_key_exists($k, $q)) {
                    throw new RuntimeException("Catalog {$testCode}: question at index {$idx} missing {$k}.");
                }
            }
            $id = (string) $q['id'];
            $code = (string) $q['code'];
            $position = $q['position'];
            $required = $q['required'];
            $content = $q['content'];
            $options = $q['options'];
            $dimensionCode = (string) $q['dimension_code'];

            if (!Uuid::isValid($id)) {
                throw new RuntimeException("Catalog {$testCode}: question id {$id} is not a valid canonical UUID.");
            }
            $lowerId = strtolower($id);
            if (isset($seenIds[$lowerId])) {
                throw new RuntimeException("Catalog {$testCode}: duplicate UUID {$id}.");
            }
            $seenIds[$lowerId] = true;

            if (!str_starts_with($code, $expectedNamespace)) {
                throw new RuntimeException("Catalog {$testCode}: code {$code} namespace mismatch.");
            }
            if (isset($seenCodes[$code])) {
                throw new RuntimeException("Catalog {$testCode}: duplicate code {$code}.");
            }
            $seenCodes[$code] = true;
            $uuidByCode[$code] = $lowerId;

            if (!is_int($position)) {
                throw new RuntimeException("Catalog {$testCode}: position for {$code} must be int.");
            }
            $seenPositions[] = $position;

            if ($required !== true) {
                throw new RuntimeException("Catalog {$testCode}: required for {$code} must be boolean true.");
            }

            if (!is_string($content) || trim($content) === '' || !mb_check_encoding($content, 'UTF-8')) {
                throw new RuntimeException("Catalog {$testCode}: invalid content for {$code}.");
            }

            if (!is_array($options) || count($options) !== 5) {
                throw new RuntimeException("Catalog {$testCode}: options for {$code} must be 5 Likert items.");
            }
            foreach ($options as $oIdx => $opt) {
                if (!is_array($opt) || !isset($opt['value'], $opt['label'])) {
                    throw new RuntimeException("Catalog {$testCode}: option at {$oIdx} for {$code} missing value/label.");
                }
                if ((int) $opt['value'] !== $oIdx + 1) {
                    throw new RuntimeException("Catalog {$testCode}: option value for {$code} must be 1..5.");
                }
            }
            // Stable JSON for fingerprint (decoded optionsJson equivalent — normalized via json encode/decode).
            $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            // Normalize by decoding then re-encoding to ensure stable whitespace.
            $decoded = json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR);
            $normalizedOptionsJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            // Validate round-trip: stored optionsJson must be the normalized form.
            $optionsJsonByCode[$code] = $normalizedOptionsJson;

            if (trim($dimensionCode) === '') {
                throw new RuntimeException("Catalog {$testCode}: dimension_code for {$code} cannot be empty.");
            }
        }

        sort($seenPositions);
        $expectedPositions = range(1, count($questions));
        if ($seenPositions !== $expectedPositions) {
            throw new RuntimeException("Catalog {$testCode}: positions must be contiguous 1..N.");
        }

        $canonicalHash = self::computeCanonicalSchemaHash($questions);
        if (!is_string($declaredHash) || $declaredHash === '') {
            throw new RuntimeException("Catalog {$testCode}: metadata.schema_hash missing.");
        }
        if (!hash_equals(strtolower($canonicalHash), strtolower($declaredHash))) {
            throw new RuntimeException(
                "Catalog {$testCode}: canonical schema hash mismatch: computed {$canonicalHash}, declared {$declaredHash}."
            );
        }

        // Per-question fingerprints (in-memory, no DB column).
        $fingerprints = [];
        foreach ($questions as $q) {
            $code = (string) $q['code'];
            $fingerprints[$code] = self::computePerQuestionFingerprint(
                $code,
                (string) $q['content'],
                $optionsJsonByCode[$code],
                (string) $q['dimension_code'],
                (bool) $q['required'],
                (int) $q['position'],
            );
        }

        return [
            'canonical_hash' => strtolower($canonicalHash),
            'scoring_version' => $scoringVersion,
            'questions' => $questions,
            'per_question_fingerprints' => $fingerprints,
            'uuid_by_code' => $uuidByCode,
        ];
    }

    // ---- Canonical hash (must match validator exactly) ----

    /**
     * Canonical schema hash: sort by position, fixed key order, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES, SHA-256.
     * UUID is excluded. Mirrors tests/learner_catalog_content_validator.php::computeCanonicalSchemaHash.
     *
     * @param list<array<string,mixed>> $questions
     */
    public static function computeCanonicalSchemaHash(array $questions): string
    {
        $sorted = $questions;
        usort($sorted, static fn (array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));

        $canonical = [];
        foreach ($sorted as $q) {
            $options = [];
            foreach ($q['options'] ?? [] as $opt) {
                $options[] = [
                    'value' => (int) ($opt['value'] ?? 0),
                    'label' => (string) ($opt['label'] ?? ''),
                ];
            }
            $canonical[] = [
                'code' => (string) ($q['code'] ?? ''),
                'content' => (string) ($q['content'] ?? ''),
                'options' => $options,
                'dimension_code' => (string) ($q['dimension_code'] ?? ''),
                'required' => (bool) ($q['required'] ?? false),
                'position' => (int) ($q['position'] ?? 0),
            ];
        }

        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode canonical question payload.');
        }
        return strtolower(hash('sha256', $json));
    }

    /**
     * Per-question fingerprint: SHA-256 of stable JSON with keys code, content, normalized decoded optionsJson,
     * dimension_code, required, position. Fingerprint is in-memory only.
     */
    public static function computePerQuestionFingerprint(
        string $code,
        string $content,
        string $normalizedOptionsJson,
        string $dimensionCode,
        bool $required,
        int $position,
    ): string {
        $decodedOptions = json_decode($normalizedOptionsJson, true, 512, JSON_THROW_ON_ERROR);
        $payload = [
            'code' => $code,
            'content' => $content,
            'options' => $decodedOptions,
            'dimension_code' => $dimensionCode,
            'required' => $required,
            'position' => $position,
        ];
        // Ensure stable key order as listed above (insertion order is preserved).
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return strtolower(hash('sha256', $json));
    }

    // ---- Idempotency helpers ----

    /**
     * @return array{id:string,test_id:string,scoring_version:string,schema_hash:string,status:string}|null
     */
    private function findExistingVersion(string $testCode, string $catalogVersion): ?array
    {
        $sql = <<<'SQL'
SELECT v.id AS id, v.testId AS test_id, v.scoringVersion AS scoring_version,
       v.schemaHash AS schema_hash, v.status AS status
FROM learner_assessment_versions v
INNER JOIN talent_tests t ON t.id = v.testId
WHERE t.code = :test_code AND v.version = :version
LIMIT 1
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['test_code' => $testCode, 'version' => $catalogVersion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Verify existing version matches exactly; fail closed on any mismatch.
     *
     * @param array<string,string> $perQuestionFingerprints
     * @param array<string,string> $uuidByCode
     * @param array{id:string,test_id:string,scoring_version:string,schema_hash:string,status:string} $existingVersion
     */
    private function assertIdempotentMatch(
        string $testCode,
        string $catalogVersion,
        string $scoringVersion,
        string $canonicalHash,
        array $questions,
        array $perQuestionFingerprints,
        array $uuidByCode,
        array $existingVersion,
    ): void {
        if (!hash_equals(strtolower((string) $existingVersion['scoring_version']), strtolower($scoringVersion))) {
            throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: scoringVersion mismatch.");
        }
        if (!hash_equals(strtolower((string) $existingVersion['schema_hash']), strtolower($canonicalHash))) {
            throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: schemaHash mismatch.");
        }
        if (strtolower((string) $existingVersion['status']) !== self::CATALOG_STATUS_PUBLISHED) {
            throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: status must be published.");
        }

        $versionId = (string) $existingVersion['id'];
        $testId = (string) $existingVersion['test_id'];

        // Count bindings must match.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM learner_assessment_question_versions WHERE versionId = :version_id');
        $stmt->execute(['version_id' => $versionId]);
        $bindingCount = (int) $stmt->fetchColumn();
        if ($bindingCount !== count($questions)) {
            throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: binding count mismatch.");
        }

        // UUID manifest and per-question fingerprints must match via JOIN.
        $sql = <<<'SQL'
SELECT tq.id AS question_id, tq.code AS code, tq.content AS content, tq.optionsJson AS options_json,
       qv.dimensionCode AS dimension_code, qv.required AS required, qv.position AS position
FROM learner_assessment_question_versions qv
INNER JOIN test_questions tq ON tq.id = qv.questionId
INNER JOIN learner_assessment_versions v ON v.id = qv.versionId
WHERE v.id = :version_id
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['version_id' => $versionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $existingByCode = [];
        foreach ($rows as $r) {
            $code = (string) $r['code'];
            $existingByCode[$code] = $r;
        }

        if (count($existingByCode) !== count($questions)) {
            throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: question count mismatch in DB.");
        }

        foreach ($questions as $q) {
            $code = (string) $q['code'];
            if (!isset($existingByCode[$code])) {
                throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: missing code {$code} in DB.");
            }
            $row = $existingByCode[$code];
            $expectedUuid = $uuidByCode[$code];
            if (!hash_equals(strtolower((string) $row['question_id']), strtolower($expectedUuid))) {
                throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: UUID mismatch for {$code}.");
            }
            $normalizedOptionsJson = json_encode(
                json_decode((string) $row['options_json'], true, 512, JSON_THROW_ON_ERROR),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $existingFingerprint = self::computePerQuestionFingerprint(
                $code,
                (string) $row['content'],
                $normalizedOptionsJson,
                (string) $row['dimension_code'],
                (bool) ((int) $row['required'] === 1),
                (int) $row['position'],
            );
            if (!hash_equals($existingFingerprint, $perQuestionFingerprints[$code])) {
                throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: fingerprint mismatch for {$code}.");
            }
        }

        // publishedAt must be NOT NULL for published status.
        $stmt = $this->pdo->prepare('SELECT publishedAt FROM learner_assessment_versions WHERE id = :id');
        $stmt->execute(['id' => $versionId]);
        $publishedAt = $stmt->fetchColumn();
        if ($publishedAt === false || $publishedAt === null || trim((string) $publishedAt) === '') {
            throw new RuntimeException("Idempotency fail-closed for {$testCode} {$catalogVersion}: publishedAt must be NOT NULL when published.");
        }
    }

    /**
     * Fail closed if any UUID or stable code already maps to different content.
     *
     * @param array<string,string> $uuidByCode
     * @param array<string,string> $perQuestionFingerprints
     */
    private function assertNoIdentityCollision(array $questions, array $uuidByCode, array $perQuestionFingerprints): void
    {
        foreach ($questions as $q) {
            $code = (string) $q['code'];
            $uuid = $uuidByCode[$code];
            $fingerprint = $perQuestionFingerprints[$code];

            // UUID exists but maps to different code/fingerprint.
            $stmt = $this->pdo->prepare('SELECT code, content, optionsJson FROM test_questions WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $uuid]);
            $rowByUuid = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($rowByUuid)) {
                $existingCode = (string) $rowByUuid['code'];
                if ($existingCode !== $code) {
                    throw new RuntimeException("Identity collision: UUID {$uuid} already maps to code {$existingCode}, cannot map to {$code}.");
                }
                // Also compare fingerprint via the single question's test_questions + any binding that carries dimension/position.
                // For a new version, the UUID may exist only in test_questions without a version binding yet — check content/optionsJson alone.
                $normalizedOptionsJson = json_encode(
                    json_decode((string) $rowByUuid['optionsJson'], true, 512, JSON_THROW_ON_ERROR),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
                // Need dimension/required/position from the incoming question to compare; if the DB row's existing binding
                // for another version has different dimension/position, that is not a collision for this code — the question
                // identity is (testId, code). So we only compare content+options when no binding context exists.
                // To be strict, fetch any existing binding for this questionId to reconstruct full fingerprint.
                $stmt2 = $this->pdo->prepare(
                    'SELECT dimensionCode, required, position FROM learner_assessment_question_versions WHERE questionId = :qid LIMIT 1'
                );
                $stmt2->execute(['qid' => $uuid]);
                $binding = $stmt2->fetch(PDO::FETCH_ASSOC);
                if (is_array($binding)) {
                    $existingFp = self::computePerQuestionFingerprint(
                        $existingCode,
                        (string) $rowByUuid['content'],
                        $normalizedOptionsJson,
                        (string) $binding['dimensionCode'],
                        (bool) ((int) $binding['required'] === 1),
                        (int) $binding['position'],
                    );
                    if (!hash_equals($existingFp, $fingerprint)) {
                        throw new RuntimeException("Identity collision: UUID {$uuid} fingerprint mismatch for code {$code}.");
                    }
                } else {
                    // No binding yet — compare content+options only; differing dimension/position for a new version is allowed
                    // because the binding is version-scoped. Content/options must still match if UUID is reused.
                    $existingContent = (string) $rowByUuid['content'];
                    if ($existingContent !== (string) $q['content'] || $normalizedOptionsJson !== json_encode($q['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) {
                        throw new RuntimeException("Identity collision: UUID {$uuid} content/options mismatch for code {$code}.");
                    }
                }
            }

            // Stable code exists with different UUID/content.
            $stmt = $this->pdo->prepare(
                'SELECT tq.id AS id, tq.content AS content, tq.optionsJson AS options_json
                 FROM test_questions tq
                 INNER JOIN talent_tests t ON t.id = tq.testId
                 WHERE tq.code = :code LIMIT 1'
            );
            $stmt->execute(['code' => $code]);
            $rowByCode = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($rowByCode)) {
                if (!hash_equals(strtolower((string) $rowByCode['id']), strtolower($uuid))) {
                    throw new RuntimeException("Identity collision: code {$code} already maps to UUID {$rowByCode['id']}, cannot map to {$uuid}.");
                }
            }
        }
    }

    // ---- Insert helpers (INSERT only) ----

    private function assertExistingTalentTestCompatible(string $testCode, string $testType): void
    {
        $stmt = $this->pdo->prepare('SELECT type, status FROM talent_tests WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $testCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }

        if ((string) $row['type'] !== $testType) {
            throw new RuntimeException("Existing talent_tests row for {$testCode} has type mismatch.");
        }
        if ((string) $row['status'] !== self::CATALOG_STATUS_PUBLISHED) {
            throw new RuntimeException("Existing talent_tests row for {$testCode} must be published before seeding.");
        }
    }

    private function insertTalentTest(string $testCode, string $testName, string $testType): string
    {
        // If talent_tests row for this code already exists (shared testId across versions), reuse it.
        $this->assertExistingTalentTestCompatible($testCode, $testType);
        $stmt = $this->pdo->prepare('SELECT id FROM talent_tests WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $testCode]);
        $existing = $stmt->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = strtolower(Uuid::v4());
        $now = $this->nowUtc();
        $sql = 'INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES (:id, :code, :name, :type, :status, :created_at, :updated_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'code' => $testCode,
            'name' => $testName,
            'type' => $testType,
            'status' => self::CATALOG_STATUS_PUBLISHED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $id;
    }

    /**
     * @param list<array<string,mixed>> $questions
     */
    private function insertTestQuestions(string $testId, array $questions): void
    {
        $now = $this->nowUtc();
        $sql = 'INSERT INTO test_questions (id, testId, code, content, optionsJson, status, createdAt, updatedAt) VALUES (:id, :test_id, :code, :content, :options_json, :status, :created_at, :updated_at)';
        $stmt = $this->pdo->prepare($sql);
        foreach ($questions as $q) {
            $optionsJson = json_encode($q['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $stmt->execute([
                'id' => strtolower((string) $q['id']),
                'test_id' => $testId,
                'code' => (string) $q['code'],
                'content' => (string) $q['content'],
                'options_json' => $optionsJson,
                'status' => self::CATALOG_STATUS_PUBLISHED,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertAssessmentVersion(string $testId, string $version, string $scoringVersion, string $schemaHash): string
    {
        $id = strtolower(Uuid::v4());
        $now = $this->nowUtc();
        $sql = 'INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES (:id, :test_id, :version, :scoring_version, :schema_hash, :status, :published_at, :created_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'test_id' => $testId,
            'version' => $version,
            'scoring_version' => $scoringVersion,
            'schema_hash' => strtolower($schemaHash),
            'status' => self::CATALOG_STATUS_PUBLISHED,
            'published_at' => $now,
            'created_at' => $now,
        ]);
        return $id;
    }

    /**
     * @param list<array<string,mixed>> $questions
     */
    private function insertQuestionVersionBindings(string $versionId, array $questions): void
    {
        $now = $this->nowUtc();
        $sql = 'INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required, createdAt) VALUES (:id, :version_id, :question_id, :position, :dimension_code, :required, :created_at)';
        $stmt = $this->pdo->prepare($sql);
        foreach ($questions as $q) {
            $stmt->execute([
                'id' => strtolower(Uuid::v4()),
                'version_id' => $versionId,
                'question_id' => strtolower((string) $q['id']),
                'position' => (int) $q['position'],
                'dimension_code' => (string) $q['dimension_code'],
                'required' => 1,
                'created_at' => $now,
            ]);
        }
    }

    private function assertPostInsertIntegrity(string $testId, string $versionId, int $expectedCount, string $canonicalHash): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM test_questions WHERE testId = :test_id');
        $stmt->execute(['test_id' => $testId]);
        $qCount = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM learner_assessment_question_versions WHERE versionId = :version_id');
        $stmt->execute(['version_id' => $versionId]);
        $bCount = (int) $stmt->fetchColumn();

        if ($qCount < $expectedCount || $bCount !== $expectedCount) {
            throw new RuntimeException("Post-insert count check failed: questions={$qCount}, bindings={$bCount}, expected={$expectedCount}.");
        }

        $stmt = $this->pdo->prepare('SELECT schemaHash FROM learner_assessment_versions WHERE id = :id');
        $stmt->execute(['id' => $versionId]);
        $storedHash = strtolower((string) $stmt->fetchColumn());
        if (!hash_equals($storedHash, strtolower($canonicalHash))) {
            throw new RuntimeException("Post-insert hash check failed.");
        }
    }

    private function nowUtc(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }
}
