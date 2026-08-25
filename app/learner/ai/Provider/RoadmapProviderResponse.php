<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

final class RoadmapProviderResponse
{
    /** @param array<string,mixed> $payload */
    private function __construct(
        private readonly bool $success,
        private readonly array $payload,
        private readonly ?string $errorCode,
        private readonly ?int $retryAfterSeconds,
        private readonly ?string $safeStatusClass,
        private readonly ?string $providerRequestId,
        private readonly ?string $responseHash,
    ) {}

    /** @param array<string,mixed> $payload */
    public static function success(array $payload, ?string $providerRequestId, string $responseHash): self
    {
        if ($payload === [] || preg_match('/\A[a-f0-9]{64}\z/', $responseHash) !== 1) {
            throw new \InvalidArgumentException('Roadmap provider success metadata is invalid.');
        }
        return new self(true, $payload, null, null, '2xx', self::safeRequestId($providerRequestId), $responseHash);
    }

    public static function failure(
        string $errorCode,
        ?int $retryAfterSeconds = null,
        ?string $safeStatusClass = null,
        ?string $providerRequestId = null,
        ?string $responseHash = null,
    ): self {
        return new self(
            false,
            [],
            trim($errorCode) === '' ? 'provider_unavailable' : trim($errorCode),
            $retryAfterSeconds,
            $safeStatusClass,
            self::safeRequestId($providerRequestId),
            is_string($responseHash) && preg_match('/\A[a-f0-9]{64}\z/', $responseHash) === 1 ? $responseHash : null,
        );
    }

    public function isSuccess(): bool { return $this->success; }
    /** @return array<string,mixed> */ public function payload(): array { return $this->payload; }
    public function errorCode(): ?string { return $this->errorCode; }
    public function retryAfterSeconds(): ?int { return $this->retryAfterSeconds; }
    public function safeStatusClass(): ?string { return $this->safeStatusClass; }
    public function providerRequestId(): ?string { return $this->providerRequestId; }
    public function responseHash(): ?string { return $this->responseHash; }

    /** @return array<string,mixed> */
    public function safeMetadata(): array
    {
        return array_filter([
            'error_code' => $this->errorCode,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'status_class' => $this->safeStatusClass,
            'provider_request_id' => $this->providerRequestId,
            'response_hash' => $this->responseHash,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private static function safeRequestId(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' && preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $value) === 1 ? $value : null;
    }
}
