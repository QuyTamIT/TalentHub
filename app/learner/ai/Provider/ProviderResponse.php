<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

final class ProviderResponse
{
    /** @var list<array<string,mixed>> */
    private readonly array $items;

    /** @param list<array<string,mixed>> $items */
    private function __construct(
        private readonly bool $success,
        array $items,
        private readonly ?string $errorCode,
        private readonly ?int $retryAfterSeconds,
    ) {
        $this->items = array_values($items);
    }

    /** @param list<array<string,mixed>> $items */
    public static function success(array $items): self
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Provider items must be structured arrays.');
            }
        }
        return new self(true, $items, null, null);
    }

    public static function failure(string $errorCode, ?int $retryAfterSeconds = null): self
    {
        return new self(false, [], trim($errorCode) === '' ? 'provider_unavailable' : trim($errorCode), $retryAfterSeconds);
    }

    public function isSuccess(): bool { return $this->success; }
    /** @return list<array<string,mixed>> */
    public function items(): array { return $this->items; }
    public function errorCode(): ?string { return $this->errorCode; }
    public function retryAfterSeconds(): ?int { return $this->retryAfterSeconds; }
}
