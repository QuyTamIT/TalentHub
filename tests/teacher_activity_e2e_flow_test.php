<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/teacher/includes/cover-upload.php';
require_once dirname(__DIR__) . '/app/teacher/includes/activity-data.php';
require_once dirname(__DIR__) . '/app/learner/includes/activity-data.php';

function check(bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException('[FAIL] ' . $label);
    }
    echo '[PASS] ' . $label . PHP_EOL;
}

// 1. Establish database connection
$config = require dirname(__DIR__) . '/config/database.php';
try {
    $pdo = (new \TalentHub\Database\Connection($config))->connect();
    check($pdo instanceof PDO, 'Database connection established');
} catch (Throwable $e) {
    echo 'Database connection unavailable: ' . $e->getMessage() . PHP_EOL;
    exit(0);
}

// 2. Fetch a valid teacher & school
$stmt = $pdo->query('SELECT id, schoolId FROM teacher_profiles WHERE schoolId IS NOT NULL AND schoolId != "" LIMIT 1');
$teacher = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$teacher) {
    echo 'No eligible teacher found in database. Skipping DB integration.' . PHP_EOL;
    exit(0);
}

$teacherId = (string) $teacher['id'];
$schoolId = (string) $teacher['schoolId'];
check($teacherId !== '' && $schoolId !== '', 'Found active teacher: ' . $teacherId);

// 3. Upload a test cover image via upload handler
$tmpPng = tempnam(sys_get_temp_dir(), 'e2e_cover_');
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($tmpPng, $pngData);

$uploadedCoverUrl = teacherActivitiesHandleCoverUpload([
    'name' => 'e2e-banner.png',
    'type' => 'image/png',
    'tmp_name' => $tmpPng,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($pngData),
], $teacherId);
@unlink($tmpPng);

check(str_starts_with((string) $uploadedCoverUrl, '/storage/activity-covers/cover-'), 'Cover image uploaded to storage: ' . $uploadedCoverUrl);
$diskCoverPath = dirname(__DIR__) . $uploadedCoverUrl;
check(is_file($diskCoverPath), 'Uploaded cover exists on physical disk');

// 4. Create Activity with complete details via TeacherActivityService
$service = teacherActivitiesService($pdo);

$now = new DateTimeImmutable('now');
$startAt = $now->modify('+2 days 08:00:00');
$endAt = $now->modify('+2 days 17:00:00');
$regOpensAt = $now->modify('-1 hour');
$regClosesAt = $startAt->modify('-2 hours');
$cancelClosesAt = $startAt->modify('-24 hours');

$activityPayload = [
    'title' => 'E2E Hackathon AI Sinh vien 2026 - Test',
    'category' => 'Cuá»™c thi & Há»c thuáº­t',
    'displayCategory' => 'Cuá»™c thi & Há»c thuáº­t',
    'filterCategory' => 'academic',
    'summary' => 'SÃ¢n chÆ¡i sÃ¡ng táº¡o AI vÃ  mÃ´ hÃ¬nh thÃ´ng minh dÃ nh cho sinh viÃªn.',
    'description' => 'MÃ´ táº£ chi tiáº¿t giáº£i thÆ°á»Ÿng, lá»‹ch trÃ¬nh, mentor Ä‘á»“ng hÃ nh vÃ  ban giÃ¡m kháº£o cuá»™c thi Hackathon AI 2026.',
    'experienceHighlights' => [
        'Láº­p trÃ¬nh mÃ´ hÃ¬nh AI thá»±c chiáº¿n',
        'Thuyáº¿t trÃ¬nh trÆ°á»›c há»™i Ä‘á»“ng chuyÃªn gia',
        'Nháº­n giáº£i thÆ°á»Ÿng giÃ¡ trá»‹',
    ],
    'skillTags' => [
        'Python AI',
        'Machine Learning',
        'Teamwork',
    ],
    'eligibilityRules' => [
        'Sinh viÃªn toÃ n trÆ°á»ng',
        'Äá»™i thi 3-5 thÃ nh viÃªn',
    ],
    'benefitItems' => [
        'Cáº¥p giáº¥y chá»©ng nháº­n tham gia',
        'Cá»™ng 8 giá» tráº£i nghiá»‡m',
        'CÆ¡ há»™i phá»ng váº¥n thá»±c táº­p',
    ],
    'locationName' => 'Há»™i trÆ°á»ng Innovation Hall',
    'locationAddress' => 'TÃ²a nhÃ  FPT Polytechnic, Nam Tá»« LiÃªm, HÃ  Ná»™i',
    'deliveryMode' => 'in_person',
    'onlineMeetingUrl' => '',
    'organizerName' => 'Khoa CÃ´ng nghá»‡ ThÃ´ng tin BTEC',
    'organizerContact' => 'Ban tá»• chá»©c Hackathon AI',
    'organizerEmail' => 'hackathon@school.edu.vn',
    'organizerPhone' => '0987654321',
    'coverImageUrl' => $uploadedCoverUrl,
    'coverImageAlt' => 'Banner chÃ­nh thá»©c Hackathon AI Sinh viÃªn 2026',
    'feeAmount' => '0.00',
    'currency' => 'VND',
    'targetAudience' => 'Sinh viÃªn khá»‘i ngÃ nh CÃ´ng nghá»‡ vÃ  Thiáº¿t káº¿',
    'certificateLabel' => 'Giáº¥y chá»©ng nháº­n Hackathon AI 2026',
    'responsibleTeacherId' => $teacherId,
    'registrationOpensAt' => $regOpensAt,
    'registrationClosesAt' => $regClosesAt,
    'cancellationClosesAt' => $cancelClosesAt,
    'approvalMode' => 'automatic',
    'confirmedHours' => '8.00',
    'startAt' => $startAt,
    'endAt' => $endAt,
    'capacity' => 60,
];

