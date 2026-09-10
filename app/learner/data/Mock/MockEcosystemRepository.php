<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use TalentHub\Learner\Data\Contracts\EcosystemRepository;
use TalentHub\Learner\Data\Enums\OpportunityStatus;
use TalentHub\Learner\Data\Support\MockRecordNormalizer;

final class MockEcosystemRepository implements EcosystemRepository
{
    private array $partners;
    private array $opportunityRecords;

    public function __construct(array $partners, array $opportunities)
    {
        $this->partners = array_map([$this, 'normalizePartner'], $partners);
        $this->opportunityRecords = array_map([$this, 'normalizeOpportunity'], $opportunities);
    }

    public function partners(?string $type = null, ?string $schoolId = null): array
    {
        if ($type === null) {
            return $this->partners;
        }

        return array_values(array_filter(
            $this->partners,
            static fn (array $partner): bool => ($partner['type'] ?? '') === $type
        ));
    }

    public function opportunities(?string $studentId = null): array
    {
        return $this->opportunityRecords;
    }

    public function findPartner(string $type, string $partnerId): ?array
    {
        foreach ($this->partners($type) as $partner) {
            if (MockRecordNormalizer::matches($partner, $partnerId)
                || MockRecordNormalizer::matches($partner, $partnerId, $type . '_id')) {
                return $partner;
            }
        }

        return null;
    }

    public function findOpportunity(string $type, string $opportunityId): ?array
    {
        foreach ($this->opportunityRecords as $opportunity) {
            if (($opportunity['type'] ?? '') === $type
                && MockRecordNormalizer::matches($opportunity, $opportunityId)) {
                return $opportunity;
            }
        }

        return null;
    }

    public function opportunitiesForPartner(string $partnerId, bool $activeOnly = false, ?string $studentId = null): array
    {
        return array_values(array_filter(
            $this->opportunityRecords,
            static fn (array $opportunity): bool => (
                (string) ($opportunity['partner_id'] ?? '') === (string) $partnerId
                || (string) ($opportunity['legacy_partner_id'] ?? '') === (string) $partnerId
            ) && (!$activeOnly || ($opportunity['status'] ?? '') === OpportunityStatus::Active->value)
        ));
    }

    private function normalizePartner(array $partner): array
    {
        $type = ($partner['type'] ?? '') === 'school' ? 'school' : 'enterprise';
        $partner = MockRecordNormalizer::primary($partner, $type);
        if (isset($partner['id'])) {
            $partner['legacy_' . $type . '_id'] = $partner['legacy_id'];
            $partner[$type . '_id'] = $partner['id'];
        }

        return $partner;
    }

    private function normalizeOpportunity(array $opportunity): array
    {
        $opportunity = MockRecordNormalizer::primary($opportunity, 'opportunity');
        $partnerType = ($opportunity['partner_type'] ?? '') === 'school' ? 'school' : 'enterprise';
        $opportunity = MockRecordNormalizer::foreign($opportunity, 'partner_id', $partnerType);
        if (isset($opportunity['partner_id'])) {
            $opportunity[$partnerType . '_id'] = $opportunity['partner_id'];
            $opportunity['legacy_' . $partnerType . '_id'] = $opportunity['legacy_partner_id'];
        }
        $opportunity['status'] = OpportunityStatus::normalize($opportunity['status'] ?? null)->value;

        $rawAppStatus = $opportunity['application_status'] ?? null;
        $opportunity['application_status'] = is_string($rawAppStatus) && $rawAppStatus !== '' ? $rawAppStatus : null;
        $opportunity['user_participation_status'] = match ($rawAppStatus) {
            'submitted', 'reviewing', 'interview' => 'applied',
            'accepted' => 'interning',
            'completed' => 'completed',
            default => 'open',
        };
        $opportunity['membership_status'] = match ($opportunity['user_participation_status']) {
            'applied', 'interning' => 'active',
            'completed' => 'completed',
            default => 'recruiting',
        };

        return $opportunity;
    }
}
