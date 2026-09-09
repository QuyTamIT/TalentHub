<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bin/bootstrap.php';
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Service\PortfolioAccess;
header('Cache-Control: no-store, private');
try {
    $config=require dirname(__DIR__,2).'/config/session.php';
    $config['name']=SessionManager::SESSION_TEACHER;
    $session=new SessionManager($config);$session->start();$session->requireUser();
    $pdo=(new Connection(require dirname(__DIR__,2).'/config/database.php'))->connect();
    PortfolioAccess::identity($pdo,$session,'teacher');
} catch(Throwable $e) {
    http_response_code($e instanceof ApiException?$e->status:503);
    echo '<!doctype html><meta charset="utf-8"><h1>Chưa thể mở trang duyệt</h1><p>Vui lòng đăng nhập đúng tài khoản giảng viên hoặc thử lại sau.</p>';
    exit;
}
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Duyệt báo cáo dự án / thực tập</title>
<link rel="stylesheet" href="<?= $escape(app_href('/assets/css/learner-portfolio.css')); ?>"></head>
<body class="portfolio-review-page"><a href="<?= $escape(app_href('/app/teacher/index.php')); ?>">← Khu vực Giảng viên</a>
<main class="portfolio-panel"><h1>Duyệt báo cáo dự án / thực tập</h1><p>Chỉ hiển thị báo cáo của sinh viên thuộc dự án hoặc vị trí thực tập bạn đang được phân công hướng dẫn. Chỉ xác nhận kỹ năng có minh chứng; không tự quy đổi hoạt động phong trào.</p>
<div data-portfolio data-role="teacher" data-endpoint="<?= $escape(app_href('/app/teacher/api/portfolio.php')); ?>"></div></main>
<script src="<?= $escape(app_href('/assets/js/learner-portfolio.js')); ?>" defer></script></body></html>
