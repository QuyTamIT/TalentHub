<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Activity;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;
use TalentHub\Database\ProtectedDatabasePolicy;

require_once dirname(__DIR__, 4) . '/src/Database/ProtectedDatabasePolicy.php';
require_once __DIR__ . '/SchoolActivityCatalogDataset.php';
require_once __DIR__ . '/SchoolActivityQrHandoff.php';

final class SchoolActivityCatalogSeeder
{
    public const DISPOSABLE_SCHEMA = 'talenthub_activity_phase4_disposable';
    public const SQLITE_TEST_SCHEMA = 'sqlite_activity_phase4_test';

    /** @var array<string,array{id:string,activityId:string}> */
    private const QR_FIXTURES = [
        'talenthub' => [
            'id' => '41000000-0000-4000-8000-000000000001',
            'activityId' => '31000000-0000-4000-8000-000000000001',
        ],
        'nguyen_trai' => [
            'id' => '41000000-0000-4000-8000-000000000002',
            'activityId' => '31000000-0000-4000-8000-000000000005',
        ],
        'fpt' => [
            'id' => '41000000-0000-4000-8000-000000000003',
            'activityId' => '31000000-0000-4000-8000-000000000009',
        ],
    ];

    private readonly Closure $tokenFactory;
    private bool $successfulQrHandoff = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $expectedSchema,
        private readonly bool $allowPrimary,
        private readonly DateTimeImmutable $clock,
        ?callable $tokenFactory = null,
        private readonly ?SchoolActivityQrHandoff $qrHandoff = null,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->tokenFactory = $tokenFactory === null
            ? static fn (): string => bin2hex(random_bytes(32))
            : Closure::fromCallable($tokenFactory);
    }

    /** @return array{records:int,existing:int,new:int,published:int,completed:int,qr:int} */
    public function preflight(bool $forWrite = false): array
    {
        $this->assertConnection($forWrite);
        $this->assertRequiredTables();
        $this->assertUtcSession();

        $records = SchoolActivityCatalogDataset::records();
        $this->assertDatasetShape($records);
        $this->assertParents($records);
        $this->assertExistingActivitySnapshots($records);
        $this->assertNewActivityCollisions($records);
        $this->assertExistingChildren($records);

        return [
            'records' => count($records),
            'existing' => 5,
            'new' => 12,
            'published' => 15,
            'completed' => 2,
            'qr' => count(self::QR_FIXTURES),
        ];
    }

    /**
     * @return array{existing:int,inserted:int,details:int,registration_policies:int,experience_policies:int,qr_sessions:int}
     */
    public function run(): array
    {
        $this->preflight(true);
        $records = SchoolActivityCatalogDataset::records();
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Activity catalog seeder must own its transaction to finalize QR handoff only after commit.');
        }

        $preparedQr = [];
        $preparedNewHandoff = false;
        $existingFixtureCount = $this->fixtureQrCount();
        if ($existingFixtureCount === 0) {
            if ($this->qrHandoff === null) {
                throw new RuntimeException('Creating QR fixture sessions requires an explicit one-time handoff output directory.');
            }
            $preparedQr = $this->qrHandoff->prepare($this->qrHandoffEntries($records), $this->tokenFactory);
            $preparedNewHandoff = true;
        } elseif ($existingFixtureCount !== count(self::QR_FIXTURES) || !$this->successfulQrHandoff) {
            throw new RuntimeException('Stable QR fixture session already exists but its one-time handoff is unavailable; refusing token rotation or regeneration.');
        }

        $committed = false;
        $this->pdo->beginTransaction();

        try {
            $inserted = 0;
            foreach ($records as $record) {
                if (($record['source'] ?? null) === 'new' && $this->activityById((string) $record['activity']['id']) === null) {
                    $this->insertActivity($record);
                    $inserted++;
                }
            }
            foreach ($records as $record) {
                $this->insertOrValidateDetails($record);
                if (($record['activity']['status'] ?? null) === 'published') {
                    $this->insertOrValidateRegistrationPolicy($record);
                    $this->insertOrValidateExperiencePolicy($record);
                }
            }
            foreach (self::QR_FIXTURES as $fixture) {
                $this->insertOrValidateQrFixture($fixture, $preparedQr[$fixture['id']] ?? null);
            }
            $this->pdo->commit();
            $committed = true;
            if ($preparedNewHandoff) {
                $this->qrHandoff?->finalize();
                $this->successfulQrHandoff = true;
            }
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($preparedNewHandoff && !$committed) {
                $this->qrHandoff?->rollback();
            }
            throw $exception;
        }

        return [
            'existing' => 17 - $inserted,
            'inserted' => $inserted,
            'details' => $this->countTable('activity_details'),
            'registration_policies' => $this->countTable('activity_registration_policies'),
            'experience_policies' => $this->countTable('activity_experience_policies'),
            'qr_sessions' => $this->fixtureQrCount(),
        ];
    }

    /** @return array<string,array{id:string,activityId:string}> */
    public static function qrFixtures(): array
    {
        return self::QR_FIXTURES;
    }

    private function assertConnection(bool $forWrite): void
    {
        $schema = trim($this->expectedSchema);
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($schema === ProtectedDatabasePolicy::LEGACY_BACKUP) {
            throw new RuntimeException('talenthub_local is read-only and is never a valid activity catalog target.');
        }
        if ($this->allowPrimary && $schema !== ProtectedDatabasePolicy::PRIMARY) {
            throw new RuntimeException('--allow-primary applies only to the talenthub primary schema.');
        }
        $allowed = $schema === self::DISPOSABLE_SCHEMA
            || $schema === ProtectedDatabasePolicy::PRIMARY
            || ($driver === 'sqlite' && $schema === self::SQLITE_TEST_SCHEMA);
        if (!$allowed) {
            throw new RuntimeException('Activity catalog target schema is not approved.');
        }
        if ($forWrite && $schema === ProtectedDatabasePolicy::PRIMARY
            && !ProtectedDatabasePolicy::allowsExplicitPrimaryWrite($schema, $this->allowPrimary)) {
            throw new RuntimeException('Writing talenthub requires explicit --allow-primary approval.');
        }
        if ($driver === 'mysql') {
            $actual = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($actual !== $schema) {
                throw new RuntimeException("PDO schema mismatch: expected {$schema}, got {$actual}.");
            }
        } elseif ($driver !== 'sqlite' || $schema !== self::SQLITE_TEST_SCHEMA) {
            throw new RuntimeException('Activity catalog seeder supports pinned MySQL or its isolated SQLite contract fixture only.');
        }
    }

    private function assertRequiredTables(): void
    {
        $required = [
            'schools', 'teacher_profiles', 'activities', 'activity_details',
            'activity_registration_policies', 'activity_experience_policies', 'activity_qr_sessions',
            'activity_registrations', 'checkins', 'experience_logs', 'assessments',
            'assessment_scores', 'notifications',
        ];
        foreach ($required as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException("Required Phase 4A table is missing: {$table}.");
            }
        }
    }

    private function assertUtcSession(): void
    {
        if ($this->isMysql() && $this->pdo->query('SELECT @@session.time_zone')->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Activity catalog seeder requires MySQL session time zone +00:00.');
        }
    }

    /** @param list<array<string,mixed>> $records */
    private function assertDatasetShape(array $records): void
    {
        $existing = array_filter($records, static fn (array $row): bool => ($row['source'] ?? null) === 'existing');
        $new = array_filter($records, static fn (array $row): bool => ($row['source'] ?? null) === 'new');
        $published = array_filter($records, static fn (array $row): bool => ($row['activity']['status'] ?? null) === 'published');
        $completed = array_filter($records, static fn (array $row): bool => ($row['activity']['status'] ?? null) === 'completed');
        if (count($records) !== 17 || count($existing) !== 5 || count($new) !== 12 || count($published) !== 15 || count($completed) !== 2) {
            throw new RuntimeException('Activity catalog dataset cardinality is incompatible with Phase 4A.');
        }
    }

    /** @param list<array<string,mixed>> $records */
    private function assertParents(array $records): void
    {
        foreach ($records as $record) {
            $schoolId = (string) $record['school_id'];
            $teacherId = (string) $record['details']['responsibleTeacherId'];
            $school = $this->selectOne('SELECT id, status FROM schools WHERE id = :id', ['id' => $schoolId]);
            if ($school === null || (string) $school['status'] !== 'active') {
                throw new RuntimeException("Missing active school parent: {$schoolId}.");
            }
            $teacher = $this->selectOne('SELECT id, schoolId FROM teacher_profiles WHERE id = :id', ['id' => $teacherId]);
            if ($teacher === null || (string) $teacher['schoolId'] !== $schoolId) {
                throw new RuntimeException("Responsible teacher {$teacherId} does not belong to activity school {$schoolId}.");
            }
        }
    }

    /** @param list<array<string,mixed>> $records */
    private function assertExistingActivitySnapshots(array $records): void
    {
        foreach ($records as $record) {
            if (($record['source'] ?? null) !== 'existing') {
                continue;
            }
            $expected = $record['existingActivitySnapshot'] ?? null;
            $actual = $this->activityById((string) $record['activity']['id']);
            if (!is_array($expected) || $actual === null || !$this->sameActivitySnapshot($expected, $actual)) {
                throw new RuntimeException('Existing activity snapshot mismatch: ' . $record['activity']['id'] . '.');
            }
        }
    }

    /** @param list<array<string,mixed>> $records */
    private function assertNewActivityCollisions(array $records): void
    {
        foreach ($records as $record) {
            if (($record['source'] ?? null) !== 'new') {
                continue;
            }
            $actual = $this->activityById((string) $record['activity']['id']);
            if ($actual === null) {
                continue;
            }
            $activity = $record['activity'];
            $expectedStatic = [
                'id' => $activity['id'],
                'schoolId' => $record['school_id'],
                'createdByTeacherId' => $record['details']['responsibleTeacherId'],
                'title' => $activity['title'],
                'category' => $activity['category'],
                'capacity' => $activity['capacity'],
                'status' => 'published',
            ];
            foreach ($expectedStatic as $field => $expected) {
                if ((string) $actual[$field] !== (string) $expected) {
                    throw new RuntimeException("New activity UUID collision has incompatible {$field}: {$activity['id']}.");
                }
            }
            if ($this->parseUtc((string) $actual['endAt']) <= $this->parseUtc((string) $actual['startAt'])) {
                throw new RuntimeException('Existing new-catalog activity has an invalid activity window: ' . $activity['id'] . '.');
            }
        }
    }

    /** @param list<array<string,mixed>> $records */
    private function assertExistingChildren(array $records): void
    {
        foreach ($records as $record) {
            $id = (string) $record['activity']['id'];
            $status = (string) $record['activity']['status'];
            if ($status === 'completed') {
                if ($this->selectOne('SELECT activityId FROM activity_registration_policies WHERE activityId=:id', ['id' => $id]) !== null
                    || $this->selectOne('SELECT activityId FROM activity_experience_policies WHERE activityId=:id', ['id' => $id]) !== null) {
                    throw new RuntimeException("Completed activity must not receive new policies: {$id}.");
                }
                continue;
            }
            $activity = $this->activityById($id);
            if ($activity !== null) {
                $close = $this->parseUtc((string) $activity['startAt'])->modify('-24 hours');
                $policy = $this->selectOne('SELECT * FROM activity_registration_policies WHERE activityId=:id', ['id' => $id]);
                if ($policy !== null) {
                    $close = $this->parseUtc((string) $policy['registrationClosesAt']);
                    if ((string) $policy['approvalMode'] !== (string) $record['policy']['approvalMode']
                        || $this->parseUtc((string) $policy['registrationOpensAt']) > $this->clock
                        || $close >= $this->parseUtc((string) $activity['startAt'])) {
                        throw new RuntimeException("Existing registration policy is incompatible: {$id}.");
                    }
                }
                if ($close <= $this->clock) {
                    throw new RuntimeException("Registration window has expired; schedule approval is required: {$id}.");
                }
            }
        }
    }

    /** @param array<string,mixed> $record */
    private function insertActivity(array $record): void
    {
        $activity = $record['activity'];
        $start = $this->clock->modify(sprintf('%+d days', (int) $activity['start_offset_days']));
        $end = $start->modify('+' . (int) round((float) $activity['duration_hours'] * 3600) . ' seconds');
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO activities
                (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status, createdAt, updatedAt)
            VALUES
                (:id, :schoolId, :teacherId, :title, :category, :startAt, :endAt, :capacity, 'published', :createdAt, :updatedAt)
        SQL);
        $statement->execute([
            'id' => $activity['id'],
            'schoolId' => $record['school_id'],
            'teacherId' => $record['details']['responsibleTeacherId'],
            'title' => $activity['title'],
            'category' => $activity['category'],
            'startAt' => $this->formatUtc($start),
            'endAt' => $this->formatUtc($end),
            'capacity' => $activity['capacity'],
            'createdAt' => $this->formatUtc($this->clock),
            'updatedAt' => $this->formatUtc($this->clock),
        ]);
    }

    /** @param array<string,mixed> $record */
    private function insertOrValidateDetails(array $record): void
    {
        $id = (string) $record['activity']['id'];
        $details = $record['details'];
        $expected = [
            'activityId' => $id,
            'responsibleTeacherId' => $details['responsibleTeacherId'],
            'audienceScope' => $details['audienceScope'],
            'displayCategory' => $details['displayCategory'],
            'filterCategory' => $details['filterCategory'],
            'summary' => $details['summary'],
            'description' => $details['description'],
            'experienceHighlights' => $this->json($details['experienceHighlights']),
            'skillTags' => $this->json($details['skillTags']),
            'eligibilityRules' => $this->json($details['eligibilityRules']),
            'benefitItems' => $this->json($details['benefitItems']),
            'locationName' => $details['locationName'],
            'locationAddress' => $details['locationAddress'],
            'deliveryMode' => $details['deliveryMode'],
            'onlineMeetingUrl' => null,
            'organizerName' => $details['organizerName'],
            'organizerContact' => $details['organizerContact'],
            'organizerEmail' => $details['organizerEmail'],
            'organizerPhone' => $details['organizerPhone'],
            'coverImageUrl' => $details['coverImageUrl'],
            'coverImageAlt' => $details['coverImageAlt'],
            'feeAmount' => number_format((float) $details['feeAmount'], 2, '.', ''),
            'currency' => $details['currency'],
            'targetAudience' => $details['targetAudience'],
            'certificateLabel' => $details['certificateLabel'],
        ];
        $actual = $this->selectOne('SELECT * FROM activity_details WHERE activityId=:id', ['id' => $id]);
        if ($actual !== null) {
            foreach ($expected as $field => $value) {
                if (!$this->sameNullableValue($field, $value, $actual[$field] ?? null)) {
                    throw new RuntimeException("Existing activity detail is incompatible at {$field}: {$id}.");
                }
            }
            return;
        }
        $columns = array_keys($expected);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO activity_details (' . implode(',', $columns) . ',createdAt,updatedAt) VALUES ('
            . implode(',', $placeholders) . ',:createdAt,:updatedAt)';
        $expected['createdAt'] = $this->formatUtc($this->clock);
        $expected['updatedAt'] = $this->formatUtc($this->clock);
        $this->pdo->prepare($sql)->execute($expected);
    }

    /** @param array<string,mixed> $record */
    private function insertOrValidateRegistrationPolicy(array $record): void
    {
        $id = (string) $record['activity']['id'];
        $activity = $this->activityById($id) ?? throw new RuntimeException("Activity missing before policy insert: {$id}.");
        $start = $this->parseUtc((string) $activity['startAt']);
        $close = $start->modify('-24 hours');
        if ($close <= $this->clock) {
            throw new RuntimeException("Registration window has expired; schedule approval is required: {$id}.");
        }
        $actual = $this->selectOne('SELECT * FROM activity_registration_policies WHERE activityId=:id', ['id' => $id]);
        if ($actual !== null) {
            if ((string) $actual['approvalMode'] !== (string) $record['policy']['approvalMode']
                || $this->parseUtc((string) $actual['registrationOpensAt']) > $this->clock
                || $this->parseUtc((string) $actual['registrationClosesAt']) != $close
                || $this->parseUtc((string) $actual['cancellationClosesAt']) > $start) {
                throw new RuntimeException("Existing registration policy is incompatible: {$id}.");
            }
            return;
        }
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO activity_registration_policies
                (activityId,registrationOpensAt,registrationClosesAt,cancellationClosesAt,approvalMode,createdAt,updatedAt)
            VALUES (:id,:opensAt,:closesAt,:cancellationAt,:approvalMode,:createdAt,:updatedAt)
        SQL)->execute([
            'id' => $id,
            'opensAt' => $this->formatUtc($this->clock->modify('-7 days')),
            'closesAt' => $this->formatUtc($close),
            'cancellationAt' => $this->formatUtc($close),
            'approvalMode' => $record['policy']['approvalMode'],
            'createdAt' => $this->formatUtc($this->clock),
            'updatedAt' => $this->formatUtc($this->clock),
        ]);
    }

    /** @param array<string,mixed> $record */
    private function insertOrValidateExperiencePolicy(array $record): void
    {
        $id = (string) $record['activity']['id'];
        $hours = number_format((float) $record['policy']['confirmedHours'], 2, '.', '');
        $actual = $this->selectOne('SELECT * FROM activity_experience_policies WHERE activityId=:id', ['id' => $id]);
        if ($actual !== null) {
            if (number_format((float) $actual['confirmedHours'], 2, '.', '') !== $hours) {
                throw new RuntimeException("Existing experience policy is incompatible: {$id}.");
            }
            return;
        }
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO activity_experience_policies (activityId,confirmedHours,createdAt,updatedAt)
            VALUES (:id,:hours,:createdAt,:updatedAt)
        SQL)->execute([
            'id' => $id,
            'hours' => $hours,
            'createdAt' => $this->formatUtc($this->clock),
            'updatedAt' => $this->formatUtc($this->clock),
        ]);
    }

    /** @param array{id:string,activityId:string} $fixture @param array{token:string,tokenHash:string}|null $prepared */
    private function insertOrValidateQrFixture(array $fixture, ?array $prepared): void
    {
        $activity = $this->activityById($fixture['activityId']) ?? throw new RuntimeException('QR fixture activity is missing.');
        $teacher = $this->selectOne('SELECT schoolId FROM teacher_profiles WHERE id=:id', ['id' => $activity['createdByTeacherId']]);
        if ($teacher === null || (string) $teacher['schoolId'] !== (string) $activity['schoolId']) {
            throw new RuntimeException('QR fixture teacher and activity must belong to the same school.');
        }
        $policy = $this->selectOne('SELECT approvalMode FROM activity_registration_policies WHERE activityId=:id', ['id' => $fixture['activityId']]);
        if ($policy === null || (string) $policy['approvalMode'] !== 'automatic') {
            throw new RuntimeException('QR fixture activity must use automatic approval.');
        }
        $actual = $this->selectOne('SELECT * FROM activity_qr_sessions WHERE id=:id', ['id' => $fixture['id']]);
        if ($actual !== null) {
            if ((string) $actual['activityId'] !== $fixture['activityId']
                || (string) $actual['createdByTeacherId'] !== (string) $activity['createdByTeacherId']
                || preg_match('/\A[a-f0-9]{64}\z/', (string) $actual['tokenHash']) !== 1
                || (string) $actual['status'] !== 'active'
                || $actual['revokedAt'] !== null
                || (int) $actual['maxScans'] > (int) $activity['capacity']
                || (int) $actual['usedScans'] > (int) $actual['maxScans']
                || $this->parseUtc((string) $actual['expiresAt']) <= $this->clock) {
                throw new RuntimeException('Existing stable QR fixture is incompatible: ' . $fixture['id'] . '.');
            }
            return;
        }
        if ($prepared === null
            || $prepared['token'] === ''
            || preg_match('/\A[a-f0-9]{64}\z/', $prepared['tokenHash']) !== 1
            || !hash_equals(hash('sha256', $prepared['token']), $prepared['tokenHash'])) {
            throw new RuntimeException('QR fixture insert requires a prepared one-time handoff token.');
        }
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO activity_qr_sessions
                (id,activityId,createdByTeacherId,tokenHash,status,expiresAt,maxScans,usedScans,revokedAt,createdAt,updatedAt)
            VALUES (:id,:activityId,:teacherId,:tokenHash,'active',:expiresAt,:maxScans,0,NULL,:createdAt,:updatedAt)
        SQL)->execute([
            'id' => $fixture['id'],
            'activityId' => $fixture['activityId'],
            'teacherId' => $activity['createdByTeacherId'],
            'tokenHash' => $prepared['tokenHash'],
            'expiresAt' => $activity['endAt'],
            'maxScans' => $activity['capacity'],
            'createdAt' => $this->formatUtc($this->clock),
            'updatedAt' => $this->formatUtc($this->clock),
        ]);
    }

    /** @param list<array<string,mixed>> $records @return list<array{schoolId:string,schoolName:string,activityId:string,activityTitle:string,sessionId:string,expiresAt:string}> */
    private function qrHandoffEntries(array $records): array
    {
        $byActivityId = [];
        foreach ($records as $record) {
            $byActivityId[(string) $record['activity']['id']] = $record;
        }
        $entries = [];
        foreach (self::QR_FIXTURES as $fixture) {
            $record = $byActivityId[$fixture['activityId']] ?? null;
            if (!is_array($record)) {
                throw new RuntimeException('QR fixture activity is absent from the canonical dataset.');
            }
            $activity = $record['activity'];
            $start = $this->clock->modify(sprintf('%+d days', (int) $activity['start_offset_days']));
            $expiresAt = $start->modify('+' . (int) round((float) $activity['duration_hours'] * 3600) . ' seconds');
            $entries[] = [
                'schoolId' => (string) $record['school_id'],
                'schoolName' => (string) $record['school_name'],
                'activityId' => (string) $activity['id'],
                'activityTitle' => (string) $activity['title'],
                'sessionId' => $fixture['id'],
                'expiresAt' => $this->formatUtc($expiresAt),
            ];
        }
        return $entries;
    }

    /** @return array<string,mixed>|null */
    private function activityById(string $id): ?array
    {
        return $this->selectOne('SELECT * FROM activities WHERE id=:id', ['id' => $id]);
    }

    /** @param array<string,mixed> $expected @param array<string,mixed> $actual */
    private function sameActivitySnapshot(array $expected, array $actual): bool
    {
        foreach ($expected as $field => $value) {
            if ($field === 'capacity') {
                if ((int) ($actual[$field] ?? -1) !== (int) $value) {
                    return false;
                }
            } elseif ((string) ($actual[$field] ?? '') !== (string) $value) {
                return false;
            }
        }
        return true;
    }

    private function sameNullableValue(string $field, mixed $expected, mixed $actual): bool
    {
        if ($expected === null || $actual === null) {
            return $expected === $actual;
        }
        if ($field === 'feeAmount') {
            return number_format((float) $expected, 2, '.', '') === number_format((float) $actual, 2, '.', '');
        }
        if (in_array($field, ['experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems'], true)) {
            return $this->json(json_decode((string) $expected, true, 512, JSON_THROW_ON_ERROR))
                === $this->json(json_decode((string) $actual, true, 512, JSON_THROW_ON_ERROR));
        }
        return (string) $expected === (string) $actual;
    }

    /** @param array<string,mixed> $parameters @return array<string,mixed>|null */
    private function selectOne(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function tableExists(string $table): bool
    {
        if ($this->isMysql()) {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        } else {
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table");
        }
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function countTable(string $table): int
    {
        if (preg_match('/\A[a-z_]+\z/', $table) !== 1) {
            throw new RuntimeException('Unsafe table identifier.');
        }
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    private function fixtureQrCount(): int
    {
        $ids = array_column(self::QR_FIXTURES, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM activity_qr_sessions WHERE id IN ({$placeholders})");
        $statement->execute($ids);
        return (int) $statement->fetchColumn();
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function parseUtc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function formatUtc(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
