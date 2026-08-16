<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class Router
{
    /**
     * @var list<array{method:string,pattern:string,regex:string,paramNames:list<string>,handler:callable(Request):JsonResponse}>
     */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        [$pattern, $regex, $paramNames] = self::compile($path);
        $this->routes[] = [
            'method'    => strtoupper($method),
            'pattern'   => $pattern,
            'regex'     => $regex,
            'paramNames'=> $paramNames,
            'handler'   => $handler,
        ];
    }

    public function dispatch(Request $request): JsonResponse
    {
        $method = $request->method;
        $path   = $request->path;
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $params = [];
            foreach ($route['paramNames'] as $name) {
                if (isset($matches[$name]) && is_string($matches[$name])) {
                    $params[$name] = $matches[$name];
                }
            }
            $request = new Request(
                $request->method,
                $request->path,
                $request->headers(),
                $request->rawBody(),
                $params,
                $request->queryParams(),
            );
            return ($route['handler'])($request);
        }
        throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tài nguyên.');
    }

    /**
     * @return array{0:string,1:string,2:list<string>}
     */
    private static function compile(string $path): array
    {
        $paramNames = [];
        $regex = '#^';
        $offset = 0;
        $length = strlen($path);
        while ($offset < $length) {
            $open = strpos($path, '{', $offset);
            if ($open === false) {
                $regex .= preg_quote(substr($path, $offset), '#');
                break;
            }
            $regex .= preg_quote(substr($path, $offset, $open - $offset), '#');
            $close = strpos($path, '}', $open);
            if ($close === false) {
                throw new \InvalidArgumentException("Router path '{$path}' has unclosed parameter at offset {$open}.");
            }
            $name = substr($path, $open + 1, $close - $open - 1);
            if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new \InvalidArgumentException("Router path '{$path}' has invalid parameter name.");
            }
            $paramNames[] = $name;
            $regex .= '(?P<' . $name . '>[^/]+)';
            $offset = $close + 1;
        }
        $regex .= '$#';
        return [$path, $regex, $paramNames];
    }
}
