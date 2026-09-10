<?php
declare(strict_types=1);

namespace TalentHub\Domain\Activity;

/**
 * Student-visible catalog buckets returned by ActivityPolicy::catalogBucket().
 * The front-end maps these to UI sections (đang mở, sắp mở, hết chỗ, ẩn ...).
 */
final class CatalogBucket
{
    public const OPEN       = 'open';        // đang mở, còn chỗ
    public const UPCOMING   = 'upcoming';    // sắp mở, có ngày mở rõ ràng
    public const FULL       = 'full';        // hết chỗ, có waitlist
    public const CLOSED     = 'closed';      // đã đóng đăng ký / sai trường
    public const HIDDEN     = 'hidden';      // không hiển thị trong catalog

    public const ENROLLED   = 'enrolled';    // sinh viên đã được duyệt/attended
    public const PENDING    = 'pending';     // sinh viên đang chờ duyệt
    public const WAITLISTED = 'waitlisted';  // sinh viên đang trong waitlist
    public const CANCELLED  = 'cancelled';   // từng bị hủy, có thể đăng ký lại
    public const REJECTED   = 'rejected';    // bị giáo viên từ chối, chỉ hiện trong lịch sử

    public const ALL_PUBLIC = [
        self::OPEN,
        self::UPCOMING,
        self::FULL,
        self::CLOSED,
    ];

    public const ALL_USER = [
        self::ENROLLED,
        self::PENDING,
        self::WAITLISTED,
        self::CANCELLED,
        self::REJECTED,
    ];
}
