<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolActivityApprovalRepository;
use TalentHub\Modules\School\Service\SchoolActivityApprovalService;

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite is not available.\n");
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL);
CREATE TABLE school_members (schoolId TEXT NOT NULL, userId TEXT NOT NULL);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, summary TEXT, description TEXT, locationName TEXT, locationAddress TEXT);
CREATE TABLE activities (
    id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL,
    title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT,
    capacity INTEGER NOT NULL, status TEXT NOT NULL, approvalStatus TEXT NOT NULL,
    approvalRequestedAt TEXT, approvalReason TEXT, approvedAt TEXT, approvedBy TEXT
);
SQL);

$schoolA = '30000000-0000-4000-8000-000000000001';
$schoolB = '30000000-0000-4000-8000-000000000002';
$schoolUser = '31000000-0000-4000-8000-000000000001';
$teacherUser = '32000000-0000-4000-8000-000000000001';
$teacherId = '33000000-0000-4000-8000-000000000001';
$pdo->exec("INSERT INTO users VALUES ('{$schoolUser}','School reviewer'),('{$teacherUser}','Teacher one')");
$pdo->exec("INSERT INTO school_members VALUES ('{$schoolA}','{$schoolUser}')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$teacherId}','{$teacherUser}')");
$insert = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$insert->execute(['40000000-0000-4000-8000-000000000001', $schoolA, $teacherId, 'Approve me', 'Tech', '2026-09-10', '2026-09-11', 20, 'draft', 'pending_school_review', '2026-09-01', null, null, null]);
$insert->execute(['40000000-0000-4000-8000-000000000002', $schoolA, $teacherId, 'Reject me', 'Tech', '2026-09-12', '2026-09-13', 20, 'draft', 'pending_school_review', '2026-09-01', null, null, null]);
$insert->execute(['40000000-0000-4000-8000-000000000003', $schoolB, $teacherId, 'Other school', 'Tech', '2026-09-12', '2026-09-13', 20, 'draft', 'pending_school_review', '2026-09-01', null, null, null]);

$passed = 0;
$failed = 0;
function schoolApprovalAssert(string $name, bool $condition): void
{
    global $passed, $failed;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $name . "\n";
    $condition ? $passed++ : $failed++;
}
function schoolApprovalThrows(string $name, callable $callback, string $code): void
{
    try {
        $callback();
        schoolApprovalAssert($name, false);
    } catch (ApiException $exception) {
        schoolApprovalAssert($name, $exception->errorCode === $code);
    } catch (Throwable) {
        schoolApprovalAssert($name, false);
    }
}

$service = new SchoolActivityApprovalService(new SchoolActivityApprovalRepository($pdo));
$pending = $service->listPending($schoolUser);
schoolApprovalAssert('Nhà trường chỉ thấy queue thuộc trường mình', count($pending) === 2);
schoolApprovalThrows('Từ chối không có lý do bị chặn', fn() => $service->review($schoolUser, '40000000-0000-4000-8000-000000000002', 'reject', null, 'request-id-123456789'), 'VALIDATION_FAILED');
$approved = $service->review($schoolUser, '40000000-0000-4000-8000-000000000001', 'approve', null, 'request-id-123456789');
schoolApprovalAssert('Duyệt chuyển approvalStatus approved và activity status published', $approved['approvalStatus'] === 'approved' && $approved['status'] === 'published');
$approvedRow = $pdo->query("SELECT status,approvalStatus,approvedBy FROM activities WHERE id='40000000-0000-4000-8000-000000000001'")->fetch(PDO::FETCH_ASSOC);
schoolApprovalAssert('Lưu reviewer và không lộ status approval vào lifecycle sai', $approvedRow['status'] === 'published' && $approvedRow['approvalStatus'] === 'approved' && $approvedRow['approvedBy'] === $schoolUser);
$rejected = $service->review($schoolUser, '40000000-0000-4000-8000-000000000002', 'reject', 'Cần bổ sung mô tả.', 'request-id-123456789');
schoolApprovalAssert('Từ chối giữ activity status draft và lưu lý do', $rejected['status'] === 'draft' && $rejected['approvalStatus'] === 'rejected' && $rejected['approvalReason'] === 'Cần bổ sung mô tả.');
schoolApprovalThrows('Không duyệt được hoạt động của trường khác', fn() => $service->review($schoolUser, '40000000-0000-4000-8000-000000000003', 'approve', null, 'request-id-123456789'), 'RESOURCE_NOT_FOUND');
schoolApprovalThrows('Không duyệt lại activity đã kết thúc review', fn() => $service->review($schoolUser, '40000000-0000-4000-8000-000000000001', 'approve', null, 'request-id-123456789'), 'APPROVAL_STATUS_CONFLICT');

echo "Summary: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
