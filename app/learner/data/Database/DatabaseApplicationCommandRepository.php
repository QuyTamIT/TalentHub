<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\InternshipApplicationCommandRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Learner\Data\Support\Uuid;
use TalentHub\Support\Uuid as SupportUuid;

class DatabaseApplicationCommandRepository implements InternshipApplicationCommandRepository
{
    private const CONSENT_SCOPE = 'application_profile_share';
    private const CONSENT_POLICY = 'application-profile-share-1.0';
    private const SNAPSHOT_VERSION = '1.0.0';

    public function __construct(
        protected readonly PDO $pdo,
        protected readonly ?NotificationService $notifications = null
    ) {}


    public function grantApplicationProfileConsent(string $studentId, string $userId, string $requestId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'studentId');
        $this->pdo->beginTransaction();
        try {
            $student = $this->lockStudent($studentId);
            if ($student === null || (string) $student['userId'] !== $userId) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
            }
            $active = $this->lockConsent($studentId);
            if ($active !== null) {
                $this->pdo->commit();
                return $this->consentView($active);
            }
            $id = SupportUuid::v4();
            $now = $this->now();
            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO privacy_consents
                    (id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt, createdAt)
                VALUES
                    (:id, :studentId, :scope, 1, :policyVersion, :grantedAt, NULL, :createdAt)
            SQL);
            $statement->execute([
                'id' => $id,
                'studentId' => $studentId,
                'scope' => self::CONSENT_SCOPE,
                'policyVersion' => self::CONSENT_POLICY,
                'grantedAt' => $now,
                'createdAt' => $now,
            ]);
            $this->pdo->commit();
            return ['id' => $id, 'scope' => self::CONSENT_SCOPE, 'policyVersion' => self::CONSENT_POLICY, 'isGranted' => true, 'grantedAt' => $now];
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function submit(string $studentId, string $userId, string $requestId, string $postId, string $message): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'studentId');
        $postId = Uuid::normalizeDatabase($postId, 'postId');
        $this->pdo->beginTransaction();
        try {
            $post = $this->lockPost($postId);
            if ($post === null || (string) $post['status'] !== 'active' || $this->utc((string) $post['deadline']) < new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                throw new ApiException(422, 'OPPORTUNITY_NOT_AVAILABLE', 'Cơ hội không còn nhận hồ sơ.');
            }
            $student = $this->lockStudent($studentId);
            if ($student === null || (string) $student['userId'] !== $userId) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
            }
            if (!$this->studentCanAccessPost($studentId, $post)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Cơ hội không thuộc phạm vi trường của học viên.');
            }
            if ($this->hasAcceptedPlacement($studentId)) {
                throw new ApiException(
                    409,
                    'INTERNSHIP_PLACEMENT_LOCKED',
                    'Bạn đã xác nhận một vị trí thực tập và không thể nộp thêm hồ sơ mới.'
                );
            }
            $consent = $this->lockConsent($studentId);
            if ($consent === null) {
                throw new ApiException(422, 'CONSENT_REQUIRED', 'Cần xác nhận chia sẻ hồ sơ trước khi ứng tuyển.');
            }
            if ($this->hasDuplicate($postId, $studentId)) {
                throw new ApiException(409, 'DUPLICATE_APPLICATION', 'Bạn đã ứng tuyển cơ hội này.');
            }

            $applicationId = SupportUuid::v4();
            $now = $this->now();
            $snapshot = $this->buildSnapshot($studentId, (string) $consent['id'], $now);
            $application = $this->pdo->prepare(<<<'SQL'
                INSERT INTO internship_applications
                    (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt)
                VALUES
                    (:id, :postId, :studentId, 'submitted', :message, :appliedAt, :createdAt, :updatedAt)
            SQL);
            $application->execute([
                'id' => $applicationId,
                'postId' => $postId,
                'studentId' => $studentId,
                'message' => $message === '' ? null : $message,
                'appliedAt' => $now,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
            $snapshotStatement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO application_profile_snapshots
                    (id, applicationId, consentId, schemaVersion, snapshotPayload, createdAt)
                VALUES
                    (:id, :applicationId, :consentId, :schemaVersion, :snapshotPayload, :createdAt)
            SQL);
            $snapshotStatement->execute([
                'id' => SupportUuid::v4(),
                'applicationId' => $applicationId,
                'consentId' => $consent['id'],
                'schemaVersion' => self::SNAPSHOT_VERSION,
                'snapshotPayload' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'createdAt' => $now,
            ]);
            $this->ensureEnterpriseApplicationConsent(
                $studentId,
                (string) $post['enterpriseId'],
                (string) $consent['id'],
                $now
            );
            $this->appendHistory($applicationId, null, 'submitted', $userId, 'student', 'Ứng tuyển cơ hội', $now);

            $this->getNotificationService()->publish(
                $userId,
                'internship_application_submitted',
                'Ứng tuyển cơ hội thành công',
                'Hồ sơ ứng tuyển cho vị trí ' . ($post['title'] ?? '') . ' đã được gửi.',
                '/app/learner/ecosystem.php',
                'internship_application:' . $applicationId,
                $studentId
            );
            $this->notifyEnterpriseMembers((string) $post['enterpriseId'], $applicationId, (string) ($post['title'] ?? ''));

            $this->pdo->commit();
            return $this->readOneForStudent($studentId, $applicationId);
        } catch (PDOException $exception) {
            $this->rollback();
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ApiException(409, 'DUPLICATE_APPLICATION', 'Bạn đã ứng tuyển cơ hội này.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function readForStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'studentId');
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT ia.id, ia.postId, ia.status, ia.message, ia.appliedAt, ia.updatedAt,
                   ip.title, ip.location, ip.deadline, ip.enterpriseId, e.name AS enterpriseName
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN enterprises e ON e.id = ip.enterpriseId
            WHERE ia.studentId = :studentId
            ORDER BY ia.appliedAt DESC, ia.id
        SQL);
        $statement->execute(['studentId' => $studentId]);
        return ['items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public function readOneForStudent(string $studentId, string $applicationId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'studentId');
        $applicationId = Uuid::normalizeDatabase($applicationId, 'applicationId');
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT ia.id, ia.postId, ia.status, ia.message, ia.reviewedAt,
                   ia.appliedAt, ia.updatedAt, ip.title, ip.location, ip.deadline,
                   ip.status AS postStatus, ip.enterpriseId, e.name AS enterpriseName,
                   aps.schemaVersion, aps.snapshotPayload, aps.createdAt AS snapshotCreatedAt
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN enterprises e ON e.id = ip.enterpriseId
            INNER JOIN application_profile_snapshots aps ON aps.applicationId = ia.id
            WHERE ia.studentId = :studentId AND ia.id = :applicationId
            LIMIT 1
        SQL);
        $statement->execute(['studentId' => $studentId, 'applicationId' => $applicationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
        }
        $row['snapshot'] = json_decode((string) $row['snapshotPayload'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['snapshotPayload']);
        $row['history'] = $this->history($applicationId);
        return $row;
    }

    public function withdraw(string $studentId, string $userId, string $requestId, string $applicationId, string $reason): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'studentId');
        $applicationId = Uuid::normalizeDatabase($applicationId, 'applicationId');
        $this->pdo->beginTransaction();
        try {
            $application = $this->lockApplication($studentId, $applicationId);
            if ($application === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
            }
            $fromStatus = (string) $application['status'];
            if (!in_array($fromStatus, ['submitted', 'reviewing', 'interview'], true)) {
                throw new ApiException(422, 'ILLEGAL_STATUS_TRANSITION', 'Không thể rút hồ sơ ở trạng thái hiện tại.');
            }
            $now = $this->now();
            $update = $this->pdo->prepare("UPDATE internship_applications SET status = 'withdrawn', updatedAt = :updatedAt WHERE id = :id AND studentId = :studentId AND status = :expectedStatus");
            $update->execute(['updatedAt' => $now, 'id' => $applicationId, 'studentId' => $studentId, 'expectedStatus' => $fromStatus]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái hồ sơ đã thay đổi.');
            }
            $this->appendHistory($applicationId, $fromStatus, 'withdrawn', $userId, 'student', $reason === '' ? 'Rút hồ sơ' : $reason, $now);

            $this->getNotificationService()->publish(
                $userId,
                'internship_application_withdrawn',
                'Rút hồ sơ ứng tuyển',
                'Bạn đã rút hồ sơ ứng tuyển cho cơ hội thực tập.',
                '/app/learner/ecosystem.php',
                'internship_application_withdrawn:' . $applicationId,
                $studentId
            );

            $this->pdo->commit();
            return $this->readOneForStudent($studentId, $applicationId);
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function lockPost(string $postId): ?array
    {
        $audience = $this->hasColumn('internship_posts', 'audience') ? 'ip.audience' : "'public' AS audience";
        $statement = $this->pdo->prepare(
            "SELECT ip.id, ip.enterpriseId, ip.title, ip.status, ip.deadline, {$audience},
                    e.status AS enterpriseStatus, e.verificationStatus AS enterpriseVerificationStatus
             FROM internship_posts ip
             INNER JOIN enterprises e ON e.id = ip.enterpriseId
             WHERE ip.id = :id LIMIT 1" . $this->lockSuffix()
        );
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $post */
    private function studentCanAccessPost(string $studentId, array $post): bool
    {
        if (($post['enterpriseStatus'] ?? '') !== 'active'
            || ($post['enterpriseVerificationStatus'] ?? '') !== 'verified'
        ) {
            return false;
        }
        if (($post['audience'] ?? 'public') !== 'partner_schools') {
            return true;
        }
        if (!$this->hasTable('internship_post_target_schools')) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM student_profiles sp
             INNER JOIN classes c ON c.id = sp.classId
             INNER JOIN internship_post_target_schools target ON target.schoolId = c.schoolId
             WHERE sp.id = :studentId AND target.postId = :postId
             LIMIT 1'
        );
        $statement->execute(['studentId' => $studentId, 'postId' => $post['id']]);
        return $statement->fetchColumn() !== false;
    }

    private function ensureEnterpriseApplicationConsent(string $studentId, string $enterpriseId, string $consentId, string $now): void
    {
        if (!$this->hasTable('enterprise_talent_access_grants')) {
            return;
        }
        $expiresAt = (new DateTimeImmutable($now, new DateTimeZone('UTC')))->modify('+90 days')->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'SELECT id FROM enterprise_talent_access_grants
             WHERE studentId = :studentId AND enterpriseId = :enterpriseId AND scope = :scope
             LIMIT 1' . $this->lockSuffix()
        );
        $parameters = ['studentId' => $studentId, 'enterpriseId' => $enterpriseId, 'scope' => self::CONSENT_SCOPE];
        $statement->execute($parameters);
        $grantId = $statement->fetchColumn();
        if (is_string($grantId) && $grantId !== '') {
            $update = $this->pdo->prepare(
                'UPDATE enterprise_talent_access_grants
                 SET consentId = :consentId, grantedAt = :grantedAt, expiresAt = :expiresAt,
                     revokedAt = NULL, updatedAt = :updatedAt
                 WHERE id = :id'
            );
            $update->execute(['consentId' => $consentId, 'grantedAt' => $now, 'expiresAt' => $expiresAt, 'updatedAt' => $now, 'id' => $grantId]);
            return;
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO enterprise_talent_access_grants
                (id, studentId, enterpriseId, consentId, scope, grantedAt, expiresAt, revokedAt, createdAt, updatedAt)
             VALUES
                (:id, :studentId, :enterpriseId, :consentId, :scope, :grantedAt, :expiresAt, NULL, :createdAt, :updatedAt)'
        );
        $insert->execute($parameters + [
            'id' => SupportUuid::v4(),
            'consentId' => $consentId,
            'grantedAt' => $now,
            'expiresAt' => $expiresAt,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    private function notifyEnterpriseMembers(string $enterpriseId, string $applicationId, string $postTitle): void
    {
        if (!$this->hasTable('enterprise_members')) {
            return;
        }
        $statement = $this->pdo->prepare('SELECT userId FROM enterprise_members WHERE enterpriseId = :enterpriseId ORDER BY id');
        $statement->execute(['enterpriseId' => $enterpriseId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) ?: [] as $enterpriseUserId) {
            $this->getNotificationService()->publish(
                (string) $enterpriseUserId,
                'internship_application_submitted',
                'Có hồ sơ ứng tuyển mới',
                'Một học viên vừa ứng tuyển vị trí ' . $postTitle . '.',
                '/app/enterprise/applications.php',
                'enterprise_application_submitted:' . $applicationId . ':' . $enterpriseUserId
            );
        }
    }

    private function lockStudent(string $studentId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, userId FROM student_profiles WHERE id = :id LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockConsent(string $studentId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, scope, policyVersion, isGranted, grantedAt ' .
            'FROM privacy_consents ' .
            'WHERE studentId = :studentId AND scope = \'application_profile_share\' ' .
            '  AND isGranted = 1 AND grantedAt IS NOT NULL AND revokedAt IS NULL ' .
            'ORDER BY createdAt DESC, id DESC LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function consentView(array $row): array
    {
        return ['id' => (string) $row['id'], 'scope' => (string) $row['scope'], 'policyVersion' => (string) $row['policyVersion'], 'isGranted' => true, 'grantedAt' => (string) $row['grantedAt']];
    }

    private function hasDuplicate(string $postId, string $studentId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM internship_applications WHERE postId = :postId AND studentId = :studentId AND status NOT IN (\'rejected\', \'declined\', \'withdrawn\', \'cancelled\') LIMIT 1' . $this->lockSuffix());
        $statement->execute(['postId' => $postId, 'studentId' => $studentId]);
        return $statement->fetchColumn() !== false;
    }

    private function hasAcceptedPlacement(string $studentId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM internship_applications "
            . "WHERE studentId = :studentId AND status = 'accepted' LIMIT 1" . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId]);
        return $statement->fetchColumn() !== false;
    }

    private function lockApplication(string $studentId, string $applicationId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, status FROM internship_applications WHERE id = :id AND studentId = :studentId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $applicationId, 'studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }


    private function buildSnapshot(string $studentId, string $consentId, string $capturedAt): array
    {
        $profileStatement = $this->pdo->prepare(<<<'SQL'
            SELECT sp.id AS studentProfileId, u.fullName, u.email, sp.phone, sp.dateOfBirth,
                   sp.studyStatus, c.name AS className, s.name AS schoolName,
                   spd.headline, spd.location, spd.bio, spd.avatarUrl
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
            WHERE sp.id = :studentId LIMIT 1
        SQL);
        $profileStatement->execute(['studentId' => $studentId]);
        $student = $profileStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($student)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
        }
        $skills = array_map(
            static fn (array $skill): array => [
                'skillName' => (string) $skill['skillName'],
                'level' => self::snapshotSkillLevel((float) $skill['levelScore']),
                'category' => (string) $skill['category'],
            ],
            $this->fetchAll("SELECT s.name AS skillName, ss.levelScore, s.category FROM student_skills ss INNER JOIN skills s ON s.id = ss.skillId WHERE ss.studentId = :studentId AND s.status = 'active' ORDER BY s.category, s.name, s.id", ['studentId' => $studentId])
        );
        $certificates = $this->fetchAll("SELECT id AS certificateId, title AS name, issuingOrganization, issueDate, credentialUrl FROM certificates WHERE studentId = :studentId AND verificationStatus = 'verified' ORDER BY issueDate DESC, id", ['studentId' => $studentId]);
        $projects = $this->fetchAll("SELECT p.id AS projectId, p.title, p.category, pm.role, p.description AS summary, p.projectUrl AS link FROM projects p INNER JOIN project_members pm ON pm.projectId = p.id WHERE pm.studentId = :studentId AND pm.status = 'active' AND p.status IN ('in_progress', 'completed') ORDER BY p.createdAt DESC, p.id", ['studentId' => $studentId]);
        $student['avatarUrl'] = self::safeSnapshotUrl($student['avatarUrl'] ?? null);
        $certificates = array_map(static function (array $certificate): array {
            $certificate['credentialUrl'] = self::safeSnapshotUrl($certificate['credentialUrl'] ?? null);
            return $certificate;
        }, $certificates);
        $projects = array_map(static function (array $project): array {
            $project['link'] = self::safeSnapshotUrl($project['link'] ?? null);
            return $project;
        }, $projects);
        $experience = $this->pdo->prepare("SELECT COALESCE(SUM(hours), 0) AS totalConfirmedHours, COUNT(DISTINCT activityId) AS totalActivitiesAttended FROM experience_logs WHERE studentId = :studentId AND status = 'confirmed'");
        $experience->execute(['studentId' => $studentId]);
        $experienceRow = $experience->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'schemaVersion' => self::SNAPSHOT_VERSION,
            'capturedAt' => str_replace(' ', 'T', $capturedAt) . 'Z',
            'consentId' => $consentId,
            'student' => $student,
            'skills' => $skills,
            'certificates' => $certificates,
            'projects' => $projects,
            'experience' => ['totalConfirmedHours' => round((float) ($experienceRow['totalConfirmedHours'] ?? 0), 2), 'totalActivitiesAttended' => (int) ($experienceRow['totalActivitiesAttended'] ?? 0)],
        ];
    }

    private function appendHistory(string $applicationId, ?string $fromStatus, string $toStatus, string $userId, string $role, string $note, string $now): void
    {
        $statement = $this->pdo->prepare('INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, note, createdAt) VALUES (:id, :applicationId, :fromStatus, :toStatus, :changedByUserId, :changedByRole, :note, :createdAt)');
        $statement->execute(['id' => SupportUuid::v4(), 'applicationId' => $applicationId, 'fromStatus' => $fromStatus, 'toStatus' => $toStatus, 'changedByUserId' => $userId, 'changedByRole' => $role, 'note' => $note, 'createdAt' => $now]);
    }

    private function history(string $applicationId): array
    {
        $statement = $this->pdo->prepare('SELECT fromStatus, toStatus, changedByRole, createdAt FROM application_status_history WHERE applicationId = :applicationId ORDER BY createdAt, id');
        $statement->execute(['applicationId' => $applicationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchAll(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function snapshotSkillLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'advanced',
            $score >= 50 => 'intermediate',
            default => 'beginner',
        };
    }

    private static function safeSnapshotUrl(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 500) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        return $value;
    }

    private function rollback(): void { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
    private function now(): string { return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'); }
    private function utc(string $value): DateTimeImmutable { return new DateTimeImmutable($value, new DateTimeZone('UTC')); }
    private function lockSuffix(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE'; }

    private function hasTable(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:table LIMIT 1");
            $statement->execute(['table' => $table]);
            return $statement->fetchColumn() !== false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table LIMIT 1');
        $statement->execute(['table' => $table]);
        return $statement->fetchColumn() !== false;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (($row['name'] ?? null) === $column) return true;
            }
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column LIMIT 1');
        $statement->execute(['table' => $table, 'column' => $column]);
        return $statement->fetchColumn() !== false;
    }

    protected function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__) . '/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__) . '/Service/NotificationService.php';
            require_once dirname(__DIR__) . '/Database/DatabaseNotificationRepository.php';
        }
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }
}
