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
            'submitted_at' => 'Chưa cập nhật',
            'updated_at' => 'Chưa cập nhật',
            'status' => 'unknown',
            'status_label' => 'Chưa xác định',
            'can_withdraw' => false,
            'timeline' => [],
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
            'reviewing' => 'Đang xem xét',
            'interview' => 'Mời phỏng vấn',
            'accepted' => 'Đã chấp nhận',
            'declined' => 'Chưa phù hợp',
            'withdrawn' => 'Đã rút',
            default => 'Chưa xác định',
        };
    }
}
