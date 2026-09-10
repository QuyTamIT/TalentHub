<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Service;

use PDO;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Modules\Student\Repository\PortfolioRepository;

final class PortfolioHttp
{
    public static function handle(PDO $pdo, SessionManager $session, Request $request, string $role): array
    {
        $identity=PortfolioAccess::identity($pdo,$session,$role);
        $repository=new PortfolioRepository($pdo);
        if ($request->method==='GET') {
            $completedOnly = ($request->queryParam('filter') ?? '') === 'completed';
            $data=$role==='student' ? $repository->listForStudent($identity['studentId'], $completedOnly) : ['items'=>$repository->listForTeacher($identity['userId'])];
            if ($role==='teacher') $data['skills']=$pdo->query("SELECT id,name FROM skills WHERE status='active' ORDER BY name,id")->fetchAll(PDO::FETCH_ASSOC);
            $data['csrfToken']=$session->csrfToken();
            return $data;
        }
        if ($request->method!=='POST') throw new ApiException(405,'METHOD_NOT_ALLOWED','Chỉ hỗ trợ GET và POST.');
        $session->assertCsrf($request->header('x-csrf-token'));
        $input=$request->json();
        $allowed=$role==='student' ? ['kind','contextId','expectedVersion','notes','repositoryUrl','demoUrl','startDate','endDate','hours','stage','submit','newRevision'] : ['kind','reportId','expectedVersion','decision','feedback','skillIds'];
        if (array_diff(array_keys($input),$allowed)) throw new ApiException(422,'VALIDATION_FAILED','Yêu cầu chứa trường không được phép.');
        if (!is_int($input['expectedVersion']??null) || $input['expectedVersion']<0) throw new ApiException(422,'VALIDATION_FAILED','Phiên bản không hợp lệ.');
        foreach (array_intersect(array_keys($input),['kind','contextId','reportId','notes','repositoryUrl','demoUrl','startDate','endDate','stage','decision','feedback']) as $key) {
            if (!is_string($input[$key])) throw new ApiException(422,'VALIDATION_FAILED','Dữ liệu văn bản không hợp lệ.');
        }
        foreach (['submit','newRevision'] as $key) if (isset($input[$key])&&!is_bool($input[$key])) throw new ApiException(422,'VALIDATION_FAILED','Thao tác không hợp lệ.');
        if (array_key_exists('hours',$input) && !is_int($input['hours']) && !is_float($input['hours']) && !is_string($input['hours'])) throw new ApiException(422,'VALIDATION_FAILED','Số giờ không hợp lệ.');
        if ($role==='student' && ($input['kind']??'')==='project' && array_intersect(array_keys($input),['startDate','endDate','hours','stage'])) throw new ApiException(422,'VALIDATION_FAILED','Báo cáo dự án không có trường tiến độ thực tập.');
        if ($role==='student') return ['report'=>$repository->save($identity['studentId'],$input['kind']??'',$input['contextId']??'',$input['expectedVersion'],$input)];
        $skills=$input['skillIds']??[];
        if (!is_array($skills)||!array_is_list($skills)||count($skills)>10||count(array_filter($skills,'is_string'))!==count($skills)) throw new ApiException(422,'VALIDATION_FAILED','Danh sách kỹ năng không hợp lệ.');
        return ['report'=>$repository->review($identity['userId'],$input['kind']??'',$input['reportId']??'',$input['expectedVersion'],$input['decision']??'',$input['feedback']??'',$skills)];
    }
}
