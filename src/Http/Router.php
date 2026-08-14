<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class Router
{
    /** @var array<string,callable(Request):JsonResponse> */ private array $routes=[];
    public function add(string $method,string $path,callable $handler): void{$this->routes[strtoupper($method).' '.$path]=$handler;}
    public function dispatch(Request $request): JsonResponse
    { $handler=$this->routes[$request->method.' '.$request->path]??null;if(!$handler){throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy tài nguyên.');}return $handler($request); }
}
