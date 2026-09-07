<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';
require_once dirname(__DIR__) . '/shared/PortalNotificationPage.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$school = $context['school'];
$schoolInfo = [
    'name' => $school['name'],
    'logo_initials' => mb_substr((string) $school['name'], 0, 2),
    'level' => $school['level'] ?? 'Đại học / Cao đẳng',
    'district' => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];
$currentRoute = '/app/school/notifications.php';
$pageTitle = 'Thông báo';

ob_start();
renderPortalNotificationCenter('Theo dõi cập nhật học viên, huy hiệu, chứng nhận và các hoạt động trong nhà trường.');
$pageBody = ob_get_clean();
$extraStyles = '<link rel="stylesheet" href="../../assets/css/portal-notifications.css">';
require __DIR__ . '/includes/layout.php';