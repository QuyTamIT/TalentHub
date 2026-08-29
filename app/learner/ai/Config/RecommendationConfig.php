<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Config;

final class RecommendationConfig
{
    /** @var list<string> Environments where strict mode is mandatory and cannot be
     * turned off via the override. */
    private const STRICT_ENFORCED_ENVIRONMENTS = ['production', 'staging'];

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
        private readonly int $roadmapTimeoutSeconds,
        private readonly int $roadmapPerStudentLimit,
        private readonly int $roadmapGlobalLimit,
        private readonly bool $shadowEnabled,
        private readonly bool $shadowGateApproved,
        private readonly int $visiblePercent,
        private readonly ?string $pilotApprovalReference,
        private readonly bool $pilotPaused,
        private readonly bool $strictMode,
        private readonly bool $strictModeOverrideRejected,
        private readonly string $environment,
    ) {
    }

    /** @param array<string,string> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $enabled = strtolower(self::value($environment, 'TALENTHUB_AI_ENABLED', 'false')) === 'true';
        if (!$enabled) {
            return new self(
                false,
                null,
                null,
                null,
                null,
                [],
                2,
                1,
                1,
                1,
                30,
                2,
                20,
                false,
                false,
                0,
                null,
                true,
                self::resolveStrictMode($environment, false),
                self::resolveStrictModeOverrideRejected($environment, false),
                self::resolveEnvironment($environment),
            );
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
            self::boundedInt($environment, 'TALENTHUB_AI_ROADMAP_TIMEOUT_SECONDS', 30, 1, 60),
            self::boundedInt($environment, 'TALENTHUB_AI_ROADMAP_PER_STUDENT_LIMIT', 2, 1, 60),
            self::boundedInt($environment, 'TALENTHUB_AI_ROADMAP_GLOBAL_LIMIT', 20, 1, 600),
            strtolower(self::value($environment, 'TALENTHUB_AI_SHADOW', 'false')) === 'true',
            strtolower(self::value($environment, 'TALENTHUB_AI_SHADOW_GATE_APPROVED', 'false')) === 'true',
            self::boundedInt($environment, 'TALENTHUB_AI_VISIBLE_PERCENT', 0, 0, 100),
            self::optional($environment, 'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE'),
            self::strictBoolean($environment, 'TALENTHUB_AI_PILOT_PAUSED', true),
            self::resolveStrictMode($environment, true),
            self::resolveStrictModeOverrideRejected($environment, true),
            self::resolveEnvironment($environment),
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
    public function roadmapTimeoutSeconds(): int { return $this->roadmapTimeoutSeconds; }
    public function roadmapPerStudentLimit(): int { return $this->roadmapPerStudentLimit; }
    public function roadmapGlobalLimit(): int { return $this->roadmapGlobalLimit; }
    public function shadowEnabled(): bool { return $this->shadowEnabled; }
    public function shadowGateApproved(): bool { return $this->shadowGateApproved; }
    public function visiblePercent(): int { return $this->visiblePercent; }
    public function pilotApprovalReference(): ?string { return $this->pilotApprovalReference; }
    public function pilotPaused(): bool { return $this->pilotPaused; }
    public function strictMode(): bool { return $this->strictMode; }
    public function strictModeOverrideRejected(): bool { return $this->strictModeOverrideRejected; }
    public function environment(): string { return $this->environment; }

    /** @return array{enabled:bool,provider:?string,model:?string,timeout_seconds:int,strict_mode:bool,strict_mode_override_rejected:bool,environment:string} */
    public function diagnostics(): array
    {
        return [
            'enabled' => $this->enabled,
            'provider' => $this->provider,
            'model' => $this->model,
            'timeout_seconds' => $this->timeoutSeconds,
            'strict_mode' => $this->strictMode,
            'strict_mode_override_rejected' => $this->strictModeOverrideRejected,
            'environment' => $this->environment,
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
    private static function optional(array $environment, string $key): ?string
    {
        $value = self::value($environment, $key);
        return $value === '' ? null : $value;
    }

    /** @param array<string,string> $environment */
    private static function strictBoolean(array $environment, string $key, bool $default): bool
    {
        $raw = strtolower(self::value($environment, $key, $default ? 'true' : 'false'));
        if (!in_array($raw, ['true', 'false'], true)) throw new \InvalidArgumentException("{$key} must be true or false.");
        return $raw === 'true';
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

    /** @param array<string,string> $environment */
    private static function resolveEnvironment(array $environment): string
    {
        $raw = strtolower(self::value($environment, 'APP_ENV', 'production'));
        return in_array($raw, self::STRICT_ENFORCED_ENVIRONMENTS, true) || in_array($raw, ['local', 'test'], true)
            ? $raw
            : 'production';
    }

    /**
     * Resolves the effective strict-mode flag.
     *
     * @param array<string,string> $environment
     * @param bool $aiEnabled Whether the AI feature is currently enabled.
     */
    private static function resolveStrictMode(array $environment, bool $aiEnabled): bool
    {
        $environmentName = self::resolveEnvironment($environment);
        $enforced = in_array($environmentName, self::STRICT_ENFORCED_ENVIRONMENTS, true);
        $overrideRaw = strtolower(self::value($environment, 'TALENTHUB_AI_STRICT_MODE_OVERRIDE', ''));
        $override = $overrideRaw === 'true' || $overrideRaw === 'false' ? $overrideRaw === 'true' : null;
        if (!$aiEnabled) {
            // AI is disabled entirely; strict mode is moot but reported as false
            // to avoid surprising operators when they turn the feature back on.
            return false;
        }
        if ($enforced) {
            return true;
        }
        // Local/test environments default to strict mode and may opt out only
        // when the dedicated override flag is explicitly set to "false".
        if ($override === null) {
            return true;
        }
        return $override;
    }

    /**
     * Reports whether an override attempt was rejected. Useful for surfacing
     * misconfiguration in diagnostics. Only meaningful in production/staging
     * where overrides are always ignored.
     *
     * @param array<string,string> $environment
     * @param bool $aiEnabled
     */
    private static function resolveStrictModeOverrideRejected(array $environment, bool $aiEnabled): bool
    {
        $environmentName = self::resolveEnvironment($environment);
        if (!in_array($environmentName, self::STRICT_ENFORCED_ENVIRONMENTS, true)) {
            return false;
        }
        if (!$aiEnabled) {
            return false;
        }
        $overrideRaw = strtolower(self::value($environment, 'TALENTHUB_AI_STRICT_MODE_OVERRIDE', ''));
        if ($overrideRaw !== 'true' && $overrideRaw !== 'false') {
            return false;
        }
        // Anything other than "true" in a strict-enforced environment is
        // treated as an override attempt that has been ignored.
        return $overrideRaw !== 'true';
    }
}
