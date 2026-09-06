<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class SchoolCredentialManagementRepository
{
    public function __construct(private readonly PDO $pdo, private readonly ?NotificationService $notifications = null) {}

    public function schoolIdForUser(string $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT schoolId FROM school_members WHERE userId=:userId LIMIT 1');
        $statement->execute(['userId' => $userId]);
        $id = $statement->fetchColumn();
        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array{badges:list<array<string,mixed>>,certificates:list<array<string,mixed>>,awards:list<array<string,mixed>>,students:list<array<string,mixed>>} */
    public function dashboard(string $schoolId): array
    {
        $badges = $this->fetchAll('SELECT b.*,r.id AS ruleId,r.thresholdCriteria FROM badges b LEFT JOIN badge_rule_definitions r ON r.badgeId=b.id AND r.isActive=1 WHERE b.schoolId=:schoolId ORDER BY b.createdAt DESC', ['schoolId' => $schoolId]);
        $certificates = $this->fetchAll('SELECT * FROM school_certificate_catalog WHERE schoolId=:schoolId ORDER BY createdAt DESC', ['schoolId' => $schoolId]);
        $awards = $this->fetchAll('SELECT ssc.id,ssc.studentId,ssc.certificateCatalogId,ssc.status,ssc.issuedAt,ssc.evidenceContext,c.name,u.fullName AS studentName FROM student_school_certificates ssc INNER JOIN school_certificate_catalog c ON c.id=ssc.certificateCatalogId INNER JOIN student_profiles sp ON sp.id=ssc.studentId INNER JOIN users u ON u.id=sp.userId WHERE c.schoolId=:schoolId ORDER BY ssc.issuedAt DESC,ssc.id', ['schoolId' => $schoolId]);
        $students = $this->fetchAll('SELECT sp.id,u.fullName,u.email,c.name AS className FROM student_profiles sp INNER JOIN classes c ON c.id=sp.classId INNER JOIN users u ON u.id=sp.userId WHERE c.schoolId=:schoolId AND sp.studyStatus=\'active\' ORDER BY u.fullName', ['schoolId' => $schoolId]);
        return ['badges' => $badges, 'certificates' => $certificates, 'awards' => $awards, 'students' => $students];
    }

    /** @return array<string,mixed> */
    public function createBadge(string $actorUserId, string $schoolId, array $data, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $data, $requestId): array {
            $badgeId = Uuid::v4();
            $ruleId = Uuid::v4();
            $now = $this->now();
            try {
                $statement = $this->pdo->prepare('INSERT INTO badges (id,schoolId,code,name,category,description,recommendationProfile,recommendationEnabled,iconUrl,level,status,createdAt,updatedAt) VALUES (:id,:schoolId,:code,:name,:category,:description,:profile,:enabled,:iconUrl,:level,:status,:createdAt,:updatedAt)');
                $statement->execute(['id' => $badgeId, 'schoolId' => $schoolId, 'code' => $data['code'], 'name' => $data['name'], 'category' => $data['category'], 'description' => $data['description'], 'profile' => $this->json($data['recommendationProfile']), 'enabled' => $data['recommendationEnabled'] ? 1 : 0, 'iconUrl' => $data['iconUrl'], 'level' => $data['level'], 'status' => $data['status'], 'createdAt' => $now, 'updatedAt' => $now]);
                $rule = $this->pdo->prepare('INSERT INTO badge_rule_definitions (id,badgeId,ruleType,thresholdCriteria,version,isActive,createdAt,updatedAt) VALUES (:id,:badgeId,\'threshold\',:criteria,1,1,:createdAt,:updatedAt)');
                $rule->execute(['id' => $ruleId, 'badgeId' => $badgeId, 'criteria' => $this->json($data['criteria']), 'createdAt' => $now, 'updatedAt' => $now]);
            } catch (PDOException $exception) {
                if ($this->isDuplicate($exception)) throw new ApiException(409, 'DUPLICATE_CREDENTIAL_CODE', 'Mã badge đã tồn tại.');
                throw $exception;
            }
            $this->audit($actorUserId, 'school_badge.created', 'badge', $badgeId, $requestId, ['schoolId' => $schoolId, 'code' => $data['code']], $now);
            return ['id' => $badgeId, 'ruleId' => $ruleId, 'status' => $data['status']];
        });
    }

    /** @return array<string,mixed> */
    public function createCertificateCatalog(string $actorUserId, string $schoolId, array $data, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $data, $requestId): array {
            $id = Uuid::v4();
            $now = $this->now();
            try {
                $statement = $this->pdo->prepare('INSERT INTO school_certificate_catalog (id,schoolId,code,name,description,issuerName,iconKey,eligibilityCriteria,recommendationProfile,recommendationEnabled,status,createdAt,updatedAt) VALUES (:id,:schoolId,:code,:name,:description,:issuerName,:iconKey,:criteria,:profile,:enabled,:status,:createdAt,:updatedAt)');
                $statement->execute(['id' => $id, 'schoolId' => $schoolId, 'code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'], 'issuerName' => $data['issuerName'], 'iconKey' => $data['iconKey'], 'criteria' => $this->json($data['criteria']), 'profile' => $this->json($data['recommendationProfile']), 'enabled' => $data['recommendationEnabled'] ? 1 : 0, 'status' => $data['status'], 'createdAt' => $now, 'updatedAt' => $now]);
            } catch (PDOException $exception) {
                if ($this->isDuplicate($exception)) throw new ApiException(409, 'DUPLICATE_CREDENTIAL_CODE', 'Mã chứng chỉ đã tồn tại.');
                throw $exception;
            }
            $this->audit($actorUserId, 'school_certificate_catalog.created', 'school_certificate_catalog', $id, $requestId, ['schoolId' => $schoolId, 'code' => $data['code']], $now);
            return ['id' => $id, 'status' => $data['status']];
        });
    }

    /** @return array<string,mixed> */
    public function awardBadge(string $actorUserId, string $schoolId, string $badgeId, string $studentId, array $evidence, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $badgeId, $studentId, $evidence, $requestId): array {
            $scope = $this->credentialStudentScope($schoolId, $studentId, 'badge', $badgeId);
            $existing = $this->fetchOne('SELECT sb.id,sb.awardedAt FROM student_badges sb WHERE sb.studentId=:studentId AND sb.badgeId=:badgeId LIMIT 1' . $this->lockSuffix(), ['studentId' => $studentId, 'badgeId' => $badgeId]);
            if ($existing !== null) return ['id' => (string) $existing['id'], 'status' => 'awarded', 'awardedAt' => (string) $existing['awardedAt']];
            $ruleId = (string) ($scope['ruleId'] ?? '');
            if ($ruleId === '') throw new ApiException(422, 'CREDENTIAL_RULE_REQUIRED', 'Badge chưa có rule đang hoạt động.');
            $id = Uuid::v4(); $now = $this->now();
            $statement = $this->pdo->prepare('INSERT INTO student_badges (id,studentId,badgeId,ruleDefinitionId,awardedAt,awardedBy,awardContext) VALUES (:id,:studentId,:badgeId,:ruleId,:awardedAt,\'school_admin\',:context)');
            $statement->execute(['id' => $id, 'studentId' => $studentId, 'badgeId' => $badgeId, 'ruleId' => $ruleId, 'awardedAt' => $now, 'context' => $this->json($evidence + ['schoolId' => $schoolId, 'issuedBy' => $actorUserId])]);
            $this->audit($actorUserId, 'school_badge.awarded', 'student_badge', $id, $requestId, ['schoolId' => $schoolId, 'studentId' => $studentId, 'badgeId' => $badgeId], $now);
            $this->notifyStudent($studentId, 'school_badge_awarded', 'Bạn nhận được huy hiệu mới', 'Nhà trường đã cấp huy hiệu ' . (string) $scope['name'] . ' cho bạn.', 'school_badge_awarded:' . $id);
            return ['id' => $id, 'status' => 'awarded', 'awardedAt' => $now];
        });
    }

    /** @return array<string,mixed> */
    public function issueCertificate(string $actorUserId, string $schoolId, string $catalogId, string $studentId, array $evidence, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $catalogId, $studentId, $evidence, $requestId): array {
            $scope = $this->credentialStudentScope($schoolId, $studentId, 'certificate', $catalogId);
            $existing = $this->fetchOne('SELECT id,status,issuedAt FROM student_school_certificates WHERE studentId=:studentId AND certificateCatalogId=:catalogId LIMIT 1' . $this->lockSuffix(), ['studentId' => $studentId, 'catalogId' => $catalogId]);
            if ($existing !== null) {
                if ((string) $existing['status'] === 'issued') return ['id' => (string) $existing['id'], 'status' => 'issued', 'issuedAt' => (string) $existing['issuedAt']];
                throw new ApiException(409, 'CREDENTIAL_REVOKED', 'Chứng chỉ đã bị thu hồi và không thể cấp lại trên cùng catalog.');
            }
            $id = Uuid::v4(); $now = $this->now();
            $statement = $this->pdo->prepare('INSERT INTO student_school_certificates (id,studentId,certificateCatalogId,status,issuedAt,issuedBy,evidenceContext,createdAt,updatedAt) VALUES (:id,:studentId,:catalogId,\'issued\',:issuedAt,:issuedBy,:evidence,:createdAt,:updatedAt)');
            $statement->execute(['id' => $id, 'studentId' => $studentId, 'catalogId' => $catalogId, 'issuedAt' => $now, 'issuedBy' => $actorUserId, 'evidence' => $this->json($evidence + ['schoolId' => $schoolId]), 'createdAt' => $now, 'updatedAt' => $now]);
            $this->audit($actorUserId, 'school_certificate.issued', 'student_school_certificate', $id, $requestId, ['schoolId' => $schoolId, 'studentId' => $studentId, 'catalogId' => $catalogId, 'status' => 'issued'], $now);
            $this->notifyStudent($studentId, 'school_certificate_issued', 'Nhà trường đã cấp chứng chỉ', 'Bạn vừa nhận chứng chỉ ' . (string) $scope['name'] . '.', 'school_certificate_issued:' . $id);
            return ['id' => $id, 'status' => 'issued', 'issuedAt' => $now];
        });
    }

    /** @return array<string,mixed> */
    public function revokeCertificate(string $actorUserId, string $schoolId, string $awardId, string $reason, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $awardId, $reason, $requestId): array {
            $award = $this->fetchOne('SELECT ssc.id,ssc.studentId,ssc.status,ssc.evidenceContext,c.name FROM student_school_certificates ssc INNER JOIN school_certificate_catalog c ON c.id=ssc.certificateCatalogId WHERE ssc.id=:id AND c.schoolId=:schoolId LIMIT 1' . $this->lockSuffix(), ['id' => $awardId, 'schoolId' => $schoolId]);
            if ($award === null) throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy chứng chỉ thuộc trường hiện tại.');
            if ((string) $award['status'] === 'revoked') return ['id' => $awardId, 'status' => 'revoked'];
            $evidence = json_decode((string) $award['evidenceContext'], true);
            if (!is_array($evidence)) $evidence = [];
            $now = $this->now();
            $evidence['revocation'] = ['reason' => $reason, 'revokedBy' => $actorUserId, 'revokedAt' => $now];
            $update = $this->pdo->prepare("UPDATE student_school_certificates SET status='revoked',evidenceContext=:evidence,updatedAt=:updatedAt WHERE id=:id AND status='issued'");
            $update->execute(['evidence' => $this->json($evidence), 'updatedAt' => $now, 'id' => $awardId]);
            if ($update->rowCount() !== 1) throw new ApiException(409, 'CREDENTIAL_STATUS_CONFLICT', 'Trạng thái chứng chỉ đã thay đổi.');
            $this->audit($actorUserId, 'school_certificate.revoked', 'student_school_certificate', $awardId, $requestId, ['schoolId' => $schoolId, 'studentId' => $award['studentId'], 'previousStatus' => 'issued', 'status' => 'revoked', 'reason' => $reason], $now);
            $this->notifyStudent((string) $award['studentId'], 'school_certificate_revoked', 'Chứng chỉ đã được thu hồi', 'Nhà trường đã thu hồi chứng chỉ ' . (string) $award['name'] . ': ' . $reason, 'school_certificate_revoked:' . $awardId);
            return ['id' => $awardId, 'status' => 'revoked', 'revokedAt' => $now];
        });
    }

    /** @return array<string,mixed> */
    private function credentialStudentScope(string $schoolId, string $studentId, string $kind, string $credentialId): array
    {
        $student = $this->fetchOne('SELECT sp.id FROM student_profiles sp INNER JOIN classes c ON c.id=sp.classId WHERE sp.id=:studentId AND c.schoolId=:schoolId AND sp.studyStatus=\'active\' LIMIT 1' . $this->lockSuffix(), ['studentId' => $studentId, 'schoolId' => $schoolId]);
        if ($student === null) throw new ApiException(403, 'CREDENTIAL_SCHOOL_SCOPE_DENIED', 'Học viên không thuộc trường hiện tại.');
        if ($kind === 'badge') {
            $credential = $this->fetchOne('SELECT b.id,b.name,r.id AS ruleId FROM badges b LEFT JOIN badge_rule_definitions r ON r.badgeId=b.id AND r.isActive=1 WHERE b.id=:id AND b.schoolId=:schoolId AND b.status=\'active\' LIMIT 1', ['id' => $credentialId, 'schoolId' => $schoolId]);
        } else {
            $credential = $this->fetchOne('SELECT id,name FROM school_certificate_catalog WHERE id=:id AND schoolId=:schoolId AND status=\'active\' LIMIT 1', ['id' => $credentialId, 'schoolId' => $schoolId]);
        }
        if ($credential === null) throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy credential đang hoạt động thuộc trường hiện tại.');
        return $credential;
    }

    private function notifyStudent(string $studentId, string $type, string $title, string $message, string $eventKey): void
    {
        $student = $this->fetchOne('SELECT userId FROM student_profiles WHERE id=:id LIMIT 1', ['id' => $studentId]);
        if ($student === null) throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tài khoản học viên.');
        $this->notificationService()->publish((string) $student['userId'], $type, $title, $message, '/app/learner/badges.php', $eventKey, $studentId);
    }

    private function audit(string $userId, string $action, string $entityType, string $entityId, string $requestId, array $metadata, string $now): void
    {
        $statement = $this->pdo->prepare('INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt) VALUES (:id,:userId,:action,:entityType,:entityId,:requestId,NULL,:metadata,:createdAt)');
        $statement->execute(['id' => Uuid::v4(), 'userId' => $userId, 'action' => $action, 'entityType' => $entityType, 'entityId' => $entityId, 'requestId' => $requestId, 'metadata' => $this->json($metadata), 'createdAt' => $now]);
    }

    private function transaction(callable $operation): mixed
    {
        $owns = !$this->pdo->inTransaction(); if ($owns) $this->pdo->beginTransaction();
        try { $result = $operation(); if ($owns) $this->pdo->commit(); return $result; }
        catch (Throwable $exception) { if ($owns && $this->pdo->inTransaction()) $this->pdo->rollBack(); throw $exception; }
    }

    private function fetchAll(string $sql, array $parameters): array { $statement=$this->pdo->prepare($sql);$statement->execute($parameters);return array_values($statement->fetchAll(PDO::FETCH_ASSOC)?:[]); }
    private function fetchOne(string $sql, array $parameters): ?array { $statement=$this->pdo->prepare($sql);$statement->execute($parameters);$row=$statement->fetch(PDO::FETCH_ASSOC);return is_array($row)?$row:null; }
    private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    private function now(): string { return gmdate('Y-m-d H:i:s.u'); }
    private function lockSuffix(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE'; }
    private function isDuplicate(PDOException $exception): bool { return (int)($exception->errorInfo[1]??0)===1062 || ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' && (int)($exception->errorInfo[1]??0)===19); }
    private function notificationService(): NotificationService { return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo)); }
}
