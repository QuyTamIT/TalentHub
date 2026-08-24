<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherStudentRepository;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Uuid;

final class TeacherStudentService
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'attended'];
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly TeacherStudentRepository $repository,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function page(string $userId, array $query): array
    {
        $this->permissions->require($userId, 'activity.read_managed');
        $this->permissions->require($userId, 'activity_registration.read_managed');
        $this->permissions->require($userId, 'assessment.read_managed');

        $teacher = $this->repository->findTeacherByUserId($userId);
        if ($teacher === null) {
            throw new ApiException(404, 'TEACHER_NOT_FOUND', 'Không tìm thấy hồ sơ giáo viên.');
        }

        $teacherId = (string) $teacher['id'];
        $filters = $this->filters($query);
        $page = $this->positiveInt($query['page'] ?? null, 1);
        $perPage = $this->perPage($query['perPage'] ?? null);
        $offset = ($page - 1) * $perPage;

        $total = $this->repository->countRegistrations($teacherId, $filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
            $offset = ($page - 1) * $perPage;
        }

        return [
            'teacher' => $this->presentTeacher($teacher),
            'filters' => $filters,
            'activities' => $this->presentActivities($this->repository->activitiesForFilter($teacherId)),
            'statuses' => self::STATUSES,
            'summary' => $this->repository->summary($teacherId),
            'rows' => $this->presentRows($this->repository->listRegistrations($teacherId, $filters, $perPage, $offset)),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ];
    }

    /** @param array<string,mixed> $query @return array{search:string,activityId:string,status:string} */
    private function filters(array $query): array
    {
        $search = is_string($query['search'] ?? null) ? trim($query['search']) : '';
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }

        $activityId = is_string($query['activityId'] ?? null) ? trim($query['activityId']) : '';
        if ($activityId !== '' && !Uuid::isValid($activityId)) {
            $activityId = '';
        }

        $status = is_string($query['status'] ?? null) ? trim($query['status']) : '';
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        return [
            'search' => $search,
            'activityId' => strtolower($activityId),
            'status' => $status,
        ];
    }

    private function positiveInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : $default;
        }

        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/', trim($value))) {
            return $default;
        }

        return (int) $value;
    }

    private function perPage(mixed $value): int
    {
        $perPage = $this->positiveInt($value, self::DEFAULT_PER_PAGE);

        return min(self::MAX_PER_PAGE, $perPage);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function presentTeacher(array $row): array
    {
        $name = trim((string) $row['fullName']);

        return [
            'id' => (string) $row['id'],
            'full_name' => $name !== '' ? $name : 'Giáo viên TalentHub',
            'role_label' => !empty($row['isSchoolAdmin']) ? 'Giáo viên / Quản trị trường' : 'Giáo viên / Hướng dẫn viên',
            'school_name' => (string) $row['schoolName'],
            'avatar_initials' => $this->initials($name),
            'notification_count' => 0,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array{id:string,title:string}> */
    private function presentActivities(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'title' => (string) $row['title'],
        ], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function presentRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'registrationId' => (string) $row['registrationId'],
            'studentId' => (string) $row['studentId'],
            'fullName' => (string) $row['fullName'],
            'email' => (string) $row['email'],
            'activityId' => (string) $row['activityId'],
            'activityTitle' => (string) $row['activityTitle'],
            'activityCategory' => (string) $row['activityCategory'],
            'activityStartAt' => $this->formatDateTime($row['activityStartAt'] ?? null),
            'registrationStatus' => (string) $row['registrationStatus'],
            'registeredAt' => $this->formatDateTime($row['registeredAt'] ?? null),
            'teacherActivityCount' => (int) $row['teacherActivityCount'],
            'assessmentStatus' => $row['assessmentStatus'] !== null ? (string) $row['assessmentStatus'] : 'none',
            'overallScore' => $row['overallScore'] !== null ? number_format((float) $row['overallScore'], 1) : null,
            'assessmentUpdatedAt' => $this->formatDateTime($row['assessmentUpdatedAt'] ?? null),
        ], $rows);
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'GV';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[count($parts) - 1] ?? '';

        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: 'GV';
    }

    private function formatDateTime(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '' : date('d/m/Y H:i', $timestamp);
    }
}
