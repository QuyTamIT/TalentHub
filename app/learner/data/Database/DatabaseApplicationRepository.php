<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\ApplicationRepository;
use TalentHub\Learner\Data\Enums\ApplicationStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseApplicationRepository extends AbstractDatabaseRepository implements ApplicationRepository
{
    private const COLUMNS = 'ia.id, ia.postId, ia.studentId, ia.status, ia.cvUrl, ia.reviewerNote, ip.enterpriseId, ip.title, ip.location, ip.deadline, e.name AS enterpriseName';
    private const FOR_STUDENT_SQL = 'SELECT ' . self::COLUMNS . ' FROM internship_applications ia INNER JOIN internship_posts ip ON ip.id = ia.postId INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ia.studentId = :student_id ORDER BY ia.id';
    private const FIND_SQL = 'SELECT ' . self::COLUMNS . ' FROM internship_applications ia INNER JOIN internship_posts ip ON ip.id = ia.postId INNER JOIN enterprises e ON e.id = ip.enterpriseId WHERE ia.id = :application_id AND ia.studentId = :student_id LIMIT 1';

    public function forStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        return array_map(
            [$this, 'normalizeApplication'],
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

        return $application === null ? null : $this->normalizeApplication($application);
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
        $application['id_origin'] = 'database';

        return $application;
    }
}
