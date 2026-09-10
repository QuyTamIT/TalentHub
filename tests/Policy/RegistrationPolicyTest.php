<?php
declare(strict_types=1);

namespace TalentHub\Tests\Policy;

use Tests\TestCase;
use TalentHub\Domain\Activity\ActivityPolicy;
use TalentHub\Domain\Activity\RegistrationPolicy;
use TalentHub\Domain\ErrorCodes;
use TalentHub\Support\Clock\FixedClock;

class RegistrationPolicyTest
{
    use TestCaseTrait;

    private RegistrationPolicy $policy;

    public function __construct()
    {
        $this->policy = new RegistrationPolicy();
    }

    public function name(): string
    {
        return 'RegistrationPolicy';
    }

    public function tests(): array
    {
        return [
            'teacher transition: pending -> approved',
            'teacher transition: pending -> rejected',
            'teacher transition: waitlisted -> approved (promotion)',
            'teacher transition: non-pending throws REGISTRATION_NOT_PENDING',
            'teacher transition: reject action on waitlisted throws',
            'student cancel: pending + window open -> cancelled',
            'student cancel: approved + window open -> cancelled',
            'student cancel: after window closes throws',
            'student cancel: non-cancellable status throws',
            'register: no prior -> allowed when window open',
            'register: active registration exists -> throws',
            'register: rejected exists -> throws REGISTRATION_REJECTED',
            'register: cancelled + window open -> allowed (re-register)',
            'register: no_show + window open -> allowed (re-register)',
            'register: window closed -> throws REGISTRATION_WINDOW_NOT_OPEN',
            'register: at capacity + auto approval -> throws CAPACITY_REACHED',
            'register: at capacity + teacher approval -> WAITLISTED',
            'grading: attended + ongoing -> can save draft',
            'grading: not attended -> throws ASSESSMENT_NOT_ATTENDED',
            'grading: published before completed -> throws ASSESSMENT_NOT_COMPLETED',
        ];
    }

    // ─── Teacher transition ─────────────────────────────────────────────────

    public function teacher_transition_pending___approved(): void
    {
        $reg = ['status' => 'pending'];
        $next = $this->policy->assertCanTeacherTransition($reg, 'approve');
        $this->assertEquals('approved', $next);
    }

    public function teacher_transition_pending___rejected(): void
    {
        $reg = ['status' => 'pending'];
        $next = $this->policy->assertCanTeacherTransition($reg, 'reject');
        $this->assertEquals('rejected', $next);
    }

    public function teacher_transition_waitlisted___approved_promotion(): void
    {
        $reg = ['status' => 'waitlisted'];
        $next = $this->policy->assertCanTeacherTransition($reg, 'approve');
        $this->assertEquals('approved', $next);
    }

    public function teacher_transition_non_pending_throws_REGISTRATION_NOT_PENDING(): void
    {
        $reg = ['status' => 'approved'];
        $this->assertThrows(
            fn() => $this->policy->assertCanTeacherTransition($reg, 'approve'),
            ErrorCodes::REGISTRATION_NOT_PENDING,
        );
    }

    public function teacher_transition_reject_action_on_waitlisted_throws(): void
    {
        $reg = ['status' => 'waitlisted'];
        $this->assertThrows(
            fn() => $this->policy->assertCanTeacherTransition($reg, 'reject'),
            ErrorCodes::REGISTRATION_NOT_PENDING,
        );
    }

    // ─── Student cancel ───────────────────────────────────────────────────

    public function student_cancel_pending___window_open___cancelled(): void
    {
        $reg = ['status' => 'pending'];
        $next = $this->policy->assertCanStudentCancel($reg, true);
        $this->assertEquals('cancelled', $next);
    }

    public function student_cancel_approved___window_open___cancelled(): void
    {
        $reg = ['status' => 'approved'];
        $next = $this->policy->assertCanStudentCancel($reg, true);
        $this->assertEquals('cancelled', $next);
    }

    public function student_cancel_after_window_closes_throws(): void
    {
        $reg = ['status' => 'pending'];
        $this->assertThrows(
            fn() => $this->policy->assertCanStudentCancel($reg, false),
            ErrorCodes::REGISTRATION_CANCEL_WINDOW_CLOSED,
        );
    }

    public function student_cancel_non_cancellable_status_throws(): void
    {
        $reg = ['status' => 'attended'];
        $this->assertThrows(
            fn() => $this->policy->assertCanStudentCancel($reg, true),
            ErrorCodes::REGISTRATION_NOT_PENDING,
        );
    }

    // ─── Register ─────────────────────────────────────────────────────────

    public function register_no_prior___allowed_when_window_open(): void
    {
        $state = $this->policy->assertCanRegister(null, true, false, false);
        $this->assertEquals('pending', $state);
    }

    public function register_active_registration_exists___throws(): void
    {
        $prev = [['status' => 'pending']];
        $this->assertThrows(
            fn() => $this->policy->assertCanRegister($prev, true, false, false),
            ErrorCodes::REGISTRATION_ALREADY_EXISTS,
        );
    }

    public function register_rejected_exists___throws_REGISTRATION_REJECTED(): void
    {
        $prev = [['status' => 'rejected']];
        $this->assertThrows(
            fn() => $this->policy->assertCanRegister($prev, true, false, false),
            ErrorCodes::REGISTRATION_REJECTED,
        );
    }

    public function register_cancelled___window_open___allowed_re_register(): void
    {
        $prev = [['status' => 'cancelled']];
        $state = $this->policy->assertCanRegister($prev, true, false, false);
        $this->assertEquals('pending', $state);
    }

    public function register_no_show___window_open___allowed_re_register(): void
    {
        $prev = [['status' => 'no_show']];
        $state = $this->policy->assertCanRegister($prev, true, false, false);
        $this->assertEquals('pending', $state);
    }

    public function register_window_closed___throws(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanRegister(null, false, false, false),
            ErrorCodes::REGISTRATION_WINDOW_NOT_OPEN,
        );
    }

    public function register_at_capacity___auto_approval___throws(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanRegister(null, true, true, true),
            ErrorCodes::CAPACITY_REACHED,
        );
    }

    public function register_at_capacity___teacher_approval___WAITLISTED(): void
    {
        $state = $this->policy->assertCanRegister(null, true, true, false);
        $this->assertEquals('waitlisted', $state);
    }

    // ─── Grading ───────────────────────────────────────────────────────────

    public function grading_attended___ongoing___can_save_draft(): void
    {
        $this->policy->assertCanSaveGradeDraft('ongoing', 'attended');
        $this->assertTrue(true);
    }

    public function grading_not_attended___throws(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanSaveGradeDraft('ongoing', 'pending'),
            ErrorCodes::ASSESSMENT_NOT_ATTENDED,
        );
    }

    public function grading_published_before_completed___throws(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanPublishGrade('published', 'attended'),
            ErrorCodes::ASSESSMENT_NOT_COMPLETED,
        );
    }
}
