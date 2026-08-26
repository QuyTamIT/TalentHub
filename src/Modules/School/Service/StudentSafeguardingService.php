<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Support\Uuid;

final class StudentSafeguardingService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SchoolRepository $schools,
        private readonly SchoolAuthorization $authorization,
    ) {}

    /** @return array<string,mixed> */
    public function policy(string $actorUserId): array
    {
        $school = $this->schoolForActor($actorUserId);
        $stmt = $this->pdo->prepare('SELECT schoolId, minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired, updatedAt FROM student_safeguarding_policies WHERE schoolId = :schoolId LIMIT 1');
        $stmt->execute(['schoolId' => $school['id']]);
        $row = $stmt->fetch();
        return is_array($row) ? $this->presentPolicy($row) : [
            'schoolId' => $school['id'], 'minimumDirectContactAge' => 18,
            'guardianConsentRequired' => true, 'schoolApprovalRequired' => true,
            'updatedAt' => null,
        ];
    }

    /** @return array<string,mixed> */
    public function updatePolicy(string $actorUserId, array $input): array
    {
        $school = $this->schoolForActor($actorUserId);
        $this->authorization->requireWriteAccess($actorUserId, (string) $school['id']);
        $age = filter_var($input['minimumDirectContactAge'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 13, 'max_range' => 25]]);
        if ($age === false) { throw new ApiException(422, 'VALIDATION_FAILED', 'Tuổi liên hệ trực tiếp phải từ 13 đến 25.'); }
        $guardianRequired = !empty($input['guardianConsentRequired']);
        $schoolRequired = !empty($input['schoolApprovalRequired']);
        $stmt = $this->pdo->prepare(
            'INSERT INTO student_safeguarding_policies (schoolId, minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired, updatedByUserId)
             VALUES (:schoolId, :age, :guardianRequired, :schoolRequired, :actor)
             ON DUPLICATE KEY UPDATE minimumDirectContactAge = VALUES(minimumDirectContactAge), guardianConsentRequired = VALUES(guardianConsentRequired), schoolApprovalRequired = VALUES(schoolApprovalRequired), updatedByUserId = VALUES(updatedByUserId)'
        );
        $stmt->execute(['schoolId' => $school['id'], 'age' => $age, 'guardianRequired' => $guardianRequired ? 1 : 0, 'schoolRequired' => $schoolRequired ? 1 : 0, 'actor' => $actorUserId]);
        $this->schools->writeAudit($actorUserId, 'SAFEGUARDING_POLICY_UPDATE', 'school', (string) $school['id'], ['minimumDirectContactAge' => $age, 'guardianConsentRequired' => $guardianRequired, 'schoolApprovalRequired' => $schoolRequired]);
        return $this->policy($actorUserId);
    }

    /** @return array<string,mixed> */
    public function approve(string $actorUserId, string $studentId, string $enterpriseId, string $expiresAt): array
    {
        $school = $this->schoolForActor($actorUserId);
        $this->authorization->requireWriteAccess($actorUserId, (string) $school['id']);
        Uuid::orFail($studentId, 'studentId');
        Uuid::orFail($enterpriseId, 'enterpriseId');
        $expiry = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt, new DateTimeZone('UTC'));
        if ($expiry === false || $expiry <= new DateTimeImmutable('today', new DateTimeZone('UTC')) || $expiry > new DateTimeImmutable('+1 year', new DateTimeZone('UTC'))) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Hạn phê duyệt phải nằm trong tương lai và không quá 1 năm.');
        }
        $scope = $this->pdo->prepare(
            "SELECT sp.id FROM student_profiles sp INNER JOIN classes c ON c.id = sp.classId
             INNER JOIN school_enterprise_partnerships sep ON sep.schoolId = c.schoolId AND sep.enterpriseId = :enterpriseId AND sep.status = 'approved'
             WHERE sp.id = :studentId AND c.schoolId = :schoolId LIMIT 1"
        );
        $scope->execute(['studentId' => $studentId, 'enterpriseId' => $enterpriseId, 'schoolId' => $school['id']]);
        if (!$scope->fetchColumn()) { throw new ApiException(404, 'APPROVAL_SCOPE_NOT_FOUND', 'Học sinh hoặc quan hệ đối tác không thuộc phạm vi trường.'); }
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $revoke = $this->pdo->prepare('UPDATE student_enterprise_school_approvals SET revokedAt = :now WHERE studentId = :studentId AND enterpriseId = :enterpriseId AND revokedAt IS NULL');
        $revoke->execute(['now' => $now, 'studentId' => $studentId, 'enterpriseId' => $enterpriseId]);
        $id = Uuid::v4();
        $insert = $this->pdo->prepare('INSERT INTO student_enterprise_school_approvals (id, studentId, enterpriseId, approvedByUserId, approvedAt, expiresAt) VALUES (:id, :studentId, :enterpriseId, :actor, :approvedAt, :expiresAt)');
        $insert->execute(['id' => $id, 'studentId' => $studentId, 'enterpriseId' => $enterpriseId, 'actor' => $actorUserId, 'approvedAt' => $now, 'expiresAt' => $expiry->format('Y-m-d 23:59:59.000000')]);
        $this->schools->writeAudit($actorUserId, 'STUDENT_ENTERPRISE_APPROVAL_CREATE', 'student_enterprise_school_approval', $id, ['schoolId' => $school['id'], 'studentId' => $studentId, 'enterpriseId' => $enterpriseId]);
        return ['id' => $id, 'studentId' => $studentId, 'enterpriseId' => $enterpriseId, 'expiresAt' => $expiry->format('Y-m-d 23:59:59.000000'), 'status' => 'active'];
    }

    /** @return list<array<string,mixed>> */
    public function approvals(string $actorUserId): array
    {
        $school = $this->schoolForActor($actorUserId);
        $stmt = $this->pdo->prepare(
            'SELECT approval.id, approval.studentId, approval.enterpriseId, approval.expiresAt, approval.revokedAt, u.fullName AS studentName, e.name AS enterpriseName
             FROM student_enterprise_school_approvals approval
             INNER JOIN student_profiles sp ON sp.id = approval.studentId INNER JOIN users u ON u.id = sp.userId INNER JOIN classes c ON c.id = sp.classId INNER JOIN enterprises e ON e.id = approval.enterpriseId
             WHERE c.schoolId = :schoolId ORDER BY approval.approvedAt DESC'
        );
        $stmt->execute(['schoolId' => $school['id']]);
        return array_values($stmt->fetchAll());
    }

    /** @return array{id:string,status:string} */
    public function revokeApproval(string $actorUserId, string $approvalId): array
    {
        $school = $this->schoolForActor($actorUserId);
        $this->authorization->requireWriteAccess($actorUserId, (string) $school['id']);
        Uuid::orFail($approvalId, 'approvalId');
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $stmt = $this->pdo->prepare(
            'UPDATE student_enterprise_school_approvals approval
             INNER JOIN student_profiles sp ON sp.id = approval.studentId
             INNER JOIN classes c ON c.id = sp.classId
             SET approval.revokedAt = :now
             WHERE approval.id = :id AND c.schoolId = :schoolId AND approval.revokedAt IS NULL'
        );
        $stmt->execute(['now' => $now, 'id' => $approvalId, 'schoolId' => $school['id']]);
        if ($stmt->rowCount() !== 1) { throw new ApiException(404, 'APPROVAL_NOT_FOUND', 'Không tìm thấy phê duyệt đang hiệu lực.'); }
        $this->schools->writeAudit($actorUserId, 'STUDENT_ENTERPRISE_APPROVAL_REVOKE', 'student_enterprise_school_approval', $approvalId, ['schoolId' => $school['id']]);
        return ['id' => $approvalId, 'status' => 'revoked'];
    }

    /** @return array<string,mixed> */
    public function eligibility(string $studentId, string $enterpriseId, string $postId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.dateOfBirth, c.schoolId, s.name AS schoolName, ip.enterpriseId AS postEnterpriseId
             FROM student_profiles sp INNER JOIN classes c ON c.id = sp.classId INNER JOIN schools s ON s.id = c.schoolId
             INNER JOIN internship_posts ip ON ip.id = :postId WHERE sp.id = :studentId LIMIT 1'
        );
        $stmt->execute(['postId' => $postId, 'studentId' => $studentId]);
        $student = $stmt->fetch();
        if (!is_array($student) || (string) $student['postEnterpriseId'] !== $enterpriseId) { throw new ApiException(404, 'SAFEGUARDING_SCOPE_NOT_FOUND', 'Không tìm thấy phạm vi safeguarding.'); }
        $policyStmt = $this->pdo->prepare('SELECT minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired FROM student_safeguarding_policies WHERE schoolId = :schoolId');
        $policyStmt->execute(['schoolId' => $student['schoolId']]);
        $policy = $policyStmt->fetch() ?: ['minimumDirectContactAge' => 18, 'guardianConsentRequired' => 1, 'schoolApprovalRequired' => 1];
        $birth = new DateTimeImmutable((string) $student['dateOfBirth'], new DateTimeZone('UTC'));
        $age = $birth->diff(new DateTimeImmutable('today', new DateTimeZone('UTC')))->y;
        $minor = $age < (int) $policy['minimumDirectContactAge'];
        $guardianGranted = !$minor || !(bool) $policy['guardianConsentRequired'] || $this->activeGrant('student_guardian_consents', $studentId, $enterpriseId);
        $schoolGranted = !$minor || !(bool) $policy['schoolApprovalRequired'] || $this->activeGrant('student_enterprise_school_approvals', $studentId, $enterpriseId);
        $blockedReason = !$guardianGranted ? 'GUARDIAN_CONSENT_REQUIRED' : (!$schoolGranted ? 'SCHOOL_APPROVAL_REQUIRED' : null);
        return [
            'eligible' => $blockedReason === null, 'ageBand' => $minor ? 'minor' : 'adult',
            'guardianConsentRequired' => $minor && (bool) $policy['guardianConsentRequired'], 'guardianConsentGranted' => $guardianGranted,
            'schoolApprovalRequired' => $minor && (bool) $policy['schoolApprovalRequired'], 'schoolApprovalGranted' => $schoolGranted,
            'contactMode' => $minor ? 'school_mediated' : 'direct',
            'allowedSnapshotFields' => $minor ? ['displayName', 'schoolName', 'verifiedSkills', 'projects', 'experience'] : ['fullName', 'email', 'phone', 'schoolName', 'verifiedSkills', 'projects', 'experience'],
            'blockedReason' => $blockedReason,
        ];
    }

    /** @return array<string,mixed> */
    private function schoolForActor(string $actorUserId): array
    {
        $school = $this->schools->findByUserId($actorUserId);
        if ($school === null) { throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản không thuộc trường.'); }
        return $school;
    }

    private function activeGrant(string $table, string $studentId, string $enterpriseId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE studentId = :studentId AND enterpriseId = :enterpriseId AND revokedAt IS NULL AND expiresAt > :now LIMIT 1");
        $stmt->execute(['studentId' => $studentId, 'enterpriseId' => $enterpriseId, 'now' => gmdate('Y-m-d H:i:s.u')]);
        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function presentPolicy(array $row): array
    {
        return ['schoolId' => (string) $row['schoolId'], 'minimumDirectContactAge' => (int) $row['minimumDirectContactAge'], 'guardianConsentRequired' => (bool) $row['guardianConsentRequired'], 'schoolApprovalRequired' => (bool) $row['schoolApprovalRequired'], 'updatedAt' => $row['updatedAt']];
    }
}
