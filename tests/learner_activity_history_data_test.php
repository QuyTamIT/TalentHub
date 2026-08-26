<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$data = (string) file_get_contents($root . '/app/learner/includes/activity-data.php');
$repo = (string) file_get_contents($root . '/app/learner/data/Database/DatabaseActivityRepository.php');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($data, 'function learner_activity_attendance_history'), 'Attendance history has a dedicated scoped helper.');
$assert(str_contains($data, 'registrationTimelineFor($studentId)'), 'History starts from the school-scoped student timeline.');
$assert(str_contains($data, "['attended', 'no_show']"), 'History accepts only attended and no_show.');
$assert(str_contains($data, "\$registration['experience_hours'] = 0.0"), 'No-show is normalized to zero confirmed hours.');
$assert(str_contains($data, "\$registration['checked_in_at'] = null"), 'No-show is normalized to no confirmed check-in.');
$assert(str_contains($repo, "WHERE status='confirmed' AND confirmedAt IS NOT NULL GROUP BY checkinId"), 'Experience hours originate only from confirmed logs grouped per check-in.');
$assert(str_contains($repo, 'MAX(checkedInAt) AS checkedInAt'), 'Timeline projects the student scan timestamp.');
$assert(!str_contains($repo, 'MAX(confirmedAt) AS checkedInAt'), 'Timeline never aliases the confirmation timestamp as the scan timestamp.');
$assert(str_contains($repo, "status='confirmed' AND confirmedAt IS NOT NULL AND checkedInAt IS NOT NULL GROUP BY registrationId"), 'Check-in is confirmed, has a scan timestamp, and is grouped per registration.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_history_data_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "learner_activity_history_data_test: OK\n";
