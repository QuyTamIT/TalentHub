<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Database\Exception\DatabaseConnectionException;
use TalentHub\Http\ApiException;
use TalentHub\Http\JsonResponse;
use TalentHub\Http\Request;
use TalentHub\Http\Router;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Modules\Teacher\Repository\TeacherRepository;
use TalentHub\Modules\Teacher\Service\TeacherProfileService;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Id\RequestId;
use Throwable;

final class Application
{
    public function run(): never
    {
        $request=Request::fromGlobals();$requestId=RequestId::make($request->header('x-request-id'));
        try{$this->buildRouter($requestId)->dispatch($request)->send();}
        catch(ApiException $e){JsonResponse::error($e,$requestId)->send();}
        catch(DatabaseConnectionException){JsonResponse::error(new ApiException(503,'SERVICE_UNAVAILABLE','Dịch vụ dữ liệu tạm thời không khả dụng.'),$requestId)->send();}
        catch(Throwable){JsonResponse::error(new ApiException(500,'INTERNAL_ERROR','Đã xảy ra lỗi hệ thống.'),$requestId)->send();}
    }

    private function buildRouter(string $requestId): Router
    {
        $config=require dirname(__DIR__,2).'/config/database.php';$pdo=(new Connection($config))->connect();
        $session=new SessionManager(require dirname(__DIR__,2).'/config/session.php');$session->start();
        $auth=new AuthService(new AuthRepository($pdo));$permissions=new PermissionService($pdo);$teachers=new TeacherProfileService(new TeacherRepository($pdo));$schools=new SchoolDashboardService(new SchoolRepository($pdo),$pdo);$router=new Router();
        $router->add('GET','/api/v1/health',fn()=>JsonResponse::success(['status'=>'ok','database'=>'available'],$requestId));
        $router->add('GET','/api/v1/auth/csrf',fn()=>JsonResponse::success(['csrfToken'=>$session->csrfToken()],$requestId));
        $router->add('POST','/api/v1/auth/login',function(Request $r)use($auth,$session,$requestId){$session->assertLoginAllowed();try{$user=$auth->login($r->json(),$requestId,$_SERVER['REMOTE_ADDR']??null);}catch(ApiException $e){if($e->errorCode==='INVALID_CREDENTIALS'){$session->recordLoginFailure();}throw $e;}$session->clearLoginFailures();$session->login($user);return JsonResponse::success(['user'=>$user,'csrfToken'=>$session->csrfToken()],$requestId);});
        $router->add('GET','/api/v1/auth/me',function()use($auth,$session,$requestId){$cached=$session->requireUser();$user=$auth->current($cached['id']);$session->refreshUser($user);return JsonResponse::success(['user'=>$user],$requestId);});
        $router->add('POST','/api/v1/auth/logout',function(Request $r)use($session,$requestId){$session->requireUser();$session->assertCsrf($r->header('x-csrf-token'));$session->destroy();return JsonResponse::success(['loggedOut'=>true],$requestId);});
        $router->add('PATCH','/api/v1/auth/password',function(Request $r)use($auth,$session,$requestId){$user=$session->requireUser();$session->assertCsrf($r->header('x-csrf-token'));$auth->changePassword($user['id'],$r->json());$session->destroy();return JsonResponse::success(['passwordChanged'=>true,'reauthenticationRequired'=>true],$requestId);});
        $router->add('GET','/api/v1/teachers/me',function()use($session,$permissions,$teachers,$requestId){$user=$this->requireTeacher($session);$permissions->require($user['id'],'teacher_profile.read_own');return JsonResponse::success($teachers->get($user['id']),$requestId);});
        $router->add('PATCH','/api/v1/teachers/me',function(Request $r)use($session,$permissions,$teachers,$auth,$requestId){$user=$this->requireTeacher($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'teacher_profile.update_own');$profile=$teachers->update($user['id'],$r->json());$session->refreshUser($auth->current($user['id']));return JsonResponse::success($profile,$requestId);});
        $router->add('GET','/api/v1/teachers/me/dashboard',function()use($session,$permissions,$teachers,$requestId){$user=$this->requireTeacher($session);$permissions->require($user['id'],'teacher_dashboard.read_own');return JsonResponse::success($teachers->dashboard($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'school_profile.read_own');return JsonResponse::success($schools->getByUser($user['id']),$requestId);});
        $router->add('PATCH','/api/v1/schools/me',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'school_profile.update_own');return JsonResponse::success($schools->update($user['id'],$r->json()),$requestId);});
        $router->add('GET','/api/v1/schools/me/dashboard',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'school_dashboard.read_own');return JsonResponse::success($schools->dashboard($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/classes',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'class.read_own_school');return JsonResponse::success($schools->classes($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/teachers',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'teacher_profile.read_own_school');return JsonResponse::success($schools->teachers($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/students',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'student_profile.read_own_school');return JsonResponse::success($schools->students($user['id']),$requestId);});
        return $router;
    }

    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function requireTeacher(SessionManager $session): array{$user=$session->requireUser();if($user['role']!=='teacher'){throw new ApiException(403,'PERMISSION_DENIED','Endpoint chỉ dành cho giáo viên.');}return $user;}
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function requireSchool(SessionManager $session): array{$user=$session->requireUser();if($user['role']!=='school'){throw new ApiException(403,'PERMISSION_DENIED','Endpoint chỉ dành cho nhà trường.');}return $user;}
}
