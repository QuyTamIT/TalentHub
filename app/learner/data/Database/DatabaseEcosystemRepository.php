<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\EcosystemRepository;
use TalentHub\Learner\Data\Enums\OpportunityStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseEcosystemRepository extends AbstractDatabaseRepository implements EcosystemRepository
{
    private const SCHOOL_VISIBLE_SQL = 'status = :school_status';
    private const SCHOOLS_SQL = 'SELECT id, name, status FROM schools WHERE ' . self::SCHOOL_VISIBLE_SQL . ' ORDER BY name, id';
    private const SCHOOL_SQL = 'SELECT id, name, status FROM schools WHERE id = :partner_id AND ' . self::SCHOOL_VISIBLE_SQL . ' LIMIT 1';
    private const ENTERPRISE_COLUMNS = 'id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt';
    private const ENTERPRISE_VISIBLE_SQL = 'status = :enterprise_status AND verificationStatus IN (:verification_verified, :verification_approved)';
    private const ENTERPRISES_SQL = 'SELECT ' . self::ENTERPRISE_COLUMNS . ' FROM enterprises WHERE ' . self::ENTERPRISE_VISIBLE_SQL . ' ORDER BY name, id';
    private const ENTERPRISE_SQL = 'SELECT ' . self::ENTERPRISE_COLUMNS . ' FROM enterprises WHERE id = :partner_id AND ' . self::ENTERPRISE_VISIBLE_SQL . ' LIMIT 1';
    private const OPPORTUNITY_COLUMNS = 'ip.id, ip.enterpriseId, ip.title, ip.field, ip.location, ip.workType, ip.duration, ip.educationLevel, ip.description, ip.benefits, ip.skillsJson, ip.requirementsJson, ip.slots, ip.deadline, ip.createdAt, ip.updatedAt, ip.status, e.name AS enterpriseName';
    private const OPPORTUNITY_VISIBLE_SQL = 'ip.status = :opportunity_status AND ip.deadline >= CURRENT_TIMESTAMP AND e.status = :enterprise_status AND e.verificationStatus IN (:verification_verified, :verification_approved)';
    private const OPPORTUNITIES_SQL = 'SELECT ' . self::OPPORTUNITY_COLUMNS . ' FROM internship_posts ip INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ' . self::OPPORTUNITY_VISIBLE_SQL . ' ORDER BY ip.deadline, ip.id';
    private const OPPORTUNITY_SQL = 'SELECT ' . self::OPPORTUNITY_COLUMNS . ' FROM internship_posts ip INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ip.id = :opportunity_id AND ' . self::OPPORTUNITY_VISIBLE_SQL . ' LIMIT 1';
    private const PARTNER_OPPORTUNITIES_SQL = 'SELECT ' . self::OPPORTUNITY_COLUMNS . ' FROM internship_posts ip INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ip.enterpriseId = :enterprise_id AND ' . self::OPPORTUNITY_VISIBLE_SQL . ' ORDER BY ip.deadline, ip.id';

    public function partners(?string $type = null): array
    {
        if ($type === 'school') {
            return array_map(
                [$this, 'normalizeSchool'],
                $this->fetchAll('partners.school', self::SCHOOLS_SQL, $this->schoolVisibilityParameters())
            );
        }
        if ($type === 'enterprise') {
            return array_map(
                [$this, 'normalizeEnterprise'],
                $this->fetchAll('partners.enterprise', self::ENTERPRISES_SQL, $this->enterpriseVisibilityParameters())
            );
        }
        if ($type !== null) {
            return [];
        }

        return array_merge($this->partners('school'), $this->partners('enterprise'));
    }

    public function opportunities(): array
    {
        return array_map(
            [$this, 'normalizeOpportunity'],
            $this->fetchAll('opportunities', self::OPPORTUNITIES_SQL, $this->opportunityVisibilityParameters())
        );
    }

    public function findPartner(string $type, string $partnerId): ?array
    {
        $partnerId = Uuid::normalizeDatabase($partnerId, $type . '_id');
        if ($type === 'school') {
            $partner = $this->fetchOne(
                'findPartner.school',
                self::SCHOOL_SQL,
                ['partner_id' => $partnerId] + $this->schoolVisibilityParameters()
            );
            return $partner === null ? null : $this->normalizeSchool($partner);
        }
        if ($type === 'enterprise') {
            $partner = $this->fetchOne(
                'findPartner.enterprise',
                self::ENTERPRISE_SQL,
                ['partner_id' => $partnerId] + $this->enterpriseVisibilityParameters()
            );
            return $partner === null ? null : $this->normalizeEnterprise($partner);
        }

        return null;
    }

    public function findOpportunity(string $type, string $opportunityId): ?array
    {
        if ($type !== 'internship') {
            return null;
        }
        $opportunityId = Uuid::normalizeDatabase($opportunityId, 'opportunity_id');
        $opportunity = $this->fetchOne(
            'findOpportunity',
            self::OPPORTUNITY_SQL,
            ['opportunity_id' => $opportunityId] + $this->opportunityVisibilityParameters()
        );

        return $opportunity === null ? null : $this->normalizeOpportunity($opportunity);
    }

    public function opportunitiesForPartner(string $partnerId, bool $activeOnly = false): array
    {
        $partnerId = Uuid::normalizeDatabase($partnerId, 'enterprise_id');
        return array_map(
            [$this, 'normalizeOpportunity'],
            $this->fetchAll(
                'opportunitiesForPartner',
                self::PARTNER_OPPORTUNITIES_SQL,
                ['enterprise_id' => $partnerId] + $this->opportunityVisibilityParameters()
            )
        );
    }

    private function schoolVisibilityParameters(): array
    {
        return ['school_status' => 'active'];
    }

    private function enterpriseVisibilityParameters(): array
    {
        return [
            'enterprise_status' => 'active',
            'verification_verified' => 'verified',
            'verification_approved' => 'approved',
        ];
    }

    private function opportunityVisibilityParameters(): array
    {
        return ['opportunity_status' => OpportunityStatus::Active->value]
            + $this->enterpriseVisibilityParameters();
    }

    private function normalizeSchool(array $school): array
    {
        $school['id'] = Uuid::normalizeDatabase((string) $school['id'], 'schools.id');
        $school['school_id'] = $school['id'];
        $school['type'] = 'school';
        $school['id_origin'] = 'database';

        return $school;
    }

    private function normalizeEnterprise(array $enterprise): array
    {
        $enterprise['id'] = Uuid::normalizeDatabase((string) $enterprise['id'], 'enterprises.id');
        $enterprise['enterprise_id'] = $enterprise['id'];
        $enterprise['type'] = 'enterprise';
        $enterprise['id_origin'] = 'database';

        if (($enterprise['verified_by'] ?? null) !== null) {
            $enterprise['verified_by'] = Uuid::normalizeDatabase(
                (string) $enterprise['verified_by'],
                'enterprises.verifiedBy'
            );
        }

        return $enterprise;
    }

    private function normalizeOpportunity(array $opportunity): array
    {
        $opportunity['id'] = Uuid::normalizeDatabase((string) $opportunity['id'], 'internship_posts.id');
        $opportunity['enterprise_id'] = Uuid::normalizeDatabase(
            (string) $opportunity['enterprise_id'],
            'internship_posts.enterpriseId'
        );
        $opportunity['partner_id'] = $opportunity['enterprise_id'];
        $opportunity['partner_type'] = 'enterprise';
        $opportunity['partner_name'] = $opportunity['enterprise_name'];
        $opportunity['type'] = 'internship';
        $opportunity['status'] = OpportunityStatus::normalize($opportunity['status'] ?? null)->value;
        $opportunity['skills'] = $this->decodeJson($opportunity['skills_json'] ?? null, 'internship_posts.skillsJson');
        $opportunity['requirements'] = $this->decodeJson($opportunity['requirements_json'] ?? null, 'internship_posts.requirementsJson');
        unset($opportunity['skills_json'], $opportunity['requirements_json']);
        $opportunity['id_origin'] = 'database';

        return $opportunity;
    }
}
