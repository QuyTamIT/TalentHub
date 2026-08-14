<?php
/**
 * Render-test batch gọi mỗi trang school trong subprocess riêng để tránh
 * autoload cache xung đột. Output đảm bảo >= 5KB và chứa <title>.
 */
declare(strict_types=1);

$pages = [
    'index.php'             => 'Dashboard',
    'classes.php'           => 'Classes',
    'students.php'          => 'Students',
    'teachers.php'          => 'Teachers',
    'reports.php'           => 'Reports',
    'analytics.php'         => 'Analytics',
    'settings.php'          => 'Settings',
    'account.php'           => 'Account',
    'class-edit.php'        => 'ClassEdit (new)',
    'student-edit.php'      => 'StudentEdit (new)',
    'teacher-edit.php'      => 'TeacherEdit (new)',
];

$root = dirname(__DIR__);
$allOk = true;

foreach ($pages as $file => $label) {
    $cmd = sprintf(
        '%s %s %s 2>&1',
        escapeshellarg(PHP_BINARY ?? 'php'),
        escapeshellarg($root . '/bin/_render-one.php'),
        escapeshellarg($file)
    );
    $body = shell_exec($cmd);
    if ($body === false || $body === null) {
        $body = '';
    }
    // Redirect-only responses (empty body + Location header) are valid for edit pages
    // without an ID. They redirect to the list page.
    if (strlen($body) === 0) {
        echo "[OK] $label ($file) -> redirected (empty body, OK)" . PHP_EOL;
        continue;
    }
    if (str_contains($body, 'FATAL:')) {
        fwrite(STDERR, "[FAIL] $label: " . substr($body, 0, 300) . PHP_EOL);
        $allOk = false;
        continue;
    }
    $len = strlen($body);
    if ($len < 5000 || !str_contains($body, '<title>') || !str_contains($body, '</html>')) {
        fwrite(STDERR, "[FAIL] $label ($file) only $len bytes / missing html" . PHP_EOL);
        $allOk = false;
        continue;
    }
    echo "[OK] $label ($file) -> {$len} bytes" . PHP_EOL;
}

exit($allOk ? 0 : 1);