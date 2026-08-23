<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseCertificateCommandRepository;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Readiness\TalentPassportOptionalSchema;
use TalentHub\Learner\Data\Service\CertificateCommandService;
use TalentHub\Learner\Data\Service\ProfileSharingService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$config = require dirname(__DIR__) . '/config/database.php';
$databaseName = (string) ($config['database'] ?? '');
$assert(
    preg_match('/\Atalenthub_phase3_test_[0-9]{14}\z/', $databaseName) === 1,
    'Phase 3 MySQL integration runs only on a validated disposable schema.'
);
$pdo = (new Connection($config))->connect();
$inspector = new SchemaInspector($pdo, $databaseName);
$assert(TalentPassportOptionalSchema::status($inspector, 'projects') === 'available', 'Project capability is available on MySQL.');

$studentId = $pdo->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1')->fetchColumn();
$assert(is_string($studentId) && $studentId !== '', 'Disposable clone contains a Student fixture.');

$permissionRoles = $pdo->query(<<<'SQL'
    SELECT GROUP_CONCAT(r.code ORDER BY r.code)
    FROM role_permissions rp
    JOIN roles r ON r.id = rp.roleId
    JOIN permissions p ON p.id = rp.permissionId
    WHERE p.code = 'certificate.manage_own'
SQL
)->fetchColumn();
$assert($permissionRoles === 'student', 'certificate.manage_own remains Student-only.');

$certificateService = new CertificateCommandService(new DatabaseCertificateCommandRepository($pdo));
$certificate = $certificateService->create($studentId, [
    'title' => 'Phase 3 MySQL Integration Certificate',
    'issuingOrganization' => 'TalentHub Test',
    'issueDate' => '2026-01-01',
    'expiryDate' => '2027-01-01',
    'credentialUrl' => 'https://example.test/certificates/phase-3',
]);
$assert($certificate['verificationStatus'] === 'unverified', 'MySQL create persists unverified certificate.');
try {
    $certificateService->update($studentId, $certificate['id'], ['expiryDate' => '2025-01-01']);
    $assert(false, 'MySQL partial invalid expiry must be rejected.');
} catch (ApiException $exception) {
    $assert($exception->status === 422, 'MySQL partial invalid expiry returns 422.');
}
$certificateService->delete($studentId, $certificate['id']);

$sharingService = new ProfileSharingService($pdo);
$share = $sharingService->createShare($studentId, ['fullName', 'certificates'], 7);
$storedShare = $pdo->prepare('SELECT consentId, tokenHash, revokedAt FROM student_profile_shares WHERE id = :id');
$storedShare->execute(['id' => $share['id']]);
$shareRow = $storedShare->fetch(PDO::FETCH_ASSOC);
$assert(is_array($shareRow) && is_string($shareRow['consentId']) && $shareRow['consentId'] !== '', 'MySQL share links consent.');
$assert($shareRow['tokenHash'] === hash('sha256', $share['rawToken']), 'MySQL stores only the token hash.');
$assert(!str_contains(json_encode($shareRow, JSON_THROW_ON_ERROR), $share['rawToken']), 'MySQL row does not contain raw token.');

$sharingService->revokeShare($studentId, $share['id']);
$revoked = $pdo->prepare(<<<'SQL'
    SELECT s.revokedAt, c.isGranted, c.grantedAt, c.revokedAt AS consentRevokedAt
    FROM student_profile_shares s
    JOIN privacy_consents c ON c.id = s.consentId
    WHERE s.id = :id
SQL
);
$revoked->execute(['id' => $share['id']]);
$revokedRow = $revoked->fetch(PDO::FETCH_ASSOC);
$assert(is_array($revokedRow) && $revokedRow['revokedAt'] !== null, 'MySQL share revokes.');
$assert((int) $revokedRow['isGranted'] === 0 && $revokedRow['grantedAt'] === null, 'MySQL linked consent revokes.');
$assert($revokedRow['consentRevokedAt'] !== null, 'MySQL linked consent records revokedAt.');

echo "phase_3_mysql_integration_test: OK\n";
