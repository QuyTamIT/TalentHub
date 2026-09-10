<?php
declare(strict_types=1);

namespace TalentHub\Tests\Policy;

use Tests\TestCase;
use TalentHub\Domain\Profile\ProfilePolicy;
use TalentHub\Domain\ErrorCodes;

class ProfilePolicyTest
{
    use TestCaseTrait;

    private ProfilePolicy $policy;

    public function __construct()
    {
        $this->policy = new ProfilePolicy();
    }

    public function name(): string
    {
        return 'ProfilePolicy';
    }

    public function tests(): array
    {
        return [
            'student: fullName valid length passes',
            'student: fullName too short -> rejected',
            'student: dateOfBirth future -> rejected',
            'student: unknown field stripped',
            'student: phone max length enforced',
            'teacher: specialization max length enforced',
            'school: email pattern enforced',
            'enterprise: foundedYear range enforced',
            'enterprise: unknown field stripped',
            'enterprise: empty email allowed (nullable)',
            'sanitize: all valid passes',
            'sanitize: mixed valid + invalid returns clean',
        ];
    }

    // ─── Student ───────────────────────────────────────────────────────────

    public function student_fullName_valid_length_passes(): void
    {
        $clean = $this->policy->sanitize('student', ['fullName' => 'Nguyen Van A']);
        $this->assertEquals('Nguyen Van A', $clean['fullName']);
    }

    public function student_fullName_too_short___rejected(): void
    {
        $this->assertThrows(
            fn() => $this->policy->sanitize('student', ['fullName' => 'X']),
            ErrorCodes::VALIDATION_FAILED,
        );
    }

    public function student_dateOfBirth_future___rejected(): void
    {
        $this->assertThrows(
            fn() => $this->policy->sanitize('student', ['dateOfBirth' => '2099-01-01']),
            ErrorCodes::VALIDATION_FAILED,
        );
    }

    public function student_unknown_field_stripped(): void
    {
        $clean = $this->policy->sanitize('student', [
            'fullName' => 'Test',
            'secretField' => 'hack',
        ]);
        $this->assertArrayNotHasKey('secretField', $clean);
    }

    public function student_phone_max_length_enforced(): void
    {
        $this->assertThrows(
            fn() => $this->policy->sanitize('student', ['phone' => str_repeat('1', 31)]),
            ErrorCodes::VALIDATION_FAILED,
        );
    }

    // ─── Teacher ───────────────────────────────────────────────────────────

    public function teacher_specialization_max_length_enforced(): void
    {
        $this->assertThrows(
            fn() => $this->policy->sanitize('teacher', ['specialization' => str_repeat('x', 256)]),
            ErrorCodes::VALIDATION_FAILED,
        );
    }

    // ─── School ────────────────────────────────────────────────────────────

    public function school_email_pattern_enforced(): void
    {
        $this->assertThrows(
            fn() => $this->policy->sanitize('school', ['email' => 'not-an-email']),
            ErrorCodes::VALIDATION_FAILED,
        );
    }

    // ─── Enterprise ────────────────────────────────────────────────────────

    public function enterprise_foundedYear_range_enforced(): void
    {
        $this->assertThrows(
            fn() => $this->policy->sanitize('enterprise', ['foundedYear' => 1799]),
            ErrorCodes::VALIDATION_FAILED,
        );
        $this->assertThrows(
            fn() => $this->policy->sanitize('enterprise', ['foundedYear' => 2101]),
            ErrorCodes::VALIDATION_FAILED,
        );
    }

    public function enterprise_unknown_field_stripped(): void
    {
        $clean = $this->policy->sanitize('enterprise', [
            'name' => 'Acme Corp',
            'fakeField' => 'xxx',
        ]);
        $this->assertArrayNotHasKey('fakeField', $clean);
    }

    public function enterprise_empty_email_allowed_nullable(): void
    {
        $clean = $this->policy->sanitize('enterprise', ['email' => '']);
        $this->assertArrayNotHasKey('email', $clean); // null/empty stripped
    }

    // ─── Sanitize ──────────────────────────────────────────────────────────

    public function sanitize_all_valid_passes(): void
    {
        $clean = $this->policy->sanitize('student', [
            'fullName' => 'Tran Thi B',
            'phone' => '0909123456',
            'bio' => 'Student at BTEC.',
        ]);
        $this->assertEquals('Tran Thi B', $clean['fullName']);
        $this->assertEquals('0909123456', $clean['phone']);
    }

    public function sanitize_mixed_valid___invalid_returns_clean(): void
    {
        // Pass an invalid value (too long phone) + valid name; unknown fields are stripped silently.
        try {
            $this->policy->sanitize('student', [
                'fullName' => 'Valid Name',
                'phone' => str_repeat('1', 31), // too long
            ]);
            $this->fail('Expected VALIDATION_FAILED');
        } catch (\TalentHub\Http\ApiException $e) {
            $this->assertEquals(ErrorCodes::VALIDATION_FAILED, $e->errorCode);
            $details = $e->details;
            $this->assertArrayHasKey('fields', $details);
        }
    }
}
