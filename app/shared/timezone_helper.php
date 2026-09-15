<?php
declare(strict_types=1);

/**
 * TalentHub Timezone Helper
 *
 * Helper thống nhất cho việc format và hiển thị thời gian trong toàn bộ UI.
 *
 * Quy ước dự án:
 * - Mọi thời gian trong DB được lưu theo UTC.
 * - Mọi hiển thị cho người dùng phải ở Asia/Ho_Chi_Minh (UTC+7).
 * - Helper này chỉ dùng cho view layer (file PHP render ra HTML/JSON cho user).
 *   Logic nền (so sánh thời gian trong service/repository) vẫn dùng UTC.
 */

if (!defined('TALENTHUB_TZ')) {
    define('TALENTHUB_TZ', 'Asia/Ho_Chi_Minh');
}

if (!function_exists('tz_format')) {
    /**
     * Format một timestamp (string từ DB hoặc DateTime) sang string hiển thị theo VN timezone.
     *
     * @param mixed  $value         Có thể là string ('2026-09-10 14:15:00' / '2026-09-10T14:15:00+07:00'),
     *                              DateTime, DateTimeImmutable, hoặc null.
     * @param string $format        Format output, mặc định 'd/m/Y H:i'.
     * @param string $emptyFallback Chuỗi trả về khi giá trị rỗng/không hợp lệ.
     * @return string
     */
    function tz_format(mixed $value, string $format = 'd/m/Y H:i', string $emptyFallback = 'Chưa cập nhật'): string
    {
        if ($value === null) {
            return $emptyFallback;
        }
        if (is_string($value) && trim($value) === '') {
            return $emptyFallback;
        }
        try {
            $dt = tz_to_dt($value);
            return $dt->setTimezone(new DateTimeZone(TALENTHUB_TZ))->format($format);
        } catch (Throwable) {
            return is_string($value) ? $value : $emptyFallback;
        }
    }
}

if (!function_exists('tz_split')) {
    /**
     * Trả về ['date' => '10/09/2026', 'time' => '14:15', 'full' => '10/09/2026 14:15'].
     * Dùng cho các widget hiển thị date + time riêng (ví dụ card có 2 dòng).
     *
     * @return array{date:string,time:string,full:string}
     */
    function tz_split(mixed $value, string $emptyFallback = 'Chưa cập nhật'): array
    {
        if ($value === null) {
            return ['date' => '--/--', 'time' => $emptyFallback, 'full' => $emptyFallback];
        }
        if (is_string($value) && trim($value) === '') {
            return ['date' => '--/--', 'time' => $emptyFallback, 'full' => $emptyFallback];
        }
        try {
            $dt = tz_to_dt($value)->setTimezone(new DateTimeZone(TALENTHUB_TZ));
            return [
                'date' => $dt->format('d/m/Y'),
                'time' => $dt->format('H:i'),
                'full' => $dt->format('d/m/Y H:i'),
            ];
        } catch (Throwable) {
            return ['date' => '--/--', 'time' => $emptyFallback, 'full' => $emptyFallback];
        }
    }
}

if (!function_exists('tz_relative')) {
    /**
     * Trả về chuỗi "X giây trước", "X phút trước", "X giờ trước", "X ngày trước"
     * hoặc ngày tháng nếu quá 7 ngày. So sánh với giờ hiện tại theo VN timezone.
     */
    function tz_relative(mixed $value, string $emptyFallback = '—'): string
    {
        if ($value === null) {
            return $emptyFallback;
        }
        if (is_string($value) && trim($value) === '') {
            return $emptyFallback;
        }
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone(TALENTHUB_TZ));
            $ts = tz_to_dt($value)->setTimezone(new DateTimeZone(TALENTHUB_TZ))->getTimestamp();
            $diff = $now->getTimestamp() - $ts;
            if ($diff < 0) {
                return 'Vừa xong';
            }
            if ($diff < 60) {
                return $diff . ' giây trước';
            }
            if ($diff < 3600) {
                return floor($diff / 60) . ' phút trước';
            }
            if ($diff < 86400) {
                return floor($diff / 3600) . ' giờ trước';
            }
            if ($diff < 86400 * 7) {
                return floor($diff / 86400) . ' ngày trước';
            }
            return tz_format($value, 'd/m/Y');
        } catch (Throwable) {
            return $emptyFallback;
        }
    }
}

if (!function_exists('tz_to_dt')) {
    /**
     * Internal: Chuyển mọi kiểu input thành DateTimeImmutable (giả định UTC nếu không có TZ).
     *
     * - DateTime / DateTimeImmutable: trả về bản copy.
     * - String có timezone (Z, +07:00, +0700): parse theo TZ đó.
     * - String không có timezone: mặc định coi là UTC (chuẩn của project).
     */
    function tz_to_dt(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }
        $str = trim((string) $value);
        $hasTz = preg_match('/(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $str) === 1;
        if ($hasTz) {
            return new DateTimeImmutable($str);
        }
        return new DateTimeImmutable($str, new DateTimeZone('UTC'));
    }
}