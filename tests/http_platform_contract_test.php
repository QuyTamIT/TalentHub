<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
use TalentHub\Http\ApiException;use TalentHub\Http\CollectionQuery;use TalentHub\Http\CorsPolicy;use TalentHub\Http\Request;use TalentHub\Rbac\EndpointPermissionMatrix;
$assert=static function(bool $ok,string $message):void{if(!$ok){throw new RuntimeException($message);}};
$request=new Request('GET','/items',[],'',[],['limit'=>'25','offset'=>'5','sort'=>'title','direction'=>'asc','status'=>'active']);
$query=CollectionQuery::fromRequest($request,['createdAt','title'],['status'=>['active','closed']]);
$assert($query->meta()===['limit'=>25,'offset'=>5,'sort'=>'title','direction'=>'asc','filters'=>['status'=>'active']],'collection query contract mismatch');
try{CollectionQuery::fromRequest(new Request('GET','/items',[],'',[],['sort'=>'unsafe']),['createdAt']);throw new RuntimeException('unsafe sort accepted');}catch(ApiException $e){$assert($e->status===422,'unsafe sort status');}
CorsPolicy::enforceSameOrigin(new Request('GET','/', ['origin'=>'https://talenthub.local'],'',[],[]),'talenthub.local');
try{CorsPolicy::enforceSameOrigin(new Request('GET','/',['origin'=>'https://evil.test'],'',[],[]),'talenthub.local');throw new RuntimeException('cross origin accepted');}catch(ApiException $e){$assert($e->status===403&&$e->errorCode==='CORS_ORIGIN_DENIED','CORS rejection mismatch');}
$matrix=EndpointPermissionMatrix::all();$assert(count($matrix)>=27&&EndpointPermissionMatrix::permission('GET','/api/v1/students/me')==='student_profile.read_own','endpoint matrix mismatch');
echo "http_platform_contract_test: OK\n";
