<?php
// tests/school_project_ui_test.php
declare(strict_types=1);

$content = (string) file_get_contents(__DIR__ . '/../app/school/projects.php');
if (!str_contains($content, 'manageMembersModal')) {
    fwrite(STDERR, "projects.php must contain member management modal\n");
    exit(1);
}
if (!str_contains($content, 'approve_member')) {
    fwrite(STDERR, "projects.php must contain approve_member action\n");
    exit(1);
}
if (!str_contains($content, 'reject_member')) {
    fwrite(STDERR, "projects.php must contain reject_member action\n");
    exit(1);
}

echo "Task 6 school project UI test: PASS\n";
