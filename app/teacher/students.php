<?php
/**
 * TalentHub - Teacher Portal: Students Forwarder
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

header('Location: ' . app_href('/app/teacher/students/index.php'));
exit;
