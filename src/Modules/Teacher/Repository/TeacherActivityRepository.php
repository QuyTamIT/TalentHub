<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;

final class TeacherActivityRepository
{
    /** @var array<string,string> */
    private const STATUS_TRANSITIONS = [
        'draft' => 'published',
        'published' => 'ongoing',
        'ongoing' => 'completed',
        'completed' => 'archived',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}

    public function teacherIdForUser(string $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT id FROM teacher_profiles WHERE userId = :userId LIMIT 1');
        $statement->execute(['userId' => $userId]);
        $teacherId = $statement->fetchColumn();

        return $teacherId === false ? null : (string) $teacherId;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $teacherId, string $search = ''): array
    {
        $sql = $this->activitySelectSql() . ' WHERE a.createdByTeacherId = :teacherId';
        $params = ['teacherId' => $teacherId];

        if ($search !== '') {
            $sql .= ' AND LOWER(a.title) LIKE :search';
            $params['search'] = '%' . mb_strtolower($search) . '%';
        }

        $sql .= ' ORDER BY a.startAt DESC, a.title ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(string $teacherId, string $activityId): ?array
    {
        $statement = $this->pdo->prepare($this->activitySelectSql() . ' WHERE a.createdByTeacherId = :teacherId AND a.id = :activityId LIMIT 1');
        $statement->execute(['teacherId' => $teacherId, 'activityId' => $activityId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function registrations(string $teacherId, string $activityId): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                ar.id,
                ar.status,
                u.fullName AS student_name,
                u.email AS student_email
            FROM activity_registrations ar
            INNER JOIN activities a ON a.id = ar.activityId
            INNER JOIN student_profiles sp ON sp.id = ar.studentId
            INNER JOIN users u ON u.id = sp.userId
            WHERE a.createdByTeacherId = :teacherId
              AND ar.activityId = :activityId
            ORDER BY u.fullName ASC
        ");
        $statement->execute(['teacherId' => $teacherId, 'activityId' => $activityId]);

        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public function create(string $teacherId, string $schoolId, string $activityId, array $data): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $actualSchoolId = $this->schoolIdForTeacher($teacherId);
            if ($actualSchoolId === null) throw new ApiException(403, 'PERMISSION_DENIED', 'Không tìm thấy hồ sơ giáo viên hợp lệ.');
            $columns = 'id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status';
            $values = ':id, :schoolId, :teacherId, :title, :category, :startAt, :endAt, :capacity, \'draft\'';
            if ($this->hasColumn('activities', 'visibility')) { $columns .= ', visibility'; $values .= ", 'school_only'"; }
            $statement = $this->pdo->prepare("INSERT INTO activities ({$columns}) VALUES ({$values})");
            $statement->execute([
                'id' => $activityId,
                'schoolId' => $actualSchoolId,
                'teacherId' => $teacherId,
                'title' => $data['title'], 'category' => $data['category'],
                'startAt' => $data['startAt'], 'endAt' => $data['endAt'], 'capacity' => $data['capacity'],
            ]);
            $this->writeDetails($activityId, $actualSchoolId, $data);
            $this->writePolicies($activityId, $data);
            if ($ownsTransaction) $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function update(string $teacherId, string $activityId, array $data): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $lock = $this->lockSuffix();
            $activity = $this->pdo->prepare("SELECT id, schoolId, capacity, status FROM activities WHERE id=:activityId AND createdByTeacherId=:teacherId LIMIT 1{$lock}");
            $activity->execute(['activityId' => $activityId, 'teacherId' => $teacherId]);
            $row = $activity->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.');
            $occupied = $this->pdo->prepare("SELECT COUNT(*) FROM activity_registrations WHERE activityId=:activityId AND status IN ('approved','attended')");
            $occupied->execute(['activityId' => $activityId]);
            if ((int) $data['capacity'] < (int) $occupied->fetchColumn()) {
                throw new ApiException(409, 'CAPACITY_REACHED', 'Sức chứa không được thấp hơn số đăng ký đã được duyệt hoặc đã tham dự.');
            }
            $this->assertResponsibleTeacher($data['responsibleTeacherId'] ?? null, (string) $row['schoolId']);
            $statement = $this->pdo->prepare('UPDATE activities SET title=:title, category=:category, startAt=:startAt, endAt=:endAt, capacity=:capacity WHERE id=:activityId AND createdByTeacherId=:teacherId');
            $statement->execute([
                'title' => $data['title'], 'category' => $data['category'], 'startAt' => $data['startAt'],
                'endAt' => $data['endAt'], 'capacity' => $data['capacity'], 'activityId' => $activityId, 'teacherId' => $teacherId,
            ]);
            $this->writeDetails($activityId, (string) $row['schoolId'], $data);
            $this->writePolicies($activityId, $data);
            if ($ownsTransaction) $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return list<array{id:string,name:string}> */
    public function responsibleTeachers(string $teacherId): array
    {
        $schoolId = $this->schoolIdForTeacher($teacherId);
        if ($schoolId === null) return [];
        $name = $this->hasTable('users') ? 'COALESCE(u.fullName, t.id)' : 't.id';
        $join = $this->hasTable('users') ? 'LEFT JOIN users u ON u.id = t.userId' : '';
        $statement = $this->pdo->prepare("SELECT t.id, {$name} AS name FROM teacher_profiles t {$join} WHERE t.schoolId=:schoolId ORDER BY name, t.id");
        $statement->execute(['schoolId' => $schoolId]);
        return array_map(static fn (array $row): array => ['id' => (string) $row['id'], 'name' => (string) $row['name']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function activitySelectSql(): string
    {
        $details = $this->hasTable('activity_details');
        $policies = $this->hasTable('activity_registration_policies');
        $experience = $this->hasTable('activity_experience_policies');
        $detailJoin = $details ? ' LEFT JOIN activity_details d ON d.activityId = a.id' : '';
        $policyJoin = $policies ? ' LEFT JOIN activity_registration_policies p ON p.activityId = a.id' : '';
        $experienceJoin = $experience ? ' LEFT JOIN activity_experience_policies e ON e.activityId = a.id' : '';
        $detail = static fn (string $column): string => $details ? "d.{$column} AS {$column}" : "NULL AS {$column}";
        $policy = static fn (string $column): string => $policies ? "p.{$column} AS {$column}" : "NULL AS {$column}";
        $hours = $experience ? 'e.confirmedHours AS confirmedHours' : 'NULL AS confirmedHours';
        return "SELECT a.id, a.title, a.category, a.startAt, a.endAt, a.capacity, a.status,
            {$detail('responsibleTeacherId')}, {$detail('audienceScope')}, {$detail('displayCategory')}, {$detail('filterCategory')},
            {$detail('summary')}, {$detail('description')}, {$detail('experienceHighlights')}, {$detail('skillTags')},
            {$detail('eligibilityRules')}, {$detail('benefitItems')}, {$detail('locationName')}, {$detail('locationAddress')},
            {$detail('deliveryMode')}, {$detail('onlineMeetingUrl')}, {$detail('organizerName')}, {$detail('organizerContact')},
            {$detail('organizerEmail')}, {$detail('organizerPhone')}, {$detail('coverImageUrl')}, {$detail('coverImageAlt')},
            {$detail('feeAmount')}, {$detail('currency')}, {$detail('targetAudience')}, {$detail('certificateLabel')},
            {$policy('registrationOpensAt')}, {$policy('registrationClosesAt')}, {$policy('cancellationClosesAt')}, {$policy('approvalMode')},
            {$hours}, (SELECT COUNT(*) FROM activity_registrations ar WHERE ar.activityId=a.id AND ar.status IN ('approved','attended')) AS registered_count
            FROM activities a{$detailJoin}{$policyJoin}{$experienceJoin}";
    }

    /** @param array<string,mixed> $data */
    private function writeDetails(string $activityId, string $schoolId, array $data): void
    {
        $responsible = $data['responsibleTeacherId'];
        $this->assertResponsibleTeacher($responsible, $schoolId);
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM activity_details WHERE activityId=:activityId');
        $exists->execute(['activityId' => $activityId]);
        $params = [
            'activityId' => $activityId, 'responsibleTeacherId' => $responsible, 'audienceScope' => 'school_only',
            'displayCategory' => $data['displayCategory'], 'filterCategory' => $data['filterCategory'], 'summary' => $data['summary'],
            'description' => $data['description'], 'experienceHighlights' => json_encode($data['experienceHighlights'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'skillTags' => json_encode($data['skillTags'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'eligibilityRules' => json_encode($data['eligibilityRules'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'benefitItems' => json_encode($data['benefitItems'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'locationName' => $data['locationName'], 'locationAddress' => $data['locationAddress'], 'deliveryMode' => $data['deliveryMode'],
            'onlineMeetingUrl' => $data['onlineMeetingUrl'], 'organizerName' => $data['organizerName'], 'organizerContact' => $data['organizerContact'],
            'organizerEmail' => $data['organizerEmail'], 'organizerPhone' => $data['organizerPhone'], 'coverImageUrl' => $data['coverImageUrl'],
            'coverImageAlt' => $data['coverImageAlt'], 'feeAmount' => $data['feeAmount'], 'currency' => $data['currency'],
            'targetAudience' => $data['targetAudience'], 'certificateLabel' => $data['certificateLabel'],
            'createdAt' => gmdate('Y-m-d H:i:s.u'), 'updatedAt' => gmdate('Y-m-d H:i:s.u'),
        ];
        if ((int) $exists->fetchColumn() === 1) {
            $sql = 'UPDATE activity_details SET responsibleTeacherId=:responsibleTeacherId,audienceScope=:audienceScope,displayCategory=:displayCategory,filterCategory=:filterCategory,summary=:summary,description=:description,experienceHighlights=:experienceHighlights,skillTags=:skillTags,eligibilityRules=:eligibilityRules,benefitItems=:benefitItems,locationName=:locationName,locationAddress=:locationAddress,deliveryMode=:deliveryMode,onlineMeetingUrl=:onlineMeetingUrl,organizerName=:organizerName,organizerContact=:organizerContact,organizerEmail=:organizerEmail,organizerPhone=:organizerPhone,coverImageUrl=:coverImageUrl,coverImageAlt=:coverImageAlt,feeAmount=:feeAmount,currency=:currency,targetAudience=:targetAudience,certificateLabel=:certificateLabel,updatedAt=:updatedAt WHERE activityId=:activityId';
            unset($params['createdAt']);
        } else {
            $sql = 'INSERT INTO activity_details (activityId,responsibleTeacherId,audienceScope,displayCategory,filterCategory,summary,description,experienceHighlights,skillTags,eligibilityRules,benefitItems,locationName,locationAddress,deliveryMode,onlineMeetingUrl,organizerName,organizerContact,organizerEmail,organizerPhone,coverImageUrl,coverImageAlt,feeAmount,currency,targetAudience,certificateLabel,createdAt,updatedAt) VALUES (:activityId,:responsibleTeacherId,:audienceScope,:displayCategory,:filterCategory,:summary,:description,:experienceHighlights,:skillTags,:eligibilityRules,:benefitItems,:locationName,:locationAddress,:deliveryMode,:onlineMeetingUrl,:organizerName,:organizerContact,:organizerEmail,:organizerPhone,:coverImageUrl,:coverImageAlt,:feeAmount,:currency,:targetAudience,:certificateLabel,:createdAt,:updatedAt)';
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    private function assertResponsibleTeacher(mixed $responsible, string $schoolId): void
    {
        if ($responsible === null || $responsible === '') return;
        $teacher = $this->pdo->prepare('SELECT id FROM teacher_profiles WHERE id=:teacherId AND schoolId=:schoolId LIMIT 1');
        $teacher->execute(['teacherId' => (string) $responsible, 'schoolId' => $schoolId]);
        if ($teacher->fetchColumn() === false) throw new ApiException(422, 'VALIDATION_FAILED', 'Giáo viên phụ trách phải thuộc cùng trường.');
    }

    /** @param array<string,mixed> $data */
    private function writePolicies(string $activityId, array $data): void
    {
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM activity_registration_policies WHERE activityId=:activityId');
        $exists->execute(['activityId' => $activityId]);
        $params = [
            'activityId' => $activityId, 'registrationOpensAt' => $data['registrationOpensAt'], 'registrationClosesAt' => $data['registrationClosesAt'],
            'cancellationClosesAt' => $data['cancellationClosesAt'], 'approvalMode' => $data['approvalMode'],
            'createdAt' => gmdate('Y-m-d H:i:s.u'), 'updatedAt' => gmdate('Y-m-d H:i:s.u'),
        ];
        if ((int) $exists->fetchColumn() === 1) {
            $sql = 'UPDATE activity_registration_policies SET registrationOpensAt=:registrationOpensAt,registrationClosesAt=:registrationClosesAt,cancellationClosesAt=:cancellationClosesAt,approvalMode=:approvalMode,updatedAt=:updatedAt WHERE activityId=:activityId';
            unset($params['createdAt']);
        } else {
            $sql = 'INSERT INTO activity_registration_policies (activityId,registrationOpensAt,registrationClosesAt,cancellationClosesAt,approvalMode,createdAt,updatedAt) VALUES (:activityId,:registrationOpensAt,:registrationClosesAt,:cancellationClosesAt,:approvalMode,:createdAt,:updatedAt)';
        }
        $this->pdo->prepare($sql)->execute($params);

        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM activity_experience_policies WHERE activityId=:activityId');
        $exists->execute(['activityId' => $activityId]);
        $hoursParams = ['activityId' => $activityId, 'confirmedHours' => $data['confirmedHours'], 'createdAt' => gmdate('Y-m-d H:i:s.u'), 'updatedAt' => gmdate('Y-m-d H:i:s.u')];
        if ((int) $exists->fetchColumn() === 1) {
            $sql = 'UPDATE activity_experience_policies SET confirmedHours=:confirmedHours,updatedAt=:updatedAt WHERE activityId=:activityId';
            unset($hoursParams['createdAt']);
        } else {
            $sql = 'INSERT INTO activity_experience_policies (activityId,confirmedHours,createdAt,updatedAt) VALUES (:activityId,:confirmedHours,:createdAt,:updatedAt)';
        }
        $this->pdo->prepare($sql)->execute($hoursParams);
    }

    private function schoolIdForTeacher(string $teacherId): ?string
    {
        $statement = $this->pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id=:teacherId LIMIT 1');
        $statement->execute(['teacherId' => $teacherId]);
        $schoolId = $statement->fetchColumn();
        return is_string($schoolId) && $schoolId !== '' ? $schoolId : null;
    }

    private function assertPublishable(string $teacherId, string $activityId): void
    {
        $row = $this->find($teacherId, $activityId);
        $missing = [];
        if ($row === null || $this->countRows('activity_details', $activityId) !== 1) $missing[] = 'thông tin chi tiết';
        if ($row === null || $this->countRows('activity_registration_policies', $activityId) !== 1) $missing[] = 'cấu hình đăng ký';
        if ($row === null || $this->countRows('activity_experience_policies', $activityId) !== 1) $missing[] = 'số giờ trải nghiệm';
        if ($row !== null) {
            try {
                $start = new \DateTimeImmutable((string) $row['startAt'], new \DateTimeZone('UTC'));
                $end = new \DateTimeImmutable((string) $row['endAt'], new \DateTimeZone('UTC'));
                $opens = new \DateTimeImmutable((string) $row['registrationOpensAt'], new \DateTimeZone('UTC'));
                $closes = new \DateTimeImmutable((string) $row['registrationClosesAt'], new \DateTimeZone('UTC'));
                $cancel = new \DateTimeImmutable((string) $row['cancellationClosesAt'], new \DateTimeZone('UTC'));
                if ($end <= $start || $opens > $closes || $closes >= $start || $cancel > $start) $missing[] = 'thứ tự thời gian đăng ký';
            } catch (Throwable) { $missing[] = 'thời gian đăng ký'; }
            if (!is_numeric($row['confirmedHours'] ?? null)) $missing[] = 'số giờ trải nghiệm';
            if (!in_array((string) ($row['approvalMode'] ?? ''), ['automatic', 'teacher_review'], true)) $missing[] = 'cách duyệt đăng ký';
            if (!in_array((string) ($row['deliveryMode'] ?? ''), ['in_person', 'online', 'hybrid'], true)) $missing[] = 'hình thức tổ chức';
        }
        if ($missing) throw new ApiException(422, 'INVALID_ACTIVITY_CONFIGURATION', 'Chưa thể công bố: còn thiếu hoặc chưa hợp lệ ' . implode(', ', array_unique($missing)) . '.');
    }

    private function countRows(string $table, string $activityId): int
    {
        if (!$this->hasTable($table)) return 0;
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE activityId=:activityId");
        $statement->execute(['activityId' => $activityId]);
        return (int) $statement->fetchColumn();
    }

    private function hasTable(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table");
        } else {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        }
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            foreach ($statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [] as $row) if (($row['name'] ?? null) === $column) return true;
            return false;
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function advanceStatus(string $teacherId, string $activityId, string $expectedStatus, string $nextStatus): bool
    {
        if ((self::STATUS_TRANSITIONS[$expectedStatus] ?? null) !== $nextStatus) {
            throw new \InvalidArgumentException('Invalid activity status transition.');
        }

        if ($expectedStatus === 'draft' && $nextStatus === 'published') {
            $this->assertPublishable($teacherId, $activityId);
        }
        $statement = $this->pdo->prepare("
            UPDATE activities
            SET status = :nextStatus
            WHERE id = :activityId
              AND createdByTeacherId = :teacherId
              AND status = :expectedStatus
        ");
        $statement->execute([
            'nextStatus' => $nextStatus,
            'activityId' => $activityId,
            'teacherId' => $teacherId,
            'expectedStatus' => $expectedStatus,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @return array{id:string,activityId:string,status:string,updatedAt:string} */
    public function transitionRegistration(
        string $teacherId,
        string $actorUserId,
        string $requestId,
        string $activityId,
        string $registrationId,
        string $expectedStatus,
        string $nextStatus,
    ): array {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $lock = $this->lockSuffix();
            $activity = $this->pdo->prepare(
                "SELECT id,title,capacity FROM activities WHERE id=:activityId AND createdByTeacherId=:teacherId{$lock}"
            );
            $activity->execute(['activityId' => $activityId, 'teacherId' => $teacherId]);
            $activityRow = $activity->fetch();
            if (!is_array($activityRow)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc giáo viên này.');
            }

            $registration = $this->pdo->prepare(
                "SELECT id,studentId,status FROM activity_registrations WHERE id=:registrationId AND activityId=:activityId{$lock}"
            );
            $registration->execute(['registrationId' => $registrationId, 'activityId' => $activityId]);
            $registrationRow = $registration->fetch();
            if (!is_array($registrationRow)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy đăng ký thuộc hoạt động này.');
            }
            if ((string) $registrationRow['status'] !== $expectedStatus) {
                throw new ApiException(409, 'STATUS_CONFLICT', 'Đăng ký đã được xử lý hoặc trạng thái đã thay đổi.');
            }

            if ($nextStatus === 'approved') {
                $occupied = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM activity_registrations WHERE activityId=:activityId AND status IN ('approved','attended')"
                );
                $occupied->execute(['activityId' => $activityId]);
                if ((int) $occupied->fetchColumn() >= (int) $activityRow['capacity']) {
                    throw new ApiException(409, 'CAPACITY_REACHED', 'Hoạt động đã đủ chỗ.');
                }
            }

            $updatedAt = gmdate('Y-m-d H:i:s');
            $update = $this->pdo->prepare(
                'UPDATE activity_registrations SET status=:nextStatus,updatedAt=:updatedAt '
                . 'WHERE id=:registrationId AND activityId=:activityId AND status=:expectedStatus'
            );
            $update->execute([
                'nextStatus' => $nextStatus,
                'updatedAt' => $updatedAt,
                'registrationId' => $registrationId,
                'activityId' => $activityId,
                'expectedStatus' => $expectedStatus,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'STATUS_CONFLICT', 'Đăng ký đã được xử lý hoặc trạng thái đã thay đổi.');
            }

            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt) '
                . 'VALUES (:id,:userId,:action,:entityType,:entityId,:requestId,NULL,:metadata,:createdAt)'
            );
            $audit->execute([
                'id' => Uuid::v4(),
                'userId' => $actorUserId,
                'action' => 'activity_registration.' . $nextStatus,
                'entityType' => 'activity_registration',
                'entityId' => $registrationId,
                'requestId' => $requestId,
                'metadata' => json_encode([
                    'activityId' => $activityId,
                    'previousStatus' => $expectedStatus,
                    'status' => $nextStatus,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'createdAt' => $updatedAt,
            ]);

            $studentId = (string) $registrationRow['studentId'];
            $studentUserId = $this->userIdForStudent($studentId);
            if ($nextStatus === 'approved') {
                $this->getNotificationService()->publish(
                    $studentUserId,
                    'activity_registration_approved',
                    'Đăng ký hoạt động được phê duyệt',
                    'Đăng ký tham gia hoạt động ' . ($activityRow['title'] ?? '') . ' của bạn đã được giáo viên phê duyệt.',
                    '/app/learner/my-activities.php',
                    'activity_registration_approved:' . $registrationId,
                    $studentId
                );
            } elseif ($nextStatus === 'rejected') {
                $this->getNotificationService()->publish(
                    $studentUserId,
                    'activity_registration_rejected',
                    'Đăng ký hoạt động bị từ chối',
                    'Đăng ký tham gia hoạt động ' . ($activityRow['title'] ?? '') . ' của bạn không được phê duyệt.',
                    '/app/learner/my-activities.php',
                    'activity_registration_rejected:' . $registrationId,
                    $studentId
                );
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return [
                'id' => $registrationId,
                'activityId' => $activityId,
                'status' => $nextStatus,
                'updatedAt' => $updatedAt,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
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


    private function userIdForStudent(string $studentId): string
    {
        $stmt = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        $userId = $stmt->fetchColumn();
        if (!is_string($userId) || $userId === '') {
            throw new \RuntimeException('Notification recipient is missing for the managed registration.');
        }
        return $userId;
    }
}
