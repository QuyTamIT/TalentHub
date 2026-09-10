<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bin/bootstrap.php';
require_once __DIR__.'/api/LearnerApiContext.php';
require_once __DIR__.'/data/Database/DatabasePassportCvRepository.php';
require_once __DIR__.'/data/ReadModel/PassportCvViewModel.php';
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') throw new \TalentHub\Http\ApiException(405,'METHOD_NOT_ALLOWED','Chỉ hỗ trợ xem CV.');
    $context=\TalentHub\Learner\Api\LearnerApiContext::fromGlobals();
    // Strict read-only export identity: no demo autologin or onboarding reconciliation writes.
    $sessionUser=$_SESSION['user'] ?? ['id'=>$_SESSION['user_id']??null,'role'=>$_SESSION['role']??null];
    if (empty($sessionUser['id'])) throw new \TalentHub\Http\ApiException(401,'AUTH_REQUIRED','Bạn cần đăng nhập.');
    if (!\TalentHub\Rbac\RoleCodes::matches((string)($sessionUser['role']??''),\TalentHub\Rbac\RoleCodes::STUDENT)) throw new \TalentHub\Http\ApiException(403,'PERMISSION_DENIED','CV chỉ dành cho sinh viên.');
    $identity=$context->pdo()->prepare("SELECT sp.id,r.code FROM student_profiles sp JOIN users u ON u.id=sp.userId JOIN roles r ON r.id=u.roleId WHERE u.id=? AND u.status='active' LIMIT 1");
    $identity->execute([(string)$sessionUser['id']]);
    $owner=$identity->fetch(PDO::FETCH_ASSOC);
    if (!$owner || !\TalentHub\Rbac\RoleCodes::matches((string)$owner['code'],\TalentHub\Rbac\RoleCodes::STUDENT)) throw new \TalentHub\Http\ApiException(403,'PERMISSION_DENIED','Không có hồ sơ sinh viên hợp lệ.');
    (new \TalentHub\Rbac\Service\PermissionService($context->pdo()))->require((string)$sessionUser['id'],'student_profile.read_own');
    $studentId=(string)$owner['id'];
    $data=(new \TalentHub\Learner\Data\Database\DatabasePassportCvRepository($context->pdo()))->forStudent($studentId);
    $stamp=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('d/m/Y H:i:s');
    $cv=\TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($data,$stamp);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verificationUrl = $scheme . '://' . $host . (function_exists('app_href') ? app_href('/app/learner/shared-profile.php') : '/app/learner/shared-profile.php') . '?code=' . urlencode($cv['passport_code']);
    require __DIR__.'/includes/passport-cv-template.php';
} catch (Throwable $error) {
    http_response_code($error instanceof \TalentHub\Http\ApiException ? $error->status : 503);
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Chưa thể xuất CV</title><h1>Chưa thể tải CV mới nhất</h1><p>Vui lòng kiểm tra phiên đăng nhập hoặc thử lại sau. Không xuất bản dữ liệu cũ.</p><a href="talent-passport.php">Quay lại Talent Passport</a></html>';
}
