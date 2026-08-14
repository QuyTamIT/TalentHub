<?php
/**
 * TalentHub - School Dashboard Master Layout
 *
 * Expects the parent scope to set:
 *   - $schoolInfo  (array)   resolved by SchoolAppContext
 *   - $currentRoute (string) route path used to highlight sidebar item
 *   - $pageTitle   (string)  header title
 *   - $pageBody    (string)  HTML to render inside <main class="school-body">
 *   - $extraStyles (string)  optional extra <style> tags
 *   - $extraScripts(string)  optional extra <script> tags
 *   - $bodyClass   (string)  optional extra class for <body>
 *
 * Renders the shared chrome (head, sidebar, header, toast) once and
 * prints $pageBody inside <main>. Use this in every page under app/school
 * to remove the duplicated <head>/sidebar/header/toast blocks.
 */
declare(strict_types=1);

if (!isset($schoolInfo)) {
    $schoolInfo = [
        'name'          => 'Trường học',
        'logo_initials' => 'TH',
        'level'         => '',
        'district'      => '',
        'academic_year' => '',
    ];
}
$currentRoute = $currentRoute ?? '';
$pageTitle    = $pageTitle    ?? 'Tổng quan Nhà trường';
$pageBody     = $pageBody     ?? '';
$extraStyles  = $extraStyles  ?? '';
$extraScripts = $extraScripts ?? '';
$bodyClass    = trim('school-dashboard ' . ($bodyClass ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub School Dashboard - Quản lý hoạt động năng khiếu cho Nhà trường.">
    <title><?= htmlspecialchars($pageTitle); ?> - <?= htmlspecialchars($schoolInfo['name']); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/school.css">
    <?= $extraStyles; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass); ?>">
    <div class="school-layout">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <div class="school-main-wrapper">
            <?php include __DIR__ . '/header.php'; ?>

            <main class="school-body">
                <div class="container-fluid">
                    <?= $pageBody; ?>
                </div>
            </main>
        </div>
    </div>

    <div class="school-toast" id="school-toast" role="status" aria-live="polite">
        <div class="school-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span id="toast-message">Thao tác thành công!</span>
        </div>
    </div>

    <script src="../../assets/js/school.js" defer></script>
    <?= $extraScripts; ?>
</body>
</html>