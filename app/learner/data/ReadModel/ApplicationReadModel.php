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
        $history = is_array($record['history'] ?? null) ? $record['history'] : [];
        $lastIndex = array_key_last($history);
        $record['timeline'] = array_map(
            static function (array $entry, int $index) use ($lastIndex): array {
                $status = (string) ($entry['to_status'] ?? 'unknown');
                return [
                    'label' => self::statusLabel($status),
                    'date' => $entry['created_at'] ?? null,
                    'state' => in_array($status, ['declined', 'withdrawn'], true)
                        ? $status
                        : ($index === $lastIndex ? 'current' : 'complete'),
                ];
            },
            $history,
            array_keys($history)
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
            'submitted_at' => null,
            'updated_at' => null,
            'status' => 'unknown',
            'status_label' => 'Chưa xác định',
            'can_withdraw' => false,
            'timeline' => [],
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
            'reviewing' => 'Đang xem xét',
            'interview' => 'Mời phỏng vấn',
            'accepted' => 'Đã chấp nhận',
            'declined' => 'Chưa phù hợp',
            'withdrawn' => 'Đã rút',
            default => 'Chưa xác định',
        };
    }
}
