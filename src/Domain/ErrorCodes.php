<?php
declare(strict_types=1);

namespace TalentHub\Domain;

/**
 * Canonical error codes returned by the API. Stable, machine-readable.
 *
 * The Vietnamese user-facing message is rendered by the front-end by mapping
 * the code; back-end code must NEVER compare against localized strings.
 *
 * Status code mapping follows the project convention:
 *   422 - validation / temporal boundary / illegal state for input
 *   409 - state conflict / transition conflict / lock conflict
 *   403 - permission denied
 *   404 - resource missing
 *   503 - feature unavailable / contract missing
 */
final class ErrorCodes
{
    // Generic
    public const VALIDATION_FAILED       = 'VALIDATION_FAILED';
    public const RESOURCE_NOT_FOUND      = 'RESOURCE_NOT_FOUND';
    public const PERMISSION_DENIED       = 'PERMISSION_DENIED';
    public const UNAUTHENTICATED         = 'UNAUTHENTICATED';
    public const CSRF_TOKEN_INVALID      = 'CSRF_TOKEN_INVALID';
    public const RATE_LIMIT_EXCEEDED     = 'RATE_LIMIT_EXCEEDED';
    public const CONCURRENT_MODIFICATION = 'CONCURRENT_MODIFICATION';

    // Activity lifecycle
    public const ACTIVITY_NOT_STARTED    = 'ACTIVITY_NOT_STARTED';
    public const ACTIVITY_NOT_ENDED      = 'ACTIVITY_NOT_ENDED';
    public const ACTIVITY_NOT_PUBLISHED  = 'ACTIVITY_NOT_PUBLISHED';
    public const ACTIVITY_ALREADY_STARTED = 'ACTIVITY_ALREADY_STARTED';
    public const ACTIVITY_ALREADY_COMPLETED = 'ACTIVITY_ALREADY_COMPLETED';
    public const ACTIVITY_EDIT_LOCKED    = 'ACTIVITY_EDIT_LOCKED';
    public const ACTIVITY_INVALID_TRANSITION = 'ACTIVITY_INVALID_TRANSITION';
    public const INVALID_ACTIVITY_CONFIGURATION = 'INVALID_ACTIVITY_CONFIGURATION';
    public const INVALID_ACTIVITY        = 'INVALID_ACTIVITY';

    // Registration
    public const REGISTRATION_WINDOW_CLOSED = 'REGISTRATION_WINDOW_CLOSED';
    public const REGISTRATION_WINDOW_NOT_OPEN = 'REGISTRATION_WINDOW_NOT_OPEN';
    public const REGISTRATION_NOT_PENDING   = 'REGISTRATION_NOT_PENDING';
    public const REGISTRATION_NOT_FOUND     = 'REGISTRATION_NOT_FOUND';
    public const REGISTRATION_REJECTED      = 'REGISTRATION_REJECTED';
    public const REGISTRATION_ALREADY_EXISTS = 'REGISTRATION_ALREADY_EXISTS';
    public const REGISTRATION_CANCEL_WINDOW_CLOSED = 'REGISTRATION_CANCEL_WINDOW_CLOSED';
    public const PENDING_REGISTRATIONS_EXIST = 'PENDING_REGISTRATIONS_EXIST';
    public const CAPACITY_REACHED           = 'CAPACITY_REACHED';

    // Grading
    public const ASSESSMENT_NOT_ATTENDED    = 'ASSESSMENT_NOT_ATTENDED';
    public const ASSESSMENT_NOT_ONGOING     = 'ASSESSMENT_NOT_ONGOING';
    public const ASSESSMENT_NOT_COMPLETED   = 'ASSESSMENT_NOT_COMPLETED';
    public const ASSESSMENT_ALREADY_PUBLISHED = 'ASSESSMENT_ALREADY_PUBLISHED';
    public const ASSESSMENT_PUBLISH_BLOCKED = 'ASSESSMENT_PUBLISH_BLOCKED';
    public const ARCHIVE_BLOCKED_PENDING_RESULTS = 'ARCHIVE_BLOCKED_PENDING_RESULTS';

    // QR
    public const QR_SESSION_NOT_REVOCABLE   = 'QR_SESSION_NOT_REVOCABLE';
    public const QR_SESSION_NOT_AVAILABLE   = 'QR_SESSION_NOT_AVAILABLE';

    // Internship
    public const INTERNSHIP_PLACEMENT_LOCKED = 'INTERNSHIP_PLACEMENT_LOCKED';
    public const INTERNSHIP_INVALID_TRANSITION = 'INTERNSHIP_INVALID_TRANSITION';

    // Profile / membership
    public const TEACHER_PROFILE_NOT_FOUND = 'TEACHER_PROFILE_NOT_FOUND';
    public const ENTERPRISE_MEMBERSHIP_NOT_FOUND = 'ENTERPRISE_MEMBERSHIP_NOT_FOUND';
    public const SCHOOL_MEMBERSHIP_NOT_FOUND = 'SCHOOL_MEMBERSHIP_NOT_FOUND';

    // Approval / verification
    public const APPROVAL_STATUS_CONFLICT  = 'APPROVAL_STATUS_CONFLICT';
    public const APPROVAL_CONTRACT_UNAVAILABLE = 'APPROVAL_CONTRACT_UNAVAILABLE';

    // Onboarding
    public const ONBOARDING_ACCEPTANCE_REQUIRED = 'ONBOARDING_ACCEPTANCE_REQUIRED';
    public const ONBOARDING_SEQUENCE_REQUIRED = 'ONBOARDING_SEQUENCE_REQUIRED';

    // School safeguarding
    public const SAFEGUARDING_NOT_SATISFIED = 'SAFEGUARDING_NOT_SATISFIED';
}
