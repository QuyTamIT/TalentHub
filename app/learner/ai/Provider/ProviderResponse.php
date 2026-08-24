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
        private readonly ?string $safeStatusClass,
        private readonly ?array $usage,
    ) {
        $this->items = array_values($items);
    }

    /** @param list<array<string,mixed>> $items */
    public static function success(array $items, ?array $usage = null): self
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Provider items must be structured arrays.');
            }
        }
        return new self(true, $items, null, null, '2xx', self::normalizeUsage($usage));
    }

    public static function failure(string $errorCode, ?int $retryAfterSeconds = null, ?string $safeStatusClass = null): self
    {
        return new self(false, [], trim($errorCode) === '' ? 'provider_unavailable' : trim($errorCode), $retryAfterSeconds, $safeStatusClass, null);
    }

    public function isSuccess(): bool { return $this->success; }
    /** @return list<array<string,mixed>> */
    public function items(): array { return $this->items; }
    public function errorCode(): ?string { return $this->errorCode; }
    public function retryAfterSeconds(): ?int { return $this->retryAfterSeconds; }
    public function safeStatusClass(): ?string { return $this->safeStatusClass; }
    /** @return array{input_tokens:int,output_tokens:int,estimated_cost:?float,currency:?string}|null */
    public function usage(): ?array { return $this->usage; }
    /** @return array<string,mixed> */
    public function safeMetadata(): array { return array_filter([
        'error_code' => $this->errorCode, 'retry_after_seconds' => $this->retryAfterSeconds,
        'status_class' => $this->safeStatusClass, 'usage' => $this->usage,
    ], static fn(mixed $value): bool => $value !== null); }

    /** @param array<string,mixed>|null $usage @return array{input_tokens:int,output_tokens:int,estimated_cost:?float,currency:?string}|null */
    private static function normalizeUsage(?array $usage): ?array
    {
        if ($usage === null) return null;
        $input = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null;
        if (!is_int($input) || $input < 0 || !is_int($output) || $output < 0) return null;
        $cost = $usage['estimated_cost'] ?? null; $currency = $usage['currency'] ?? null;
        if ($cost !== null && (!is_numeric($cost) || (float)$cost < 0)) return null;
        if (($cost === null) !== ($currency === null) || ($currency !== null && preg_match('/\A[A-Z]{3}\z/', (string)$currency) !== 1)) return null;
        return ['input_tokens'=>$input, 'output_tokens'=>$output, 'estimated_cost'=>$cost === null ? null : (float)$cost, 'currency'=>$currency === null ? null : (string)$currency];
    }
}
