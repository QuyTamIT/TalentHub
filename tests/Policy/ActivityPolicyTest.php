<?php
declare(strict_types=1);

namespace TalentHub\Tests\Policy;

use Tests\TestCase;
use TalentHub\Domain\Activity\ActivityPolicy;
use TalentHub\Domain\ErrorCodes;
use TalentHub\Support\Clock\FixedClock;

class ActivityPolicyTest
{
    use TestCaseTrait;

    private ActivityPolicy $policy;
    private FixedClock $clock;

    public function __construct()
    {
        $this->clock = new FixedClock('2026-08-30T10:00:00+00:00');
        $this->policy = new ActivityPolicy($this->clock);
    }

    public function name(): string
    {
        return 'ActivityPolicy';
    }

    public function tests(): array
    {
        return [
            'lifecycle: draft can transition to published',
            'lifecycle: published can transition to ongoing',
            'lifecycle: ongoing can transition to completed',
            'lifecycle: completed can transition to archived',
            'lifecycle: completed CANNOT go back to published',
            'lifecycle: archived is terminal',
            'lifecycle: draft can transition to archived',
            'start: ok when now >= startAt and no pending',
            'start: throws ACTIVITY_NOT_STARTED when before startAt',
            'start: throws PENDING_REGISTRATIONS_EXIST when pending > 0',
            'start: isOverdueStart returns true past endAt',
            'complete: ok when now >= endAt and no pending',
            'complete: throws ACTIVITY_NOT_ENDED when before endAt',
            'complete: throws PENDING_REGISTRATIONS_EXIST when pending > 0',
            'archive: blocked when unresolved results exist',
            'editability: draft -> all fields editable',
            'editability: published, window closed, no reg -> all fields editable',
            'editability: published, window open or has reg -> title/startAt/capacity locked',
            'editability: ongoing -> only contactInfo/location/joinUrl editable',
            'editability: completed -> whole config locked',
            'registrationWindow: open when now in [opens, closes)',
            'registrationWindow: closed before opens',
            'registrationWindow: closed after closes',
            'cancellationWindow: open when now < cancellationClosesAt',
            'catalogBucket: open -> OPEN',
            'catalogBucket: full capacity -> FULL',
            'catalogBucket: before opens -> UPCOMING',
            'catalogBucket: after closes -> CLOSED',
            'catalogBucket: non-published status -> HIDDEN',
            'catalogBucket: enrolled student -> ENROLLED',
            'catalogBucket: rejected student -> REJECTED',
            'catalogBucket: cancelled student -> CANCELLED',
            'qr: allowed when ongoing and now < endAt',
            'qr: throws when not ongoing',
            'qr: throws when now >= endAt',
            'qr: clamps expiresAt to endAt when duration overshoots',
            'publishConfig: ok when all required records present',
            'publishConfig: missing details -> reports missing',
            'publishConfig: time ordering violations -> reports missing',
            'publishConfig: invalid delivery/approval mode -> reports missing',
        ];
    }

    // ─── Lifecycle transitions ───────────────────────────────────────────────

    public function lifecycle_draft_can_transition_to_published(): void
    {
        $this->policy->assertCanTransition('draft', 'published');
        $this->assertTrue(true);
    }

    public function lifecycle_published_can_transition_to_ongoing(): void
    {
        $this->policy->assertCanTransition('published', 'ongoing');
        $this->assertTrue(true);
    }

    public function lifecycle_ongoing_can_transition_to_completed(): void
    {
        $this->policy->assertCanTransition('ongoing', 'completed');
        $this->assertTrue(true);
    }

    public function lifecycle_completed_can_transition_to_archived(): void
    {
        $this->policy->assertCanTransition('completed', 'archived');
        $this->assertTrue(true);
    }

