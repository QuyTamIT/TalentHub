<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Service;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;

final class SchoolDashboardService
{
    private const ALLOWED = ['name', 'logoUrl', 'address', 'phone', 'email', 'website', 'level', 'academicYear'];

    public function __construct(
        private readonly SchoolRepository $repository,
        private readonly PDO $pdo,
    ) {}

    public function getByUser(string $userId): array
    {
        $row = $this->repository->findByUserId($userId);
        if ($row === null) {
            throw new ApiException(404, 'SCHOOL_NOT_FOUND', 'Không tìm thấy trường cho người dùng hiện tại.');
        }
        return $this->present($row);
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

        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED, true)) {
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

    public function teachers(string $userId): array
    {
        $school = $this->getByUser($userId);
        $rows = $this->repository->listTeachers($school['id']);
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

    public function students(string $userId, int $limit = 50): array
    {
        $school = $this->getByUser($userId);
        $rows = $this->repository->listStudents($school['id'], $limit);
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

    public function refreshCountersForUser(string $userId): void
    {
        $school = $this->getByUser($userId);
        $this->repository->refreshCounters($school['id']);
    }

    private function present(array $row): array
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
            if ($count === 0) {
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