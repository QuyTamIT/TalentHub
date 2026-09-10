<?php
declare(strict_types=1);

namespace TalentHub\Tests\Policy;

use Tests\TestCase;
use TalentHub\Domain\Internship\InternshipPolicy;
use TalentHub\Domain\ErrorCodes;

class InternshipPolicyTest
{
    use TestCaseTrait;

    private InternshipPolicy $policy;

    public function __construct()
    {
        $this->policy = new InternshipPolicy();
    }

    public function name(): string
    {
        return 'InternshipPolicy';
    }

    public function tests(): array
    {
        return [
            'submitted -> reviewing allowed',
            'submitted -> declined allowed',
            'submitted -> accepted NOT allowed',
            'submitted -> interview NOT allowed',
            'reviewing -> interview allowed',
            'reviewing -> accepted allowed',
            'reviewing -> declined allowed',
            'interview -> accepted allowed',
            'interview -> declined allowed',
            'accepted is terminal',
            'declined is terminal',
            'placement lock: other accepted -> throws INTERNSHIP_PLACEMENT_LOCKED',
            'placement lock: no other accepted -> allowed',
            'placement lock: other accepted different student -> allowed',
        ];
    }

    // ─── State transitions ─────────────────────────────────────────────────

    public function submitted___reviewing_allowed(): void
    {
        $this->policy->assertCanTransition('submitted', 'reviewing');
        $this->assertTrue(true);
    }

    public function submitted___declined_allowed(): void
    {
        $this->policy->assertCanTransition('submitted', 'declined');
        $this->assertTrue(true);
    }

    public function submitted___accepted_NOT_allowed(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanTransition('submitted', 'accepted'),
            ErrorCodes::INTERNSHIP_INVALID_TRANSITION,
        );
    }

    public function submitted___interview_NOT_allowed(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanTransition('submitted', 'interview'),
            ErrorCodes::INTERNSHIP_INVALID_TRANSITION,
        );
    }

    public function reviewing___interview_allowed(): void
    {
        $this->policy->assertCanTransition('reviewing', 'interview');
        $this->assertTrue(true);
    }

    public function reviewing___accepted_allowed(): void
    {
        $this->policy->assertCanTransition('reviewing', 'accepted');
        $this->assertTrue(true);
    }

    public function reviewing___declined_allowed(): void
    {
        $this->policy->assertCanTransition('reviewing', 'declined');
        $this->assertTrue(true);
    }

    public function interview___accepted_allowed(): void
    {
        $this->policy->assertCanTransition('interview', 'accepted');
        $this->assertTrue(true);
    }

    public function interview___declined_allowed(): void
    {
        $this->policy->assertCanTransition('interview', 'declined');
        $this->assertTrue(true);
    }

    public function accepted_is_terminal(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanTransition('accepted', 'reviewing'),
            ErrorCodes::INTERNSHIP_INVALID_TRANSITION,
        );
    }

    public function declined_is_terminal(): void
    {
        $this->assertThrows(
            fn() => $this->policy->assertCanTransition('declined', 'reviewing'),
            ErrorCodes::INTERNSHIP_INVALID_TRANSITION,
        );
    }

    // ─── Placement lock ────────────────────────────────────────────────────

    public function placement_lock_other_accepted___throws(): void
    {
        $others = [
            ['studentId' => 'student-A', 'status' => 'accepted'],
        ];
        $this->assertThrows(
            fn() => $this->policy->assertPlacementAvailable($others, 'student-A'),
            ErrorCodes::INTERNSHIP_PLACEMENT_LOCKED,
        );
    }

    public function placement_lock_no_other_accepted___allowed(): void
    {
        $others = [
            ['studentId' => 'student-A', 'status' => 'reviewing'],
            ['studentId' => 'student-A', 'status' => 'declined'],
        ];
        $this->policy->assertPlacementAvailable($others, 'student-A');
        $this->assertTrue(true);
    }

    public function placement_lock_other_accepted_different_student___allowed(): void
    {
        $others = [
            ['studentId' => 'student-B', 'status' => 'accepted'],
        ];
        $this->policy->assertPlacementAvailable($others, 'student-A');
        $this->assertTrue(true);
    }
}
