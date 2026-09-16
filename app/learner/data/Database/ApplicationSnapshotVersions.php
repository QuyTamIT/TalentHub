<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use TalentHub\Learner\Data\ReadModel\ApplicationProfileSnapshot;
use TalentHub\Support\Uuid;

final class ApplicationSnapshotVersions
{
    public function __construct(private readonly PDO $pdo) {}

    /** SQL fragments for readers already scoped to an owned application, using alias aps. */
    public function selection(): array
    {
        $exists = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='application_profile_snapshot_versions'")->fetchColumn()
            : $this->pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='application_profile_snapshot_versions'")->fetchColumn();
        if (!$exists) return ['join'=>'', 'payload'=>'aps.snapshotPayload', 'version'=>'aps.schemaVersion'];
        return [
            'join'=>"LEFT JOIN application_profile_snapshot_versions apsv ON apsv.id = (
                SELECT v.id FROM application_profile_snapshot_versions v
                WHERE v.sourceSnapshotId=aps.id ORDER BY v.revision DESC LIMIT 1)",
            'payload'=>'COALESCE(apsv.snapshotPayload, aps.snapshotPayload)',
            'version'=>'COALESCE(apsv.schemaVersion, aps.schemaVersion)',
        ];
    }

    /** Explicit maintenance operation; never runs during an Enterprise read or application submission. */
    public function recover(string $applicationId, string $expectedStudentId): array
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('Snapshot recovery requires its own transaction.');
        // All source reads must see the same committed state, including the historical guards.
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
                SELECT aps.*, ia.studentId, ia.appliedAt, sp.userId,
                       pc.studentId consentStudentId, pc.scope, pc.isGranted, pc.grantedAt, pc.revokedAt
                FROM application_profile_snapshots aps
                JOIN internship_applications ia ON ia.id=aps.applicationId
                JOIN student_profiles sp ON sp.id=ia.studentId
                JOIN privacy_consents pc ON pc.id=aps.consentId
                WHERE ia.id=? AND ia.studentId=? FOR UPDATE
            SQL);
            $statement->execute([$applicationId,$expectedStudentId]);
            $source = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$source || $source['consentStudentId'] !== $source['studentId']
                || $source['scope'] !== 'application_profile_share' || !(bool)$source['isGranted']
                || $source['revokedAt'] !== null || !$source['grantedAt'] || $source['grantedAt'] > $source['appliedAt']) {
                throw new RuntimeException('Application ownership or original consent is invalid.');
            }
            $existing = $this->pdo->prepare('SELECT id,schemaVersion,sourceHash FROM application_profile_snapshot_versions WHERE sourceSnapshotId=? AND schemaVersion=?');
            $existing->execute([$source['id'],ApplicationProfileSnapshot::VERSION]);
            $hash = hash('sha256', $source['snapshotPayload']);
            if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
                if (!hash_equals($row['sourceHash'], $hash)) throw new RuntimeException('Original snapshot has changed.');
                $this->pdo->commit();
                return ['id'=>$row['id'],'schemaVersion'=>$row['schemaVersion'],'created'=>false];
            }
            $original = json_decode($source['snapshotPayload'], true, 512, JSON_THROW_ON_ERROR);
            if ($source['schemaVersion'] !== '1.0.0' || ($original['consentId'] ?? '') !== $source['consentId']
                || new DateTimeImmutable($original['capturedAt'] ?? '') != new DateTimeImmutable($source['appliedAt'], new DateTimeZone('UTC'))) {
                throw new RuntimeException('Original snapshot version, consent or capture time is inconsistent.');
            }
            $recovery = (new HistoricalApplicationSnapshotSource($this->pdo))->read(
                $original, $source['studentId'], $source['userId'], $source['appliedAt']
            );
            $payload = ApplicationProfileSnapshot::build($recovery['data'], $source['consentId'], $source['appliedAt']);
            // Preserve the original personal fields byte-for-value, including explicit nulls.
            $payload['student'] = $original['student'];
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            $payload['reconstruction'] = [
                'sourceSnapshotId'=>$source['id'], 'sourceSchemaVersion'=>$source['schemaVersion'],
                'sourceHash'=>$hash, 'effectiveAt'=>$original['capturedAt'], 'reconstructedAt'=>$now,
                'method'=>'unchanged_pre_application_sources', 'sources'=>$recovery['proof'],
            ];
            $next = $this->pdo->prepare('SELECT COALESCE(MAX(revision),0)+1 FROM application_profile_snapshot_versions WHERE sourceSnapshotId=?');
            $next->execute([$source['id']]);
            $revision = (int)$next->fetchColumn();
            $id = Uuid::v4();
            $insert = $this->pdo->prepare('INSERT INTO application_profile_snapshot_versions (id,sourceSnapshotId,revision,schemaVersion,sourceHash,snapshotPayload,createdAt) VALUES (?,?,?,?,?,?,?)');
            $insert->execute([$id,$source['id'],$revision,ApplicationProfileSnapshot::VERSION,$hash,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),$now]);
            $this->pdo->commit();
            return ['id'=>$id,'schemaVersion'=>ApplicationProfileSnapshot::VERSION,'revision'=>$revision,'created'=>true];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
