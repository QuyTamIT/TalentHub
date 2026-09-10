<?php

declare(strict_types=1);

namespace TalentHub\Http;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Modules\Notification\Repository\NotificationRepository;
use TalentHub\Modules\Notification\Service\NotificationService;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Id\RequestId;

/** Shared, role-isolated notification API for non-learner portals. */
final class PortalNotificationApi
{
    private const ALLOWED_QUERY_FIELDS = ['limit', 'offset', 'sort', 'direction', 'read', 'filter'];
    private const ALLOWED_BODY_FIELDS = ['action', 'notificationId'];

    public static function handle(string $role, string $sessionName): never
    {
        $request = Request::fromGlobals();
        $requestId = RequestId::make($request->header('x-request-id'));

        try {
            $role = RoleCodes::canonical($role);
            if (!in_array($role, [RoleCodes::TEACHER, RoleCodes::SCHOOL, RoleCodes::ENTERPRISE], true)) {
                throw new ApiException(500, 'INVALID_PORTAL_CONFIGURATION', 'Cấu hình cổng thông báo không hợp lệ.');
            }

            $root = dirname(__DIR__, 2);
            $sessionConfig = require $root . '/config/session.php';
            $sessionConfig['name'] = $sessionName;
            $session = new SessionManager($sessionConfig);
            $session->start();

            $pdo = (new Connection(require $root . '/config/database.php'))->connect();
            $user = self::requirePortalUser($session, $pdo, $role);
            $permissions = new PermissionService($pdo);
            $service = new NotificationService(new NotificationRepository($pdo));

            if ($request->method === 'GET') {
                self::assertAllowedFields($request->queryParams(), self::ALLOWED_QUERY_FIELDS, 'Query');
                $filter = $request->queryParam('filter');
                if ($filter !== null && !in_array($filter, ['all', 'unread'], true)) {
                    throw new ApiException(422, 'VALIDATION_FAILED', 'Bộ lọc thông báo không hợp lệ.');
                }
                if ($filter !== null && $request->queryParam('read') !== null) {
                    throw new ApiException(422, 'VALIDATION_FAILED', 'Không thể dùng đồng thời filter và read.');
                }

                $queryParams = $request->queryParams();
                unset($queryParams['filter']);
                if ($filter === 'unread') {
                    $queryParams['read'] = 'false';
                }
                $query = self::collectionQuery($request, $queryParams);

                $permissions->require($user['id'], 'notification.read_own');
                $inbox = $service->inbox($user['id'], $query);
                JsonResponse::success([
                    'items' => $inbox['items'],
                    // Compatibility with the learner inbox response shape.
                    'notifications' => $inbox['items'],
                    'unreadCount' => $service->unreadCount($user['id']),
                    'pagination' => [
                        'total' => $inbox['total'],
                        'limit' => $inbox['limit'],
                        'offset' => $inbox['offset'],
                        'hasMore' => $inbox['hasMore'],
                    ],
                    'page' => $query->meta(),
                ], $requestId)->send();
            }

            if ($request->method === 'POST') {
                $body = $request->json();
                self::assertAllowedFields($body, self::ALLOWED_BODY_FIELDS, 'Body');
                $session->assertCsrf($request->header('x-csrf-token'));
                $permissions->require($user['id'], 'notification.mark_read_own');
                $action = trim((string) ($body['action'] ?? ''));

                if ($action === 'mark-read') {
                    $id = trim((string) ($body['notificationId'] ?? ''));
                    if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $id) !== 1) {
                        throw new ApiException(422, 'VALIDATION_FAILED', 'ID thông báo không hợp lệ.');
                    }
                    JsonResponse::success([
                        'notification' => $service->markRead($user['id'], $id),
                        'unreadCount' => $service->unreadCount($user['id']),
                    ], $requestId)->send();
                }

                if ($action === 'mark-all-read') {
                    if (array_key_exists('notificationId', $body)) {
                        throw new ApiException(422, 'VALIDATION_FAILED', 'notificationId không dùng cho thao tác này.');
                    }
                    JsonResponse::success($service->markAllRead($user['id']), $requestId)->send();
                }

                throw new ApiException(422, 'VALIDATION_FAILED', 'Action thông báo không hợp lệ.');
            }

            throw new ApiException(
                405,
                'METHOD_NOT_ALLOWED',
                'Phương thức không được hỗ trợ.',
                [],
                ['Allow' => 'GET, POST']
            );
        } catch (ApiException $exception) {
            JsonResponse::error($exception, $requestId)->send();
        } catch (\Throwable $exception) {
            $debugException = Environment::appEnvironment() === 'production' ? null : $exception;
            JsonResponse::error(
                new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'),
                $requestId,
                $debugException
            )->send();
        }
    }

    /** @param array<string,string> $queryParams */
    private static function collectionQuery(Request $request, array $queryParams): CollectionQuery
    {
        $proxy = new Request(
            $request->method,
            $request->path,
            $request->headers(),
            $request->rawBody(),
            $request->pathParams(),
            $queryParams
        );
        return CollectionQuery::fromRequest($proxy, ['createdAt'], ['read' => ['true', 'false']], 25, 100);
    }

    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private static function requirePortalUser(SessionManager $session, \PDO $pdo, string $role): array
    {
        $cached = $session->user();
        if ($cached === null && self::allowsDemoAutologin()) {
            $cached = SessionManager::getFallbackUserForRole($role, $pdo);
            $session->login($cached);
        }
        if ($cached === null) {
            throw new ApiException(401, 'AUTHENTICATION_REQUIRED', 'Bạn cần đăng nhập.');
        }

        $statement = $pdo->prepare(
            'SELECT u.id,u.email,u.fullName,u.status,r.code AS role '
            . 'FROM users u INNER JOIN roles r ON r.id=u.roleId WHERE u.id=:id LIMIT 1'
        );
        $statement->execute(['id' => (string) ($cached['id'] ?? '')]);
        $user = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($user) || (string) $user['status'] !== 'active') {
            throw new ApiException(401, 'AUTHENTICATION_REQUIRED', 'Phiên đăng nhập không còn hợp lệ.');
        }
        if (!RoleCodes::matches((string) $user['role'], $role)) {
            throw new ApiException(403, 'FORBIDDEN_ROLE_MISMATCH', 'Tài khoản không có quyền truy cập cổng này.');
        }

        return [
            'id' => (string) $user['id'],
            'email' => (string) $user['email'],
            'fullName' => (string) ($user['fullName'] ?? $user['email']),
            'role' => RoleCodes::canonical((string) $user['role']),
            'status' => (string) $user['status'],
        ];
    }

    private static function allowsDemoAutologin(): bool
    {
        if (!in_array(Environment::appEnvironment(), ['local', 'test'], true)
            || !Environment::boolean('TALENTHUB_ALLOW_DEMO_AUTOLOGIN', false)
        ) {
            return false;
        }
        if (PHP_SAPI === 'cli') {
            return true;
        }
        return in_array(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), ['127.0.0.1', '::1'], true);
    }

    /** @param array<string,mixed> $input @param list<string> $allowed */
    private static function assertAllowedFields(array $input, array $allowed, string $source): void
    {
        $unknown = array_values(array_diff(array_keys($input), $allowed));
        if ($unknown === []) {
            return;
        }
        throw new ApiException(422, 'VALIDATION_FAILED', $source . ' chứa field không được phép.', array_map(
            static fn (string $field): array => [
                'field' => $field,
                'code' => 'FIELD_NOT_ALLOWED',
                'message' => 'Không được phép gửi field này.',
            ],
            $unknown
        ));
    }
}