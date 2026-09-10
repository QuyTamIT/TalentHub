<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

\TalentHub\Bootstrap\PortalGuard::requireRole(\TalentHub\Rbac\RoleCodes::TEACHER, '/app/teacher/grading.php');
// Retire the direct profile-score writer. Old GET and POST links open the scoped Rubric page.
header('Location: ' . app_href('/app/teacher/assessments/index.php'), true, 303);
exit;
