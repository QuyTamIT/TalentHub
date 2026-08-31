<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface EcosystemRepository
{
    public function partners(?string $type = null, ?string $schoolId = null): array;

    public function opportunities(): array;

    public function findPartner(string $type, string $partnerId): ?array;

    public function findOpportunity(string $type, string $opportunityId): ?array;

    public function opportunitiesForPartner(string $partnerId, bool $activeOnly = false): array;
}
