<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fixture = $root . '/.codex_tmp/phase8-1-visual/fixture-prepend.php';
$capture = $root . '/.codex_tmp/phase8-1-visual/capture-phase8-1.js';
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(is_file($fixture), 'Disposable mock fixture exists.');
$assert(is_file($capture), 'Data-backed browser assertion script exists.');
if (is_file($fixture)) {
    require $fixture;
    $registrations = learner_activity_mock_registration_history('student-demo-001');
    $byStatus = [];
    foreach ($registrations as $row) $byStatus[(string) ($row['status'] ?? '')][] = $row;
    foreach (['approved', 'pending', 'waitlisted', 'attended', 'no_show'] as $status) {
        $assert(count($byStatus[$status] ?? []) === 1, "Fixture contains exactly one {$status} registration.");
    }
    $attended = ($byStatus['attended'] ?? [])[0] ?? [];
    $noShow = ($byStatus['no_show'] ?? [])[0] ?? [];
    $assert(($attended['checked_in_at'] ?? null) === '2026-08-25 09:00:00', 'Attended fixture exposes the scan time.');
    $assert(($attended['confirmed_at'] ?? null) === '2026-08-25 09:05:00', 'Attended fixture keeps a distinct confirmation time.');
    $assert((float) ($attended['experience_hours'] ?? 0) === 3.5, 'Attended fixture has confirmed experience hours.');
    $assert(($noShow['checked_in_at'] ?? null) === null && (float) ($noShow['experience_hours'] ?? -1) === 0.0, 'No-show fixture has no check-in and zero hours.');
}
if (is_file($capture)) {
    $source = (string) file_get_contents($capture);
    foreach (['approved', 'pending', 'waitlisted', 'attended', 'no_show', '09:00', '09:05', 'consoleErrors', 'failedRequests', 'noOverflow'] as $contract) {
        $assert(str_contains($source, $contract), "Browser smoke asserts {$contract}.");
    }
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_phase81_visual_fixture_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "learner_activity_phase81_visual_fixture_test: OK\n";
