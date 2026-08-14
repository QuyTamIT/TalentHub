<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Enums;

enum StudentStudyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Graduated = 'graduated';
    case Suspended = 'suspended';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum AssessmentAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum EvaluationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum ActivityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum ActivityRegistrationStatus: string
{
    case Registered = 'registered';
    case Pending = 'pending';
    case Waitlisted = 'waitlisted';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum OpportunityStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Interview = 'interview';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}
