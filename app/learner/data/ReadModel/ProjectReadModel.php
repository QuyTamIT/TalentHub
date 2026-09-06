<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

use DateTimeImmutable;
use Throwable;

final class ProjectReadModel
{
    /** @param array<string,mixed> $record @return array<string,mixed> */
    public static function project(array $record): array
    {
        $record['category_label'] = learner_activity_category_label((string) ($record['category'] ?? ''));
        $record['status_label'] = match ((string) ($record['status'] ?? '')) {
            'in_progress' => 'Đang triển khai',
            'completed' => 'Đã hoàn thành',
            default => 'Chưa xác định',
        };
        $record['members_count'] = max(0, (int) ($record['members_count'] ?? 0));

        foreach (['start_at', 'end_at'] as $dateField) {
            $labelField = $dateField . '_label';
            $rawDate = trim((string) ($record[$dateField] ?? ''));
            try {
                $record[$labelField] = $rawDate === '' ? '' : (new DateTimeImmutable($rawDate))->format('d/m/Y');
            } catch (Throwable) {
                $record[$labelField] = '';
            }
        }

        $sponsorships = is_array($record['sponsorships'] ?? null) ? $record['sponsorships'] : [];
        $record['sponsorships'] = array_values(array_map(
            static fn (array $sponsorship): array => [
                'enterprise_id' => (string) ($sponsorship['enterprise_id'] ?? ''),
                'enterprise_name' => trim((string) ($sponsorship['enterprise_name'] ?? '')),
                'amount' => (float) ($sponsorship['amount'] ?? 0),
                'currency' => strtoupper(trim((string) ($sponsorship['currency'] ?? 'VND'))) ?: 'VND',
                'note' => trim((string) ($sponsorship['note'] ?? '')),
            ],
            $sponsorships,
        ));
        $record['raised_amount'] = array_reduce(
            $record['sponsorships'],
            static fn (float $total, array $sponsor): float => $total + (float) $sponsor['amount'],
            0.0,
        );
        unset($record['project_url']);

        return $record;
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    public static function projects(array $records): array
    {
        return array_map([self::class, 'project'], $records);
    }
}
