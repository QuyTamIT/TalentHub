<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL: {$message}\n");
        return;
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function render_page(string $path): string
{
    ob_start();
    require $path;
    return (string) ob_get_clean();
}

$dataSource = $root . '/app/learner/includes/student-data.php';
$sidebarSource = $root . '/app/learner/includes/sidebar.php';
$headerSource = $root . '/app/learner/includes/header.php';

check(file_exists($dataSource), 'Learner data provider exists');
check(file_exists($sidebarSource), 'Shared Learner sidebar exists');
check(file_exists($headerSource), 'Shared Learner header exists');

if (file_exists($dataSource)) {
    require $dataSource;

    check(learner_escape('<script>') === '&lt;script&gt;', 'Dynamic learner data is HTML escaped');
    check(count($learnerNav) === 9, 'Sidebar data contains nine navigation items');
    check(($student['name'] ?? '') === 'Nguyễn Văn A', 'Student mock data exposes the approved learner');
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d learner frontend assertion(s) failed.\n", count($failures)));
    exit(1);
}

fwrite(STDOUT, "\nAll learner frontend assertions passed.\n");
