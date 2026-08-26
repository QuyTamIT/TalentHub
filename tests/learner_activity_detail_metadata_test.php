<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\ReadModel\ActivityReadModel;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$activityId = '11111111-1111-4111-8111-111111111111';
$studentId = '22222222-2222-4222-8222-222222222222';
$record = [
    'id' => $activityId,
    'school_id' => '33333333-3333-4333-8333-333333333333',
    'title' => 'Workshop an toàn <script>alert(1)</script>',
    'category' => 'career_technical',
    'display_category' => 'Kỹ thuật',
    'filter_category' => 'Kỹ thuật',
    'summary' => 'Tóm tắt hoạt động',
    'description' => 'Mô tả đầy đủ',
    'start_at' => '2026-09-15 09:00:00',
    'end_at' => '2026-09-15 17:00:00',
    'status' => 'published',
    'participants' => 18,
    'capacity' => 25,
    'remaining' => 7,
    'school_name' => 'TalentHub Test School',
    'responsible_teacher_name' => 'Giáo viên phụ trách',
    'experience_highlights' => ['Thực hành dự án', 'Học hỏi và kết nối'],
    'skills' => ['Lập trình Python', 'Làm việc nhóm'],
    'requirements' => ['Mang theo laptop'],
    'benefits' => ['3 giờ trải nghiệm'],
    'location_name' => 'Phòng Lab B305',
    'location_address' => 'Khu học tập chính',
    'delivery_mode' => 'in_person',
    'online_meeting_url' => 'https://meet.example.edu/activity',
    'organizer_name' => 'CLB Công nghệ TalentHub',
    'organizer_contact' => 'Liên hệ đơn vị tổ chức',
    'organizer_email' => 'club@example.edu',
    'organizer_phone' => '(028) 1234 5678',
    'cover_image_url' => '/app/learner/assets/activities/covers/talenthub-python-workshop.webp',
    'cover_image_alt' => 'Sinh viên học lập trình Python trong phòng lab',
    'fee_amount' => 0,
    'currency' => 'VND',
    'target_audience' => 'Học sinh, sinh viên của trường',
    'certificate_label' => 'Giấy chứng nhận tham gia',
    'confirmed_hours' => 3.0,
    'approval_mode' => 'automatic',
    'registration_opens_at' => '2026-08-01 00:00:00',
    'registration_closes_at' => '2026-09-12 00:00:00',
    'cancellation_closes_at' => '2026-09-13 00:00:00',
];

$repository = new class($studentId, $activityId, $record) implements ActivityRepository {
    public int $unscopedCalls = 0;
    public int $scopedCalls = 0;

    public function __construct(
        private readonly string $allowedStudentId,
        private readonly string $allowedActivityId,
        private readonly array $record,
    ) {}

    public function all(): array { $this->unscopedCalls++; return []; }
    public function findById(string $activityId): ?array { $this->unscopedCalls++; return null; }
    public function registrationsFor(string $studentId): array { return []; }
    public function discoverForStudent(string $studentId, DateTimeImmutable $now): array { return []; }
    public function registrationTimelineFor(string $studentId): array { return []; }
    public function findForStudent(string $studentId, string $activityId): ?array
    {
        $this->scopedCalls++;
        return $studentId === $this->allowedStudentId && $activityId === $this->allowedActivityId
            ? $this->record
            : null;
    }
};

$resolved = ActivityReadModel::resolveForStudent($repository, $studentId, $activityId);
$assert(is_array($resolved), 'Same-school detail resolves through findForStudent().');
$assert($repository->unscopedCalls === 0, 'Detail resolution never calls all() or findById().');
$assert(ActivityReadModel::resolveForStudent($repository, $studentId, '44444444-4444-4444-8444-444444444444') === null, 'Cross-school/not-owned detail resolves to null.');
$scopedCallsBeforeInvalid = $repository->scopedCalls;
$assert(ActivityReadModel::resolveForStudent($repository, $studentId, 'not-a-uuid') === null, 'Invalid route UUID resolves to null safely.');
$assert($repository->scopedCalls === $scopedCallsBeforeInvalid + 1, 'Invalid route remains on the scoped repository path for mock compatibility.');

