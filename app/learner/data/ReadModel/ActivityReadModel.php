<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\Support\LearnerViewAdapter;
use TalentHub\Learner\Data\Support\Uuid;

final class ActivityReadModel
{
    public static function activity(array $record): array
    {
        $metadata = LearnerViewAdapter::record($record);
        $record['activity_id'] ??= $record['id'] ?? null;
        $record['route_id'] ??= $record['id'] ?? null;
        $record['filter_category'] ??= $record['category'] ?? null;
        $record['registration_closes_at'] ??= $record['start_at'] ?? null;

        $view = ReadModelDefaults::apply($record, [
            'id' => '',
            'activity_id' => '',
            'route_id' => '',
            'school_id' => '',
            'title' => 'Hoạt động TalentHub',
            'category' => 'Chưa phân loại',
            'filter_category' => 'Chưa phân loại',
            'tone' => 'neutral',
            'summary' => 'Thông tin tóm tắt chưa có trong schema hiện tại.',
            'description' => 'Mô tả hoạt động chưa có trong schema hiện tại.',
            'start_at' => '1970-01-01 00:00:00',
            'end_at' => null,
            'location' => 'Chưa cập nhật',
            'format' => 'Chưa cập nhật',
            'participants' => 0,
            'capacity' => 1,
            'approval_mode' => 'automatic',
            'skills' => [],
            'requirements' => [],
            'benefits' => [],
            'cost' => 'Chưa cập nhật',
            'registration_opens_at' => null,
            'registration_closes_at' => '1970-01-01 00:00:00',
            'cancellation_closes_at' => null,
            'status' => 'unknown',
            'can_register' => false,
        ], 'activity');

        if ((int) $view['capacity'] <= 0) {
            $view['capacity'] = 1;
            $view['data_notes'][] = 'activity.capacity uses 1 to keep the current progress UI safe from division by zero.';
        }

        $view['has_description'] = self::hasText($metadata['description'] ?? null);
        $view['has_skills'] = self::hasList($metadata['skills'] ?? null);
        $view['has_requirements'] = self::hasList($metadata['requirements'] ?? null);
        $view['has_benefits'] = self::hasList($metadata['benefits'] ?? null);
        $view['has_format'] = self::hasText($metadata['format'] ?? null);
        $view['has_cost'] = self::hasText($metadata['cost'] ?? null);
        $view['has_location'] = self::hasText($metadata['location'] ?? null);
        $view['can_register'] = self::canRegister($view);

        return $view;
    }

    public static function canRegister(array $activity, ?\DateTimeImmutable $now = null): bool
    {
        if (!in_array((string) ($activity['status'] ?? ''), ['published', 'active'], true)) {
            return false;
        }

        $closesAt = $activity['registration_closes_at'] ?? $activity['start_at'] ?? null;
        if ($closesAt === null || trim((string) $closesAt) === '') {
            return false;
        }

        try {
            $current = $now ?? new \DateTimeImmutable('now');
            $closes = new \DateTimeImmutable((string) $closesAt);
            $opensAt = $activity['registration_opens_at'] ?? null;
            $opens = $opensAt === null || trim((string) $opensAt) === ''
                ? null
                : new \DateTimeImmutable((string) $opensAt);
        } catch (\Throwable) {
            return false;
        }

        return ($opens === null || $current >= $opens) && $current <= $closes;
    }

    public static function registration(array $record): array
    {
        return ReadModelDefaults::apply($record, [
            'id' => '',
            'student_id' => '',
            'activity_id' => '',
            'status' => 'unknown',
            'created_at' => null,
            'updated_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'checkin_id' => null,
            'experience_hours' => null,
            'feedback' => null,
        ], 'activity_registration');
    }

    public static function activities(array $records): array
    {
        return array_map([self::class, 'activity'], $records);
    }

    public static function registrations(array $records): array
    {
        return array_map([self::class, 'registration'], $records);
    }

    public static function resolve(ActivityRepository $repository, string $routeId): ?array
    {
        if (Uuid::isValid($routeId)) {
            $record = $repository->findById($routeId);
            return $record === null ? null : self::activity($record);
        }

        $needle = self::slug($routeId);
        foreach ($repository->all() as $record) {
            $view = self::activity($record);
            $id = (string) ($view['id'] ?? '');
            $titleSlug = self::slug((string) ($view['title'] ?? ''));
            if ($id === $routeId || $titleSlug === $needle || str_starts_with($titleSlug, $needle . '-')) {
                return $view;
            }
        }

        return null;
    }

    private static function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = strtolower($ascii === false ? $value : $ascii);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $normalized), '-');
    }

    private static function hasText(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $text = trim($value);
        return $text !== '' && !in_array($text, [
            'Chưa cập nhật',
            'Mô tả hoạt động chưa có trong schema hiện tại.',
            'Thông tin tóm tắt chưa có trong schema hiện tại.',
        ], true);
    }

    private static function hasList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (self::hasText($item)) {
                return true;
            }
        }

        return false;
    }
}
