<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\ApplicationRepository;
use TalentHub\Learner\Data\Enums\ApplicationStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseApplicationRepository extends AbstractDatabaseRepository implements ApplicationRepository
{
    private const COLUMNS = 'ia.id, ia.postId, ia.studentId, ia.status, ia.message, ia.appliedAt, ia.updatedAt, ip.enterpriseId, ip.title, ip.field, ip.location, ip.workType, ip.duration, ip.educationLevel, ip.description, ip.benefits, ip.skillsJson, ip.requirementsJson, ip.slots, ip.deadline, e.name AS enterpriseName';
    private const FOR_STUDENT_SQL = 'SELECT ' . self::COLUMNS . ' FROM internship_applications ia INNER JOIN internship_posts ip ON ip.id = ia.postId INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ia.studentId = :student_id ORDER BY ia.appliedAt DESC, ia.id';
    private const FIND_SQL = 'SELECT ' . self::COLUMNS . ' FROM internship_applications ia INNER JOIN internship_posts ip ON ip.id = ia.postId INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ia.id = :application_id AND ia.studentId = :student_id LIMIT 1';

    public function forStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        return array_map(
            fn (array $application): array => $this->normalizeApplication(
                $this->withCanonicalEvidence($application, $studentId)
            ),
            $this->fetchAll('forStudent', self::FOR_STUDENT_SQL, ['student_id' => $studentId])
        );
    }

    public function findByIdForStudent(string $applicationId, string $studentId): ?array
    {
        $applicationId = Uuid::normalizeDatabase($applicationId, 'application_id');
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $application = $this->fetchOne('findByIdForStudent', self::FIND_SQL, [
            'application_id' => $applicationId,
            'student_id' => $studentId,
        ]);

        return $application === null
            ? null
            : $this->normalizeApplication($this->withCanonicalEvidence($application, $studentId));
    }

    private function normalizeApplication(array $application): array
    {
        $application['id'] = Uuid::normalizeDatabase((string) $application['id'], 'internship_applications.id');
        $application['application_id'] = $application['id'];
        $application['post_id'] = Uuid::normalizeDatabase(
            (string) $application['post_id'],
            'internship_applications.postId'
        );
        $application['opportunity_id'] = $application['post_id'];
        $application['student_id'] = Uuid::normalizeDatabase(
            (string) $application['student_id'],
            'internship_applications.studentId'
        );
        $application['enterprise_id'] = Uuid::normalizeDatabase(
            (string) $application['enterprise_id'],
            'internship_posts.enterpriseId'
        );
        $application['partner_name'] = $application['enterprise_name'];
        $application['status'] = ApplicationStatus::normalize($application['status'] ?? null)->value;
        $application['skills'] = $this->decodeJson($application['skills_json'] ?? null, 'internship_posts.skillsJson');
        $application['requirements'] = $this->decodeJson($application['requirements_json'] ?? null, 'internship_posts.requirementsJson');
        unset($application['skills_json'], $application['requirements_json']);
        $application['id_origin'] = 'database';

        return $application;
    }

    private function withCanonicalEvidence(array $application, string $studentId): array
    {
        $applicationId = Uuid::normalizeDatabase((string) $application['id'], 'internship_applications.id');
        $snapshot = $this->fetchOne(
            'applicationSnapshot',
            'SELECT aps.schemaVersion, aps.snapshotPayload, aps.createdAt FROM application_profile_snapshots aps INNER JOIN internship_applications ia ON ia.id = aps.applicationId WHERE aps.applicationId = :application_id AND ia.studentId = :student_id LIMIT 1',
            ['application_id' => $applicationId, 'student_id' => $studentId]
        );
        $application['snapshot'] = $snapshot === null
            ? null
            : [
                'schema_version' => (string) $snapshot['schema_version'],
                'captured_at' => (string) $snapshot['created_at'],
                'payload' => $this->decodeJson($snapshot['snapshot_payload'], 'application_profile_snapshots.snapshotPayload'),
            ];
        $application['history'] = $this->fetchAll(
            'applicationHistory',
            'SELECT h.fromStatus, h.toStatus, h.changedByRole, h.createdAt FROM application_status_history h INNER JOIN internship_applications ia ON ia.id = h.applicationId WHERE h.applicationId = :application_id AND ia.studentId = :student_id ORDER BY h.createdAt, h.id',
            ['application_id' => $applicationId, 'student_id' => $studentId]
        );
        return $application;
    }
}
