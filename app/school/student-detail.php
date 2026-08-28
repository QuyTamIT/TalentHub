<?php
/**
 * TalentHub - School Portal: Student Detail Forwarder
 *
 * Redirects to students.php with view_id parameter to automatically
 * display the full student detail modal, or fallback to students list.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

$studentId = $_GET['id'] ?? $_GET['studentId'] ?? '';

if (!empty($studentId)) {
    header('Location: ' . app_href('/app/school/students.php?view_id=' . urlencode((string) $studentId)));
    exit;
}

header('Location: ' . app_href('/app/school/students.php'));
exit;
