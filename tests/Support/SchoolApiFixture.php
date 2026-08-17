<?php
declare(strict_types=1);
namespace TalentHub\Tests\Support;

use PDO;
use TalentHub\Bootstrap\Application;
use TalentHub\Http\ApiException;
use TalentHub\Http\JsonResponse;
use TalentHub\Http\Request;
use TalentHub\Http\Router;
use TalentHub\Support\Id\RequestId;

final class SchoolApiFixture
{
    private const SESSION_NAME = 'TALENTHUBSESSID';

    private bool $sessionBooted = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $password,
    ) {
    }

    /**
     * Drive a single HTTP request through the Router and capture the response.
     *
     * @param array<string,string> $headers
     * @return array{status:int,body:array<string,mixed>}
     */
    public function call(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $this->ensureSession();
        $this->seedServer($method, $path, $body, $headers);

        $request = Request::fromGlobals();
        $requestId = RequestId::make($request->header('x-request-id'));
        $app = new Application();
        /** @var Router $router */
        $router = $app->buildRouter($requestId);

        try {
            $response = $router->dispatch($request);
        } catch (ApiException $e) {
            $response = JsonResponse::error($e, $requestId);
        } catch (\RuntimeException $e) {
            $response = JsonResponse::error(new ApiException(422, 'VALIDATION_FAILED', $e->getMessage()), $requestId);
        } catch (\Throwable $e) {
            $response = JsonResponse::error(
                new ApiException(500, 'INTERNAL_ERROR', 'Đã xảy ra lỗi hệ thống.'),
                $requestId,
            );
        }

        $payload = $response->payload;
        $this->resetGlobals();

        return ['status' => $response->status, 'body' => $payload];
    }

    /**
     * Login as the seeded school user; keep session alive across calls.
     *
     * @return array{userId:string,csrfToken:string}
     */
    public function loginAsSchool(): array
    {
        $login = $this->call('POST', '/api/v1/auth/login', [
            'email'    => 'school@test.talenthub.local',
            'password' => $this->password,
        ]);
        if ($login['status'] !== 200) {
            throw new \RuntimeException('School login failed: ' . json_encode($login));
        }
        $data = $login['body']['data'] ?? [];
        $userId = (string) ($data['user']['id'] ?? '');
        $csrf   = (string) ($data['csrfToken'] ?? '');
        if ($userId === '' || $csrf === '') {
            throw new \RuntimeException('Login response missing user/csrf.');
        }
        return ['userId' => $userId, 'csrfToken' => $csrf];
    }

    /**
     * Login as a teacher (non-school) to assert cross-role denial.
     *
     * @return array{userId:string,csrfToken:string}
     */
    public function loginAsTeacher(): array
    {
        $this->logout();
        $login = $this->call('POST', '/api/v1/auth/login', [
            'email'    => 'teacher@test.talenthub.local',
            'password' => $this->password,
        ]);
        if ($login['status'] !== 200) {
            throw new \RuntimeException('Teacher login failed: ' . json_encode($login));
        }
        $data = $login['body']['data'] ?? [];
        return [
            'userId'    => (string) ($data['user']['id'] ?? ''),
            'csrfToken' => (string) ($data['csrfToken'] ?? ''),
        ];
    }

    /**
     * Forget the current session so the next call sees no logged-in user.
     */
    public function logout(): void
    {
        if ($this->sessionBooted && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    private function ensureSession(): void
    {
        if ($this->sessionBooted && session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_id('tttalenthubapitest');
            session_name(self::SESSION_NAME);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->sessionBooted = true;
    }

    /**
     * @param array<string,string> $headers
     */
    private function seedServer(string $method, string $path, ?array $body, array $headers): void
    {
        $headerNames = ['CONTENT_TYPE' => true];
        // Strip any previous per-request headers.
        foreach (array_keys($_SERVER) as $key) {
            if ($key === 'REQUEST_METHOD' || $key === 'REQUEST_URI' || $key === 'REMOTE_ADDR') {
                unset($_SERVER[$key]);
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI']    = $path;
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        if ($body !== null) {
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $GLOBALS['__TALENTHUB_TEST_BODY__'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        foreach ($headers as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$key] = $value;
        }
    }

    private function resetGlobals(): void
    {
        $GLOBALS['__TALENTHUB_TEST_BODY__'] = null;
    }
}
