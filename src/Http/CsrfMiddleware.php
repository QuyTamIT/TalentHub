<?php
declare(strict_types=1);

namespace TalentHub\Http;

use TalentHub\Auth\Session\SessionManager;

class CsrfMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];
    
    public function __construct(
        private readonly SessionManager $session,
        private readonly string $headerName = 'x-csrf-token'
    ) {}
    
    public function protect(callable $handler): callable
    {
        return function(Request $request) use ($handler): JsonResponse {
            $method = strtoupper($request->method);
            
            if (in_array($method, self::SAFE_METHODS, true)) {
                return $handler($request);
            }
            
            $token = $request->header($this->headerName);
            if ($token === null || $token === '') {
                throw new ApiException(403, 'CSRF_TOKEN_MISSING', 'CSRF token is required for this request.');
            }
            
            $this->session->assertCsrf($token);
            
            return $handler($request);
        };
    
use TalentHublic function 
class CsrfMiddleware
{
  $method): bool
    {
        return !in_a    
    public function __clf::SAFE_METHODS, true);
    }
}
