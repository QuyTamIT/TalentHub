<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

final class StudentSafeguardingService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SchoolAuthorization $authorization,
    ) {}

    /** @return array<string,int|bool|string> */
    public function policyForActor(string $actorUserId): array
    {
        return $this->policy($this->schoolIdForActor($actorUserId));
    }

    /** @param array<string,mixed> $input @return array<string,int|bool|string> */
    public function updatePolicy(string $actorUserId, array $input): array
    {
        $schoolId = $this->schoolIdForActor($actorUserId);
        $this->authorization->requireWriteAccess($actorUserId, $schoolId);
        $minimumAge = filter_var($input['minimumDirectContactAge'] ?? 18, FILTER_VALIDATE_INT);
        if (!is_int($minimumAge) || $minimumAge < 13 || $minimumAge > 25) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tuổi liên hệ trực tiếp phải từ 13 đến 25.');
        }
        $guardianRequired = $this->boolean($input['guardianConsentRequired'] ?? true, 'guardianConsentRequired');
        $schoolRequired = $this->boolean($input['schoolApprovalRequired'] ?? true, 'schoolApprovalRequired');

        $isMySql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $sql = $isMySql
            ? 'INSERT INTO student_safeguarding_policies
                 (schoolId, minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired, updatedByUserId)
               VALUES (:schoolId, :minimumAge, :guardianRequired, :schoolRequired, :actorId)
               ON DUPLICATE KEY UPDATE minimumDirectContactAge=VALUES(minimumDirectContactAge), guardianConsentRequired=VALUES(guardianConsentRequired), schoolApprovalRequired=VALUES(schoolApprovalRequired), updatedByUserId=VALUES(updatedByUserId)'
            : 'INSERT INTO student_safeguarding_policies
                 (schoolId, minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired, updatedByUserId)
               VALUES (:schoolId, :minimumAge, :guardianRequired, :schoolRequired, :actorId)
               ON CONFLICT(schoolId) DO UPDATE SET minimumDirectContactAge=excluded.minimumDirectContactAge, guardianConsentRequired=excluded.guardianConsentRequired, schoolApprovalRequired=excluded.schoolApprovalRequired, updatedByUserId=excluded.updatedByUserId';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'schoolId' => $schoolId,
            'minimumAge' => $minimumAge,
            'guardianRequired' => $guardianRequired ? 1 : 0,
            'schoolRequired' => $schoolRequired ? 1 : 0,
            'actorId' => $actorUserId,
        ]);
        $this->audit($actorUserId, 'SAFEGUARDING_POLICY_UPDATE', 'school', $schoolId, [
            'minimumDirectContactAge' => $minimumAge,
            'guardianConsentRequired' => $guardianRequired,
            'schoolApprovalRequired' => $schoolRequired,
        ]);
        return $this->policy($schoolId);
    }

    public function grantGuardianConsent(
        string $actorUserId,
        string $studentId,
        string $enterpriseId,
        string $expiresAt,
    ): string {
        return $this->grantDecision($actorUserId, $studentId, $enterpriseId, $expiresAt, true);
    }

    public function approveEnterpriseAccess(
        string $actorUserId,
        string $studentId,
        string $enterpriseId,
        string $expiresAt,
    ): string {
        return $this->grantDecision($actorUserId, $studentId, $enterpriseId, $expiresAt, false);
    }

    public function revokeGuardianConsent(string $actorUserId, string $decisionId): void
    {
        $this->revokeDecision($actorUserId, $decisionId, true);
    }

    public function revokeEnterpriseApproval(string $actorUserId, string $decisionId): void
    {
        $this->revokeDecision($actorUserId, $decisionId, false);
    }

    /** @return array<string,mixed> */
    public function eligibility(string $studentId, string $enterpriseId, string $postId): array
    {
        Uuid::orFail($studentId, 'studentId');
        Uuid::orFail($enterpriseId, 'enterpriseId');
        Uuid::orFail($postId, 'postId');
        $studentStatement = $this->pdo->prepare(
            'SELECT sp.id, sp.dateOfBirth, c.gradeLevel, c.schoolId, s.name AS schoolName
             FROM student_profiles sp
             JOIN classes c ON c.id=sp.classId
             JOIN schools s ON s.id=c.schoolId
             WHERE sp.id=:studentId LIMIT 1'
        );
        $studentStatement->execute(['studentId' => $studentId]);
        $student = $studentStatement->fetch();
        if (!is_array($student)) {
            throw new ApiException(404, 'STUDENT_NOT_FOUND', 'Không tìm thấy học sinh.');
        }

        $postStatement = $this->pdo->prepare(
            'SELECT id, enterpriseId, educationLevel, status FROM internship_posts
             WHERE id=:postId AND enterpriseId=:enterpriseId LIMIT 1'
        );
        $postStatement->execute(['postId' => $postId, 'enterpriseId' => $enterpriseId]);
        $post = $postStatement->fetch();
        if (!is_array($post) || (string) $post['status'] !== 'active') {
            throw new ApiException(404, 'INTERNSHIP_POST_NOT_AVAILABLE', 'Tin thực tập không khả dụng.');
        }

        $policy = $this->policy((string) $student['schoolId']);
        $age = $this->age($student['dateOfBirth']);
        $ageBand = $age === null ? 'unknown' : ($age < 18 ? 'minor' : 'adult');
        $protected = $age === null || $age < (int) $policy['minimumDirectContactAge'];
        $guardianRequired = $protected && (bool) $policy['guardianConsentRequired'];
        $schoolRequired = $protected && (bool) $policy['schoolApprovalRequired'];
        $guardianGranted = !$guardianRequired || $this->hasActiveDecision('student_guardian_consents', $studentId, $enterpriseId);
        $schoolApproved = !$schoolRequired || $this->hasActiveDecision('student_enterprise_school_approvals', $studentId, $enterpriseId);
        $partnerApproved = $this->approvedPartnership((string) $student['schoolId'], $enterpriseId);
        $educationEligible = $this->educationEligible((string) $post['educationLevel'], $age, (int) $student['gradeLevel']);

        $blockedReason = null;
        if (!$partnerApproved) {
            $blockedReason = 'SCHOOL_PARTNERSHIP_REQUIRED';
        } elseif (!$educationEligible) {
            $blockedReason = 'EDUCATION_LEVEL_NOT_ELIGIBLE';
        } elseif (!$guardianGranted) {
            $blockedReason = 'GUARDIAN_CONSENT_REQUIRED';
        } elseif (!$schoolApproved) {
            $blockedReason = 'SCHOOL_APPROVAL_REQUIRED';
        }

        return [
            'eligible' => $blockedReason === null,
            'ageBand' => $ageBand,
            'guardianConsentRequired' => $guardianRequired,
            'guardianConsentGranted' => $guardianGranted,
            'schoolApprovalRequired' => $schoolRequired,
            'schoolApprovalGranted' => $schoolApproved,
            'contactMode' => $protected ? 'school_mediated' : 'direct_with_consent',
            'allowedSnapshotFields' => $protected
                ? ['displayName', 'schoolName', 'ageBand', 'verifiedSkills', 'projects', 'experience', 'schoolContact']
                : ['fullName', 'email', 'phone', 'dateOfBirth', 'schoolName', 'verifiedSkills', 'projects', 'experience'],
            'blockedReason' => $blockedReason,
        ];
    }

    /** @return array<string,int|bool|string> */
    private function policy(string $schoolId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired
             FROM student_safeguarding_policies WHERE schoolId=:schoolId LIMIT 1'
        );
        $statement->execute(['schoolId' => $schoolId]);
        $row = $statement->fetch();
        return [
            'schoolId' => $schoolId,
            'minimumDirectContactAge' => (int) ($row['minimumDirectContactAge'] ?? 18),
            'guardianConsentRequired' => (bool) ($row['guardianConsentRequired'] ?? true),
            'schoolApprovalRequired' => (bool) ($row['schoolApprovalRequired'] ?? true),
        ];
    }

    private function grantDecision(string $actorUserId, string $studentId, string $enterpriseId, string $expiresAt, bool $guardian): string
    {
        $schoolId = $this->schoolIdForActor($actorUserId);
        $this->authorization->requireWriteAccess($actorUserId, $schoolId);
        Uuid::orFail($studentId, 'studentId');
        Uuid::orFail($enterpriseId, 'enterpriseId');
        $this->assertStudentSchool($studentId, $schoolId);
        $this->assertEnterprise($enterpriseId);
        try {
            $expiry = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời hạn không đúng định dạng.');
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($expiry <= $now || $expiry > $now->modify('+1 year')) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời hạn phải ở tương lai và không quá một năm.');
        }

        $table = $guardian ? 'student_guardian_consents' : 'student_enterprise_school_approvals';
        $actorColumn = $guardian ? 'grantedByUserId' : 'approvedByUserId';
        $timeColumn = $guardian ? 'grantedAt' : 'approvedAt';
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(
            "INSERT INTO {$table} (id, studentId, enterpriseId, {$actorColumn}, {$timeColumn}, expiresAt)
             VALUES (:id, :studentId, :enterpriseId, :actorId, :decisionAt, :expiresAt)"
        );
        $statement->execute([
            'id' => $id,
            'studentId' => $studentId,
            'enterpriseId' => $enterpriseId,
            'actorId' => $actorUserId,
            'decisionAt' => $now->format('Y-m-d H:i:s'),
            'expiresAt' => $expiry->format('Y-m-d H:i:s'),
        ]);
        $this->audit($actorUserId, $guardian ? 'GUARDIAN_CONSENT_VERIFIED' : 'SCHOOL_ENTERPRISE_ACCESS_APPROVED', $table, $id, [
            'schoolId' => $schoolId,
            'studentId' => $studentId,
            'enterpriseId' => $enterpriseId,
            'expiresAt' => $expiry->format(DATE_ATOM),
        ]);
        return $id;
    }

    private function revokeDecision(string $actorUserId, string $decisionId, bool $guardian): void
    {
        $schoolId = $this->schoolIdForActor($actorUserId);
        $this->authorization->requireWriteAccess($actorUserId, $schoolId);
        Uuid::orFail($decisionId, 'decisionId');
        $table = $guardian ? 'student_guardian_consents' : 'student_enterprise_school_approvals';
        $statement = $this->pdo->prepare(
            "SELECT decision.id FROM {$table} decision
             JOIN student_profiles sp ON sp.id=decision.studentId
             JOIN classes c ON c.id=sp.classId
             WHERE decision.id=:id AND c.schoolId=:schoolId LIMIT 1"
        );
        $statement->execute(['id' => $decisionId, 'schoolId' => $schoolId]);
        if (!$statement->fetchColumn()) {
            throw new ApiException(404, 'SAFEGUARDING_DECISION_NOT_FOUND', 'Không tìm thấy quyết định safeguarding.');
        }
        $timestamp = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'UTC_TIMESTAMP(6)' : 'CURRENT_TIMESTAMP';
        $update = $this->pdo->prepare("UPDATE {$table} SET revokedAt={$timestamp} WHERE id=:id AND revokedAt IS NULL");
        $update->execute(['id' => $decisionId]);
        if ($update->rowCount() !== 1) {
            throw new ApiException(409, 'SAFEGUARDING_DECISION_ALREADY_REVOKED', 'Quyết định đã được thu hồi.');
        }
        $this->audit($actorUserId, $guardian ? 'GUARDIAN_CONSENT_REVOKE' : 'SCHOOL_ENTERPRISE_ACCESS_REVOKE', $table, $decisionId, [
            'schoolId' => $schoolId,
        ]);
    }

    private function schoolIdForActor(string $actorUserId): string
    {
        $statement = $this->pdo->prepare('SELECT schoolId FROM school_members WHERE userId=:userId LIMIT 1');
        $statement->execute(['userId' => $actorUserId]);
        $schoolId = $statement->fetchColumn();
        if (!is_string($schoolId)) {
            throw new ApiException(403, 'FORBIDDEN', 'Người dùng không thuộc trường nào.');
        }
        return $schoolId;
    }

    private function assertStudentSchool(string $studentId, string $schoolId): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM student_profiles sp JOIN classes c ON c.id=sp.classId WHERE sp.id=:studentId AND c.schoolId=:schoolId');
        $statement->execute(['studentId' => $studentId, 'schoolId' => $schoolId]);
        if (!$statement->fetchColumn()) {
            throw new ApiException(403, 'FORBIDDEN', 'Học sinh không thuộc trường hiện tại.');
        }
    }

    private function assertEnterprise(string $enterpriseId): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM enterprises WHERE id=:enterpriseId LIMIT 1');
        $statement->execute(['enterpriseId' => $enterpriseId]);
        if (!$statement->fetchColumn()) {
            throw new ApiException(404, 'ENTERPRISE_NOT_FOUND', 'Không tìm thấy doanh nghiệp.');
        }
    }

    private function hasActiveDecision(string $table, string $studentId, string $enterpriseId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM {$table} WHERE studentId=:studentId AND enterpriseId=:enterpriseId
             AND revokedAt IS NULL AND expiresAt>:now LIMIT 1"
        );
        $statement->execute(['studentId' => $studentId, 'enterpriseId' => $enterpriseId, 'now' => gmdate('Y-m-d H:i:s')]);
        return (bool) $statement->fetchColumn();
    }

    private function approvedPartnership(string $schoolId, string $enterpriseId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM school_enterprise_partnerships WHERE schoolId=:schoolId AND enterpriseId=:enterpriseId AND status='approved' LIMIT 1");
        $statement->execute(['schoolId' => $schoolId, 'enterpriseId' => $enterpriseId]);
        return (bool) $statement->fetchColumn();
    }

    private function age(mixed $dateOfBirth): ?int
    {
        if (!is_string($dateOfBirth) || $dateOfBirth === '') {
            return null;
        }
        return (new DateTimeImmutable($dateOfBirth, new DateTimeZone('UTC')))
            ->diff(new DateTimeImmutable('today', new DateTimeZone('UTC')))->y;
    }

    private function educationEligible(string $educationLevel, ?int $age, int $gradeLevel): bool
    {
        $normalized = mb_strtolower($educationLevel);
        $requiresHigherEducation = preg_match('/đại học|cao đẳng|university|college/u', $normalized) === 1;
        return !$requiresHigherEducation || (($age ?? 0) >= 18 && $gradeLevel >= 12);
    }

    private function boolean(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === 0 || $value === '1' || $value === '0') {
            return (bool) $value;
        }
        throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải là boolean.");
    }

    /** @param array<string,mixed> $metadata */
    private function audit(string $actorId, string $action, string $entityType, string $entityId, array $metadata): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (id,userId,action,entityType,entityId,metadata)
             VALUES (:id,:userId,:action,:entityType,:entityId,:metadata)'
        );
        $statement->execute([
            'id' => Uuid::v4(),
            'userId' => $actorId,
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }
}
