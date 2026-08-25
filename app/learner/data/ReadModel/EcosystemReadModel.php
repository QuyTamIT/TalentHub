<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

use TalentHub\Learner\Data\Contracts\EcosystemRepository;
use TalentHub\Learner\Data\Support\Uuid;

final class EcosystemReadModel
{
    public static function partner(array $record): array
    {
        if (($record['type'] ?? '') === 'school') {
            $record['school_type'] ??= $record['level'] ?? null;
        }
        $record['location'] ??= $record['address'] ?? null;
        $record['verified'] ??= ($record['verification_status'] ?? '') === 'verified';
        $record['logo_text'] ??= self::initials((string) ($record['name'] ?? 'TH'));

        return ReadModelDefaults::apply($record, [
            'id' => '',
            'type' => 'enterprise',
            'name' => 'Đối tác TalentHub',
            'short_name' => '',
            'logo_text' => 'TH',
            'verified' => false,
            'industry' => 'Chưa cập nhật',
            'school_type' => 'Chưa cập nhật',
            'location' => 'Chưa cập nhật',
            'address' => 'Chưa cập nhật',
            'website' => '#',
            'email' => '',
            'phone' => '',
            'size' => 'Chưa cập nhật',
            'founded' => 'Chưa cập nhật',
            'description' => 'Thông tin giới thiệu chưa có trong schema hiện tại.',
            'highlights' => [],
            'programs' => [],
            'facilities' => [],
            'events' => [],
            'opportunity_count' => 0,
        ], 'ecosystem_partner');
    }

    public static function opportunity(array $record): array
    {
        $record['partner_id'] ??= $record['enterprise_id'] ?? $record['school_id'] ?? null;
        $record['partner_type'] ??= isset($record['school_id']) ? 'school' : 'enterprise';
        $record['partner_name'] ??= $record['enterprise_name'] ?? null;
        $record['route_id'] ??= $record['id'] ?? null;
        $record['status_label'] ??= self::statusLabel((string) ($record['status'] ?? 'unknown'));

        return ReadModelDefaults::apply($record, [
            'id' => '',
            'route_id' => '',
            'type' => 'internship',
            'partner_id' => '',
            'partner_type' => 'enterprise',
            'partner_name' => 'Đối tác TalentHub',
            'title' => 'Cơ hội TalentHub',
            'field' => 'Chưa phân loại',
            'status' => 'unknown',
            'status_label' => 'Chưa xác định',
            'created_at' => null,
            'deadline' => '1970-01-01',
            'slots' => 0,
            'applicant_count' => 0,
            'work_type' => 'Chưa cập nhật',
            'duration' => 'Chưa cập nhật',
            'education_level' => 'Chưa cập nhật',
            'description' => 'Mô tả cơ hội chưa có trong schema hiện tại.',
            'skills' => [],
            'benefits' => 'Chưa cập nhật',
            'location' => 'Chưa cập nhật',
            'requirements' => [],
        ], 'ecosystem_opportunity');
    }

    public static function partners(array $records, array $opportunities = []): array
    {
        return array_map(static function (array $record) use ($opportunities): array {
            $view = self::partner($record);
            if (($view['opportunity_count'] ?? 0) === 0) {
                $view['opportunity_count'] = count(array_filter(
                    $opportunities,
                    static fn (array $opportunity): bool => ($opportunity['partner_id'] ?? '') === $view['id']
                        && ($opportunity['status'] ?? '') === 'active'
                ));
            }
            return $view;
        }, $records);
    }

    public static function opportunities(array $records): array
    {
        return array_map([self::class, 'opportunity'], $records);
    }

    public static function resolvePartner(EcosystemRepository $repository, string $type, string $routeId): ?array
    {
        if (Uuid::isValid($routeId)) {
            $record = $repository->findPartner($type, $routeId);
            return $record === null ? null : self::partner($record);
        }
        foreach ($repository->partners($type) as $record) {
            $view = self::partner($record);
            if ((string) $view['id'] === $routeId) {
                return $view;
            }
        }
        return null;
    }

    public static function resolveOpportunity(EcosystemRepository $repository, string $type, string $routeId): ?array
    {
        if (Uuid::isValid($routeId)) {
            $record = $repository->findOpportunity($type, $routeId);
            return $record === null ? null : self::opportunity($record);
        }
        foreach ($repository->opportunities() as $record) {
            $view = self::opportunity($record);
            if ((string) $view['id'] === $routeId) {
                return $view;
            }
        }
        return null;
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(
            static fn (string $part): string => function_exists('mb_substr')
                ? mb_substr($part, 0, 1, 'UTF-8')
                : substr($part, 0, 1),
            array_slice($parts, 0, 3)
        );
        return strtoupper(implode('', $letters)) ?: 'TH';
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Đang mở',
            'closed' => 'Đã đóng',
            'draft' => 'Bản nháp',
            'cancelled' => 'Đã hủy',
            default => 'Chưa xác định',
        };
    }
}
