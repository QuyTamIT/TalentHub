<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/teacher/includes/activity-data.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite is not available.\n");
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE activities (
    id TEXT PRIMARY KEY,
    schoolId TEXT NOT NULL,
    createdByTeacherId TEXT NOT NULL,
    title TEXT,
    category TEXT,
    startAt TEXT,
    endAt TEXT,
    capacity INTEGER,
    status TEXT NOT NULL,
    approvalStatus TEXT NOT NULL DEFAULT 'draft',
    approvalRequestedAt TEXT NULL,
    approvalReason TEXT NULL
);
CREATE TABLE activity_details (
    activityId TEXT PRIMARY KEY,
    responsibleTeacherId TEXT NULL,
    audienceScope TEXT NOT NULL,
    displayCategory TEXT NOT NULL,
    filterCategory TEXT NOT NULL,
    summary TEXT NOT NULL,
    description TEXT NOT NULL,
    experienceHighlights TEXT NOT NULL,
    skillTags TEXT NOT NULL,
    eligibilityRules TEXT NOT NULL,
    benefitItems TEXT NOT NULL,
    locationName TEXT NOT NULL,
    deliveryMode TEXT NOT NULL,
    organizerName TEXT NOT NULL,
    feeAmount REAL NOT NULL,
    currency TEXT NOT NULL,
    targetAudience TEXT NOT NULL
);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, status TEXT NOT NULL);
SQL);

