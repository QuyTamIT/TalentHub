<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rules;

use Closure;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;

final class RuleDefinition
{
    /** @var list<string> */
    private readonly array $requiredScopes;

    /** @var Closure(array<string,mixed>):bool */
    private readonly Closure $predicate;

    /** @var Closure(array<string,mixed>):list<array<string,mixed>> */
    private readonly Closure $itemBuilder;

    /** @var Closure(array<string,mixed>,array<string,mixed>):list<RecommendationEvidence> */
    private readonly Closure $evidenceMapper;

    /**
     * @param list<string> $requiredScopes
     * @param Closure(array<string,mixed>):bool $predicate
     * @param Closure(array<string,mixed>):list<array<string,mixed>> $itemBuilder
     * @param Closure(array<string,mixed>,array<string,mixed>):list<RecommendationEvidence> $evidenceMapper
     */
    public function __construct(
        private readonly string $id,
        private readonly string $version,
        array $requiredScopes,
        private readonly int $priority,
        Closure $predicate,
        Closure $itemBuilder,
        Closure $evidenceMapper,
    ) {
        if (trim($id) === '' || trim($version) === '' || $priority < 1 || $priority > 100) {
            throw new \InvalidArgumentException('Recommendation rule definition is invalid.');
        }
        $scopes = [];
        foreach ($requiredScopes as $scope) {
            if (is_string($scope) && trim($scope) !== '') {
                $scopes[trim($scope)] = true;
            }
        }
        $normalizedScopes = array_keys($scopes);
        sort($normalizedScopes, SORT_STRING);
        $this->requiredScopes = $normalizedScopes;
        $this->predicate = $predicate;
        $this->itemBuilder = $itemBuilder;
        $this->evidenceMapper = $evidenceMapper;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return list<string> */
    public function requiredScopes(): array
    {
        return $this->requiredScopes;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /** @param array<string,mixed> $facts */
    public function matches(array $facts): bool
    {
        return ($this->predicate)($facts);
    }

    /** @param array<string,mixed> $facts @return list<array<string,mixed>> */
    public function buildItems(array $facts): array
    {
        return ($this->itemBuilder)($facts);
    }

    /** @param array<string,mixed> $facts @param array<string,mixed> $item @return list<RecommendationEvidence> */
    public function mapEvidence(array $facts, array $item): array
    {
        return ($this->evidenceMapper)($facts, $item);
    }
}