$createdId = $service->create($teacherId, $schoolId, $activityPayload);
check($createdId !== '', 'Activity created successfully with ID: ' . $createdId);

try {
    // 5. Verify database records
    $checkStmt = $pdo->prepare('SELECT * FROM activities WHERE id = :id');
    $checkStmt->execute(['id' => $createdId]);
    $coreRow = $checkStmt->fetch(\PDO::FETCH_ASSOC);
    check((bool) $coreRow, 'Activity core record found in activities table');
    check(($coreRow['status'] ?? '') === 'draft', 'Activity initial status is draft');
    check(($coreRow['title'] ?? '') === $activityPayload['title'], 'Title preserved in activities table');

    $detailStmt = $pdo->prepare('SELECT * FROM activity_details WHERE activityId = :id');
    $detailStmt->execute(['id' => $createdId]);
    $detailRow = $detailStmt->fetch(\PDO::FETCH_ASSOC);
    check((bool) $detailRow, 'Activity details record found in activity_details table');
    check(($detailRow['coverImageUrl'] ?? '') === $uploadedCoverUrl, 'Cover image URL preserved in activity_details table');
    check(($detailRow['summary'] ?? '') === $activityPayload['summary'], 'Summary preserved in activity_details table');
    check(($detailRow['description'] ?? '') === $activityPayload['description'], 'Description preserved in activity_details table');
    check(($detailRow['organizerName'] ?? '') === $activityPayload['organizerName'], 'Organizer name preserved in activity_details table');

    // 5b. Verify Teacher Service find() queries full joined record
    $teacherActivity = teacherActivitiesFind($pdo, $teacherId, $createdId);
    check((bool) $teacherActivity, 'teacherActivitiesFind() returns full normalized activity');
    check(($teacherActivity['coverImageUrl'] ?? '') === $uploadedCoverUrl, 'Teacher view coverImageUrl matches');
    check(is_array($teacherActivity['experience_highlights_list'] ?? null), 'Teacher view highlights is parsed list');

    // 6. Simulate School approval, then advance status: draft -> published
    $pdo->prepare("UPDATE activities SET approvalStatus = 'approved' WHERE id = :id")->execute(['id' => $createdId]);
    $nextStatus = $service->advanceStatus($teacherId, $createdId);
    check($nextStatus === 'published', 'Activity status advanced to published');

    // 7. Verify Student View Representation
    // Learner repository queries joined data
    $learnerRepo = new \TalentHub\Learner\Data\Database\DatabaseActivityRepository($pdo);
    $learnerRow = $learnerRepo->findById($createdId);
    check((bool) $learnerRow, 'Learner DatabaseActivityRepository::findById() found published activity');

    $studentModel = \TalentHub\Learner\Data\ReadModel\ActivityReadModel::activity($learnerRow);
    check($studentModel['id'] === $createdId, 'Student ReadModel maps activity ID');
    check($studentModel['title'] === $activityPayload['title'], 'Student ReadModel title matches');
    check($studentModel['cover_image_url'] === $uploadedCoverUrl, 'Student ReadModel preserves storage cover URL');

    // Test Learner Cover Resolver Helper
    $fallbackIllustration = 'assets/activities/illustrations/hero-discover.svg';
    $resolvedCover = learner_activity_cover_or_fallback($studentModel['cover_image_url'], $fallbackIllustration);
    check($resolvedCover === $uploadedCoverUrl, 'learner_activity_cover_or_fallback resolves uploaded storage URL without fallback');

    // Verify detail lists in ReadModel
    check(count($studentModel['experience_highlights']) === 3, 'Experience highlights list has 3 items');
    check(count($studentModel['skills']) === 3, 'Skill tags list has 3 items');
    check(count($studentModel['benefits']) === 3, 'Benefit items list has 3 items');
    check($studentModel['target_audience'] === $activityPayload['targetAudience'], 'Target audience matches');
    check((float) $studentModel['confirmed_hours'] === 8.00, 'Confirmed hours matches in student view');
    check($studentModel['certificate_label'] === $activityPayload['certificateLabel'], 'Certificate label matches');

} finally {
    // 8. Clean up created test activity and uploaded cover file
    $pdo->prepare('DELETE FROM activities WHERE id = :id')->execute(['id' => $createdId]);
    if (is_file($diskCoverPath)) {
        @unlink($diskCoverPath);
    }
    echo '[PASS] Cleaned up test activity and storage cover' . PHP_EOL;
}

echo '[ALL PASSED] teacher_activity_e2e_flow_test.php completed successfully' . PHP_EOL;
