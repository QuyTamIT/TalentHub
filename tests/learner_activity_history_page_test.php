<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__) . '/app/learner/activity-history.php');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($source, 'learner_activity_attendance_history(learner_current_student_id())'), 'History reads attendance-only records through the scoped helper.');
$assert(str_contains($source, 'hero-history.svg'), 'History uses the approved local hero illustration.');
$assert(str_contains($source, 'data-history-kpi="attended"'), 'History renders the attended KPI from real data.');
$assert(str_contains($source, 'data-history-kpi="no-show"'), 'History renders the no-show KPI from real data.');
$assert(str_contains($source, 'data-history-kpi="hours"'), 'History renders confirmed experience hours.');
$assert(str_contains($source, 'data-history-kpi="month"'), 'History renders the current-month KPI.');
$assert(str_contains($source, "'no_show' => 'Không tham gia'"), 'History exposes the no-show filter.');
$assert(str_contains($source, 'data-history-period'), 'History exposes a period selector.');
$assert(str_contains($source, 'Tổng quan hoạt động'), 'History renders its data-backed summary.');
$assert(str_contains($source, 'Không có check-in'), 'No-show records explain the absent check-in.');
$assert(str_contains($source, 'Không tham gia'), 'No-show records use the canonical Vietnamese label.');
$assert(str_contains($source, 'attendance_resolved_at'), 'No-show records expose the real resolution timestamp when present.');
$assert(str_contains($source, 'Chưa có lịch sử hoạt động'), 'History has a real empty state.');
$assert(str_contains($source, 'learner_escape'), 'History escapes server-owned values.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_history_page_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "learner_activity_history_page_test: OK\n";
