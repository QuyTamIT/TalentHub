<?php
declare(strict_types=1);

namespace TalentHub\Domain\Profile;

use TalentHub\Domain\ErrorCodes;
use TalentHub\Domain\PolicyViolation;
use TalentHub\Http\ApiException;

/**
 * Whitelist + per-field validation rules for profile updates across roles.
 *
 * Replaces the duplicated `ALLOWED_FIELDS` constants and validation blocks
 * previously living in:
 *   - StudentProfileService
 *   - TeacherProfileService
 *   - SchoolDashboardService (school profile)
 *   - BusinessProfileService (enterprise profile)
 *
 * Caller resolves the actor's role from the session and asks the policy
 * which fields are editable plus length/range constraints.
 */
final class ProfilePolicy
{
    public const ROLE_STUDENT    = 'student';
    public const ROLE_TEACHER    = 'teacher';
    public const ROLE_SCHOOL     = 'school';
    public const ROLE_ENTERPRISE = 'enterprise';

    /**
     * @return array<string, array<string, mixed>>  field => constraints
     *                                               (maxLength?, minLength?,
     *                                                allowed? , required?,
     *                                                pattern?, notFuture?)
     */
    public function editableFieldsFor(string $role): array
    {
        return match ($role) {
            self::ROLE_STUDENT => [
                'fullName'     => ['minLength' => 2, 'maxLength' => 255, 'required' => false],
                'dateOfBirth'  => ['format' => 'Y-m-d', 'notFuture' => true],
                'phone'        => ['maxLength' => 30],
                'location'     => ['maxLength' => 255],
                'bio'          => ['maxLength' => 1000],
                'avatarUrl'    => ['maxLength' => 1024],
                'headline'     => ['maxLength' => 255],
            ],
            self::ROLE_TEACHER => [
                'fullName'       => ['minLength' => 2, 'maxLength' => 150],
                'phone'          => ['maxLength' => 30],
                'specialization' => ['maxLength' => 255],
                'bio'            => ['maxLength' => 1000],
            ],
            self::ROLE_SCHOOL => [
                'name'         => ['minLength' => 2, 'maxLength' => 255],
                'logoUrl'      => ['maxLength' => 1024],
                'address'      => ['maxLength' => 500],
                'phone'        => ['maxLength' => 30],
                'email'        => ['pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/', 'maxLength' => 255],
                'website'      => ['maxLength' => 1024],
                'level'        => ['maxLength' => 100],
                'academicYear' => ['maxLength' => 50],
            ],
            self::ROLE_ENTERPRISE => [
                'name'         => ['minLength' => 2, 'maxLength' => 255],
                'logoUrl'      => ['maxLength' => 1024],
                'industry'     => ['maxLength' => 100],
                'companySize'  => ['maxLength' => 50],
                'foundedYear'  => ['type' => 'int', 'min' => 1800, 'max' => 2100],
                'taxCode'      => ['maxLength' => 50],
                'description'  => ['maxLength' => 2000],
                'email'        => ['pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/', 'maxLength' => 255],
                'phone'        => ['maxLength' => 30],
                'website'      => ['maxLength' => 1024],
                'address'      => ['maxLength' => 500],
            ],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed> filtered patch (only whitelisted fields)
     * @throws ApiException VALIDATION_FAILED
     */
    public function sanitize(string $role, array $patch): array
    {
        $whitelist = $this->editableFieldsFor($role);
        $clean = [];
        $errors = [];
        foreach ($patch as $field => $value) {
            if (!isset($whitelist[$field])) {
                continue; // Silently strip unknown fields.
            }
            if ($value === null || $value === '') {
                continue; // Silently strip null/empty nullable fields.
            }
            $rules = $whitelist[$field];
            $valueError = $this->validateField($field, $value, $rules);
            if ($valueError !== null) {
                $errors[] = ['field' => $field, 'code' => $valueError['code'], 'message' => $valueError['message']];
                continue;
            }
            $clean[$field] = $value;
        }
        if ($errors !== []) {
            throw PolicyViolation::validation('Một số trường hồ sơ không hợp lệ.', ['fields' => $errors]);
        }
        return $clean;
    }

    /**
     * @param array<string, mixed> $rules
     * @return array{code:string,message:string}|null
     */
    private function validateField(string $field, mixed $value, array $rules): ?array
    {
        if ($value === null || $value === '') {
            return null; // Allow nullable fields to be cleared.
        }

        if (isset($rules['type']) && $rules['type'] === 'int') {
            if (!is_int($value) && !ctype_digit((string) $value)) {
                return ['code' => 'INVALID_TYPE', 'message' => sprintf('Trường "%s" phải là số nguyên.', $field)];
            }
            $intVal = (int) $value;
            if (isset($rules['min']) && $intVal < (int) $rules['min']) {
                return ['code' => 'OUT_OF_RANGE', 'message' => sprintf('Trường "%s" tối thiểu %d.', $field, (int) $rules['min'])];
            }
            if (isset($rules['max']) && $intVal > (int) $rules['max']) {
                return ['code' => 'OUT_OF_RANGE', 'message' => sprintf('Trường "%s" tối đa %d.', $field, (int) $rules['max'])];
            }
        }

        $stringValue = is_scalar($value) ? (string) $value : '';
        if (isset($rules['minLength']) && mb_strlen($stringValue) < (int) $rules['minLength']) {
            return ['code' => 'TOO_SHORT', 'message' => sprintf('Trường "%s" quá ngắn.', $field)];
        }
        if (isset($rules['maxLength']) && mb_strlen($stringValue) > (int) $rules['maxLength']) {
            return ['code' => 'TOO_LONG', 'message' => sprintf('Trường "%s" quá dài.', $field)];
        }
        if (isset($rules['pattern']) && preg_match($rules['pattern'], $stringValue) !== 1) {
            return ['code' => 'PATTERN_MISMATCH', 'message' => sprintf('Trường "%s" không đúng định dạng.', $field)];
        }
        if (isset($rules['format']) && $rules['format'] === 'Y-m-d') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $stringValue);
            if ($dt === false || $dt->format('Y-m-d') !== $stringValue) {
                return ['code' => 'INVALID_DATE', 'message' => sprintf('Trường "%s" phải theo định dạng YYYY-MM-DD.', $field)];
            }
            if (!empty($rules['notFuture']) && $dt > new \DateTimeImmutable('today')) {
                return ['code' => 'FUTURE_DATE', 'message' => sprintf('Trường "%s" không được là ngày tương lai.', $field)];
            }
        }
        return null;
    }
}
