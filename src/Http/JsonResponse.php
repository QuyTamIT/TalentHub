<?php
declare(strict_types=1);
namespace TalentHub\Http;

final class JsonResponse
{
    /** @param array<string,mixed> $payload */
    /** @param array<string,string> $headers */
    public function __construct(public readonly int $status, public readonly array $payload, public readonly string $requestId,public readonly array $headers=[]) {}

    public static function success(mixed $data,string $requestId,int $status=200): self
    { return new self($status,['data'=>$data,'meta'=>self::meta($requestId)],$requestId); }

    public static function error(ApiException $e, string $requestId, ?\Throwable $debugException = null): self
    {
        $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        if ($e->details !== []) {
            $error['details'] = $e->details;
        }

        $payload = [
            'error' => $error,
            'meta' => self::meta($requestId),
        ];

        $target = $debugException ?? ($e->getPrevious() ?: ($e->status >= 500 ? $e : null));
        if ($target !== null) {
            $payload['debug'] = [
                'exception' => get_class($target),
                'message' => $target->getMessage(),
                'file' => $target->getFile(),
                'line' => $target->getLine(),
                'trace' => array_slice(explode("\n", $target->getTraceAsString()), 0, 15),
            ];
        }

        return new self($e->status, $payload, $requestId, $e->headers);
    }

    public function send(): never
    {
        http_response_code($this->status);header('Content-Type: application/json; charset=utf-8');header('X-Request-Id: '.$this->requestId);header('Cache-Control: no-store');foreach($this->headers as $name=>$value){if(preg_match('/^[A-Za-z0-9-]+$/',$name)===1&&!str_contains($value,"\r")&&!str_contains($value,"\n")){header($name.': '.$value);}}
        echo json_encode($this->payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
    }

    /** @return array{requestId:string,timestamp:string} */
    private static function meta(string $requestId): array{return ['requestId'=>$requestId,'timestamp'=>gmdate('Y-m-d\TH:i:s\Z')];}
}
