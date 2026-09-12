<?php
// tests/learner_project_ui_test.php
declare(strict_types=1);

$content = (string) file_get_contents(__DIR__ . '/../app/learner/project.php');
if (!str_contains($content, 'learner-btn--pending')) {
    fwrite(STDERR, "project.php must have pending button class or badge\n");
    exit(1);
}
if (!str_contains($content, 'Chờ Nhà trường duyệt')) {
    fwrite(STDERR, "project.php must render awaiting school review notice\n");
    exit(1);
}

$css = (string) file_get_contents(__DIR__ . '/../assets/css/learner.css');
if (!str_contains($css, '.learner-btn--pending')) {
    fwrite(STDERR, "learner.css must have styles for .learner-btn--pending\n");
    exit(1);
}

echo "Task 5 learner project UI test: PASS\n";
