<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolAuditRepository;

final class SchoolAuditService
{
    private const ACCESS_TYPES = ['talent_detail', 'application_cv', 'shared_profile'];

    public function __construct(private readonly SchoolAuditRepository $repository) {}

    /**
     * @param array{search?:mixed,accessType?:mixed,from?:mixed,to?:mixed,limit?:mixed,offset?:mixed} $input
     * @return array{items:list<array<string,mixed>>,summary:array<string,int>,page:array<string,int>}
     */
    public function profileAccessOverview(string $userId, array $input = []): array
    {
        $schoolId = $this->repository->schoolIdForUser($userId);
        $limit = filter_var($input['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
        $offset = filter_var($input['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($limit === false || $offset === false) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Phân trang nhật ký không hợp lệ.');
        }

        $accessType = trim((string) ($input['accessType'] ?? ''));
        if ($accessType !== '' && !in_array($accessType, self::ACCESS_TYPES, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Loại truy cập không hợp lệ.');
        }

        $from = $this->dateOrEmpty($input['from'] ?? '', 'from');
        $to = $this->dateOrEmpty($input['to'] ?? '', 'to');
        if ($from !== '' && $to !== '' && $from > $to) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.');
        }

        $logs = $this->repository->profileAccessLogs($schoolId, [
            'search' => trim((string) ($input['search'] ?? '')),
            'accessType' => $accessType,
            'from' => $from,
            'to' => $to,
        ], $limit, $offset);

        $recentSince = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-30 days')
            ->format('Y-m-d H:i:s.u');

        return [
            'items' => $logs['items'],
            'summary' => $this->repository->profileAccessSummary($schoolId, $recentSince),
            'page' => [
                'total' => $logs['total'],
                'limit' => $logs['limit'],
                'offset' => $logs['offset'],
            ],
        ];
    }

    private function dateOrEmpty(mixed $value, string $field): string
    {
        $value = trim(is_string($value) ? $value : '');
        if ($value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không phải ngày hợp lệ.");
        }
        return $value;
    }
}
