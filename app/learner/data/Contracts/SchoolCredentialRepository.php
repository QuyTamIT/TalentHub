<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface SchoolCredentialRepository
{
    /** @return array{student_id:string,school_id:string,school_name:string,grade_level:?int}|null */
    public function studentContext(string $studentId): ?array;

    /** @return array<string,mixed> */
    public function latestAssessmentProfile(string $studentId): array;

    /** @return array<string,float> */
    public function verifiedSkillProfile(string $studentId): array;

    /** @return list<array<string,mixed>> */
    public function credentialCatalog(string $schoolId): array;

    /** @return list<array<string,mixed>> */
    public function issuedSchoolCertificates(string $studentId): array;

    public function hasCompletedRoadmap(string $studentId): bool;

    /** @return array<string,mixed>|null */
    public function latestRoadmapAnalysis(string $studentId): ?array;
}