if (is_array($resolved)) {
    foreach (['school_name', 'responsible_teacher_name', 'location_name', 'location_address', 'organizer_name', 'organizer_email', 'organizer_phone', 'cover_image_alt', 'target_audience', 'certificate_label'] as $field) {
        $assert(is_string($resolved[$field] ?? null) && trim($resolved[$field]) !== '', "Full metadata exposes {$field}.");
    }
    $assert($resolved['experience_highlights'] === $record['experience_highlights'], 'Experience highlights remain a safe list.');
    $assert($resolved['skills'] === $record['skills'], 'Skills remain a safe list.');
    $assert($resolved['requirements'] === $record['requirements'], 'Eligibility rules map to requirements.');
    $assert($resolved['benefits'] === $record['benefits'], 'Benefit items map to benefits.');
    $assert(($resolved['delivery_mode_label'] ?? null) === 'Trực tiếp', 'Delivery mode has a Vietnamese label.');
    $assert(($resolved['fee_label'] ?? null) === 'Miễn phí', 'Zero fee has a deterministic label.');
    $assert(($resolved['cover_image_url'] ?? null) === $record['cover_image_url'], 'Approved local cover path is preserved.');
    $assert(($resolved['online_meeting_url'] ?? null) === $record['online_meeting_url'], 'HTTPS meeting URL is preserved.');
}

$unsafe = ActivityReadModel::activity(array_merge($record, [
    'experience_highlights' => '{bad-json',
    'skills' => '{bad-json',
    'requirements' => [' ', null, ['nested']],
    'benefits' => null,
    'cover_image_url' => 'https://remote.example/cover.webp',
    'online_meeting_url' => 'http://insecure.example/meeting',
    'organizer_email' => '',
    'organizer_phone' => null,
]));
$assert($unsafe['experience_highlights'] === [], 'Malformed experience JSON fails closed to an empty list.');
$assert($unsafe['skills'] === [], 'Malformed skill JSON fails closed to an empty list.');
$assert($unsafe['requirements'] === [], 'Non-scalar requirement entries fail closed.');
$assert($unsafe['benefits'] === [], 'Missing benefits fail closed to an empty list.');
$assert(($unsafe['cover_image_url'] ?? null) === '', 'Remote cover URL is removed by the read model.');
$assert(($unsafe['online_meeting_url'] ?? null) === '', 'Non-HTTPS meeting URL is removed by the read model.');
$assert(($unsafe['has_organizer_email'] ?? true) === false, 'Missing optional email is hidden.');
$assert(($unsafe['has_organizer_phone'] ?? true) === false, 'Missing optional phone is hidden.');

if (!method_exists(ActivityReadModel::class, 'availabilityState')) {
    $failures[] = 'ActivityReadModel::availabilityState() is missing.';
} else {
    $state = static fn (array $overrides, string $now): array => ActivityReadModel::availabilityState(
        array_merge($record, $overrides),
        new DateTimeImmutable($now, new DateTimeZone('UTC')),
    );
    $assert(($state([], '2026-08-01 00:00:00')['code'] ?? null) === 'open', 'Registration opening is inclusive.');
    $assert(($state([], '2026-09-12 00:00:00')['code'] ?? null) === 'expired', 'Registration closing is exclusive.');
    $assert(($state(['registration_opens_at' => '2026-08-10 00:00:00'], '2026-08-09 23:59:59')['code'] ?? null) === 'not_open', 'Not-yet-open activity has an explicit state.');
    $assert(($state(['participants' => 25, 'remaining' => 0], '2026-08-20 00:00:00')['code'] ?? null) === 'full', 'Full activity has an explicit state.');
    $assert(($state(['status' => 'ongoing'], '2026-09-15 10:00:00')['code'] ?? null) === 'ongoing', 'Ongoing activity has an explicit state.');
    $assert(($state(['status' => 'completed'], '2026-09-16 00:00:00')['code'] ?? null) === 'completed', 'Completed activity has an explicit state.');
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_detail_metadata_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_detail_metadata_test: OK\n";
