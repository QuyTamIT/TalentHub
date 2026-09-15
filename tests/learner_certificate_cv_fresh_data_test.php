<?php
declare(strict_types=1);
// Reuse the existing in-memory CV repository fixture and its regression assertions.
require __DIR__ . '/learner_passport_cv_repository_test.php';

$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$before = $repo->forStudent($student);
$check(array_key_exists('certificates', $before) && $before['certificates'] === [], 'CV contract must expose certificates even when table is absent');
$pdo->exec('CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT, title TEXT, issuingOrganization TEXT, issueDate TEXT, verificationStatus TEXT, createdAt TEXT, updatedAt TEXT)');
$insert = $pdo->prepare('INSERT INTO certificates VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute(['old', $student, 'Older certificate', 'Academy', '2024-01-01', 'verified', '2024-01-01', '2024-01-01']);
$insert->execute(['new', $student, 'New external certificate', 'IDP', '2025-01-15', 'unverified', '2025-01-15', '2025-01-15']);
$insert->execute(['other', 'other-student', 'Private certificate', 'Secret', '2026-01-01', 'verified', '2026-01-01', '2026-01-01']);
$fresh = $repo->forStudent($student);
$check(count($fresh['certificates']) === 2, 'CV certificate read must be student-scoped');
$check($fresh['certificates'][0]['id'] === 'new', 'Latest issue date must come first');
$check($fresh['certificates'][0]['verification_status'] === 'unverified', 'Certificate status must remain unchanged');
$model = \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($fresh, 'now');
$check(($model['certificates'][0]['title'] ?? '') === 'New external certificate', 'CV data contract must retain latest certificate title');
$check(($model['certificates'][0]['issuing_organization'] ?? '') === 'IDP', 'CV data contract must retain issuer');
$check(($model['certificates'][0]['issue_date'] ?? '') === '2025-01-15', 'CV data contract must retain issue date');
$check(($model['certificates'][0]['verification_status'] ?? '') === 'unverified', 'View model must not certify external entries');
$check($model['skills'] === [], 'New certificates must not award verified skills');
$check($model['badges'] === [], 'New certificates must not become school badges');
$check($model['strengths_summary'] === \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($before, 'now')['strengths_summary'], 'Certificates must not invent verified competence in CV summary');
$pdo->exec("UPDATE certificates SET title='Updated external certificate' WHERE id='new'");
$check($repo->forStudent($student)['certificates'][0]['title'] === 'Updated external certificate', 'Next read must reflect update');
$pdo->exec("DELETE FROM certificates WHERE id='new'");
$check(count($repo->forStudent($student)['certificates']) === 1, 'Next read must reflect delete');
$template = file_get_contents(dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php');
$check(!str_contains($template, 'Chứng chỉ bên ngoài'), 'Do not introduce a visible external certificate CV section');
$check(!str_contains($template, "\$cv['certificates']"), 'Keep the current CV presentation unchanged');
echo "learner_certificate_cv_fresh_data_test: OK\n";
