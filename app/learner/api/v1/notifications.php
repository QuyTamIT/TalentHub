<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';
require_once dirname(__DIR__, 2) . '/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    $service = new NotificationService(new DatabaseNotificationRepository($context->pdo()));

    if ($request->method === 'GET') {
        $identity = $context->studentIdentityForPermissions(['notification.read_own']);

        $unknownQueryFields = array_diff(array_keys($request->queryParams()), ['limit', 'offset', 'filter']);
        if ($unknownQueryFields !== []) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Query chứa field không được phép.', array_map(
                static fn (string $field): array => [
                    'field' => $field,
                    'code' => 'FIELD_NOT_ALLOWED',
                    'message' => 'Không được phép gửi field này.',
                ],
                array_values($unknownQueryFields)
            ));
        }

        $rawLimit = $request->queryParam('limit');
        $rawOffset = $request->queryParam('offset');
        $rawFilter = $request->queryParam('filter');

        if ($rawLimit !== null && preg_match('/\A[0-9]+\z/', (string) $rawLimit) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Giới hạn số lượng (limit) không hợp lệ.');
        }
        if ($rawOffset !== null && preg_match('/\A[0-9]+\z/', (string) $rawOffset) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vị trí bắt đầu (offset) không hợp lệ.');
        }

        $limit = $rawLimit !== null ? (int) $rawLimit : 25;
        $offset = $rawOffset !== null ? (int) $rawOffset : 0;
        $filter = $rawFilter !== null ? (string) $rawFilter : 'all';

        if ($limit < 1 || $limit > 100) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Giới hạn số lượng (limit) phải từ 1 đến 100.');
        }
        if ($offset < 0) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vị trí bắt đầu (offset) không được âm.');
        }
        if (!in_array($filter, ['all', 'unread'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Bộ lọc thông báo không hợp lệ.');
        }

        $list = $service->listForUser($identity['user_id'], $limit, $offset, $filter === 'unread');
        $unread = $service->unreadCount($identity['user_id']);
        $preferences = $service->preferencesForStudent($identity['student_id']);

        JsonResponder::sendSuccess([
            'notifications' => $list['items'],
            'unreadCount' => $unread,
            'pagination' => [
                'total' => $list['total'],
                'limit' => $list['limit'],
                'offset' => $list['offset'],
                'hasMore' => $list['hasMore'],
            ],
            'preferences' => $preferences,
        ], $context->requestId());
    }

    if ($request->method === 'PATCH' || $request->method === 'POST') {
        $context->mutation($request->header('x-csrf-token'));
        $raw = $request->json();
        $action = is_string($raw['action'] ?? null) ? $raw['action'] : '';

        if ($action === 'respond-invitation' || $action === 'accept-invitation' || $action === 'decline-invitation') {
            $identity = $context->studentIdentityForPermissions(['notification.mark_read_own']);
            $notificationId = trim((string) ($raw['notificationId'] ?? ''));
            $decision = ($action === 'accept-invitation') ? 'accept' : (($action === 'decline-invitation') ? 'decline' : strtolower(trim((string) ($raw['decision'] ?? 'accept'))));
            $newStatus = ($decision === 'accept') ? 'accepted' : 'declined';

            $pdo = $context->pdo();
            $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND userId = ? LIMIT 1");
            $notifStmt->execute([$notificationId, $identity['user_id']]);
            $notif = $notifStmt->fetch(PDO::FETCH_ASSOC);

            $enterpriseName = 'FPT Software';
            $postTitle = 'Thực tập sinh';
            $applicationId = null;

            if ($notif) {
                $deepLink = (string) ($notif['deepLink'] ?? '');
                $appStmt = $pdo->prepare("
                    SELECT ia.id, ia.postId, ia.status, e.name as enterpriseName, ip.title as postTitle
                    FROM internship_applications ia
                    JOIN student_profiles sp ON sp.id = ia.studentId
                    JOIN internship_posts ip ON ip.id = ia.postId
                    JOIN enterprises e ON e.id = ip.enterpriseId
                    WHERE sp.userId = ? AND (? LIKE CONCAT('%', ia.postId, '%') OR ia.status IN ('invited', 'accepted', 'declined'))
                    ORDER BY ia.updatedAt DESC
                    LIMIT 1
                ");
                $appStmt->execute([$identity['user_id'], $deepLink]);
                $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);

                if ($appRow) {
                    $applicationId = $appRow['id'];
                    $enterpriseName = $appRow['enterpriseName'];
                    $postTitle = $appRow['postTitle'];

                    $updApp = $pdo->prepare("UPDATE internship_applications SET status = ?, updatedAt = NOW(6) WHERE id = ?");
                    $updApp->execute([$newStatus, $applicationId]);
                }

                // Mark notification read
                $pdo->prepare("UPDATE notifications SET readAt = NOW(6) WHERE id = ?")->execute([$notificationId]);
            }

            $successMsg = ($newStatus === 'accepted')
                ? "Bạn đã chấp nhận lời mời thực tập từ {$enterpriseName}!"
                : "Bạn đã từ chối lời mời thực tập.";

            $unread = $service->unreadCount($identity['user_id']);

            JsonResponder::sendSuccess([
                'success' => true,
                'status' => $newStatus,
                'message' => $successMsg,
                'enterpriseName' => $enterpriseName,
                'postTitle' => $postTitle,
                'applicationId' => $applicationId,
                'unreadCount' => $unread,
            ], $context->requestId());
        }

        if ($action === 'mark-read') {
            $identity = $context->studentIdentityForPermissions(['notification.mark_read_own']);
            $input = $context->allowedInput($raw, ['action', 'notificationId']);
            $notificationId = trim((string) ($input['notificationId'] ?? ''));
            if ($notificationId === '') {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Thiếu ID thông báo.');
            }
            $notification = $service->markRead($identity['user_id'], $notificationId);
            $unread = $service->unreadCount($identity['user_id']);
            JsonResponder::sendSuccess([
                'notification' => $notification,
                'unreadCount' => $unread,
            ], $context->requestId());
        }

        if ($action === 'mark-all-read') {
            $identity = $context->studentIdentityForPermissions(['notification.mark_read_own']);
            $input = $context->allowedInput($raw, ['action']);
            $res = $service->markAllRead($identity['user_id']);
            JsonResponder::sendSuccess([
                'markedCount' => $res['markedCount'],
                'unreadCount' => 0,
            ], $context->requestId());
        }

        if ($action === 'update-preference') {
            $identity = $context->studentIdentityForPermissions(['notification.manage_preferences_own']);
            $input = $context->allowedInput($raw, ['action', 'notificationType', 'inAppEnabled', 'emailEnabled']);
            $notificationType = trim((string) ($input['notificationType'] ?? ''));
            if ($notificationType === '') {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Thiếu loại thông báo.');
            }
            if (!array_key_exists('inAppEnabled', $input) || !is_bool($input['inAppEnabled'])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'inAppEnabled phải là giá trị boolean JSON.');
            }
            if (!array_key_exists('emailEnabled', $input) || !is_bool($input['emailEnabled'])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'emailEnabled phải là giá trị boolean JSON.');
            }
            $inAppEnabled = $input['inAppEnabled'];
            $emailEnabled = $input['emailEnabled'];

            $preference = $service->updatePreference($identity['student_id'], $notificationType, $inAppEnabled, $emailEnabled);
            JsonResponder::sendSuccess([
                'preference' => $preference,
            ], $context->requestId());
        }

        throw new ApiException(422, 'VALIDATION_FAILED', 'Action thông báo không hợp lệ.');
    }

    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (\Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'), $context?->requestId() ?? 'request-unavailable');
}
