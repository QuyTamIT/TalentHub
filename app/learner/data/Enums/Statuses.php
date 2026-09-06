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
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Archived = 'archived';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}

enum ActivityRegistrationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case Waitlisted = 'waitlisted';
    case NoShow = 'no_show';
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
        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'published') {
            return self::Active;
        }
        return self::tryFrom($normalized) ?? self::Unknown;
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

final class StudentPortalStatusContract
{
    /** @return list<string> */
    public static function canonicalActivityStatuses(): array
    {
        return ['draft', 'published', 'ongoing', 'completed', 'archived'];
    }

    /** @return array<string,list<string>> */
    public static function activityAliases(): array
    {
        return ['active' => ['published', 'ongoing'], 'closed' => ['completed', 'archived']];
    }

    /** @return list<string> */
    public static function canonicalActivityRegistrationStatuses(): array
    {
        return ['pending', 'approved', 'rejected', 'cancelled', 'attended', 'waitlisted', 'no_show'];
    }

    /** @return array<string,string> */
    public static function activityRegistrationAliases(): array
    {
        return ['registered' => 'approved', 'checked_in' => 'attended', 'completed' => 'attended'];
    }

    public static function aiVisiblePercent(): string
    {
        return (string) (getenv('TALENTHUB_AI_VISIBLE_PERCENT') === false ? '0' : getenv('TALENTHUB_AI_VISIBLE_PERCENT'));
    }
}
