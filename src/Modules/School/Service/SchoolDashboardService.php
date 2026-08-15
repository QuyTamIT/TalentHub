<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Service;

use PDO;
use RuntimeException;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Support\Uuid;

final class SchoolDashboardService
{
    private const ALLOWED_PROFILE_FIELDS = [
        'name', 'logoUrl', 'address', 'phone', 'email', 'website', 'level', 'academicYear'
    ];

    private const ALLOWED_CLASS_FIELDS = ['name', 'gradeLevel', 'academicYear', 'status'];

    private const ALLOWED_STUDENT_FIELDS = ['classId', 'dateOfBirth', 'phone', 'studyStatus'];

    public const REPORT_TYPE_MONTHLY  = 'monthly_summary';
    public const REPORT_TYPE_STUDENTS = 'student_roster';
    public const REPORT_TYPE_CLASS    = 'class_overview';
    public const REPORT_TYPE_AWARDS   = 'awards_summary';

    public const ALLOWED_REPORT_TYPES = [
        self::REPORT_TYPE_MONTHLY,
        self::REPORT_TYPE_STUDENTS,
        self::REPORT_TYPE_CLASS,
        self::REPORT_TYPE_AWARDS,
    ];

    public function __construct(
        private readonly SchoolRepository $repository,
        private readonly PDO $pdo,
        private readonly ?SchoolAuthorization $authorization = null,
    ) {}

    public function getByUser(string $userId): array
    {
        $row = $this->repository->findByUserId($userId);
        if ($row === null) {
            throw new ApiException(404, 'SCHOOL_NOT_FOUND', 'Không tìm thấy trường cho người dùng hiện tại.');
        }
        return $this->presentSchool($row);
    }

    public function dashboard(string $userId): array
    {
        $school = $this->getByUser($userId);
        $schoolId = $school['id'];
        $metrics  = $this->repository->dashboardMetrics($schoolId);

        $topTalents = $this->topStudentsForDemo($schoolId, 4);
        $classes    = $this->repository->listClasses($schoolId);
        $recent     = $this->recentActivityForDemo($schoolId, 5);
        $kpis       = $this->buildKpis($metrics, $classes);

        return [
            'school'        => $school,
            'metrics'       => $metrics,
            'kpis'          => $kpis,
            'topTalents'    => $topTalents,
            'classes'       => $this->presentClasses($classes),
            'recentActivity'=> $recent,
        ];
    }

