<?php
declare(strict_types=1);

namespace TalentHub\Auth\Service;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;

final class TeacherRegistrationService
{
    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,string> $input @return array<string,string> */
    public function register(array $input): array
    {
        $fullName=trim($input['fullName']??'');$email=strtolower(trim($input['email']??''));
        $phone=trim($input['phone']??'');$specialization=trim($input['specialization']??'');
        $schoolId=trim($input['schoolId']??'');$password=$input['password']??'';
        if(mb_strlen($fullName)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($phone)<6||mb_strlen($specialization)<2||!Uuid::isValid($schoolId)||strlen($password)<12){throw new RuntimeException('Vui lòng điền đầy đủ thông tin hợp lệ; mật khẩu tối thiểu 12 ký tự.');}
        $schoolStmt = $this->pdo->prepare("SELECT id, name FROM schools WHERE id=? AND status='active'");
        $schoolStmt->execute([$schoolId]);
        $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
        if (!$school || empty($school['id'])) {
            throw new RuntimeException('Nhà trường đã chọn không tồn tại hoặc chưa hoạt động.');
        }
        $schoolName = (string) $school['name'];
        $duplicate = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
        $duplicate->execute([$email]);
        if ((int) $duplicate->fetchColumn() > 0) {
            throw new RuntimeException('Email đã được sử dụng.');
        }

        $userId = Uuid::v4();
        $profileId = Uuid::v4();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $legacy = $this->columnExists('users', 'roles');
        $this->pdo->beginTransaction();
        try {
            if ($legacy) {
                $this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,'pending')")->execute([$userId, $email, $hash, $fullName, 'teacher']);
            } else {
                $role = $this->pdo->prepare("SELECT id FROM roles WHERE code='teacher'");
                $role->execute();
                $roleId = $role->fetchColumn();
                if (!is_string($roleId)) {
                    throw new RuntimeException('Vai trò giáo viên chưa được cấu hình.');
                }
                $this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'pending')")->execute([$userId, $roleId, $email, $hash, $fullName]);
            }

            $columns = $this->columns('teacher_profiles');
            $profile = [
                'id' => $profileId,
                'userId' => $userId,
                'schoolId' => $schoolId,
                'isSchoolAdmin' => 0,
                'phone' => $phone,
                'specialization' => $specialization,
            ];
            $profile = array_intersect_key($profile, array_flip($columns));
            $names = array_keys($profile);
            $this->pdo->prepare('INSERT INTO teacher_profiles(' . implode(',', $names) . ') VALUES(' . implode(',', array_fill(0, count($names), '?')) . ')')->execute(array_values($profile));

            // Định tuyến thông báo đến các Quản trị viên của đúng Nhà trường được chọn
            if ($this->tableExists('school_members') && $this->tableExists('notifications')) {
                $membersStmt = $this->pdo->prepare("SELECT sm.userId FROM school_members sm WHERE sm.schoolId = ? AND sm.memberRole = 'admin'");
                $membersStmt->execute([$schoolId]);
                $notifStmt = $this->pdo->prepare("INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($membersStmt->fetchAll(PDO::FETCH_COLUMN) as $schoolAdminUserId) {
                    if (is_string($schoolAdminUserId) && $schoolAdminUserId !== '') {
                        $notifStmt->execute([
                            Uuid::v4(),
                            $schoolAdminUserId,
                            'teacher_registration:' . $userId,
                            'teacher_registration_pending',
                            'Hồ sơ giáo viên mới chờ duyệt',
                            'Giáo viên ' . $fullName . ' vừa đăng ký công tác tại trường và đang chờ duyệt hồ sơ.',
                            '/app/school/teachers.php',
                        ]);
                    }
                }
            }

            $metadata = json_encode([
                'schoolId' => $schoolId,
                'schoolName' => $schoolName,
                'teacherUserId' => $userId,
                'fullName' => $fullName,
                'email' => $email,
                'specialization' => $specialization,
            ], JSON_UNESCAPED_UNICODE);

            if ($this->columnExists('audit_logs', 'metadata')) {
                $this->pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId,metadata) VALUES(?,?,?,'school',?,?)")->execute([Uuid::v4(), $userId, 'auth.teacher_registration_submitted', $schoolId, $metadata]);
            } else {
                $this->pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,?,'school',?)")->execute([Uuid::v4(), $userId, 'auth.teacher_registration_submitted', $schoolId]);
            }

            $this->pdo->commit();
            return [
                'id' => $userId,
                'userId' => $userId,
                'profileId' => $profileId,
                'email' => $email,
                'status' => 'pending',
                'schoolId' => $schoolId,
                'schoolName' => $schoolName,
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $statement->execute([$table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
        $statement->execute([$table]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}
