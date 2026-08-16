<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

use JsonException;

final class RecommendationInput
{
    private const SCHEMA_VERSION = '1.0';
    private const PRIVATE_KEYS = [
        'email', 'phone', 'dateofbirth', 'birthdate', 'name', 'fullname', 'token', 'password', 'cvurl',
        'studentid', 'teacherid', 'userid',
    ];

    private readonly array $payload;
    private readonly array $sourceUpdatedAt;
    private readonly array $qualityFlags;
    private readonly array $evidenceReferences;
    private readonly string $canonicalJson;
    private readonly string $contentHash;

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $sourceUpdatedAt
     * @param array<string,mixed> $qualityFlags
     * @param list<array{source_type:string,source_id:string,observed_at:?string}> $evidenceReferences
     */
    public function __construct(array $payload, array $sourceUpdatedAt, array $qualityFlags, array $evidenceReferences)
    {
        $this->payload = self::normalize($payload);
        $this->sourceUpdatedAt = self::normalize($sourceUpdatedAt);
        $this->qualityFlags = self::normalize($qualityFlags);
        $this->evidenceReferences = self::normalize($evidenceReferences);
        $canonical = [
            'schema_version' => self::SCHEMA_VERSION,
            'payload' => $this->payload,
            'source_updated_at' => $this->sourceUpdatedAt,
            'quality_flags' => $this->qualityFlags,
            'evidence_references' => $this->evidenceReferences,
        ];
        $this->canonicalJson = self::encode($canonical);
        $this->contentHash = hash('sha256', $this->canonicalJson);
    }

    public function schemaVersion(): string
    {
        return self::SCHEMA_VERSION;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function canonicalJson(): string
    {
        return $this->canonicalJson;
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    /** @return array<string,string> */
    public function sourceUpdatedAt(): array
    {
        return $this->sourceUpdatedAt;
    }

    /** @return array<string,mixed> */
    public function qualityFlags(): array
    {
        return $this->qualityFlags;
    }

    /** @return list<array{source_type:string,source_id:string,observed_at:?string}> */
    public function evidenceReferences(): array
    {
        return $this->evidenceReferences;
    }

    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $child) {
            if (is_string($key) && self::isPrivateKey($key)) {
                continue;
            }
            $normalized[$key] = self::normalize($child);
        }
        if (array_is_list($normalized)) {
            usort($normalized, static fn (mixed $left, mixed $right): int => self::encode($left) <=> self::encode($right));
            return $normalized;
        }

        uksort($normalized, static fn (string|int $left, string|int $right): int => (string) $left <=> (string) $right);
        return $normalized;
    }

    private static function isPrivateKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
        foreach (self::PRIVATE_KEYS as $privateKey) {
            if ($normalized === $privateKey || str_contains($normalized, $privateKey)) {
                return true;
            }
        }
        return false;
    }

    private static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Recommendation input must be JSON serializable.', 0, $exception);
        }
    }
}
