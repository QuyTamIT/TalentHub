<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$detailPath = $root . '/app/learner/project.php';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$assert(is_file($detailPath), 'learner project detail route exists');
$detailPage = (string) file_get_contents($detailPath);

$assert(
    str_contains($detailPage, "learner_project((string) (\$_GET['id'] ?? ''))"),
    'detail uses the student-scoped project lookup'
);
$assert(str_contains($detailPage, 'Chi tiết dự án'), 'detail has a project-specific title');
$assert(str_contains($detailPage, 'Mô tả dự án'), 'detail renders the project description');
$assert(str_contains($detailPage, 'Doanh nghiệp đồng hành'), 'detail supports paid sponsorships');
$assert(
    str_contains($detailPage, "foreach (\$project['sponsorships'] as \$sponsorship)"),
    'detail renders normalized sponsorships'
);
$assert(str_contains($detailPage, 'Mục tiêu tài trợ'), 'detail renders funding information');
$assert(str_contains($detailPage, 'Không tìm thấy dự án'), 'detail has an authorization-safe not-found state');
$assert(!preg_match('/projectUrl|project_url|github\.com/i', $detailPage), 'detail never renders repository links');
$assert(!str_contains($detailPage, 'Ứng tuyển'), 'detail has no application action');

// Registration primary action (2026-08-30 spec).
$assert(str_contains($detailPage, 'findActiveMembershipForStudent'), 'detail resolves the learner membership state from the read model');
$assert(str_contains($detailPage, 'Đăng ký dự án'), 'detail renders the primary registration action');
$assert(str_contains($detailPage, 'Đã tham gia dự án'), 'detail renders the already-joined state for active members');
$assert(str_contains($detailPage, 'actions/register-project.php'), 'registration posts to the same-origin learner action');
$assert(str_contains($detailPage, 'name="projectId"'), 'registration form carries the project identifier');
$assert(str_contains($detailPage, 'name="csrfToken"'), 'registration form carries the session CSRF token');
$assert(str_contains($detailPage, 'method="post"'), 'registration uses a POST form');
$assert(!str_contains($detailPage, 'studentId'), 'learner identity is never accepted from browser input');

// PRG feedback notices.
$assert(str_contains($detailPage, "\$_GET['registered']"), 'detail renders the post-redirect success notice flag');
$assert(str_contains($detailPage, "\$_GET['register']"), 'detail renders the post-redirect failure notice flag');
$assert(str_contains($detailPage, 'role="status"'), 'success notice is announced politely');
$assert(str_contains($detailPage, 'role="alert"'), 'failure notice is announced assertively');

echo "learner_project_detail_ui_test: OK\n";
