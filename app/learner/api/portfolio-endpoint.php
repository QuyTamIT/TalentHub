<?php
declare(strict_types=1);
// Included only by fixed learner/teacher routes; the client cannot select its role.
require_once dirname(__DIR__,3).'/bin/bootstrap.php';
require_once __DIR__.'/JsonResponder.php';
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Modules\Student\Service\PortfolioHttp;
$requestId=bin2hex(random_bytes(12));
try {
    if (!isset($portfolioRole)||!in_array($portfolioRole,['student','teacher'],true)) throw new ApiException(404,'NOT_FOUND','Không tìm thấy endpoint.');
    $config=require dirname(__DIR__,3).'/config/session.php';
    $config['name']=SessionManager::sessionNameForRole($portfolioRole);
    $session=new SessionManager($config);
    $session->start();
    $session->requireUser();
    $pdo=(new Connection(require dirname(__DIR__,3).'/config/database.php'))->connect();
    $data=PortfolioHttp::handle($pdo,$session,Request::fromGlobals(),$portfolioRole);
    JsonResponder::sendSuccess($data,$requestId);
} catch (ApiException $e) { JsonResponder::sendError($e,$requestId); }
catch (Throwable) { JsonResponder::sendError(new ApiException(503,'PORTFOLIO_UNAVAILABLE','Báo cáo chưa sẵn sàng. Vui lòng kiểm tra migration hoặc thử lại sau.'),$requestId); }
