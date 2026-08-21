<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Config;

final class RecommendationConfig
{
    /** @param list<string> $allowedHosts */
    private function __construct(
        private readonly bool $enabled,
        private readonly ?string $provider,
        private readonly ?string $model,
        private readonly ?string $apiUrl,
        private readonly ?string $apiKey,
        private readonly array $allowedHosts,
        private readonly int $timeoutSeconds,
        private readonly int $maxAttempts,
        private readonly int $perStudentLimit,
        private readonly int $globalLimit,
        private readonly bool $shadowEnabled,
        private readonly bool $shadowGateApproved,
        private readonly int $visiblePercent,
    ) {
    }

    /** @param array<string,string> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $enabled = strtolower(self::value($environment, 'TALENTHUB_AI_ENABLED', 'false')) === 'true';
        if (!$enabled) {
            return new self(false, null, null, null, null, [], 2, 1, 1, 1, false, false, 0);
        }

        $provider = self::required($environment, 'TALENTHUB_AI_PROVIDER');
        $model = self::required($environment, 'TALENTHUB_AI_MODEL');
        $apiUrl = self::required($environment, 'TALENTHUB_AI_API_URL');
        $apiKey = self::required($environment, 'TALENTHUB_AI_API_KEY');
        $allowedHosts = array_values(array_unique(array_filter(array_map(
            static fn (string $host): string => self::normalizeHost($host),
            explode(',', self::required($environment, 'TALENTHUB_AI_ALLOWED_HOSTS')),
        ), static fn (string $host): bool => $host !== '')));
        $parts = parse_url($apiUrl);
        if (!is_array($parts) || !self::isApprovedApiUrl($parts, $allowedHosts, $environment)) {
            throw new \InvalidArgumentException(
                'AI provider URL must use an approved HTTPS host or an approved local loopback endpoint.',
            );
        }

        return new self(
            true,
            $provider,
            $model,
            $apiUrl,
            $apiKey,
            $allowedHosts,
            self::boundedInt($environment, 'TALENTHUB_AI_TIMEOUT_SECONDS', 2, 1, 30),
            self::boundedInt($environment, 'TALENTHUB_AI_MAX_ATTEMPTS', 1, 1, 2),
            self::boundedInt($environment, 'TALENTHUB_AI_PER_STUDENT_LIMIT', 2, 1, 60),
            self::boundedInt($environment, 'TALENTHUB_AI_GLOBAL_LIMIT', 20, 1, 600),
            strtolower(self::value($environment, 'TALENTHUB_AI_SHADOW', 'false')) === 'true',
            strtolower(self::value($environment, 'TALENTHUB_AI_SHADOW_GATE_APPROVED', 'false')) === 'true',
            self::boundedInt($environment, 'TALENTHUB_AI_VISIBLE_PERCENT', 0, 0, 100),
        );
    }

    public function enabled(): bool { return $this->enabled; }
    public function provider(): ?string { return $this->provider; }
    public function model(): ?string { return $this->model; }
    public function apiUrl(): ?string { return $this->apiUrl; }
    public function apiKey(): ?string { return $this->apiKey; }
    public function timeoutSeconds(): int { return $this->timeoutSeconds; }
    public function maxAttempts(): int { return $this->maxAttempts; }
    public function perStudentLimit(): int { return $this->perStudentLimit; }
    public function globalLimit(): int { return $this->globalLimit; }
    public function shadowEnabled(): bool { return $this->shadowEnabled; }
    public function shadowGateApproved(): bool { return $this->shadowGateApproved; }
    public function visiblePercent(): int { return $this->visiblePercent; }

    /** @return array{enabled:bool,provider:?string,model:?string,timeout_seconds:int} */
    public function diagnostics(): array
    {
        return [
            'enabled' => $this->enabled,
            'provider' => $this->provider,
            'model' => $this->model,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }

    /**
     * @param array<string,mixed> $parts
     * @param list<string> $allowedHosts
     * @param array<string,string> $environment
     */
    private static function isApprovedApiUrl(array $parts, array $allowedHosts, array $environment): bool
    {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = self::normalizeHost((string) ($parts['host'] ?? ''));
        if ($host === '' || !in_array($host, $allowedHosts, true)) {
            return false;
        }
        if ($scheme === 'https') {
            return true;
        }

        $appEnv = strtolower(self::value($environment, 'APP_ENV', 'production'));
        return $scheme === 'http'
            && in_array($appEnv, ['local', 'test'], true)
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ($parts['port'] ?? null) === 20128
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(trim(trim($host), '[]'));
    }

    /** @param array<string,string> $environment */
    private static function value(array $environment, string $key, string $default = ''): string
    {
        if (array_key_exists($key, $environment)) {
            return trim((string) $environment[$key]);
        }
        $value = getenv($key);
        return $value === false ? $default : trim((string) $value);
    }

    /** @param array<string,string> $environment */
    private static function required(array $environment, string $key): string
    {
        $value = self::value($environment, $key);
        if ($value === '') {
            throw new \InvalidArgumentException("{$key} is required when AI is enabled.");
        }
        return $value;
    }

    /** @param array<string,string> $environment */
    private static function boundedInt(array $environment, string $key, int $default, int $minimum, int $maximum): int
    {
        $raw = self::value($environment, $key, (string) $default);
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException("{$key} must be an integer.");
        }
        $value = (int) $raw;
        if ($value < $minimum || $value > $maximum) {
            throw new \InvalidArgumentException("{$key} is outside its approved range.");
        }
        return $value;
    }
}
