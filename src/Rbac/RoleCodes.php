<?php
declare(strict_types=1);

namespace TalentHub\Rbac;

final class RoleCodes
{
    public const STUDENT = 'student';
    public const TEACHER = 'teacher';
    public const SCHOOL = 'school';
    public const ENTERPRISE = 'enterprise';
    public const PLATFORM_ADMIN = 'platform_admin';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::STUDENT, self::TEACHER, self::SCHOOL, self::ENTERPRISE, self::PLATFORM_ADMIN];
    }

    public static function canonical(string $role): string
    {
        $role = strtolower(trim($role));
        return $role === 'business' ? self::ENTERPRISE : $role;
    }

    public static function matches(string $actual, string $expected): bool
    {
        return self::canonical($actual) === self::canonical($expected);
    }
}
