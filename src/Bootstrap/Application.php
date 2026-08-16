<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Service\LoginRateLimiter;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Database\Exception\DatabaseConnectionException;
use TalentHub\Http\ApiException;
use TalentHub\Http\JsonResponse;
use TalentHub\Http\Request;
use TalentHub\Http\Router;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
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
        catch(\RuntimeException $e){JsonResponse::error(new ApiException(422,'VALIDATION_FAILED',$e->getMessage()),$requestId)->send();}
        catch(DatabaseConnectionException){JsonResponse::error(new ApiException(503,'SERVICE_UNAVAILABLE','Dịch vụ dữ liệu tạm thời không khả dụng.'),$requestId)->send();}
        catch(Throwable){JsonResponse::error(new ApiException(500,'INTERNAL_ERROR','Đã xảy ra lỗi hệ thống.'),$requestId)->send();}
    }

    /**
     * Builds the router with all dependencies persisted across the request.
     * Kept public so server-side tests can dispatch handlers without going
     * through the `exit()`-style CLI path used by `run()`.
     */
    public function buildRouter(string $requestId): Router
    {
        $config=require dirname(__DIR__,2).'/config/database.php';$pdo=(new Connection($config))->connect();
        $session=new SessionManager(require dirname(__DIR__,2).'/config/session.php');$session->start();
        $auth=new AuthService(new AuthRepository($pdo));$loginLimiter=new LoginRateLimiter($pdo);$permissions=new PermissionService($pdo);$teachers=new TeacherProfileService(new TeacherRepository($pdo));$schools=new SchoolDashboardService(new SchoolRepository($pdo),$pdo,new SchoolAuthorization($pdo));$students=new StudentProfileService(new StudentRepository($pdo));$businesses=new BusinessProfileService(new BusinessRepository($pdo));$router=new Router();
        $router->add('GET','/api/v1/health',fn()=>JsonResponse::success(['status'=>'ok','database'=>'available'],$requestId));
        $router->add('GET','/api/v1/auth/csrf',fn()=>JsonResponse::success(['csrfToken'=>$session->csrfToken()],$requestId));
        $router->add('POST','/api/v1/auth/register',function(Request $r)use($auth,$requestId){$user=$auth->registerStudent($r->json(),$requestId,$_SERVER['REMOTE_ADDR']??null);return JsonResponse::success(['user'=>$user],$requestId,201);});
        $router->add('POST','/api/v1/auth/login',function(Request $r)use($auth,$loginLimiter,$session,$requestId){$input=$r->json();$email=is_string($input['email']??null)?$input['email']:'';$ip=$_SERVER['REMOTE_ADDR']??null;$loginLimiter->assertAllowed($email,$ip);$session->assertLoginAllowed();try{$user=$auth->login($input,$requestId,$ip);}catch(ApiException $e){if($e->errorCode==='INVALID_CREDENTIALS'){$loginLimiter->recordFailure($email,$ip);$session->recordLoginFailure();}throw $e;}$loginLimiter->clearIdentity($email,$ip);$session->clearLoginFailures();$session->login($user);return JsonResponse::success(['user'=>$user,'csrfToken'=>$session->csrfToken()],$requestId);});
        $router->add('GET','/api/v1/auth/me',function()use($auth,$session,$requestId){$cached=$session->requireUser();$user=$auth->current($cached['id']);$session->refreshUser($user);return JsonResponse::success(['user'=>$user],$requestId);});
        $router->add('POST','/api/v1/auth/logout',function(Request $r)use($session,$requestId){$session->requireUser();$session->assertCsrf($r->header('x-csrf-token'));$session->destroy();return JsonResponse::success(['loggedOut'=>true],$requestId);});
        $router->add('PATCH','/api/v1/auth/password',function(Request $r)use($auth,$session,$requestId){$user=$session->requireUser();$session->assertCsrf($r->header('x-csrf-token'));$auth->changePassword($user['id'],$r->json());$session->destroy();return JsonResponse::success(['passwordChanged'=>true,'reauthenticationRequired'=>true],$requestId);});
        $router->add('GET','/api/v1/teachers/me',function()use($session,$permissions,$teachers,$requestId){$user=$this->requireTeacher($session);$permissions->require($user['id'],'teacher_profile.read_own');return JsonResponse::success($teachers->get($user['id']),$requestId);});
        $router->add('PATCH','/api/v1/teachers/me',function(Request $r)use($session,$permissions,$teachers,$auth,$requestId){$user=$this->requireTeacher($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'teacher_profile.update_own');$profile=$teachers->update($user['id'],$r->json());$session->refreshUser($auth->current($user['id']));return JsonResponse::success($profile,$requestId);});
        $router->add('GET','/api/v1/teachers/me/dashboard',function()use($session,$permissions,$teachers,$requestId){$user=$this->requireTeacher($session);$permissions->require($user['id'],'teacher_dashboard.read_own');return JsonResponse::success($teachers->dashboard($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'school_profile.read_own');return JsonResponse::success($schools->getByUser($user['id']),$requestId);});
        $router->add('PATCH','/api/v1/schools/me',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'school_profile.update_own');return JsonResponse::success($schools->update($user['id'],$r->json()),$requestId);});
        $router->add('GET','/api/v1/schools/me/dashboard',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'school_dashboard.read_own');return JsonResponse::success($schools->dashboard($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/analytics',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'school_analytics.read_own');return JsonResponse::success($schools->analytics($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/classes',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'class.read_own_school');$limit=$this->parseInt($r->queryParam('limit'),1,200,50);$offset=$this->parseInt($r->queryParam('offset'),0,1000000,0);return JsonResponse::success($schools->classes($user['id']),$requestId);});
        $router->add('POST','/api/v1/schools/me/classes',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'class.create_own_school');return JsonResponse::success($schools->createClass($user['id'],$r->json()),$requestId,201);});
        $router->add('PATCH','/api/v1/schools/me/classes/{classId}',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$classId=(string)$r->pathParam('classId');$permissions->require($user['id'],'class.update_own_school');return JsonResponse::success($schools->updateClass($user['id'],$classId,$r->json()),$requestId);});
        $router->add('POST','/api/v1/schools/me/classes/{classId}/archive',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$classId=(string)$r->pathParam('classId');$permissions->require($user['id'],'class.archive_own_school');return JsonResponse::success($schools->archiveClass($user['id'],$classId),$requestId);});
        $router->add('GET','/api/v1/schools/me/teachers',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'teacher_profile.read_own_school');return JsonResponse::success($schools->teachers($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/teachers/{profileId}',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'teacher_profile.read_own_school');$profileId=(string)$r->pathParam('profileId');return JsonResponse::success($schools->getTeacher($user['id'],$profileId),$requestId);});
        $router->add('POST','/api/v1/schools/me/teachers',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'teacher_profile.invite_own_school');return JsonResponse::success($schools->inviteTeacher($user['id'],$r->json()),$requestId,201);});
        $router->add('PATCH','/api/v1/schools/me/teachers/{profileId}/admin',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$profileId=(string)$r->pathParam('profileId');$permissions->require($user['id'],'teacher_profile.update_role_own_school');$input=$r->json();$isAdmin=!empty($input['isSchoolAdmin']);return JsonResponse::success($schools->setTeacherAdmin($user['id'],$profileId,$isAdmin),$requestId);});
        $router->add('PATCH','/api/v1/schools/me/teachers/{profileId}/status',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$profileId=(string)$r->pathParam('profileId');$permissions->require($user['id'],'teacher_profile.deactivate_own_school');$input=$r->json();$active=!empty($input['active']);return JsonResponse::success($schools->setTeacherActive($user['id'],$profileId,$active),$requestId);});
        $router->add('GET','/api/v1/schools/me/students',function()use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'student_profile.read_own_school');return JsonResponse::success($schools->students($user['id']),$requestId);});
        $router->add('GET','/api/v1/schools/me/students/{profileId}',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$permissions->require($user['id'],'student_profile.read_own_school');$profileId=(string)$r->pathParam('profileId');return JsonResponse::success($schools->getStudent($user['id'],$profileId),$requestId);});
        $router->add('POST','/api/v1/schools/me/students',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'student_profile.create_own_school');return JsonResponse::success($schools->createStudent($user['id'],$r->json()),$requestId,201);});
        $router->add('PATCH','/api/v1/schools/me/students/{profileId}',function(Request $r)use($session,$permissions,$schools,$requestId){$user=$this->requireSchool($session);$session->assertCsrf($r->header('x-csrf-token'));$profileId=(string)$r->pathParam('profileId');$permissions->require($user['id'],'student_profile.update_own_school');return JsonResponse::success($schools->updateStudent($user['id'],$profileId,$r->json()),$requestId);});
        $router->add('GET','/api/v1/students/me',function()use($session,$permissions,$students,$requestId){$user=$this->requireRole($session,'student','học viên');$permissions->require($user['id'],'student_profile.read_own');return JsonResponse::success($students->get($user['id']),$requestId);});
        $router->add('PATCH','/api/v1/students/me',function(Request $r)use($session,$permissions,$students,$auth,$requestId){$user=$this->requireRole($session,'student','học viên');$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'student_profile.update_own');$profile=$students->update($user['id'],$r->json());$session->refreshUser($auth->current($user['id']));return JsonResponse::success($profile,$requestId);});
        $router->add('GET','/api/v1/students/me/dashboard',function()use($session,$permissions,$students,$requestId){$user=$this->requireRole($session,'student','học viên');$permissions->require($user['id'],'student_dashboard.read_own');return JsonResponse::success($students->dashboard($user['id']),$requestId);});
        $router->add('GET','/api/v1/businesses/me',function()use($session,$permissions,$businesses,$requestId){$user=$this->requireRole($session,'business','doanh nghiệp');$permissions->require($user['id'],'business_profile.read_own');return JsonResponse::success($businesses->get($user['id']),$requestId);});
        $router->add('PATCH','/api/v1/businesses/me',function(Request $r)use($session,$permissions,$businesses,$requestId){$user=$this->requireRole($session,'business','doanh nghiệp');$session->assertCsrf($r->header('x-csrf-token'));$permissions->require($user['id'],'business_profile.update_own');return JsonResponse::success($businesses->update($user['id'],$r->json()),$requestId);});
        $router->add('GET','/api/v1/businesses/me/dashboard',function()use($session,$permissions,$businesses,$requestId){$user=$this->requireRole($session,'business','doanh nghiệp');$permissions->require($user['id'],'business_dashboard.read_own');return JsonResponse::success($businesses->dashboard($user['id']),$requestId);});
        return $router;
    }

    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function requireTeacher(SessionManager $session): array{$user=$session->requireUser();if($user['role']!=='teacher'){throw new ApiException(403,'PERMISSION_DENIED','Endpoint chỉ dành cho giáo viên.');}return $user;}
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function requireSchool(SessionManager $session): array{$user=$session->requireUser();if($user['role']!=='school'){throw new ApiException(403,'PERMISSION_DENIED','Endpoint chỉ dành cho nhà trường.');}return $user;}
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function requireRole(SessionManager $session,string $role,string $label): array{$user=$session->requireUser();if($user['role']!==$role){throw new ApiException(403,'PERMISSION_DENIED',"Endpoint chỉ dành cho {$label}.");}return $user;}

    private function parseInt(?string $value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        $intVal = (int) $value;
        if ($intVal < $min || $intVal > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tham số query không hợp lệ.');
        }
        return $intVal;
    }
}