$passed = 0;
$failed = 0;
function approvalAssert(string $name, bool $condition): void
{
    global $passed, $failed;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $name . "\n";
    $condition ? $passed++ : $failed++;
}
function approvalThrows(string $name, callable $callback, string $expectedCode): void
{
    try {
        $callback();
        approvalAssert($name, false);
    } catch (ApiException $exception) {
        approvalAssert($name, $exception->errorCode === $expectedCode);
    } catch (Throwable) {
        approvalAssert($name, false);
    }
}
function approvalInsert(PDO $pdo, string $id, string $teacherId, string $status, string $approvalStatus = 'draft', string $title = 'Hoạt động kiểm thử'): void
{
    $statement = $pdo->prepare(
        'INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status,approvalStatus,approvalReason) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $statement->execute([$id, '30000000-0000-4000-8000-000000000001', $teacherId, $title, 'Kỹ thuật', '2026-09-10 09:00:00', '2026-09-10 17:00:00', 30, $status, $approvalStatus, $approvalStatus === 'rejected' ? 'Thiếu mô tả hoạt động.' : null]);
    $details = $pdo->prepare(
        "INSERT INTO activity_details (activityId,responsibleTeacherId,audienceScope,displayCategory,filterCategory,summary,description,experienceHighlights,skillTags,eligibilityRules,benefitItems,locationName,deliveryMode,organizerName,feeAmount,currency,targetAudience) VALUES (?,?,?,?,?,?,?,'[]','[]','[]','[]',?,'in_person','TalentHub',0,'VND','Học viên')"
    );
    $details->execute([$id, $teacherId, 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Mô tả kiểm thử', 'Mô tả kiểm thử', 'Phòng B305']);
}

$teacherId = '10000000-0000-4000-8000-000000000001';
$otherTeacherId = '10000000-0000-4000-8000-000000000002';
$service = new TeacherActivityService(new TeacherActivityRepository($pdo));

$createdId = $service->create($teacherId, '30000000-0000-4000-8000-000000000001', [
    'title' => 'Hoạt động tạo cùng mô tả địa điểm', 'category' => 'Kỹ thuật',
    'startAt' => new DateTimeImmutable('2026-09-08 09:00:00'), 'endAt' => new DateTimeImmutable('2026-09-08 17:00:00'),
    'capacity' => 25, 'summary' => 'Mô tả ngắn ban đầu', 'locationName' => 'Phòng B305',
]);
$createdDetails = $pdo->query("SELECT summary,description,locationName FROM activity_details WHERE activityId = '{$createdId}'")->fetch(PDO::FETCH_ASSOC);
approvalAssert('Tạo hoạt động lưu mô tả và địa điểm vào activity_details', $createdDetails['summary'] === 'Mô tả ngắn ban đầu' && $createdDetails['description'] === 'Mô tả ngắn ban đầu' && $createdDetails['locationName'] === 'Phòng B305');
$service->update($teacherId, $createdId, [
    'title' => 'Hoạt động đã sửa', 'category' => 'Kỹ thuật',
    'startAt' => new DateTimeImmutable('2026-09-08 10:00:00'), 'endAt' => new DateTimeImmutable('2026-09-08 18:00:00'),
    'capacity' => 30, 'summary' => 'Mô tả sau chỉnh sửa', 'locationName' => 'Hội trường A',
]);
$updatedDetails = $pdo->query("SELECT summary,description,locationName FROM activity_details WHERE activityId = '{$createdId}'")->fetch(PDO::FETCH_ASSOC);
approvalAssert('Chỉnh sửa đọc lại đúng mô tả và địa điểm', $updatedDetails['summary'] === 'Mô tả sau chỉnh sửa' && $updatedDetails['description'] === 'Mô tả sau chỉnh sửa' && $updatedDetails['locationName'] === 'Hội trường A');
$pdo->exec("CREATE TRIGGER fail_activity_detail_insert BEFORE INSERT ON activity_details WHEN NEW.summary = 'ROLLBACK' BEGIN SELECT RAISE(ABORT, 'detail write failed'); END");
$rollbackId = null;
try {
    $rollbackId = $service->create($teacherId, '30000000-0000-4000-8000-000000000001', [
        'title' => 'Hoạt động rollback', 'category' => 'Kỹ thuật',
        'startAt' => new DateTimeImmutable('2026-09-08 09:00:00'), 'endAt' => new DateTimeImmutable('2026-09-08 17:00:00'),
        'capacity' => 20, 'summary' => 'ROLLBACK', 'locationName' => 'Phòng B305',
    ]);
} catch (Throwable) {
    $rollbackId = 'rollback-failed';
}
approvalAssert('Lỗi lưu activity_details rollback cả activity', (int) $pdo->query("SELECT COUNT(*) FROM activities WHERE title = 'Hoạt động rollback'")->fetchColumn() === 0);

$draftId = '20000000-0000-4000-8000-000000000001';
approvalInsert($pdo, $draftId, $teacherId, 'draft');
approvalAssert('Chủ sở hữu gửi draft sang pending_school_review', $service->submitForApproval($teacherId, $draftId) === 'pending_school_review');
$draftRow = $pdo->query("SELECT status, approvalStatus, approvalRequestedAt, approvalReason FROM activities WHERE id = '{$draftId}'")->fetch(PDO::FETCH_ASSOC);
approvalAssert('Giữ nguyên status draft và chuyển approvalStatus pending_school_review', $draftRow['status'] === 'draft' && $draftRow['approvalStatus'] === 'pending_school_review');
approvalAssert('Có thời điểm gửi duyệt và xóa lý do cũ', is_string($draftRow['approvalRequestedAt']) && $draftRow['approvalRequestedAt'] !== '' && $draftRow['approvalReason'] === null);

$pendingId = '20000000-0000-4000-8000-000000000002';
approvalInsert($pdo, $pendingId, $teacherId, 'draft', 'pending_school_review');
approvalThrows('Không gửi lại hoạt động đang chờ duyệt', fn() => $service->submitForApproval($teacherId, $pendingId), 'INVALID_TRANSITION');
approvalThrows('Action publish cũ không chuyển được hoạt động chờ duyệt', fn() => $service->advanceStatus($teacherId, $pendingId), 'INVALID_TRANSITION');

$rejectedId = '20000000-0000-4000-8000-000000000003';
approvalInsert($pdo, $rejectedId, $teacherId, 'draft', 'rejected');
$service->update($teacherId, $rejectedId, ['title' => 'Hoạt động đã chỉnh sửa', 'category' => 'Kỹ thuật', 'startAt' => new DateTimeImmutable('2026-09-11 09:00:00'), 'endAt' => new DateTimeImmutable('2026-09-11 17:00:00'), 'capacity' => 40, 'summary' => 'Mô tả đã bổ sung', 'locationName' => 'Hội trường A']);
approvalAssert('Hoạt động rejected được chỉnh sửa và gửi lại', $service->submitForApproval($teacherId, $rejectedId) === 'pending_school_review');

$otherActivityId = '20000000-0000-4000-8000-000000000004';
approvalInsert($pdo, $otherActivityId, $otherTeacherId, 'draft');
approvalThrows('Không gửi được hoạt động của giáo viên khác', fn() => $service->submitForApproval($teacherId, $otherActivityId), 'RESOURCE_NOT_FOUND');

$legacyPublishId = '20000000-0000-4000-8000-000000000005';
approvalInsert($pdo, $legacyPublishId, $teacherId, 'draft');
approvalThrows('Action publish cũ không đưa draft thành published', fn() => $service->advanceStatus($teacherId, $legacyPublishId), 'INVALID_TRANSITION');
$legacyRow = $pdo->query("SELECT status, approvalStatus FROM activities WHERE id = '{$legacyPublishId}'")->fetch(PDO::FETCH_ASSOC);
approvalAssert('Action publish cũ không làm thay đổi status hoặc approvalStatus', $legacyRow['status'] === 'draft' && $legacyRow['approvalStatus'] === 'draft');

$invalidId = '20000000-0000-4000-8000-000000000006';
approvalInsert($pdo, $invalidId, $teacherId, 'draft', 'draft', '');
approvalThrows('Thiếu dữ liệu bắt buộc thì không chuyển trạng thái', fn() => $service->submitForApproval($teacherId, $invalidId), 'VALIDATION_FAILED');
$invalidRow = $pdo->query("SELECT status, approvalStatus FROM activities WHERE id = '{$invalidId}'")->fetch(PDO::FETCH_ASSOC);
approvalAssert('Validation failure giữ nguyên draft/draft', $invalidRow['status'] === 'draft' && $invalidRow['approvalStatus'] === 'draft');
$missingDetailsId = '20000000-0000-4000-8000-000000000007';
approvalInsert($pdo, $missingDetailsId, $teacherId, 'draft');
$pdo->exec("UPDATE activity_details SET summary = '', description = '', locationName = '' WHERE activityId = '{$missingDetailsId}'");
approvalThrows('Thiếu mô tả hoặc địa điểm thì không gửi duyệt', fn() => $service->submitForApproval($teacherId, $missingDetailsId), 'VALIDATION_FAILED');

$_SESSION = ['csrfToken' => 'valid-csrf-token'];
$session = new SessionManager(['name' => SessionManager::SESSION_TEACHER]);
try {
    teacherActivitiesAssertCsrf($session, 'invalid-csrf-token');
    approvalAssert('CSRF sai bị từ chối', false);
} catch (RuntimeException) {
    approvalAssert('CSRF sai bị từ chối', true);
}
try {
    teacherActivitiesAssertCsrf($session, null);
    approvalAssert('CSRF thiếu bị từ chối', false);
} catch (RuntimeException) {
    approvalAssert('CSRF thiếu bị từ chối', true);
}

$teacherPage = (string) file_get_contents(dirname(__DIR__) . '/app/teacher/activities/index.php');
approvalAssert('UI không còn form action publish', !str_contains($teacherPage, 'form_action" value="publish'));
approvalAssert('UI có nút Gửi yêu cầu duyệt và xác nhận', str_contains($teacherPage, 'Gửi yêu cầu duyệt') && str_contains($teacherPage, 'data-confirm'));
approvalAssert('UI có badge chờ duyệt', str_contains($teacherPage, 'Đang chờ nhà trường duyệt') && str_contains($teacherPage, 'pending_school_review'));
approvalAssert('UI có textarea mô tả và input địa điểm giới hạn đúng', str_contains($teacherPage, 'name="summary"') && str_contains($teacherPage, 'maxlength="500"') && str_contains($teacherPage, 'name="locationName"') && str_contains($teacherPage, 'maxlength="255"'));
approvalAssert('UI không còn địa điểm giả hoặc thông báo cũ', !str_contains($teacherPage, 'Phòng Thực hành B305 - BTEC Cần Thơ') && !str_contains($teacherPage, 'Địa điểm chưa được lưu vì'));
approvalAssert('POST submit yêu cầu quyền activity.update_managed', str_contains($teacherPage, "'activity.update_managed'"));

echo "Summary: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
