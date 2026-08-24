<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Quality;

final class DataQualityResult
{
    /** @param list<string> $missingConsentScopes @param list<string> $missingCategories @param list<array<string,string>> $completionActions */
    public function __construct(
        private readonly string $state,
        private readonly array $missingConsentScopes = [],
        private readonly array $missingCategories = [],
        private readonly array $completionActions = [],
    ) {
    }

    public function state(): string
    {
        return $this->state;
    }

    /** @return list<string> */
    public function missingConsentScopes(): array
    {
        return $this->missingConsentScopes;
    }

    /** @return list<string> */
    public function missingCategories(): array
    {
        return $this->missingCategories;
    }

    /** @return list<array<string,string>> */
    public function completionActions(): array
    {
        return $this->completionActions;
    }
}
