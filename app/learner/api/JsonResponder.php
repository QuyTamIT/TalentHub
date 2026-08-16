<?php

declare(strict_types=1);

namespace TalentHub\Learner\Api;

use JsonException;
use TalentHub\Http\ApiException;

final class JsonResponder
{
    /** @return array{status:int,payload:array<string,mixed>} */
    public static function success(mixed $data, string $requestId, int $status = 200): array
    {
        return ['status' => $status, 'payload' => ['data' => $data, 'meta' => self::meta($requestId)]];
    }

    /** @return array{status:int,payload:array<string,mixed>} */
    public static function error(ApiException $exception, string $requestId): array
    {
        $error = ['code' => $exception->errorCode, 'message' => $exception->getMessage()];
        if ($exception->details !== []) {
            $error['details'] = $exception->details;
        }
        return ['status' => $exception->status, 'payload' => ['error' => $error, 'meta' => self::meta($requestId)]];
    }

    public static function sendSuccess(mixed $data, string $requestId, int $status = 200): never
    {
        self::send(self::success($data, $requestId, $status));
    }

    public static function sendError(ApiException $exception, string $requestId): never
    {
        self::send(self::error($exception, $requestId));
    }

    /** @param array{status:int,payload:array<string,mixed>} $response */
    private static function send(array $response): never
    {
        http_response_code($response['status']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Request-Id: ' . (string) $response['payload']['meta']['requestId']);
        try {
            echo json_encode($response['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            echo json_encode([
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Đã xảy ra lỗi hệ thống.'],
                'meta' => $response['payload']['meta'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** @return array{requestId:string,timestamp:string} */
    private static function meta(string $requestId): array
    {
        return ['requestId' => $requestId, 'timestamp' => gmdate('Y-m-d\TH:i:s\Z')];
    }
}
