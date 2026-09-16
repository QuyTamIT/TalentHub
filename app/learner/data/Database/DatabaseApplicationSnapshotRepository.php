<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Service\ScoreViewer;

/** Snapshot-only enrichment of the canonical Export CV source; no Student view changes. */
final class DatabaseApplicationSnapshotRepository extends AbstractDatabaseRepository
{
    public function forStudent(string $studentId, string $userId): array
    {
        $data = (new DatabasePassportCvRepository(
            $this->pdo, new ScoreViewer(ScoreViewer::ROLE_STUDENT, $userId)
        ))->forStudent($studentId, true);
        $data['experience']['confirmed_entries'] = $this->fetchAll('snapshot activity details',
            "SELECT el.id, el.activityId, el.hours, el.status, el.confirmedAt,
                    a.title AS activityTitle, a.category, a.startAt, a.endAt
             FROM experience_logs el JOIN activities a ON a.id=el.activityId
             WHERE el.studentId=:id AND el.status='confirmed' AND el.confirmedAt IS NOT NULL
             ORDER BY el.confirmedAt DESC, el.id", ['id'=>$studentId]);
        return $data;
    }
}