    public function lifecycle_completed_CANNOT_go_back_to_published(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanTransition('completed', 'published'),
            ErrorCodes::ACTIVITY_INVALID_TRANSITION,
        );
    }

    public function lifecycle_archived_is_terminal(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanTransition('archived', 'published'),
            ErrorCodes::ACTIVITY_INVALID_TRANSITION,
        );
    }

    public function lifecycle_draft_can_transition_to_archived(): void
    {
        $this->policy->assertCanTransition('draft', 'archived');
        $this->assertTrue(true);
    }

    // ─── Start ─────────────────────────────────────────────────────────────

    public function start_ok_when_now___startAt_and_no_pending(): void
    {
        $activity = ['startAt' => '2026-08-30T09:00:00+00:00'];
        $this->policy->assertCanStart($activity, 0);
        $this->assertTrue(true);
    }

    public function start_throws_ACTIVITY_NOT_STARTED_when_before_startAt(): void
    {
        $this->clock->setNow('2026-08-30T08:00:00+00:00');
        $activity = ['startAt' => '2026-08-30T09:00:00+00:00'];
        $this->assertThrows(
            fn() => $this->policy->assertCanStart($activity, 0),
            ErrorCodes::ACTIVITY_NOT_STARTED,
        );
    }

    public function start_throws_PENDING_REGISTRATIONS_EXIST_when_pending___0(): void
    {
        $this->clock->setNow('2026-08-30T10:00:00+00:00'); // ensure now >= startAt
        $activity = ['startAt' => '2026-08-30T09:00:00+00:00'];
        $this->assertThrows(
            fn() => $this->policy->assertCanStart($activity, 3),
            ErrorCodes::PENDING_REGISTRATIONS_EXIST,
        );
    }

    public function start_isOverdueStart_returns_true_past_endAt(): void
    {
        $this->clock->setNow('2026-08-30T10:00:00+00:00'); // now > endAt (08:00)
        $activity = ['endAt' => '2026-08-30T08:00:00+00:00'];
        $this->assertTrue($this->policy->isOverdueStart($activity));
    }

    // ─── Complete ──────────────────────────────────────────────────────────

    public function complete_ok_when_now___endAt_and_no_pending(): void
    {
        $this->clock->setNow('2026-08-30T18:00:00+00:00');
        $activity = ['endAt' => '2026-08-30T17:00:00+00:00'];
        $this->policy->assertCanComplete($activity, 0);
        $this->assertTrue(true);
    }

    public function complete_throws_ACTIVITY_NOT_ENDED_when_before_endAt(): void
    {
        $this->clock->setNow('2026-08-30T10:00:00+00:00');
        $activity = ['endAt' => '2026-08-30T17:00:00+00:00'];
        $this->assertThrows(
            fn() => $this->policy->assertCanComplete($activity, 0),
            ErrorCodes::ACTIVITY_NOT_ENDED,
        );
    }

    public function complete_throws_PENDING_REGISTRATIONS_EXIST_when_pending___0(): void
    {
        $this->clock->setNow('2026-08-30T18:00:00+00:00');
        $activity = ['endAt' => '2026-08-30T17:00:00+00:00'];
        $this->assertThrows(
            fn() => $this->policy->assertCanComplete($activity, 2),
            ErrorCodes::PENDING_REGISTRATIONS_EXIST,
        );
    }

    // ─── Archive ──────────────────────────────────────────────────────────

    public function archive_blocked_when_unresolved_results_exist(): void
    {
        $activity = ['status' => 'completed'];
        $this->assertThrows(
            fn() => $this->policy->assertCanArchive($activity, 5),
            ErrorCodes::ARCHIVE_BLOCKED_PENDING_RESULTS,
        );
    }

    // ─── Editability ──────────────────────────────────────────────────────

    public function editability_draft___all_fields_editable(): void
    {
        $activity = ['status' => 'draft'];
        $regPolicy = [];
        $patch = ['title' => 'New', 'startAt' => '2026-09-01', 'confirmedHours' => 10];
        $locked = $this->policy->lockedFieldsForPatch($activity, $regPolicy, $patch, false);
        $this->assertEquals([], $locked);
    }

    public function editability_published_window_closed_no_reg___all_fields_editable(): void
    {
        $this->clock->setNow('2026-08-20T10:00:00+00:00'); // before opens
        $activity = ['status' => 'published'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
            'cancellationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $patch = ['title' => 'New', 'confirmedHours' => 5];
        $locked = $this->policy->lockedFieldsForPatch($activity, $regPolicy, $patch, false);
        $this->assertEquals([], $locked);
    }

    public function editability_published_window_open_or_has_reg___title_startAt_capacity_locked(): void
    {
        $this->clock->setNow('2026-08-27T10:00:00+00:00'); // window open
        $activity = ['status' => 'published'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
            'cancellationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        // Per plan: title is locked; confirmedHours is locked (in LOCKED_FIELDS_AFTER_OPEN)
        $patch = ['title' => 'New Title', 'confirmedHours' => 10];
        $locked = $this->policy->lockedFieldsForPatch($activity, $regPolicy, $patch, true);
        $this->assertContains('title', $locked);
        $this->assertContains('confirmedHours', $locked);
    }

    public function editability_ongoing___only_contactInfo_location_joinUrl_editable(): void
    {
        $activity = ['status' => 'ongoing'];
        $regPolicy = [];
        $patch = ['contactInfo' => '0909', 'location' => 'Room 101', 'title' => 'Changed'];
        $locked = $this->policy->lockedFieldsForPatch($activity, $regPolicy, $patch, true);
        $this->assertNotContains('contactInfo', $locked);
        $this->assertNotContains('location', $locked);
        $this->assertContains('title', $locked);
    }

    public function editability_completed___whole_config_locked(): void
    {
        $activity = ['status' => 'completed'];
        $regPolicy = [];
        $patch = ['title' => 'Changed', 'confirmedHours' => 99];
        $locked = $this->policy->lockedFieldsForPatch($activity, $regPolicy, $patch, true);
        $this->assertContains('title', $locked);
    }

    // ─── Registration window ───────────────────────────────────────────────

    public function registrationWindow_open_when_now_in_opens_closes(): void
    {
        $this->clock->setNow('2026-08-27T10:00:00+00:00');
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $this->assertTrue($this->policy->registrationWindowIsOpen([], $regPolicy));
    }

    public function registrationWindow_closed_before_opens(): void
    {
        $this->clock->setNow('2026-08-24T10:00:00+00:00');
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $this->assertFalse($this->policy->registrationWindowIsOpen([], $regPolicy));
    }

    public function registrationWindow_closed_after_closes(): void
    {
        $this->clock->setNow('2026-08-29T10:00:00+00:00');
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $this->assertFalse($this->policy->registrationWindowIsOpen([], $regPolicy));
    }

    public function cancellationWindow_open_when_now___cancellationClosesAt(): void
    {
        $this->clock->setNow('2026-08-27T10:00:00+00:00');
        $regPolicy = [
            'cancellationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $this->assertTrue($this->policy->cancellationWindowIsOpen($regPolicy));
    }

    // ─── Catalog buckets ────────────────────────────────────────────────────

    public function catalogBucket_open___OPEN(): void
    {
        $this->clock->setNow('2026-08-27T10:00:00+00:00');
        $activity = ['status' => 'published'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $bucket = $this->policy->catalogBucket($activity, $regPolicy, null, 0, 10);
        $this->assertEquals('open', $bucket);
    }

    public function catalogBucket_full_capacity___FULL(): void
    {
        $this->clock->setNow('2026-08-27T10:00:00+00:00');
        $activity = ['status' => 'published'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $bucket = $this->policy->catalogBucket($activity, $regPolicy, null, 10, 10);
        $this->assertEquals('full', $bucket);
    }

    public function catalogBucket_before_opens___UPCOMING(): void
    {
        $this->clock->setNow('2026-08-20T10:00:00+00:00');
        $activity = ['status' => 'published'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $bucket = $this->policy->catalogBucket($activity, $regPolicy, null, 0, 10);
        $this->assertEquals('upcoming', $bucket);
    }

    public function catalogBucket_after_closes___CLOSED(): void
    {
        $this->clock->setNow('2026-08-30T10:00:00+00:00');
        $activity = ['status' => 'published'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
        ];
        $bucket = $this->policy->catalogBucket($activity, $regPolicy, null, 0, 10);
        $this->assertEquals('closed', $bucket);
    }

    public function catalogBucket_non_published_status___HIDDEN(): void
    {
        $activity = ['status' => 'draft'];
        $bucket = $this->policy->catalogBucket($activity, [], null, 0, null);
        $this->assertEquals('hidden', $bucket);
    }

    public function catalogBucket_enrolled_student___ENROLLED(): void
    {
        $registration = ['status' => 'approved'];
        $bucket = $this->policy->catalogBucket(['status' => 'published'], [], $registration, 5, 10);
        $this->assertEquals('enrolled', $bucket);
    }

    public function catalogBucket_rejected_student___REJECTED(): void
    {
        $registration = ['status' => 'rejected'];
        $bucket = $this->policy->catalogBucket(['status' => 'published'], [], $registration, 5, 10);
        $this->assertEquals('rejected', $bucket);
    }

    public function catalogBucket_cancelled_student___CANCELLED(): void
    {
        $registration = ['status' => 'cancelled'];
        $bucket = $this->policy->catalogBucket(['status' => 'published'], [], $registration, 0, 10);
        $this->assertEquals('cancelled', $bucket);
    }

    // ─── QR ───────────────────────────────────────────────────────────────

    public function qr_allowed_when_ongoing_and_now___endAt(): void
    {
        $this->clock->setNow('2026-08-27T10:00:00+00:00');
        $activity = ['status' => 'ongoing', 'endAt' => '2026-08-27T17:00:00+00:00'];
        $expiresAt = $this->policy->assertQrCanBeCreated($activity, 60);
        $this->assertEquals('2026-08-27T11:00:00+00:00', $expiresAt->format('c'));
    }

    public function qr_throws_when_not_ongoing(): void
    {
        $activity = ['status' => 'published'];
        $this->assertThrows(
            fn() => $this->policy->assertQrCanBeCreated($activity, 60),
            ErrorCodes::QR_SESSION_NOT_AVAILABLE,
        );
    }

    public function qr_throws_when_now___endAt(): void
    {
        $this->clock->setNow('2026-08-27T18:00:00+00:00');
        $activity = ['status' => 'ongoing', 'endAt' => '2026-08-27T17:00:00+00:00'];
        $this->assertThrows(
            fn() => $this->policy->assertQrCanBeCreated($activity, 60),
            ErrorCodes::QR_SESSION_NOT_AVAILABLE,
        );
    }

    public function qr_clamps_expiresAt_to_endAt_when_duration_overshoots(): void
    {
        $this->clock->setNow('2026-08-27T16:30:00+00:00');
        $activity = ['status' => 'ongoing', 'endAt' => '2026-08-27T17:00:00+00:00'];
        $expiresAt = $this->policy->assertQrCanBeCreated($activity, 120); // 120 min > 30 min left
        $this->assertEquals('2026-08-27T17:00:00+00:00', $expiresAt->format('c'));
    }

    // ─── Publish config ────────────────────────────────────────────────────

    public function publishConfig_ok_when_all_required_records_present(): void
    {
        $activity = [
            'startAt' => '2026-08-30T09:00:00+00:00',
            'endAt' => '2026-08-30T17:00:00+00:00',
            'deliveryMode' => 'in_person',
        ];
        $details = ['title' => 'Workshop'];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
            'cancellationClosesAt' => '2026-08-28T00:00:00+00:00',
            'approvalMode' => 'automatic',
        ];
        $expPolicy = ['confirmedHours' => 4];
        $missing = $this->policy->missingPublishConfiguration($activity, $details, $regPolicy, $expPolicy);
        $this->assertEquals([], $missing);
    }

    public function publishConfig_missing_details___reports_missing(): void
    {
        $missing = $this->policy->missingPublishConfiguration([], [], [], []);
        $this->assertContains('thông tin chi tiết', $missing);
    }

    public function publishConfig_time_ordering_violations___reports_missing(): void
    {
        $activity = [
            'startAt' => '2026-08-30T17:00:00+00:00',
            'endAt' => '2026-08-30T09:00:00+00:00', // end before start
            'deliveryMode' => 'in_person',
        ];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-29T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-30T00:00:00+00:00', // closes >= start
            'cancellationClosesAt' => '2026-08-30T00:00:00+00:00',
            'approvalMode' => 'automatic',
        ];
        $expPolicy = ['confirmedHours' => 4];
        $missing = $this->policy->missingPublishConfiguration($activity, ['title' => 'X'], $regPolicy, $expPolicy);
        $this->assertNotEquals([], $missing);
    }

    public function publishConfig_invalid_delivery_approval_mode___reports_missing(): void
    {
        $activity = [
            'startAt' => '2026-08-30T09:00:00+00:00',
            'endAt' => '2026-08-30T17:00:00+00:00',
            'deliveryMode' => 'invalid_mode',
        ];
        $regPolicy = [
            'registrationOpensAt' => '2026-08-25T00:00:00+00:00',
            'registrationClosesAt' => '2026-08-28T00:00:00+00:00',
            'cancellationClosesAt' => '2026-08-28T00:00:00+00:00',
            'approvalMode' => 'invalid',
        ];
        $expPolicy = ['confirmedHours' => 4];
        $missing = $this->policy->missingPublishConfiguration($activity, ['title' => 'X'], $regPolicy, $expPolicy);
        $this->assertContains('hình thức tổ chức', $missing);
        $this->assertContains('chính sách duyệt đăng ký', $missing);
    }
}
