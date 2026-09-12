<?php
// tests/teacher_decoupling_verification_test.php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../app/teacher/portfolio-reviews.php')) {
    fwrite(STDERR, "portfolio-reviews.php must be deleted\n");
    exit(1);
}
if (file_exists(__DIR__ . '/../app/teacher/api/portfolio.php')) {
    fwrite(STDERR, "api/portfolio.php must be deleted\n");
    exit(1);
}

$sidebar = (string) file_get_contents(__DIR__ . '/../app/teacher/includes/sidebar.php');
if (str_contains($sidebar, 'portfolio-reviews.php')) {
    fwrite(STDERR, "sidebar.php must not link to portfolio-reviews\n");
    exit(1);
}

$grading = (string) file_get_contents(__DIR__ . '/../app/teacher/grading.php');
if (str_contains($grading, '>>>>>>> origin/main')) {
    fwrite(STDERR, "grading.php must not contain conflict marker\n");
    exit(1);
}

$learnerProject = (string) file_get_contents(__DIR__ . '/../app/learner/project.php');
if (str_contains($learnerProject, 'portfolio-panel.php')) {
    fwrite(STDERR, "learner project.php must not include portfolio-panel.php\n");
    exit(1);
}

echo "Task 4 teacher decoupling test: PASS\n";