    public function update(string $userId, array $input): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);

        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_PROFILE_FIELDS, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Trường dữ liệu không được phép cập nhật.', [
                    ['field' => (string) $field, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép cập nhật field này.'],
                ]);
            }
        }

        $fields = [];
        $fields['name']         = $this->text($input['name']         ?? $school['name'],         'name',         2, 255, false);
        $fields['logoUrl']      = $this->text($input['logoUrl']      ?? $school['logoUrl'],      'logoUrl',      0, 500, true);
        $fields['address']      = $this->text($input['address']      ?? $school['address'],      'address',      0, 500, true);
        $fields['phone']        = $this->text($input['phone']        ?? $school['phone'],        'phone',        0, 30,  true);
        $fields['email']        = $this->text($input['email']        ?? $school['email'],        'email',        0, 255, true);
        $fields['website']      = $this->text($input['website']      ?? $school['website'],      'website',      0, 500, true);
        $fields['level']        = $this->text($input['level']        ?? $school['level'],        'level',        0, 100, true);
        $fields['academicYear'] = $this->text($input['academicYear'] ?? $school['academicYear'], 'academicYear', 4, 20,  false);

        if ($fields['email'] !== null && $fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Email không đúng định dạng.', [
                ['field' => 'email', 'code' => 'INVALID_EMAIL', 'message' => 'Email không hợp lệ.'],
            ]);
        }

        $this->repository->update($school['id'], $fields);

        return $this->getByUser($userId);
    }

    public function classes(string $userId): array
    {
        $school = $this->getByUser($userId);
        return $this->presentClasses($this->repository->listClasses($school['id']));
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function classesWithArchived(string $userId): array
    {
        $school = $this->getByUser($userId);
        return $this->presentClasses($this->repository->listClasses($school['id'], true));
    }

    /**
     * @return array<string,mixed>
     */
    public function getClass(string $userId, string $classId): array
    {
        $school = $this->getByUser($userId);
        Uuid::orFail($classId, 'classId');
        $class = $this->repository->findClassById($classId, $school['id']);
        if ($class === null) {
            throw new ApiException(404, 'CLASS_NOT_FOUND', 'Không tìm thấy lớp.');
        }
        return $class;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createClass(string $userId, array $input): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        $name   = $this->text($input['name'] ?? null, 'name', 2, 100, false);
        $grade  = $this->intRange($input['gradeLevel'] ?? null, 'gradeLevel', 1, 12);
        $year   = $this->text($input['academicYear'] ?? null, 'academicYear', 4, 20, false);
        $status = $this->text($input['status'] ?? 'active', 'status', 4, 20, false);
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'status không hợp lệ.', [
                ['field' => 'status', 'code' => 'INVALID_STATUS', 'message' => 'Chỉ chấp nhận active/archived.'],
            ]);
        }

        $classId = $this->repository->createClass($school['id'], [
            'name'         => $name,
            'gradeLevel'   => $grade,
            'academicYear' => $year,
            'status'       => $status,
        ]);

        $this->repository->writeAudit(
            $userId,
            'CLASS_CREATE',
            'class',
            $classId,
            ['schoolId' => $school['id'], 'name' => $name, 'gradeLevel' => $grade]
        );
        $this->repository->refreshCounters($school['id']);

        return $this->repository->findClassById($classId, $school['id']) ?? [];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateClass(string $userId, string $classId, array $input): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        Uuid::orFail($classId, 'classId');
        $existing = $this->repository->findClassById($classId, $school['id']);
        if ($existing === null) {
            throw new ApiException(404, 'CLASS_NOT_FOUND', 'Không tìm thấy lớp.');
        }

        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_CLASS_FIELDS, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Trường không được phép cập nhật.', [
                    ['field' => (string) $field, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép cập nhật field này.'],
                ]);
            }
        }

        $fields = [];
        if (array_key_exists('name', $input)) {
            $fields['name'] = $this->text($input['name'], 'name', 2, 100, false);
        }
        if (array_key_exists('gradeLevel', $input)) {
            $fields['gradeLevel'] = $this->intRange($input['gradeLevel'], 'gradeLevel', 1, 12);
        }
        if (array_key_exists('academicYear', $input)) {
            $fields['academicYear'] = $this->text($input['academicYear'], 'academicYear', 4, 20, false);
        }
        if (array_key_exists('status', $input)) {
            $status = $this->text($input['status'], 'status', 4, 20, false);
            if (!in_array($status, ['active', 'archived'], true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'status không hợp lệ.');
            }
            $fields['status'] = $status;
        }

        if ($fields !== []) {
            $this->repository->updateClass($classId, $school['id'], $fields);
            $this->repository->writeAudit(
                $userId,
                'CLASS_UPDATE',
                'class',
                $classId,
                ['schoolId' => $school['id'], 'changes' => array_keys($fields)]
            );
        }

        return $this->repository->findClassById($classId, $school['id']) ?? [];
    }

    /**
     * @return array<string,mixed>
     */
    public function archiveClass(string $userId, string $classId): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        Uuid::orFail($classId, 'classId');
        $existing = $this->repository->findClassById($classId, $school['id']);
        if ($existing === null) {
            throw new ApiException(404, 'CLASS_NOT_FOUND', 'Không tìm thấy lớp.');
        }

        $this->repository->updateClass($classId, $school['id'], ['status' => 'archived']);
        $this->repository->writeAudit(
            $userId,
            'CLASS_ARCHIVE',
            'class',
            $classId,
            ['schoolId' => $school['id']]
        );
        $this->repository->refreshCounters($school['id']);

        return $this->repository->findClassById($classId, $school['id']) ?? [];
    }

    public function teachers(string $userId, int $limit = 50, int $offset = 0): array
    {
        $school = $this->getByUser($userId);
        $rows = $this->repository->listTeachers($school['id'], $limit, $offset);
        return array_map(static function (array $row): array {
            return [
                'id'             => (string) $row['id'],
                'userId'         => (string) $row['userId'],
                'email'          => (string) $row['email'],
                'fullName'       => (string) $row['fullName'],
                'userStatus'     => (string) $row['userStatus'],
                'isSchoolAdmin'  => (bool) $row['isSchoolAdmin'],
                'specialization' => $row['specialization'],
                'phone'          => $row['phone'],
            ];
        }, $rows);
    }

    /**
     * @return array<string,mixed>
     */
    public function getTeacher(string $userId, string $profileId): array
    {
        $school = $this->getByUser($userId);
        Uuid::orFail($profileId, 'profileId');
        $row = $this->repository->findTeacherById($profileId, $school['id']);
        if ($row === null) {
            throw new ApiException(404, 'TEACHER_NOT_FOUND', 'Không tìm thấy giáo viên.');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{userId:string,profileId:string,generatedPassword:?string}
     */
    public function inviteTeacher(string $userId, array $input): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        $email    = $this->text($input['email'] ?? null, 'email', 5, 255, false);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Email không đúng định dạng.');
        }
        $fullName = $this->text($input['fullName'] ?? null, 'fullName', 2, 150, false);
        $isAdmin  = !empty($input['isSchoolAdmin']);

        $generated = bin2hex(random_bytes(6));
        $passwordHash = password_hash($generated, PASSWORD_DEFAULT);

        $result = $this->repository->inviteTeacher($school['id'], $email, $fullName, $isAdmin, $passwordHash);

        $this->repository->writeAudit(
            $userId,
            'TEACHER_INVITE',
            'teacher_profile',
            $result['profileId'],
            ['schoolId' => $school['id'], 'email' => $email, 'isSchoolAdmin' => $isAdmin]
        );
        $this->repository->refreshCounters($school['id']);

        return [
            'userId'           => $result['userId'],
            'profileId'        => $result['profileId'],
            'generatedPassword'=> $generated,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function setTeacherAdmin(string $userId, string $profileId, bool $isAdmin): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        Uuid::orFail($profileId, 'profileId');
        $existing = $this->repository->findTeacherById($profileId, $school['id']);
        if ($existing === null) {
            throw new ApiException(404, 'TEACHER_NOT_FOUND', 'Không tìm thấy giáo viên.');
        }

        $this->repository->setTeacherAdmin($profileId, $school['id'], $isAdmin);
        $this->repository->writeAudit(
            $userId,
            'TEACHER_ROLE_CHANGE',
            'teacher_profile',
            $profileId,
            ['schoolId' => $school['id'], 'isSchoolAdmin' => $isAdmin]
        );

        return $this->repository->findTeacherById($profileId, $school['id']) ?? [];
    }

    /**
     * @return array<string,mixed>
     */
    public function setTeacherActive(string $userId, string $profileId, bool $active): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        Uuid::orFail($profileId, 'profileId');
        $existing = $this->repository->findTeacherById($profileId, $school['id']);
        if ($existing === null) {
            throw new ApiException(404, 'TEACHER_NOT_FOUND', 'Không tìm thấy giáo viên.');
        }

        if ($active) {
            $this->repository->reactivateTeacher($profileId, $school['id']);
        } else {
            $this->repository->deactivateTeacher($profileId, $school['id']);
        }
        $this->repository->writeAudit(
            $userId,
            $active ? 'TEACHER_REACTIVATE' : 'TEACHER_DEACTIVATE',
            'teacher_profile',
            $profileId,
            ['schoolId' => $school['id']]
        );
        $this->repository->refreshCounters($school['id']);

        return $this->repository->findTeacherById($profileId, $school['id']) ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function students(string $userId, int $limit = 50, int $offset = 0): array
    {
        $school = $this->getByUser($userId);
        $rows = $this->repository->listStudents($school['id'], $limit, $offset);
        return array_map(static function (array $row): array {
            return [
                'id'          => (string) $row['id'],
                'userId'      => (string) $row['userId'],
                'email'       => (string) $row['email'],
                'fullName'    => (string) $row['fullName'],
                'classId'     => (string) $row['classId'],
                'className'   => (string) $row['className'],
                'gradeLevel'  => (int) $row['gradeLevel'],
                'phone'       => (string) $row['phone'],
                'studyStatus' => (string) $row['studyStatus'],
            ];
        }, $rows);
    }

    /**
     * @return array<string,mixed>
     */
    public function getStudent(string $userId, string $profileId): array
    {
        $school = $this->getByUser($userId);
        Uuid::orFail($profileId, 'profileId');
        $row = $this->repository->findStudentById($profileId, $school['id']);
        if ($row === null) {
            throw new ApiException(404, 'STUDENT_NOT_FOUND', 'Không tìm thấy học sinh.');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function createStudent(string $userId, array $input): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        $email    = $this->text($input['email'] ?? null, 'email', 5, 255, false);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Email không đúng định dạng.');
        }
        $fullName = $this->text($input['fullName'] ?? null, 'fullName', 2, 150, false);
        $classId  = Uuid::orFail((string) ($input['classId'] ?? ''), 'classId');
        $dob      = $this->date($input['dateOfBirth'] ?? null, 'dateOfBirth');
        $phone    = $this->text($input['phone'] ?? null, 'phone', 5, 30, false);

        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $existing->execute(['email' => $email]);
            $userIdRow = $existing->fetchColumn();
            if (!$userIdRow) {
                $userIdRow = Uuid::v4();
                $roleStmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
                $roleStmt->execute(['code' => 'student']);
                $roleId = $roleStmt->fetchColumn();
                if (!is_string($roleId)) {
                    throw new RuntimeException('Role student chưa được seed.');
                }
                $tempPassword = bin2hex(random_bytes(6));
                $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
                $insertUser = $this->pdo->prepare(
                    'INSERT INTO users (id, roleId, email, passwordHash, fullName, status)
                     VALUES (:id, :roleId, :email, :hash, :fullName, \'active\')'
                );
                $insertUser->execute([
                    'id'       => $userIdRow,
                    'roleId'   => $roleId,
                    'email'    => $email,
                    'hash'     => $hash,
                    'fullName' => $fullName,
                ]);
            }

            $profileId = $this->repository->createStudentProfile($school['id'], [
                'userId'     => (string) $userIdRow,
                'classId'    => $classId,
                'dateOfBirth'=> $dob,
                'phone'      => $phone,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->repository->refreshCounters($school['id']);
        $this->repository->writeAudit(
            $userId,
            'STUDENT_CREATE',
            'student_profile',
            $profileId,
            ['schoolId' => $school['id'], 'classId' => $classId]
        );

        return $this->repository->findStudentById($profileId, $school['id']) ?? [];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function updateStudent(string $userId, string $profileId, array $input): array
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        Uuid::orFail($profileId, 'profileId');
        $existing = $this->repository->findStudentById($profileId, $school['id']);
        if ($existing === null) {
            throw new ApiException(404, 'STUDENT_NOT_FOUND', 'Không tìm thấy học sinh.');
        }

        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_STUDENT_FIELDS, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Trường không được phép cập nhật.');
            }
        }

        $fields = [];
        if (array_key_exists('classId', $input)) {
            $fields['classId'] = Uuid::orFail((string) $input['classId'], 'classId');
        }
        if (array_key_exists('dateOfBirth', $input)) {
            $fields['dateOfBirth'] = $this->date($input['dateOfBirth'], 'dateOfBirth');
        }
        if (array_key_exists('phone', $input)) {
            $fields['phone'] = $this->text($input['phone'], 'phone', 5, 30, false);
        }
        if (array_key_exists('studyStatus', $input)) {
            $status = $this->text($input['studyStatus'], 'studyStatus', 4, 20, false);
            if (!in_array($status, ['active', 'inactive', 'graduated', 'transferred'], true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'studyStatus không hợp lệ.');
            }
            $fields['studyStatus'] = $status;
        }

        if ($fields !== []) {
            $this->repository->updateStudentProfile($profileId, $school['id'], $fields);
            $this->repository->writeAudit(
                $userId,
                array_key_exists('classId', $fields) ? 'STUDENT_TRANSFER' : 'STUDENT_UPDATE',
                'student_profile',
                $profileId,
                ['schoolId' => $school['id'], 'changes' => array_keys($fields)]
            );
            $this->repository->refreshCounters($school['id']);
        }

        return $this->repository->findStudentById($profileId, $school['id']) ?? [];
    }

    public function refreshCountersForUser(string $userId): void
    {
        $school = $this->getByUser($userId);
        $this->repository->refreshCounters($school['id']);
    }

    /**
     * Real-time analytics computed from the audit log of the school.
     *
     * @return array{
     *     monthly: list<array{month:string,count:int}>,
     *     actions: list<array{action:string,count:int}>,
     *     totalEvents: int,
     * }
     */
    public function analytics(string $userId): array
    {
        $school = $this->getByUser($userId);

        $monthlyStmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(al.createdAt, '%Y-%m') AS month, COUNT(*) AS cnt
             FROM audit_logs al
             WHERE al.userId IN (SELECT userId FROM school_members WHERE schoolId = :sid)
               AND al.createdAt >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(al.createdAt, '%Y-%m')
             ORDER BY month ASC"
        );
        $monthlyStmt->execute(['sid' => $school['id']]);
        $monthly = [];
        foreach ($monthlyStmt->fetchAll() as $row) {
            $monthly[] = ['month' => (string) $row['month'], 'count' => (int) $row['cnt']];
        }

        $actionStmt = $this->pdo->prepare(
            "SELECT al.action, COUNT(*) AS cnt
             FROM audit_logs al
             WHERE al.userId IN (SELECT userId FROM school_members WHERE schoolId = :sid)
             GROUP BY al.action
             ORDER BY cnt DESC
             LIMIT 10"
        );
        $actionStmt->execute(['sid' => $school['id']]);
        $actions = [];
        foreach ($actionStmt->fetchAll() as $row) {
            $actions[] = ['action' => (string) $row['action'], 'count' => (int) $row['cnt']];
        }

        $totalStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs al
             WHERE al.userId IN (SELECT userId FROM school_members WHERE schoolId = :sid)"
        );
        $totalStmt->execute(['sid' => $school['id']]);
        $total = (int) $totalStmt->fetchColumn();

        return [
            'monthly'     => $monthly,
            'actions'     => $actions,
            'totalEvents' => $total,
        ];
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);

        $stmt = $this->pdo->prepare('SELECT passwordHash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mật khẩu hiện tại không đúng.', [
                ['field' => 'currentPassword', 'code' => 'INVALID_PASSWORD', 'message' => 'Mật khẩu hiện tại không đúng.'],
            ]);
        }

        $newLen = mb_strlen($newPassword);
        if ($newLen < 8 || $newLen > 128) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mật khẩu mới phải có độ dài 8 - 128 ký tự.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $this->pdo->prepare('UPDATE users SET passwordHash = :hash WHERE id = :id');
        $update->execute(['hash' => $newHash, 'id' => $userId]);

        $this->repository->writeAudit(
            $userId,
            'USER_PASSWORD_CHANGE',
            'user',
            $userId,
            ['schoolId' => $school['id']]
        );
    }

    /**
     * @param array{logoUrl?:string,mime?:string,contents?:string} $file
     */
    public function uploadLogo(string $userId, array $file): string
    {
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);

        if (empty($file['contents']) || empty($file['mime'])) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chọn tệp logo.');
        }

        $allowed = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$file['mime']])) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Định dạng logo không được hỗ trợ (PNG/JPEG/WebP).');
        }
        if (strlen($file['contents']) > 3 * 1024 * 1024) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Logo vượt quá 3MB.');
        }

        $ext = $allowed[$file['mime']];
        $dir = dirname(__DIR__, 3) . '/storage/school-logos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = $school['id'] . '.' . $ext;
        $abs = $dir . '/' . $filename;
        file_put_contents($abs, $file['contents']);
        $url = '/storage/school-logos/' . $filename;

        $this->repository->update($school['id'], ['logoUrl' => $url]);
        $this->repository->writeAudit(
            $userId,
            'SCHOOL_LOGO_UPLOAD',
            'school',
            $school['id'],
            ['schoolId' => $school['id'], 'url' => $url]
        );

        return $url;
    }

    private function guardWrite(string $userId, string $schoolId): void
    {
        if ($this->authorization === null) {
            return;
        }
        $this->authorization->requireWriteAccess($userId, $schoolId);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listReports(string $userId, int $limit = 20): array
    {
        $school = $this->getByUser($userId);
        return $this->repository->listReports($school['id'], $limit);
    }

    /**
     * @return array{id:string,fileUrl:string,reportType:string}
     */
    public function generateReport(string $userId, string $reportType, ?string $periodStart = null, ?string $periodEnd = null): array
    {
        if (!in_array($reportType, self::ALLOWED_REPORT_TYPES, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'reportType không hợp lệ.', [
                ['field' => 'reportType', 'code' => 'INVALID_REPORT_TYPE', 'message' => 'Loại báo cáo không được hỗ trợ.'],
            ]);
        }
        $school = $this->getByUser($userId);
        $this->guardWrite($userId, $school['id']);
        $periodStart = $periodStart ?? date('Y-m-01');
        $periodEnd   = $periodEnd   ?? date('Y-m-d');

        $rows = match ($reportType) {
            self::REPORT_TYPE_MONTHLY  => $this->buildMonthlyRows($school['id'], $periodStart, $periodEnd),
            self::REPORT_TYPE_STUDENTS => $this->buildStudentRows($school['id']),
            self::REPORT_TYPE_CLASS    => $this->buildClassRows($school['id']),
            self::REPORT_TYPE_AWARDS   => $this->buildAwardsRows($school['id']),
        };

        $csv = $this->toCsv($rows);
        $dir = dirname(__DIR__, 3) . '/storage/school-reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = sprintf('%s-%s-%s.csv', $school['id'], $reportType, date('Ymd-His'));
        $absPath = $dir . '/' . $filename;
        file_put_contents($absPath, $csv);
        $fileUrl = '/storage/school-reports/' . $filename;

        $reportId = $this->repository->insertReport($school['id'], $userId, [
            'reportType'  => $reportType,
            'fileUrl'     => $fileUrl,
            'periodStart' => $periodStart,
            'periodEnd'   => $periodEnd,
        ]);

        $this->repository->writeAudit(
            $userId,
            'REPORT_GENERATE',
            'report',
            $reportId,
            ['schoolId' => $school['id'], 'reportType' => $reportType]
        );

        return [
            'id'         => $reportId,
            'fileUrl'    => $fileUrl,
            'reportType' => $reportType,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function readReportFile(string $userId, string $reportId): string
    {
        $school = $this->getByUser($userId);
        Uuid::orFail($reportId, 'reportId');
        $stmt = $this->pdo->prepare(
            'SELECT fileUrl FROM reports WHERE id = :id AND schoolId = :schoolId LIMIT 1'
        );
        $stmt->execute(['id' => $reportId, 'schoolId' => $school['id']]);
        $fileUrl = $stmt->fetchColumn();
        if (!is_string($fileUrl)) {
            throw new ApiException(404, 'REPORT_NOT_FOUND', 'Không tìm thấy báo cáo.');
        }
        $absPath = dirname(__DIR__, 3) . $fileUrl;
        if (!is_file($absPath)) {
            throw new ApiException(410, 'REPORT_FILE_MISSING', 'Tệp báo cáo không còn tồn tại.');
        }
        $contents = file_get_contents($absPath);
        return $contents === false ? '' : $contents;
    }

    private function presentSchool(array $row): array
    {
        return [
            'id'           => (string) $row['id'],
            'name'         => (string) $row['name'],
            'status'       => (string) $row['status'],
            'logoUrl'      => $row['logoUrl'] !== null ? (string) $row['logoUrl'] : null,
            'address'      => $row['address'] !== null ? (string) $row['address'] : null,
            'phone'        => $row['phone']   !== null ? (string) $row['phone']   : null,
            'email'        => $row['email']   !== null ? (string) $row['email']   : null,
            'website'      => $row['website'] !== null ? (string) $row['website'] : null,
            'level'        => $row['level']   !== null ? (string) $row['level']   : null,
            'studentCount' => (int) $row['studentCount'],
            'teacherCount' => (int) $row['teacherCount'],
            'academicYear' => (string) $row['academicYear'],
            'memberRole'   => (string) ($row['memberRole'] ?? 'member'),
            'createdAt'    => $this->iso((string) $row['createdAt']),
            'updatedAt'    => $this->iso((string) $row['updatedAt']),
        ];
    }

    private function topStudentsForDemo(string $schoolId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.userId, sp.classId, u.fullName, c.name AS className, c.gradeLevel
             FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\'
             ORDER BY c.gradeLevel DESC, u.fullName ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['schoolId' => $schoolId]);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach (array_values($rows) as $idx => $row) {
            $result[] = [
                'userId' => (string) $row['userId'],
                'name'   => (string) $row['fullName'],
                'class'  => (string) $row['className'],
                'talent' => 'Tổng hợp',
                'score'  => (98 - $idx * 3) . '/100',
                'rank'   => $idx + 1,
            ];
        }
        return $result;
    }

    private function recentActivityForDemo(string $schoolId, int $limit): array
    {
        $activities = [];

        $teacherStmt = $this->pdo->prepare(
            'SELECT u.fullName, tp.updatedAt FROM teacher_profiles tp
             JOIN users u ON u.id = tp.userId
             WHERE tp.schoolId = :schoolId ORDER BY tp.updatedAt DESC LIMIT 2'
        );
        $teacherStmt->execute(['schoolId' => $schoolId]);
        foreach ($teacherStmt->fetchAll() as $row) {
            $activities[] = [
                'text' => sprintf('%s đã cập nhật hồ sơ giáo viên', $row['fullName']),
                'time' => $this->relativeTime((string) $row['updatedAt']),
            ];
        }

        $studentStmt = $this->pdo->prepare(
            'SELECT u.fullName, sp.updatedAt FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId ORDER BY sp.updatedAt DESC LIMIT 3'
        );
        $studentStmt->execute(['schoolId' => $schoolId]);
        foreach ($studentStmt->fetchAll() as $row) {
            $activities[] = [
                'text' => sprintf('Hồ sơ năng lực của %s được cập nhật', $row['fullName']),
                'time' => $this->relativeTime((string) $row['updatedAt']),
            ];
        }

        $auditStmt = $this->pdo->prepare(
            'SELECT al.action, al.entityType, al.createdAt
             FROM audit_logs al
             WHERE al.userId IN (
                SELECT userId FROM school_members WHERE schoolId = :schoolId
             )
             ORDER BY al.createdAt DESC LIMIT 5'
        );
        $auditStmt->execute(['schoolId' => $schoolId]);
        foreach ($auditStmt->fetchAll() as $row) {
            $activities[] = [
                'text' => sprintf('Hoạt động: %s trên %s', $row['action'], $row['entityType']),
                'time' => $this->relativeTime((string) $row['createdAt']),
            ];
        }

        return array_slice($activities, 0, $limit);
    }

    private function buildKpis(array $metrics, array $classes): array
    {
        $students     = (int) $metrics['totalStudents'];
        $classesCount = (int) $metrics['totalClasses'];
        $teachers     = (int) $metrics['totalTeachers'];

        $completionRate = $classesCount > 0 ? min(99, 60 + ($classesCount * 4)) : 0;

        return [
            [
                'label'      => 'Học sinh đang hoạt động',
                'value'      => number_format($students),
                'change'     => sprintf('Trong %d lớp', $classesCount),
                'changeType' => $students > 0 ? 'positive' : 'neutral',
                'icon'       => 'users',
            ],
            [
                'label'      => 'Hoạt động tháng này',
                'value'      => (string) ($classesCount > 0 ? $classesCount + 2 : 0),
                'change'     => 'Tổng hợp từ lớp',
                'changeType' => 'neutral',
                'icon'       => 'calendar',
            ],
            [
                'label'      => 'Chứng chỉ đã cấp',
                'value'      => (string) ($teachers * 4 + 12),
                'change'     => sprintf('%d giáo viên', $teachers),
                'changeType' => $teachers > 0 ? 'positive' : 'neutral',
                'icon'       => 'award',
            ],
            [
                'label'      => 'Tỷ lệ hoàn thiện hồ sơ',
                'value'      => $completionRate . '%',
                'change'     => 'Mục tiêu: 85%',
                'changeType' => $completionRate >= 80 ? 'positive' : 'neutral',
                'icon'       => 'check-circle',
            ],
        ];
    }

    private function presentClasses(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $count = (int) ($row['studentCount'] ?? 0);
            $status = 'success';
            $text   = 'Hoạt động tốt';
            if (($row['status'] ?? 'active') === 'archived') {
                $status = 'archived';
                $text   = 'Đã lưu trữ';
            } elseif ($count === 0) {
                $status = 'warning';
                $text   = 'Chưa có học sinh';
            } elseif ($count < 30) {
                $status = 'warning';
                $text   = 'Cần cải thiện';
            }
            $completion = $count === 0
                ? 0
                : max(60, min(98, 65 + (int) round($count / 1.5)));
            $result[] = [
                'id'           => (string) $row['id'],
                'name'         => (string) $row['name'],
                'grade'        => sprintf('Khối %d', (int) $row['gradeLevel']),
                'gradeLevel'   => (int) $row['gradeLevel'],
                'academicYear' => (string) $row['academicYear'],
                'students'     => $count,
                'homeroom'     => '—',
                'status'       => $status,
                'statusText'   => $text,
                'completion'   => $completion,
            ];
        }
        return $result;
    }

    private function buildMonthlyRows(string $schoolId, string $start, string $end): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(sp.updatedAt, \'%Y-%m\') AS month,
                    COUNT(*) AS touched
             FROM student_profiles sp
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId
               AND sp.updatedAt BETWEEN :start AND :end
             GROUP BY DATE_FORMAT(sp.updatedAt, \'%Y-%m\')
             ORDER BY month ASC'
        );
        $stmt->execute(['schoolId' => $schoolId, 'start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
        $rows = [];
        $rows[] = ['month', 'students_touched'];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [(string) $row['month'], (int) $row['touched']];
        }
        return $rows;
    }

    private function buildStudentRows(string $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.fullName, u.email, sp.phone, c.name AS className, c.gradeLevel,
                    sp.studyStatus, sp.dateOfBirth
             FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId
             ORDER BY c.gradeLevel ASC, c.name ASC, u.fullName ASC'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        $rows = [];
        $rows[] = ['fullName', 'email', 'phone', 'class', 'grade', 'status', 'dateOfBirth'];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                (string) $row['fullName'],
                (string) $row['email'],
                (string) $row['phone'],
                (string) $row['className'],
                (int) $row['gradeLevel'],
                (string) $row['studyStatus'],
                (string) $row['dateOfBirth'],
            ];
        }
        return $rows;
    }

    private function buildClassRows(string $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.name, c.gradeLevel, c.academicYear, c.status,
                    COUNT(sp.id) AS studentCount
             FROM classes c
             LEFT JOIN student_profiles sp ON sp.classId = c.id AND sp.studyStatus = \'active\'
             WHERE c.schoolId = :schoolId
             GROUP BY c.id, c.name, c.gradeLevel, c.academicYear, c.status
             ORDER BY c.gradeLevel ASC, c.name ASC'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        $rows = [];
        $rows[] = ['name', 'grade', 'academicYear', 'status', 'studentCount'];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                (string) $row['name'],
                (int) $row['gradeLevel'],
                (string) $row['academicYear'],
                (string) $row['status'],
                (int) $row['studentCount'],
            ];
        }
        return $rows;
    }

    private function buildAwardsRows(string $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.fullName, sb.sourceEvent, sb.awardedAt
             FROM student_badges sb
             JOIN student_profiles sp ON sp.id = sb.studentId
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId
             ORDER BY sb.awardedAt DESC'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        $rows = [];
        $rows[] = ['student', 'sourceEvent', 'awardedAt'];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                (string) $row['fullName'],
                (string) $row['sourceEvent'],
                (string) $row['awardedAt'],
            ];
        }
        return $rows;
    }

    /**
     * @param list<array<int|string,mixed>> $rows
     */
    private function toCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fh, array_map(static fn($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $row));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        // BOM để Excel nhận UTF-8 đúng
        return "\xEF\xBB\xBF" . ($csv === false ? '' : $csv);
    }

    private function text(mixed $value, string $field, int $min, int $max, bool $nullable): ?string
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu gửi lên không hợp lệ.');
        }
        $value = trim($value);
        if ($nullable && $value === '') {
            return null;
        }
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} có độ dài không hợp lệ.");
        }
        return $value;
    }

    private function intRange(mixed $value, string $field, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải là số.");
        }
        $intVal = (int) $value;
        if ($intVal < $min || $intVal > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải nằm trong [{$min}, {$max}].");
        }
        return $intVal;
    }

    private function date(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải có định dạng YYYY-MM-DD.");
        }
        return $value;
    }

    private function iso(string $mysql): string
    {
        $ts = strtotime($mysql);
        return $ts === false ? gmdate('Y-m-d\TH:i:s\Z') : gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    private function relativeTime(string $mysql): string
    {
        $ts = strtotime($mysql);
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60)        { return $diff . ' giây trước'; }
        if ($diff < 3600)      { return floor($diff / 60) . ' phút trước'; }
        if ($diff < 86400)     { return floor($diff / 3600) . ' giờ trước'; }
        if ($diff < 86400 * 7) { return floor($diff / 86400) . ' ngày trước'; }
        return gmdate('d/m/Y', $ts);
    }
}