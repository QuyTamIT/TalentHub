<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class EnterpriseTalentRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return array{id:string,name:string,status:string,verificationStatus:string}
     */
    public function enterpriseForUser(string $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT e.id, e.name, e.status, e.verificationStatus
            FROM enterprise_members em
            INNER JOIN enterprises e ON e.id = em.enterpriseId
            WHERE em.userId = :userId
            LIMIT 2
        SQL);
        $statement->execute(['userId' => $userId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (count($rows) !== 1) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản phải thuộc đúng một doanh nghiệp.');
        }

        $enterprise = $rows[0];
        if (($enterprise['status'] ?? '') !== 'active' || ($enterprise['verificationStatus'] ?? '') !== 'verified') {
            throw new ApiException(403, 'ENTERPRISE_NOT_VERIFIED', 'Chỉ doanh nghiệp đang hoạt động và đã được xác thực mới có quyền tìm kiếm nhân tài.');
        }

        return $enterprise;
    }

    public function studentIdForUser(string $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId = :userId LIMIT 1');
        $stmt->execute(['userId' => $userId]);
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
        }
        return $id;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listTalents(string $enterpriseId, array $filters = []): array
    {
        $now = $this->now();
        $hasPartnership = $this->tableExists('school_enterprise_partnerships');

        $where = [
            "u.status = 'active'",
            'accessGrant.enterpriseId = :enterpriseId',
            "accessGrant.scope = 'enterprise_talent_discovery'",
            'accessGrant.revokedAt IS NULL',
            'accessGrant.expiresAt > :now',
            "consent.scope = 'enterprise_talent_discovery'",
            'consent.isGranted = 1',
            'consent.revokedAt IS NULL',
        ];

        $params = [
            'enterpriseId' => $enterpriseId,
            'now' => $now,
        ];

        if ($hasPartnership) {
            $where[] = '(s.id IS NULL OR EXISTS (SELECT 1 FROM school_enterprise_partnerships sep WHERE sep.schoolId = s.id AND sep.enterpriseId = :enterpriseId AND sep.status = \'approved\'))';
        }

        if (isset($filters['school']) && is_string($filters['school']) && trim($filters['school']) !== '') {
            $schoolFilter = trim($filters['school']);
            if (Uuid::isValid($schoolFilter)) {
                $where[] = 's.id = :schoolIdFilter';
                $params['schoolIdFilter'] = $schoolFilter;
            } else {
                $where[] = 's.name LIKE :schoolNameFilter';
                $params['schoolNameFilter'] = '%' . $schoolFilter . '%';
            }
        }

        if (isset($filters['search']) && is_string($filters['search']) && trim($filters['search']) !== '') {
            $search = '%' . trim($filters['search']) . '%';
            $where[] = '(u.fullName LIKE :search1 OR spd.headline LIKE :search2 OR spd.bio LIKE :search3 OR s.name LIKE :search4)';
            $params['search1'] = $search;
            $params['search2'] = $search;
            $params['search3'] = $search;
            $params['search4'] = $search;
        }

        $whereClause = implode(' AND ', $where);

        $sql = <<<SQL
            SELECT
                student.id AS studentId,
                u.fullName AS displayName,
                s.id AS schoolId,
                s.name AS schoolName,
                c.id AS classId,
                c.name AS className,
                sp.studyStatus,
                spd.location,
                spd.headline,
                spd.bio,
                spd.avatarUrl,
                accessGrant.grantedAt,
                accessGrant.expiresAt,
                COUNT(DISTINCT verifiedSkill.id) AS verifiedSkillCount,
                EXISTS(
                    SELECT 1 FROM enterprise_talent_access_grants contactGrant
                    WHERE contactGrant.studentId = student.id
                      AND contactGrant.enterpriseId = :enterpriseIdContact
                      AND contactGrant.scope = 'enterprise_talent_contact'
                      AND contactGrant.revokedAt IS NULL
                      AND contactGrant.expiresAt > :nowContact
                ) AS contactAllowed,
                EXISTS(
                    SELECT 1 FROM enterprise_contact_requests cr
                    WHERE cr.studentId = student.id
                      AND cr.enterpriseId = :enterpriseIdCr
                      AND cr.status = 'pending'
                ) AS hasPendingContactRequest
            FROM student_profiles student
            INNER JOIN users u ON u.id = student.userId
            INNER JOIN enterprise_talent_access_grants accessGrant
              ON accessGrant.studentId = student.id
            INNER JOIN privacy_consents consent
              ON consent.id = accessGrant.consentId
             AND consent.studentId = student.id
            LEFT JOIN student_profiles sp ON sp.id = student.id
            LEFT JOIN classes c ON c.id = student.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = student.id
            LEFT JOIN student_skills verifiedSkill
              ON verifiedSkill.studentId = student.id
             AND verifiedSkill.verificationStatus = 'verified'
            WHERE {$whereClause}
            GROUP BY student.id, u.fullName, s.id, s.name, c.id, c.name, sp.studyStatus,
                     spd.location, spd.headline, spd.bio, spd.avatarUrl, accessGrant.grantedAt, accessGrant.expiresAt
        SQL;

        $params['enterpriseIdContact'] = $enterpriseId;
        $params['nowContact'] = $now;
        $params['enterpriseIdCr'] = $enterpriseId;

        // Sorting
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'newest';
        $orderClause = match ($sort) {
            'skills' => 'ORDER BY verifiedSkillCount DESC, u.fullName ASC',
            'name' => 'ORDER BY u.fullName ASC',
            default => 'ORDER BY accessGrant.grantedAt DESC, student.id ASC',
        };

        $stmt = $this->pdo->prepare("{$sql} {$orderClause}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Post-process skills filter and populate verifiedSkills list
        $filterSkills = [];
        if (isset($filters['skills'])) {
            $rawSkills = is_array($filters['skills']) ? $filters['skills'] : explode(',', (string) $filters['skills']);
            $filterSkills = array_values(array_filter(array_map('trim', $rawSkills)));
        }

        $items = [];
        foreach ($rows as $row) {
            $studentId = (string) $row['studentId'];
            $skills = $this->verifiedSkillsForStudent($studentId);

            if ($filterSkills !== []) {
                $hasAllSkills = true;
                $lowerSkills = array_map('mb_strtolower', $skills);
                foreach ($filterSkills as $requiredSkill) {
                    if (!in_array(mb_strtolower($requiredSkill), $lowerSkills, true)) {
                        $hasAllSkills = false;
                        break;
                    }
                }
                if (!$hasAllSkills) {
                    continue;
                }
            }

            $items[] = [
                'studentId' => $studentId,
                'displayName' => (string) ($row['displayName'] ?? 'Ứng viên'),
                'schoolName' => (string) ($row['schoolName'] ?? ''),
                'className' => (string) ($row['className'] ?? ''),
                'studyStatus' => (string) ($row['studyStatus'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'headline' => (string) ($row['headline'] ?? ''),
                'bio' => (string) ($row['bio'] ?? ''),
                'avatarUrl' => $row['avatarUrl'] !== null ? (string) $row['avatarUrl'] : null,
                'verifiedSkillCount' => (int) $row['verifiedSkillCount'],
                'verifiedSkills' => $skills,
                'contactAllowed' => (bool) ((int) ($row['contactAllowed'] ?? 0) === 1),
                'hasPendingContactRequest' => (bool) ((int) ($row['hasPendingContactRequest'] ?? 0) === 1),
            ];
        }

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    public function getTalentDetail(string $enterpriseId, string $studentId): ?array
    {
        $now = $this->now();
        $hasPartnership = $this->tableExists('school_enterprise_partnerships');

        $where = [
            'student.id = :studentId',
            "u.status = 'active'",
            'accessGrant.enterpriseId = :enterpriseId',
            "accessGrant.scope = 'enterprise_talent_discovery'",
            'accessGrant.revokedAt IS NULL',
            'accessGrant.expiresAt > :now',
            "consent.scope = 'enterprise_talent_discovery'",
            'consent.isGranted = 1',
            'consent.revokedAt IS NULL',
        ];

        $params = [
            'studentId' => $studentId,
            'enterpriseId' => $enterpriseId,
            'now' => $now,
        ];

        if ($hasPartnership) {
            $where[] = '(s.id IS NULL OR EXISTS (SELECT 1 FROM school_enterprise_partnerships sep WHERE sep.schoolId = s.id AND sep.enterpriseId = :enterpriseId AND sep.status = \'approved\'))';
        }

        $whereClause = implode(' AND ', $where);

        $sql = <<<SQL
            SELECT
                student.id AS studentId,
                u.fullName AS displayName,
                u.email,
                sp.phone,
                s.id AS schoolId,
                s.name AS schoolName,
                c.id AS classId,
                c.name AS className,
                sp.studyStatus,
                spd.location,
                spd.headline,
                spd.bio,
                spd.avatarUrl,
                EXISTS(
                    SELECT 1 FROM enterprise_talent_access_grants contactGrant
                    WHERE contactGrant.studentId = student.id
                      AND contactGrant.enterpriseId = :enterpriseIdContact
                      AND contactGrant.scope = 'enterprise_talent_contact'
                      AND contactGrant.revokedAt IS NULL
                      AND contactGrant.expiresAt > :nowContact
                ) AS contactAllowed,
                EXISTS(
                    SELECT 1 FROM enterprise_contact_requests cr
                    WHERE cr.studentId = student.id
                      AND cr.enterpriseId = :enterpriseIdCr
                      AND cr.status = 'pending'
                ) AS hasPendingContactRequest
            FROM student_profiles student
            INNER JOIN users u ON u.id = student.userId
            INNER JOIN enterprise_talent_access_grants accessGrant
              ON accessGrant.studentId = student.id
            INNER JOIN privacy_consents consent
              ON consent.id = accessGrant.consentId
             AND consent.studentId = student.id
            LEFT JOIN student_profiles sp ON sp.id = student.id
            LEFT JOIN classes c ON c.id = student.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = student.id
            WHERE {$whereClause}
            LIMIT 1
        SQL;

        $params['enterpriseIdContact'] = $enterpriseId;
        $params['nowContact'] = $now;
        $params['enterpriseIdCr'] = $enterpriseId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $contactAllowed = (bool) ((int) ($row['contactAllowed'] ?? 0) === 1);
        $hasPendingContact = (bool) ((int) ($row['hasPendingContactRequest'] ?? 0) === 1);

        // Load aggregate details via DatabaseTalentPassportRepository or robust fallback queries
        $passportRepo = $this->getTalentPassportRepository();
        $aggregate = $passportRepo !== null
            ? $passportRepo->sharedSectionsForStudent($studentId, ['skills', 'experience', 'certificates', 'projects'])
            : [];

        $detail = [
            'studentId' => (string) $row['studentId'],
            'displayName' => (string) ($row['displayName'] ?? 'Ứng viên'),
            'schoolName' => (string) ($row['schoolName'] ?? ''),
            'className' => (string) ($row['className'] ?? ''),
            'studyStatus' => (string) ($row['studyStatus'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'headline' => (string) ($row['headline'] ?? ''),
            'bio' => (string) ($row['bio'] ?? ''),
            'avatarUrl' => $row['avatarUrl'] !== null ? (string) $row['avatarUrl'] : null,
            'contactAllowed' => $contactAllowed,
            'hasPendingContactRequest' => $hasPendingContact,
            'skills' => $aggregate['skills'] ?? $this->skillsWithDetailsForStudent($studentId),
            'experience' => $aggregate['experience'] ?? $this->experienceForStudent($studentId),
            'certificates' => $aggregate['certificates'] ?? $this->certificatesForStudent($studentId),
            'projects' => $aggregate['projects'] ?? $this->projectsForStudent($studentId),
        ];

        // Only include email & phone if contact grant was explicitly granted
        if ($contactAllowed) {
            $detail['email'] = (string) ($row['email'] ?? '');
            $detail['phone'] = (string) ($row['phone'] ?? '');
        }

        return $detail;
    }

    public function createContactRequest(
        string $enterpriseId,
        string $userId,
        string $studentId,
        string $idempotencyKey,
        ?string $message
    ): array {
        $talent = $this->getTalentDetail($enterpriseId, $studentId);
        if ($talent === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng viên hoặc ứng viên chưa cấp quyền.');
        }

        // Idempotency check
        $stmtCheck = $this->pdo->prepare('SELECT id, enterpriseId, studentId, idempotencyKey, status, message, requestedAt FROM enterprise_contact_requests WHERE enterpriseId = ? AND idempotencyKey = ? LIMIT 1');
        $stmtCheck->execute([$enterpriseId, $idempotencyKey]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            return $existing;
        }

        // Check if there is already an active pending request
        $stmtPending = $this->pdo->prepare("SELECT id, enterpriseId, studentId, idempotencyKey, status, message, requestedAt FROM enterprise_contact_requests WHERE enterpriseId = ? AND studentId = ? AND status = 'pending' LIMIT 1");
        $stmtPending->execute([$enterpriseId, $studentId]);
        $existingPending = $stmtPending->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingPending)) {
            return $existingPending;
        }

        $id = Uuid::v4();
        $now = $this->now();

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO enterprise_contact_requests (id, enterpriseId, studentId, idempotencyKey, status, message, requestedAt)
                VALUES (:id, :enterpriseId, :studentId, :idempotencyKey, 'pending', :message, :requestedAt)
            SQL);
            $insert->execute([
                'id' => $id,
                'enterpriseId' => $enterpriseId,
                'studentId' => $studentId,
                'idempotencyKey' => $idempotencyKey,
                'message' => $message === '' ? null : $message,
                'requestedAt' => $now,
            ]);

            // Audit log
            $audit = $this->pdo->prepare('INSERT INTO audit_logs (id, userId, action, entityType, entityId, createdAt) VALUES (?, ?, ?, ?, ?, ?)');
            $audit->execute([Uuid::v4(), $userId, 'enterprise_contact_request.created', 'enterprise_contact_request', $id, $now]);

            // Publish notification to student
            $studentUserId = $this->userIdForStudent($studentId);
            $enterpriseName = $this->enterpriseName($enterpriseId);

            $this->getNotificationService()->publish(
                $studentUserId,
                'internship_application_status_changed',
                'Yêu cầu kết nối từ doanh nghiệp',
                "Doanh nghiệp {$enterpriseName} muốn kết nối và xem thông tin liên hệ của bạn.",
                '/app/learner/ecosystem.php',
                'enterprise_contact_request:' . $id,
                $studentId
            );

            $this->pdo->commit();

            return [
                'id' => $id,
                'enterpriseId' => $enterpriseId,
                'studentId' => $studentId,
                'idempotencyKey' => $idempotencyKey,
                'status' => 'pending',
                'message' => $message,
                'requestedAt' => $now,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function grantAccess(string $studentId, string $enterpriseId, string $scope, int $durationDays = 30): array
    {
        if (!in_array($scope, ['enterprise_talent_discovery', 'enterprise_talent_contact'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "Scope không hợp lệ: {$scope}");
        }
        if ($durationDays < 1 || $durationDays > 365) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời hạn chia sẻ phải từ 1 đến 365 ngày.');
        }

        $nowObj = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $nowObj->format('Y-m-d H:i:s.u');
        $expiresAt = $nowObj->modify("+{$durationDays} days")->format('Y-m-d H:i:s.u');

        $this->pdo->beginTransaction();
        try {
            // Find or create consent
            $stmtConsent = $this->pdo->prepare('SELECT id FROM privacy_consents WHERE studentId = ? AND scope = ? LIMIT 1');
            $stmtConsent->execute([$studentId, $scope]);
            $consentId = $stmtConsent->fetchColumn();

            if (is_string($consentId) && $consentId !== '') {
                $updConsent = $this->pdo->prepare('UPDATE privacy_consents SET isGranted = 1, grantedAt = :now, revokedAt = NULL WHERE id = :id');
                $updConsent->execute(['now' => $now, 'id' => $consentId]);
            } else {
                $consentId = Uuid::v4();
                $insConsent = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt, createdAt)
                    VALUES (:id, :studentId, :scope, 1, '1.0', :now, NULL, :now)
                SQL);
                $insConsent->execute([
                    'id' => $consentId,
                    'studentId' => $studentId,
                    'scope' => $scope,
                    'now' => $now,
                ]);
            }

            // Find or insert grant
            $stmtGrant = $this->pdo->prepare('SELECT id FROM enterprise_talent_access_grants WHERE studentId = ? AND enterpriseId = ? AND scope = ? LIMIT 1');
            $stmtGrant->execute([$studentId, $enterpriseId, $scope]);
            $grantId = $stmtGrant->fetchColumn();

            if (is_string($grantId) && $grantId !== '') {
                $updGrant = $this->pdo->prepare(<<<'SQL'
                    UPDATE enterprise_talent_access_grants
                    SET consentId = :consentId, grantedAt = :grantedAt, expiresAt = :expiresAt, revokedAt = NULL, updatedAt = :updatedAt
                    WHERE id = :id
                SQL);
                $updGrant->execute([
                    'consentId' => $consentId,
                    'grantedAt' => $now,
                    'expiresAt' => $expiresAt,
                    'updatedAt' => $now,
                    'id' => $grantId,
                ]);
            } else {
                $grantId = Uuid::v4();
                $insGrant = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO enterprise_talent_access_grants (id, studentId, enterpriseId, consentId, scope, grantedAt, expiresAt, revokedAt, createdAt, updatedAt)
                    VALUES (:id, :studentId, :enterpriseId, :consentId, :scope, :grantedAt, :expiresAt, NULL, :createdAt, :updatedAt)
                SQL);
                $insGrant->execute([
                    'id' => $grantId,
                    'studentId' => $studentId,
                    'enterpriseId' => $enterpriseId,
                    'consentId' => $consentId,
                    'scope' => $scope,
                    'grantedAt' => $now,
                    'expiresAt' => $expiresAt,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            }

            $this->pdo->commit();

            return [
                'id' => $grantId,
                'studentId' => $studentId,
                'enterpriseId' => $enterpriseId,
                'scope' => $scope,
                'grantedAt' => $now,
                'expiresAt' => $expiresAt,
                'revokedAt' => null,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function revokeGrant(string $studentId, string $grantId): bool
    {
        if (!Uuid::isValid($grantId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'ID grant không hợp lệ.');
        }

        $now = $this->now();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id, consentId, enterpriseId, scope, revokedAt FROM enterprise_talent_access_grants WHERE id = ? AND studentId = ? LIMIT 1');
            $stmt->execute([$grantId, $studentId]);
            $grant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($grant)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy quyền chia sẻ hồ sơ.');
            }

            if (!empty($grant['revokedAt'])) {
                $this->pdo->commit();
                return true;
            }

            $upd = $this->pdo->prepare('UPDATE enterprise_talent_access_grants SET revokedAt = :now, updatedAt = :now WHERE id = :id AND studentId = :studentId AND revokedAt IS NULL');
            $upd->execute(['now' => $now, 'id' => $grantId, 'studentId' => $studentId]);

            // Check if there are any remaining active grants for this student and scope
            $stmtRemaining = $this->pdo->prepare('SELECT COUNT(*) FROM enterprise_talent_access_grants WHERE studentId = ? AND scope = ? AND revokedAt IS NULL AND expiresAt > ?');
            $stmtRemaining->execute([$studentId, $grant['scope'], $now]);
            $activeCount = (int) $stmtRemaining->fetchColumn();

            if ($activeCount === 0 && !empty($grant['consentId'])) {
                $updConsent = $this->pdo->prepare('UPDATE privacy_consents SET isGranted = 0, revokedAt = :now WHERE id = :id AND studentId = :studentId');
                $updConsent->execute(['now' => $now, 'id' => $grant['consentId'], 'studentId' => $studentId]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listGrants(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT g.id, g.studentId, g.enterpriseId, e.name AS enterpriseName, e.logoUrl AS enterpriseLogo,
                   g.scope, g.grantedAt, g.expiresAt, g.revokedAt, g.createdAt
            FROM enterprise_talent_access_grants g
            INNER JOIN enterprises e ON e.id = g.enterpriseId
            WHERE g.studentId = :studentId
            ORDER BY g.createdAt DESC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $nowObj = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $items = [];
        foreach ($rows as $row) {
            $expiresAt = new DateTimeImmutable((string) $row['expiresAt'], new DateTimeZone('UTC'));
            $isExpired = $expiresAt <= $nowObj;
            $isRevoked = !empty($row['revokedAt']);

            $items[] = [
                'id' => (string) $row['id'],
                'enterpriseId' => (string) $row['enterpriseId'],
                'enterpriseName' => (string) $row['enterpriseName'],
                'enterpriseLogo' => $row['enterpriseLogo'] !== null ? (string) $row['enterpriseLogo'] : null,
                'scope' => (string) $row['scope'],
                'grantedAt' => (string) $row['grantedAt'],
                'expiresAt' => (string) $row['expiresAt'],
                'revokedAt' => $row['revokedAt'] !== null ? (string) $row['revokedAt'] : null,
                'isActive' => !$isExpired && !$isRevoked,
            ];
        }

        return $items;
    }

    /** @return list<string> */
    private function verifiedSkillsForStudent(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT s.name
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
              AND ss.verificationStatus = 'verified'
            ORDER BY ss.createdAt ASC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter($names, static fn ($n) => is_string($n) && trim($n) !== ''));
    }

    /** @return list<array<string,mixed>> */
    private function skillsWithDetailsForStudent(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT ss.id, s.name AS skillName, ss.levelScore,
                   ss.verificationStatus, ss.verifiedAt, ss.createdAt
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
            ORDER BY ss.createdAt ASC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function userIdForStudent(string $studentId): string
    {
        $stmt = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $userId = $stmt->fetchColumn();
        if (!is_string($userId) || $userId === '') {
            throw new \RuntimeException('Student user record not found.');
        }
        return $userId;
    }

    private function enterpriseName(string $enterpriseId): string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM enterprises WHERE id = ? LIMIT 1');
        $stmt->execute([$enterpriseId]);
        $name = $stmt->fetchColumn();
        return is_string($name) ? $name : 'Doanh nghiệp';
    }

    private function tableExists(string $tableName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function getTalentPassportRepository(): ?DatabaseTalentPassportRepository
    {
        if (!class_exists('TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository', false)) {
            $bootstrapPath = dirname(__DIR__, 4) . '/app/learner/data/bootstrap.php';
            if (file_exists($bootstrapPath)) {
                require_once $bootstrapPath;
            }
        }
        if (class_exists('TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository', false)) {
            return new DatabaseTalentPassportRepository($this->pdo);
        }
        return null;
    }

    /** @return array{confirmed_hours:int,confirmed_entries:list<array<string,mixed>>} */
    private function experienceForStudent(string $studentId): array
    {
        if (!$this->tableExists('student_experience_entries')) {
            return ['confirmed_hours' => 0, 'confirmed_entries' => []];
        }
        $stmt = $this->pdo->prepare("SELECT id, title, organization, hours, status, createdAt FROM student_experience_entries WHERE studentId = ? AND status = 'confirmed' ORDER BY createdAt DESC");
        $stmt->execute([$studentId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totalHours = array_sum(array_column($entries, 'hours'));
        return ['confirmed_hours' => (int) $totalHours, 'confirmed_entries' => $entries];
    }

    /** @return list<array<string,mixed>> */
    private function certificatesForStudent(string $studentId): array
    {
        if (!$this->tableExists('certificates')) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, title, issuingOrganization, issueDate, expiryDate, credentialId, credentialUrl, verificationStatus, verifiedAt, createdAt, updatedAt FROM certificates WHERE studentId = ? ORDER BY createdAt DESC");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function projectsForStudent(string $studentId): array
    {
        if (!$this->tableExists('projects') || !$this->tableExists('project_members')) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT p.id, p.title, p.category, p.description, p.projectUrl, p.startAt, p.endAt, p.status, p.createdAt, p.updatedAt, pm.role, pm.contribution FROM projects p INNER JOIN project_members pm ON pm.projectId = p.id WHERE pm.studentId = ? ORDER BY p.createdAt DESC");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__, 4) . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Service/NotificationService.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Database/DatabaseNotificationRepository.php';
        }
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
