<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class SchoolCredentialManagementRepository
{
    public function __construct(private readonly PDO $pdo, private readonly ?NotificationService $notifications = null) {}

    public function schoolIdForUser(string $userId): ?string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT schoolId FROM school_members WHERE userId = :memberUserId
UNION ALL
SELECT schoolId FROM teacher_profiles WHERE userId = :teacherUserId
LIMIT 1
SQL);
        $statement->execute(['memberUserId' => $userId, 'teacherUserId' => $userId]);
        $id = $statement->fetchColumn();
        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array<string,mixed> */
    public function dashboard(string $schoolId, string $actorUserId): array
    {
        $scope = $this->actorScope($schoolId, $actorUserId);
        $params = ['schoolId' => $schoolId] + $scope['params'];

        $badges = $this->fetchAll(<<<'SQL'
SELECT b.*, r.id AS ruleId, r.thresholdCriteria
FROM badges b
LEFT JOIN badge_rule_definitions r ON r.badgeId = b.id AND r.isActive = 1
WHERE b.schoolId = :schoolId
ORDER BY b.createdAt DESC
SQL, ['schoolId' => $schoolId]);
        $certificates = $this->fetchAll(
            'SELECT * FROM school_certificate_catalog WHERE schoolId=:schoolId ORDER BY createdAt DESC',
            ['schoolId' => $schoolId],
        );
        $students = $this->fetchAll(<<<SQL
SELECT sp.id, sp.classId, u.fullName, u.email, c.name AS className,
       COALESCE((SELECT SUM(lt.activeSeconds) FROM student_learning_time_logs lt WHERE lt.studentId = sp.id), 0) AS onlineSeconds
FROM student_profiles sp
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN users u ON u.id = sp.userId
WHERE c.schoolId = :schoolId
  AND sp.studyStatus = 'active'
  {$scope['studentClause']}
ORDER BY c.name, u.fullName
SQL, $params);
        $classes = $this->fetchAll(<<<SQL
SELECT c.id, c.name, COUNT(sp.id) AS studentCount
FROM classes c
LEFT JOIN student_profiles sp ON sp.classId = c.id AND sp.studyStatus = 'active'
WHERE c.schoolId = :schoolId
  AND c.status = 'active'
  {$scope['classClause']}
GROUP BY c.id, c.name
ORDER BY c.name
SQL, $params);
        $awards = $this->fetchAll(<<<SQL
SELECT sc.id, sc.studentId, sc.certificateCatalogId, sc.status, sc.issuedAt,
       sc.issueSource, sc.reason, sc.activityName, sc.evidenceContext,
       catalog.name, u.fullName AS studentName, c.name AS className
FROM student_certificates sc
INNER JOIN school_certificate_catalog catalog ON catalog.id = sc.certificateCatalogId
INNER JOIN student_profiles sp ON sp.id = sc.studentId
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN users u ON u.id = sp.userId
WHERE catalog.schoolId = :schoolId
  {$scope['studentClause']}
ORDER BY sc.issuedAt DESC, sc.id
LIMIT 200
SQL, $params);
        $badgeAwards = $this->fetchAll(<<<SQL
SELECT sb.id, sb.studentId, sb.badgeId, sb.awardedAt, sb.awardedBy, sb.awardContext,
       b.name, u.fullName AS studentName, c.name AS className
FROM student_badges sb
INNER JOIN badges b ON b.id = sb.badgeId
INNER JOIN student_profiles sp ON sp.id = sb.studentId
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN users u ON u.id = sp.userId
WHERE b.schoolId = :schoolId
  {$scope['studentClause']}
ORDER BY sb.awardedAt DESC, sb.id
LIMIT 200
SQL, $params);

        $totalSeconds = array_sum(array_map(static fn (array $student): int => (int) $student['onlineSeconds'], $students));
        foreach ($students as &$student) {
            $minutes = (int) floor((int) $student['onlineSeconds'] / 60);
            $student['onlineMinutes'] = $minutes;
            $student['attendanceMilestone'] = match (true) {
                $minutes >= 1200 => 1200,
                $minutes >= 300 => 300,
                $minutes >= 60 => 60,
                default => 0,
            };
        }
        unset($student);

        return [
            'badges' => $badges,
            'certificates' => $certificates,
            'awards' => $awards,
            'badgeAwards' => $badgeAwards,
            'students' => $students,
            'classes' => $classes,
            'learningSummary' => [
                'totalMinutes' => (int) floor($totalSeconds / 60),
                'activeStudents' => count(array_filter($students, static fn (array $student): bool => (int) $student['onlineMinutes'] > 0)),
                'trackedStudents' => count($students),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function createBadge(string $actorUserId, string $schoolId, array $data, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $data, $requestId): array {
            $badgeId = Uuid::v4();
            $ruleId = Uuid::v4();
            $now = $this->now();
            try {
                $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO badges (id,schoolId,code,name,category,description,recommendationProfile,recommendationEnabled,iconUrl,level,status,createdAt,updatedAt)
VALUES (:id,:schoolId,:code,:name,:category,:description,:profile,:enabled,:iconUrl,:level,:status,:createdAt,:updatedAt)
SQL);
                $statement->execute([
                    'id' => $badgeId,
                    'schoolId' => $schoolId,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'profile' => $this->json($data['recommendationProfile']),
                    'enabled' => $data['recommendationEnabled'] ? 1 : 0,
                    'iconUrl' => $data['iconUrl'],
                    'level' => $data['level'],
                    'status' => $data['status'],
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
                $rule = $this->pdo->prepare(<<<'SQL'
INSERT INTO badge_rule_definitions (id,badgeId,ruleType,thresholdCriteria,version,isActive,createdAt,updatedAt)
VALUES (:id,:badgeId,'threshold',:criteria,1,1,:createdAt,:updatedAt)
SQL);
                $rule->execute([
                    'id' => $ruleId,
                    'badgeId' => $badgeId,
                    'criteria' => $this->json($data['criteria']),
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            } catch (PDOException $exception) {
                if ($this->isDuplicate($exception)) {
                    throw new ApiException(409, 'DUPLICATE_CREDENTIAL_CODE', 'Mã huy hiệu đã tồn tại.');
                }
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
                $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO school_certificate_catalog (id,schoolId,code,name,description,issuerName,iconKey,eligibilityCriteria,recommendationProfile,recommendationEnabled,status,createdAt,updatedAt)
VALUES (:id,:schoolId,:code,:name,:description,:issuerName,:iconKey,:criteria,:profile,:enabled,:status,:createdAt,:updatedAt)
SQL);
                $statement->execute([
                    'id' => $id,
                    'schoolId' => $schoolId,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'issuerName' => $data['issuerName'],
                    'iconKey' => $data['iconKey'],
                    'criteria' => $this->json($data['criteria']),
                    'profile' => $this->json($data['recommendationProfile']),
                    'enabled' => $data['recommendationEnabled'] ? 1 : 0,
                    'status' => $data['status'],
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            } catch (PDOException $exception) {
                if ($this->isDuplicate($exception)) {
                    throw new ApiException(409, 'DUPLICATE_CREDENTIAL_CODE', 'Mã chứng chỉ đã tồn tại.');
                }
                throw $exception;
            }
            $this->audit($actorUserId, 'school_certificate_catalog.created', 'school_certificate_catalog', $id, $requestId, ['schoolId' => $schoolId, 'code' => $data['code']], $now);
            return ['id' => $id, 'status' => $data['status']];
        });
    }

    /** @return array{created:int,updated:int,total:int,items:list<array<string,mixed>>} */
    public function awardBadgeToTarget(
        string $actorUserId,
        string $schoolId,
        string $badgeId,
        ?string $studentId,
        ?string $classId,
        string $issuedDate,
        array $evidence,
        string $requestId,
    ): array {
        return $this->transaction(function () use ($actorUserId, $schoolId, $badgeId, $studentId, $classId, $issuedDate, $evidence, $requestId): array {
            $credential = $this->credentialForSchool($schoolId, 'badge', $badgeId);
            $students = $this->targetStudents($schoolId, $actorUserId, $studentId, $classId);
            $items = [];
            $created = 0;
            $updated = 0;
            foreach ($students as $student) {
                $item = $this->upsertBadgeAward($actorUserId, $schoolId, $credential, $student, $issuedDate, $evidence, $requestId);
                $items[] = $item;
                $item['operation'] === 'created' ? $created++ : $updated++;
            }
            return ['created' => $created, 'updated' => $updated, 'total' => count($items), 'items' => $items];
        });
    }

    /** @return array{created:int,updated:int,total:int,items:list<array<string,mixed>>} */
    public function issueCertificateToTarget(
        string $actorUserId,
        string $schoolId,
        string $catalogId,
        ?string $studentId,
        ?string $classId,
        string $issuedDate,
        array $evidence,
        string $requestId,
    ): array {
        return $this->transaction(function () use ($actorUserId, $schoolId, $catalogId, $studentId, $classId, $issuedDate, $evidence, $requestId): array {
            $credential = $this->credentialForSchool($schoolId, 'certificate', $catalogId);
            $students = $this->targetStudents($schoolId, $actorUserId, $studentId, $classId);
            $items = [];
            $created = 0;
            $updated = 0;
            foreach ($students as $student) {
                $item = $this->upsertCertificateAward($actorUserId, $schoolId, $credential, $student, $issuedDate, $evidence, $requestId);
                $items[] = $item;
                $item['operation'] === 'created' ? $created++ : $updated++;
            }
            return ['created' => $created, 'updated' => $updated, 'total' => count($items), 'items' => $items];
        });
    }

    /** @return array<string,mixed> */
    public function revokeCertificate(string $actorUserId, string $schoolId, string $awardId, string $reason, string $requestId): array
    {
        return $this->transaction(function () use ($actorUserId, $schoolId, $awardId, $reason, $requestId): array {
            $award = $this->fetchOne(<<<'SQL'
SELECT sc.id, sc.studentId, sc.status, sc.evidenceContext, catalog.name
FROM student_certificates sc
INNER JOIN school_certificate_catalog catalog ON catalog.id = sc.certificateCatalogId
WHERE sc.id = :id AND catalog.schoolId = :schoolId
LIMIT 1
SQL . $this->lockSuffix(), ['id' => $awardId, 'schoolId' => $schoolId]);
            if ($award === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy chứng chỉ thuộc trường hiện tại.');
            }
            $this->assertStudentAccess($schoolId, $actorUserId, (string) $award['studentId']);
            if ((string) $award['status'] === 'revoked') {
                return ['id' => $awardId, 'status' => 'revoked'];
            }
            $evidence = json_decode((string) $award['evidenceContext'], true);
            $evidence = is_array($evidence) ? $evidence : [];
            $now = $this->now();
            $evidence['revocation'] = ['reason' => $reason, 'revokedBy' => $actorUserId, 'revokedAt' => $now];
            $update = $this->pdo->prepare("UPDATE student_certificates SET status='revoked', evidenceContext=:evidence, updatedAt=:updatedAt WHERE id=:id AND status='issued'");
            $update->execute(['evidence' => $this->json($evidence), 'updatedAt' => $now, 'id' => $awardId]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'CREDENTIAL_STATUS_CONFLICT', 'Trạng thái chứng chỉ đã thay đổi.');
            }
            $this->audit($actorUserId, 'school_certificate.revoked', 'student_certificate', $awardId, $requestId, [
                'schoolId' => $schoolId,
                'studentId' => $award['studentId'],
                'reason' => $reason,
            ], $now);
            $this->notifyStudent((string) $award['studentId'], 'school_certificate_revoked', 'Chứng chỉ đã được thu hồi', 'Nhà trường đã thu hồi chứng chỉ ' . (string) $award['name'] . ': ' . $reason, 'school_certificate_revoked:' . $awardId, $now);
            return ['id' => $awardId, 'status' => 'revoked', 'revokedAt' => $now];
        });
    }

    /** @return array<string,mixed> */
    private function upsertBadgeAward(string $actorUserId, string $schoolId, array $credential, array $student, string $issuedDate, array $evidence, string $requestId): array
    {
        $existing = $this->fetchOne(
            'SELECT id FROM student_badges WHERE studentId=:studentId AND badgeId=:badgeId LIMIT 1' . $this->lockSuffix(),
            ['studentId' => $student['id'], 'badgeId' => $credential['id']],
        );
        $now = $this->now();
        $awardedAt = $issuedDate . ' 00:00:00.000000';
        $context = $evidence + ['schoolId' => $schoolId, 'issuedBy' => $actorUserId, 'issuedDate' => $issuedDate];
        if ($existing === null) {
            $id = Uuid::v4();
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO student_badges (id,studentId,badgeId,ruleDefinitionId,awardedAt,awardedBy,awardContext)
VALUES (:id,:studentId,:badgeId,:ruleId,:awardedAt,:awardedBy,:context)
SQL);
            $statement->execute([
                'id' => $id,
                'studentId' => $student['id'],
                'badgeId' => $credential['id'],
                'ruleId' => $credential['ruleId'],
                'awardedAt' => $awardedAt,
                'awardedBy' => $this->actorAwardType($actorUserId),
                'context' => $this->json($context),
            ]);
            $operation = 'created';
        } else {
            $id = (string) $existing['id'];
            $statement = $this->pdo->prepare('UPDATE student_badges SET ruleDefinitionId=:ruleId, awardedAt=:awardedAt, awardedBy=:awardedBy, awardContext=:context WHERE id=:id');
            $statement->execute([
                'ruleId' => $credential['ruleId'],
                'awardedAt' => $awardedAt,
                'awardedBy' => $this->actorAwardType($actorUserId),
                'context' => $this->json($context),
                'id' => $id,
            ]);
            $operation = 'updated';
        }
        $this->audit($actorUserId, 'school_badge.' . $operation, 'student_badge', $id, $requestId, [
            'schoolId' => $schoolId,
            'studentId' => $student['id'],
            'badgeId' => $credential['id'],
            'issuedDate' => $issuedDate,
        ], $now);
        $this->notifyStudent((string) $student['id'], 'school_badge_awarded', 'Bạn nhận được huy hiệu mới', 'Nhà trường đã cấp huy hiệu ' . (string) $credential['name'] . ' cho bạn.', 'school_badge_awarded:' . $id, $now);
        return ['id' => $id, 'studentId' => $student['id'], 'studentName' => $student['fullName'], 'status' => 'awarded', 'operation' => $operation, 'awardedAt' => $awardedAt];
    }

    /** @return array<string,mixed> */
    private function upsertCertificateAward(string $actorUserId, string $schoolId, array $credential, array $student, string $issuedDate, array $evidence, string $requestId): array
    {
        $existing = $this->fetchOne(
            'SELECT id,status FROM student_certificates WHERE studentId=:studentId AND certificateCatalogId=:catalogId LIMIT 1' . $this->lockSuffix(),
            ['studentId' => $student['id'], 'catalogId' => $credential['id']],
        );
        if ($existing !== null && (string) $existing['status'] === 'revoked') {
            throw new ApiException(409, 'CREDENTIAL_REVOKED', 'Chứng chỉ đã bị thu hồi; cần tạo mẫu mới nếu muốn cấp lại.');
        }
        $now = $this->now();
        $issuedAt = $issuedDate . ' 00:00:00.000000';
        $context = $evidence + ['schoolId' => $schoolId, 'issuedBy' => $actorUserId, 'issuedDate' => $issuedDate];
        if ($existing === null) {
            $id = Uuid::v4();
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO student_certificates (id,studentId,certificateCatalogId,status,issuedAt,issuedBy,issueSource,reason,activityName,evidenceContext,createdAt,updatedAt)
VALUES (:id,:studentId,:catalogId,'issued',:issuedAt,:issuedBy,'manual',:reason,:activityName,:evidence,:createdAt,:updatedAt)
SQL);
            $statement->execute([
                'id' => $id,
                'studentId' => $student['id'],
                'catalogId' => $credential['id'],
                'issuedAt' => $issuedAt,
                'issuedBy' => $actorUserId,
                'reason' => $evidence['reason'],
                'activityName' => $evidence['activityName'],
                'evidence' => $this->json($context),
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
            $operation = 'created';
        } else {
            $id = (string) $existing['id'];
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE student_certificates
SET issuedAt=:issuedAt, issuedBy=:issuedBy, issueSource='manual', reason=:reason,
    activityName=:activityName, evidenceContext=:evidence, updatedAt=:updatedAt
WHERE id=:id AND status='issued'
SQL);
            $statement->execute([
                'issuedAt' => $issuedAt,
                'issuedBy' => $actorUserId,
                'reason' => $evidence['reason'],
                'activityName' => $evidence['activityName'],
                'evidence' => $this->json($context),
                'updatedAt' => $now,
                'id' => $id,
            ]);
            $operation = 'updated';
        }
        $this->audit($actorUserId, 'school_certificate.' . $operation, 'student_certificate', $id, $requestId, [
            'schoolId' => $schoolId,
            'studentId' => $student['id'],
            'catalogId' => $credential['id'],
            'issuedDate' => $issuedDate,
        ], $now);
        $this->notifyStudent((string) $student['id'], 'school_certificate_issued', 'Nhà trường đã cấp chứng chỉ', 'Bạn vừa nhận chứng chỉ ' . (string) $credential['name'] . '.', 'school_certificate_issued:' . $id, $now);
        return ['id' => $id, 'studentId' => $student['id'], 'studentName' => $student['fullName'], 'status' => 'issued', 'operation' => $operation, 'issuedAt' => $issuedAt];
    }

    /** @return array<string,mixed> */
    private function credentialForSchool(string $schoolId, string $kind, string $credentialId): array
    {
        if ($kind === 'badge') {
            $credential = $this->fetchOne(<<<'SQL'
SELECT b.id, b.name, r.id AS ruleId
FROM badges b
LEFT JOIN badge_rule_definitions r ON r.badgeId = b.id AND r.isActive = 1
WHERE b.id = :id AND b.schoolId = :schoolId AND b.status = 'active'
LIMIT 1
SQL, ['id' => $credentialId, 'schoolId' => $schoolId]);
        } else {
            $credential = $this->fetchOne(
                "SELECT id,name FROM school_certificate_catalog WHERE id=:id AND schoolId=:schoolId AND status='active' LIMIT 1",
                ['id' => $credentialId, 'schoolId' => $schoolId],
            );
        }
        if ($credential === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy mẫu thành tích đang hoạt động thuộc trường hiện tại.');
        }
        $credential['ruleId'] = $credential['ruleId'] ?? null;
        return $credential;
    }

    /** @return list<array{id:string,fullName:string,classId:string}> */
    private function targetStudents(string $schoolId, string $actorUserId, ?string $studentId, ?string $classId): array
    {
        $scope = $this->actorScope($schoolId, $actorUserId);
        $params = ['schoolId' => $schoolId] + $scope['params'];
        $targetClause = '';
        if ($studentId !== null) {
            $targetClause = ' AND sp.id = :studentId';
            $params['studentId'] = $studentId;
        } elseif ($classId !== null) {
            $targetClause = ' AND c.id = :classId';
            $params['classId'] = $classId;
        } else {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Cần chọn sinh viên hoặc lớp học.');
        }
        $students = $this->fetchAll(<<<SQL
SELECT sp.id, sp.classId, u.fullName
FROM student_profiles sp
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN users u ON u.id = sp.userId
WHERE c.schoolId = :schoolId
  AND sp.studyStatus = 'active'
  {$scope['studentClause']}
  {$targetClause}
ORDER BY u.fullName
SQL, $params);
        if ($students === []) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không có sinh viên đang hoạt động trong phạm vi được phép.');
        }
        return $students;
    }

    private function assertStudentAccess(string $schoolId, string $actorUserId, string $studentId): void
    {
        $this->targetStudents($schoolId, $actorUserId, $studentId, null);
    }

    /** @return array{studentClause:string,classClause:string,params:array<string,string>} */
    private function actorScope(string $schoolId, string $actorUserId): array
    {
        $actor = $this->fetchOne(
            'SELECT sm.memberRole, tp.id AS teacherId, tp.isSchoolAdmin
             FROM school_members sm
             LEFT JOIN teacher_profiles tp ON tp.userId = sm.userId AND tp.schoolId = sm.schoolId
             WHERE sm.userId=:userId AND sm.schoolId=:schoolId LIMIT 1',
            ['userId' => $actorUserId, 'schoolId' => $schoolId],
        );
        if ($actor === null) {
            throw new ApiException(403, 'CREDENTIAL_SCHOOL_SCOPE_DENIED', 'Tài khoản không thuộc trường hiện tại.');
        }
        if (($actor['memberRole'] ?? '') === 'admin' || (int) ($actor['isSchoolAdmin'] ?? 0) === 1) {
            return ['studentClause' => '', 'classClause' => '', 'params' => []];
        }
        if (empty($actor['teacherId'])) {
            throw new ApiException(403, 'CREDENTIAL_ADMIN_REQUIRED', 'Chỉ School Admin hoặc Teacher được quản lý thành tích.');
        }
        return [
            'studentClause' => "AND EXISTS (SELECT 1 FROM teacher_class_assignments tca WHERE tca.classId = sp.classId AND tca.teacherId = :scopeTeacherId AND tca.status = 'active')",
            'classClause' => "AND EXISTS (SELECT 1 FROM teacher_class_assignments tca WHERE tca.classId = c.id AND tca.teacherId = :scopeTeacherId AND tca.status = 'active')",
            'params' => ['scopeTeacherId' => (string) $actor['teacherId']],
        ];
    }

    private function actorAwardType(string $actorUserId): string
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM teacher_profiles WHERE userId=:userId LIMIT 1');
        $statement->execute(['userId' => $actorUserId]);
        return $statement->fetchColumn() === false ? 'school_admin' : 'teacher';
    }

    private function notifyStudent(string $studentId, string $type, string $title, string $message, string $eventKey, string $now): void
    {
        $student = $this->fetchOne('SELECT userId FROM student_profiles WHERE id=:id LIMIT 1', ['id' => $studentId]);
        if ($student === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tài khoản sinh viên.');
        }
        if ($this->notifications !== null) {
            $this->notifications->publish(
                (string) $student['userId'],
                $type,
                $title,
                $message,
                '/app/learner/badges.php',
                $eventKey,
                $studentId,
            );
            return;
        }
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO notifications (id,userId,eventKey,notificationType,title,message,deepLink,readAt,createdAt)
VALUES (:id,:userId,:eventKey,:type,:title,:message,'/app/learner/badges.php',NULL,:createdAt)
SQL);
            $statement->execute([
                'id' => Uuid::v4(),
                'userId' => $student['userId'],
                'eventKey' => $eventKey,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'createdAt' => $now,
            ]);
        } catch (PDOException $exception) {
            if (!$this->isDuplicate($exception)) {
                throw $exception;
            }
        }
    }

    private function audit(string $userId, string $action, string $entityType, string $entityId, string $requestId, array $metadata, string $now): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt)
VALUES (:id,:userId,:action,:entityType,:entityId,:requestId,NULL,:metadata,:createdAt)
SQL);
        $statement->execute([
            'id' => Uuid::v4(),
            'userId' => $userId,
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'requestId' => $requestId,
            'metadata' => $this->json($metadata),
            'createdAt' => $now,
        ]);
    }

    private function transaction(callable $operation): mixed
    {
        $owns = !$this->pdo->inTransaction();
        if ($owns) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($owns) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function fetchAll(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function fetchOne(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s.u');
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function isDuplicate(PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            || ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && (int) ($exception->errorInfo[1] ?? 0) === 19);
    }
}
