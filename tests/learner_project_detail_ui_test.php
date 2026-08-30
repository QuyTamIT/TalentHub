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
$assert(!str_contains($detailPage, 'Tham gia dự án'), 'detail does not imply an enrollment workflow');

echo "learner_project_detail_ui_test: OK\n";
