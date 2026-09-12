<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

final class ApplicationReadModel
{
    public static function application(array $record): array
    {
        $record['opportunity_type'] ??= 'internship';
        $record['partner_name'] ??= $record['enterprise_name'] ?? null;
        $record['status_label'] ??= self::statusLabel((string) ($record['status'] ?? 'unknown'));
        $record['submitted_at'] = $record['applied_at'] ?? null;
        $record['updated_at'] = $record['updated_at'] ?? null;
        $record['submitted_at_formatted'] = self::formatDate($record['submitted_at']);
        $record['updated_at_formatted'] = self::formatDate($record['updated_at']);
        $record['partner_initials'] = self::initials((string) ($record['partner_name'] ?? 'DN'));

        $history = is_array($record['history'] ?? null) ? $record['history'] : [];
        $lastIndex = array_key_last($history);
        $record['timeline'] = array_map(
            static function (array $entry, int $index) use ($lastIndex): array {
                $status = (string) ($entry['to_status'] ?? 'unknown');
                return [
                    'label' => self::statusLabel($status),
                    'date' => self::formatDate($entry['created_at'] ?? null),
                    'state' => in_array($status, ['declined', 'withdrawn'], true)
                        ? $status
                        : ($index === $lastIndex ? 'current' : 'complete'),
                ];
            },
            $history,
            array_keys($history)
        );

        $record['pipeline'] = self::buildPipeline(
            (string) ($record['status'] ?? 'submitted'),
            $record['submitted_at_formatted'],
            $record['updated_at_formatted']
        );

        $record['can_withdraw'] ??= in_array(
            $record['status'] ?? 'unknown',
            ['submitted', 'reviewing', 'interview'],
            true
        );

        return ReadModelDefaults::apply($record, [
            'id' => '',
            'application_id' => '',
            'student_id' => '',
            'opportunity_id' => '',
            'opportunity_type' => 'internship',
            'title' => 'Cơ hội TalentHub',
            'partner_name' => 'Đối tác TalentHub',
            'partner_initials' => 'TH',
            'submitted_at' => null,
            'submitted_at_formatted' => '',
            'updated_at' => null,
            'updated_at_formatted' => '',
            'status' => 'unknown',
            'status_label' => 'Chưa xác định',
            'can_withdraw' => false,
            'timeline' => [],
            'pipeline' => [],
            'snapshot' => null,
        ], 'application');
    }

    public static function applications(array $records): array
    {
        return array_map([self::class, 'application'], $records);
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Đã nộp',
            'invited' => 'Được mời thực tập',
            'reviewing' => 'Đang xem xét',
            'interview' => 'Mời phỏng vấn',
            'accepted', 'hired' => 'Đã nhận',
            'declined' => 'Chưa phù hợp',
            'withdrawn' => 'Đã rút',
            default => 'Chưa xác định',
        };
    }

    private static function formatDate(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($raw);
            return $dt->format('H:i · d/m/Y');
        } catch (\Throwable) {
            return substr((string) $raw, 0, 10);
        }
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($words)) {
            return 'DN';
        }
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    }

    /**
     * Build 4-stage recruitment pipeline
     *
     * @return array<int, array{id: string, label: string, desc: string, date: ?string, state: string}>
     */
    private static function buildPipeline(string $status, string $submittedDate, string $updatedDate): array
    {
        return [
            [
                'id' => 'submitted',
                'label' => 'Đã nộp hồ sơ',
                'desc' => 'Hồ sơ đã chuyển tới doanh nghiệp',
                'date' => $submittedDate !== '' ? $submittedDate : null,
                'state' => 'complete',
            ],
            [
                'id' => 'reviewing',
                'label' => 'Tiếp nhận & Xem xét',
                'desc' => in_array($status, ['reviewing', 'interview', 'accepted', 'hired', 'declined'], true)
                    ? 'Doanh nghiệp đã tiếp nhận hồ sơ'
                    : 'Chờ doanh nghiệp mở xét duyệt',
                'date' => ($status === 'reviewing' && $updatedDate !== '') ? $updatedDate : null,
                'state' => match ($status) {
                    'submitted' => 'current',
                    'reviewing' => 'current',
                    'interview', 'accepted', 'hired', 'declined' => 'complete',
                    'withdrawn' => 'withdrawn',
                    default => 'upcoming',
                },
            ],
            [
                'id' => 'interview',
                'label' => 'Phỏng vấn',
                'desc' => ($status === 'interview')
                    ? 'Bạn được mời phỏng vấn! Vui lòng kiểm tra email'
                    : 'Trao đổi chuyên môn & lịch thực tập',
                'date' => ($status === 'interview' && $updatedDate !== '') ? $updatedDate : null,
                'state' => match ($status) {
                    'interview' => 'current',
                    'accepted', 'hired' => 'complete',
                    'declined' => 'declined',
                    'withdrawn' => 'withdrawn',
                    default => 'upcoming',
                },
            ],
            [
                'id' => 'decision',
                'label' => 'Kết quả tiếp nhận',
                'desc' => match ($status) {
                    'accepted', 'hired' => 'Chúc mừng! Bạn đã trúng tuyển thực tập',
                    'declined' => 'Chưa phù hợp trong đợt tuyển này',
                    'withdrawn' => 'Bạn đã chủ động rút hồ sơ',
                    default => 'Nhà tuyển dụng thông báo kết quả cuối',
                },
                'date' => in_array($status, ['accepted', 'hired', 'declined', 'withdrawn'], true) && $updatedDate !== '' ? $updatedDate : null,
                'state' => match ($status) {
                    'accepted', 'hired' => 'complete',
                    'declined' => 'declined',
                    'withdrawn' => 'withdrawn',
                    default => 'upcoming',
                },
            ],
        ];
    }
}
