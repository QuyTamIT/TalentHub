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
        $record = LearnerViewAdapter::record($record);
        if (!array_key_exists('start_at', $record) && array_key_exists('startAt', $record)) {
            $record['start_at'] = $record['startAt'];
        }
        if (!array_key_exists('end_at', $record) && array_key_exists('endAt', $record)) {
            $record['end_at'] = $record['endAt'];
        }
        if (!array_key_exists('school_id', $record) && array_key_exists('schoolId', $record)) {
            $record['school_id'] = $record['schoolId'];
        }
        if (!array_key_exists('school_name', $record) && array_key_exists('schoolName', $record)) {
            $record['school_name'] = $record['schoolName'];
        }
        if (!array_key_exists('responsible_teacher_name', $record) && array_key_exists('responsibleTeacherName', $record)) {
            $record['responsible_teacher_name'] = $record['responsibleTeacherName'];
        }
        if (!array_key_exists('registration_opens_at', $record) && array_key_exists('registrationOpensAt', $record)) {
            $record['registration_opens_at'] = $record['registrationOpensAt'];
        }
        if (!array_key_exists('registration_closes_at', $record) && array_key_exists('registrationClosesAt', $record)) {
            $record['registration_closes_at'] = $record['registrationClosesAt'];
        }
        if (!array_key_exists('cancellation_closes_at', $record) && array_key_exists('cancellationClosesAt', $record)) {
            $record['cancellation_closes_at'] = $record['cancellationClosesAt'];
        }
        if (!array_key_exists('approval_mode', $record) && array_key_exists('approvalMode', $record)) {
            $record['approval_mode'] = $record['approvalMode'];
        }
        if (!array_key_exists('confirmed_hours', $record) && array_key_exists('confirmedHours', $record)) {
            $record['confirmed_hours'] = $record['confirmedHours'];
        }
        if (!array_key_exists('filter_category', $record) && array_key_exists('filterCategory', $record)) {
            $record['filter_category'] = $record['filterCategory'];
        }
        $metadata = $record;
        $record['activity_id'] ??= $record['id'] ?? null;
        $record['route_id'] ??= $record['id'] ?? null;
        if (!is_string($record['filter_category'] ?? null) || trim((string) $record['filter_category']) === '') {
            $record['filter_category'] = \learner_activity_category_label((string) ($record['category'] ?? ''));
        }
        $record['registration_closes_at'] ??= $record['end_at'] ?? $record['start_at'] ?? null;

        $view = ReadModelDefaults::apply($record, [
            'id' => '',
            'activity_id' => '',
            'route_id' => '',
            'school_id' => '',
            'school_name' => '',
            'responsible_teacher_name' => '',
            'title' => 'Hoạt động TalentHub',
            'category' => 'Chưa phân loại',
            'display_category' => '',
            'filter_category' => 'Chưa phân loại',
            'tone' => 'neutral',
            'summary' => '',
            'description' => 'Mô tả hoạt động chưa có trong schema hiện tại.',
            'start_at' => '1970-01-01 00:00:00',
            'end_at' => null,
            'location' => 'Chưa cập nhật',
            'format' => 'Chưa cập nhật',
            'participants' => 0,
            'capacity' => 1,
            'approval_mode' => 'automatic',
            'skills' => [],
            'experience_highlights' => [],
            'requirements' => [],
            'benefits' => [],
            'location_name' => '',
            'location_address' => '',
            'delivery_mode' => '',
            'online_meeting_url' => '',
            'organizer_name' => '',
            'organizer_contact' => '',
            'organizer_email' => '',
            'organizer_phone' => '',
            'cover_image_url' => '',
            'cover_image_alt' => '',
            'fee_amount' => null,
            'currency' => '',
            'target_audience' => '',
            'certificate_label' => '',
            'confirmed_hours' => null,
            'cost' => 'Chưa cập nhật',
            'registration_opens_at' => null,
            'registration_closes_at' => null,
            'cancellation_closes_at' => null,
            'status' => 'unknown',
            'can_register' => false,
        ], 'activity');

        if ((int) $view['capacity'] <= 0) {
            $view['capacity'] = 1;
            $view['data_notes'][] = 'activity.capacity uses 1 to keep the current progress UI safe from division by zero.';
        }

        $rawCloses = $view['registration_closes_at'] ?? null;
        if ($rawCloses === null || $rawCloses === '' || str_starts_with((string)$rawCloses, '1970')) {
            $view['registration_closes_at'] = $view['end_at'] ?? $view['start_at'] ?? null;
        }

        $view['skills'] = self::normalizeTextList($metadata['skills'] ?? null);
        $view['experience_highlights'] = self::normalizeTextList($metadata['experience_highlights'] ?? null);
        $view['requirements'] = self::normalizeTextList($metadata['requirements'] ?? null);
        $view['benefits'] = self::normalizeTextList($metadata['benefits'] ?? null);
        $view['display_category'] = self::text($metadata['display_category'] ?? null)
            ?: (string) $view['filter_category'];
        $view['location_name'] = self::text($metadata['location_name'] ?? null)
            ?: self::text($metadata['location'] ?? null);
        $view['location'] = $view['location_name'] !== '' ? $view['location_name'] : 'Chưa cập nhật';
        $view['delivery_mode_label'] = self::deliveryModeLabel($metadata['delivery_mode'] ?? $metadata['format'] ?? null);
        $view['format'] = $view['delivery_mode_label'] !== '' ? $view['delivery_mode_label'] : 'Chưa cập nhật';
        $view['online_meeting_url'] = self::safeHttpsUrl($metadata['online_meeting_url'] ?? null);
        $view['cover_image_url'] = self::safeLocalActivityAsset($metadata['cover_image_url'] ?? null);
        $view['cover_image_alt'] = self::text($metadata['cover_image_alt'] ?? null);
        $view['organizer_email'] = self::safeEmail($metadata['organizer_email'] ?? null);
        $view['organizer_phone'] = self::text($metadata['organizer_phone'] ?? null);
        $view['remaining'] = max(0, (int) $view['capacity'] - (int) $view['participants']);
        $view['fee_label'] = self::feeLabel(
            $metadata['fee_amount'] ?? null,
            $metadata['currency'] ?? null,
            $metadata['cost'] ?? null,
        );
        $view['cost'] = $view['fee_label'] !== '' ? $view['fee_label'] : 'Chưa cập nhật';
        $view['has_summary'] = self::hasText($metadata['summary'] ?? null);
        $view['has_description'] = self::hasText($metadata['description'] ?? null);
        $view['has_experience_highlights'] = $view['experience_highlights'] !== [];
        $view['has_skills'] = $view['skills'] !== [];
        $view['has_requirements'] = $view['requirements'] !== [];
        $view['has_benefits'] = $view['benefits'] !== [];
        $view['has_format'] = $view['delivery_mode_label'] !== '';
        $view['has_cost'] = $view['fee_label'] !== '';
        $view['has_location'] = $view['location_name'] !== '';
        $view['has_location_address'] = self::hasText($metadata['location_address'] ?? null);
        $view['has_organizer_email'] = $view['organizer_email'] !== '';
        $view['has_organizer_phone'] = $view['organizer_phone'] !== '';
        $view['has_contact'] = self::hasText($metadata['organizer_name'] ?? null)
            || self::hasText($metadata['organizer_contact'] ?? null)
            || $view['has_organizer_email']
            || $view['has_organizer_phone'];
        $view['availability'] = self::availabilityState($view);
        $view['can_register'] = self::canRegister($view);

        return $view;
    }

    public static function canRegister(array $activity, ?\DateTimeImmutable $now = null): bool
    {
        return self::availabilityState($activity, $now)['code'] === 'open';
    }

    /** @return array{code:string,label:string,explanation:string} */
    public static function availabilityState(array $activity, ?\DateTimeImmutable $now = null): array
    {
        $status = strtolower(trim((string) ($activity['status'] ?? '')));
        if (in_array($status, ['ongoing', 'active'], true)) {
            return ['code' => 'ongoing', 'label' => 'Đang diễn ra', 'explanation' => 'Hoạt động đang diễn ra và không nhận đăng ký mới.'];
        }
        if ($status === 'completed') {
            return ['code' => 'completed', 'label' => 'Đã kết thúc', 'explanation' => 'Hoạt động đã kết thúc.'];
        }
        if ($status !== 'published') {
            return ['code' => 'unavailable', 'label' => 'Không nhận đăng ký', 'explanation' => 'Hoạt động hiện không nhận đăng ký.'];
        }

        try {
            $current = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'));
            $start = self::date($activity['start_at'] ?? null);
            $end = self::date($activity['end_at'] ?? null) ?? $start;
            $opens = self::date($activity['registration_opens_at'] ?? null);
            $closes = self::date($activity['registration_closes_at'] ?? null) ?? $start;
        } catch (\Throwable) {
            return ['code' => 'unavailable', 'label' => 'Không nhận đăng ký', 'explanation' => 'Không thể xác định thời gian đăng ký.'];
        }
        if ($end !== null && $current >= $end) {
            return ['code' => 'completed', 'label' => 'Đã kết thúc', 'explanation' => 'Hoạt động đã kết thúc.'];
        }
        if ($opens !== null && $current < $opens) {
            return ['code' => 'not_open', 'label' => 'Chưa mở đăng ký', 'explanation' => 'Hoạt động chưa đến thời gian mở đăng ký.'];
        }
        if ($closes === null || $current >= $closes) {
            return ['code' => 'expired', 'label' => 'Đã hết hạn đăng ký', 'explanation' => 'Hoạt động đã hết hạn đăng ký.'];
        }
        $capacity = (int) ($activity['capacity'] ?? 0);
        $participants = (int) ($activity['participants'] ?? 0);
        $remaining = array_key_exists('remaining', $activity)
            ? (int) $activity['remaining']
            : $capacity - $participants;
        if ($capacity <= 0 || $participants >= $capacity || $remaining <= 0) {
            return ['code' => 'full', 'label' => 'Đã hết chỗ', 'explanation' => 'Hoạt động đã đủ số lượng đăng ký.'];
        }
        return ['code' => 'open', 'label' => 'Đang mở đăng ký', 'explanation' => 'Hoạt động đang nhận đăng ký.'];
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


    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    public static function activities(array $records): array
    {
        $unique = [];
        foreach ($records as $record) {
            $view = self::activity($record);
            $id = (string) ($view['id'] ?? '');
            if ($id !== '' && !isset($unique[$id])) {
                $unique[$id] = $view;
            }
        }
        return array_values($unique);
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

    public static function resolveForStudent(ActivityRepository $repository, string $studentId, string $routeId): ?array
    {
        $record = $repository->findForStudent($studentId, $routeId);
        return $record === null ? null : self::activity($record);
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

    private static function normalizeTextList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value),
            [self::class, 'hasText']
        ));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) && self::hasText($value) ? trim($value) : '';
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        $value = self::text($value);
        return $value === '' ? null : new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function deliveryModeLabel(mixed $value): string
    {
        $value = self::text($value);
        return match (strtolower($value)) {
            'in_person', 'in-person', 'direct' => 'Trực tiếp',
            'online', 'virtual' => 'Trực tuyến',
            'hybrid' => 'Kết hợp',
            default => $value,
        };
    }

    private static function feeLabel(mixed $amount, mixed $currency, mixed $legacyCost): string
    {
        if (is_numeric($amount)) {
            $number = (float) $amount;
            if ($number <= 0) return 'Miễn phí';
            $currency = self::text($currency) ?: 'VND';
            return number_format($number, 0, ',', '.') . ' ' . $currency;
        }
        return self::text($legacyCost);
    }

    private static function safeEmail(mixed $value): string
    {
        $value = self::text($value);
        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : '';
    }

    private static function safeHttpsUrl(mixed $value): string
    {
        $value = self::text($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) return '';
        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : '';
    }

    private static function safeLocalActivityAsset(mixed $value): string
    {
        $value = self::text($value);
        if ($value === '' || str_contains($value, '..')) return '';
        return preg_match('#\A(?:/app/learner/)?assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg)\z#i', $value) === 1
            ? $value
            : '';
    }
}
