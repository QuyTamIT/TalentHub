<?php
declare(strict_types=1);
namespace TalentHub\Auth\Service;

final class AuthPortalRouter
{
    private const PORTALS=['student'=>'/app/learner/index.php','teacher'=>'/app/teacher/index.php','school'=>'/app/school/index.php','business'=>'/app/enterprise/index.php'];
    private const PREFIXES=['student'=>'/app/learner/','teacher'=>'/app/teacher/','school'=>'/app/school/','business'=>'/app/enterprise/'];

    public static function destination(string $role,?string $requested=null): string
    {
        $default=self::PORTALS[$role]??'/role-selection.php';
        if(!is_string($requested)||$requested===''||str_starts_with($requested,'//')){return $default;}
        $path=parse_url($requested,PHP_URL_PATH);if(!is_string($path)||!str_starts_with($path,'/')){return $default;}
        $prefix=self::PREFIXES[$role]??null;return $prefix!==null&&str_starts_with($path,$prefix)?$requested:$default;
    }
}
