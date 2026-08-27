<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/app/learner/my-activities.php');
$data = (string) file_get_contents($root . '/app/learner/includes/activity-data.php');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($data, 'function learner_activity_active_registrations'), 'A scoped active-registration helper exists.');
$assert(str_contains($data, 'registrationTimelineFor($studentId)'), 'Active registrations originate from the school-scoped registration timeline.');
$assert(str_contains($data, "['pending', 'approved', 'waitlisted']"), 'Only pending, approved, and waitlisted are classified as active.');
$assert(str_contains($page, 'learner_activity_active_registrations(learner_current_student_id())'), 'Page reads only current-student active registrations.');
$assert(!str_contains($page, 'learner_activity_catalog()'), 'Registered page does not expose the full discovery catalog.');
$assert(str_contains($page, 'hero-registered.svg'), 'Registered hero uses the approved local illustration.');
$assert(str_contains($page, 'Hoạt động đã đăng ký'), 'Registered page uses the approved title.');
$assert(str_contains($page, 'data-registered-kpi="total"'), 'Real total KPI is rendered.');
$assert(str_contains($page, 'data-registered-kpi="approved"'), 'Real approved KPI is rendered.');
$assert(str_contains($page, 'data-registered-kpi="pending"'), 'Real pending KPI is rendered.');
$assert(str_contains($page, "=== 'pending'"), 'Pending KPI counts only pending registrations.');
$assert(!str_contains($page, 'count($activeRegistrations) - $approvedCount'), 'Pending KPI never absorbs waitlisted registrations.');
$assert(str_contains($page, 'data-registration-search'), 'Registered page has search.');
$assert(str_contains($page, "'approved' => 'Đã duyệt'"), 'Registered page has approved filter.');
$assert(str_contains($page, "'pending' => 'Chờ duyệt'"), 'Registered page has pending filter.');
$assert(str_contains($page, 'checkin.php?activity='), 'Eligible approved cards link to the activity-scoped check-in route.');
$assert(str_contains($page, 'data-cancel-registration'), 'Cancelable records expose the existing cancellation command hook.');
$assert(str_contains($page, 'cancellation_closes_at'), 'Cancellation rendering observes the real policy window.');
$assert(str_contains($page, 'Chưa có hoạt động đang đăng ký'), 'Registered page has a real empty state.');
$assert(str_contains($page, 'learner_escape'), 'All server-owned text is escaped.');

foreach (['attended', 'no_show', 'cancelled', 'rejected'] as $excluded) {
    $assert(!str_contains($page, "data-status=\"{$excluded}\""), "Registered page never renders {$excluded} cards.");
}

if ($failures !== []) {
    fwrite(STDERR, "learner_registered_activities_page_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_registered_activities_page_test: OK\n";
