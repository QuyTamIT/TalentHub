<?php
declare(strict_types=1);
namespace TalentHub\Auth\Service;

use TalentHub\Rbac\RoleCodes;

final class AuthPortalRouter
{
    public static function destination(string $role, ?string $requested = null): string
    {
        return \TalentHub\Bootstrap\AuthPortalRouter::destination($role, $requested);
    }

    public static function getDashboardUrl(string $role): string
    {
        return \TalentHub\Bootstrap\AuthPortalRouter::getDashboardUrl($role);
    }

    public static function getLoginUrl(): string
    {
        return \TalentHub\Bootstrap\AuthPortalRouter::getLoginUrl();
    }

    public static function redirectToDashboard(string $role): never
    {
        \TalentHub\Bootstrap\AuthPortalRouter::redirectToDashboard($role);
    }

    public static function redirect(string $path): never
    {
        \TalentHub\Bootstrap\AuthPortalRouter::redirect($path);
    }
}
