<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Support\KeyMapper;

/**
 * Conservative recovery of legacy omissions, NOT a live-CV backfill.
 * Only unchanged, pre-application sources are supported. A later edit/revision
 * aborts recovery; it requires a separate reader of that source's history.
 */
final class HistoricalApplicationSnapshotSource
{
    private array $proof = [];

    public function __construct(private readonly PDO $pdo) {}

    public function read(array $original, string $studentId, string $userId, string $cutoff): array
    {
        if (!$this->pdo->inTransaction()) throw new RuntimeException('Historical capture requires a transaction.');
        if (($original['schemaVersion'] ?? '') !== '1.0.0'
            || ($original['student']['studentProfileId'] ?? '') !== $studentId) {
            throw new RuntimeException('Recovery requires the original 1.0.0 snapshot of this applicant.');
        }
        $queries = [
            'student_context'=>["SELECT sp.id,sp.userId,sp.classId,sp.updatedAt,c.schoolId,c.updatedAt classUpdatedAt,s.updatedAt schoolUpdatedAt
                FROM student_profiles sp JOIN classes c ON c.id=sp.classId JOIN schools s ON s.id=c.schoolId WHERE sp.id=?",
                ['updatedAt','classUpdatedAt','schoolUpdatedAt']],
            'certificates'=>['SELECT * FROM certificates WHERE studentId=?', ['createdAt','updatedAt']],
            // Group scores are written in the same transaction as the parent assessment/version.
            'assessments'=>['SELECT * FROM assessments WHERE studentId=?', ['createdAt','updatedAt','publishedAt']],
            'group_scores'=>['SELECT gs.* FROM assessment_skill_group_scores gs JOIN assessments a ON a.id=gs.assessmentId WHERE a.studentId=?', []],
            'evaluations'=>['SELECT * FROM learner_evaluations WHERE studentId=?', ['createdAt','updatedAt','publishedAt','supersededAt','revokedAt']],
            'evaluation_items'=>['SELECT i.* FROM learner_evaluation_items i JOIN learner_evaluations e ON e.id=i.evaluationId WHERE e.studentId=?', ['createdAt']],
            'projects'=>['SELECT p.* FROM projects p JOIN project_members pm ON pm.projectId=p.id WHERE pm.studentId=?', ['createdAt','updatedAt']],
            'memberships'=>['SELECT * FROM project_members WHERE studentId=?', ['createdAt','updatedAt','joinedAt','leftAt']],
            'attempts'=>['SELECT * FROM test_attempts WHERE studentId=?', ['createdAt','updatedAt','submittedAt']],
            // Base resultCode/summary are immutable after submission; teacher overrides have their own timestamp.
            'results'=>['SELECT tr.* FROM test_results tr JOIN test_attempts ta ON ta.id=tr.attemptId WHERE ta.studentId=?', ['createdAt','teacherGradedAt']],
            'tests'=>['SELECT tt.* FROM talent_tests tt JOIN test_attempts ta ON ta.testId=tt.id WHERE ta.studentId=?', ['createdAt','updatedAt']],
            'badges'=>['SELECT b.* FROM badges b JOIN student_badges sb ON sb.badgeId=b.id WHERE sb.studentId=?', ['createdAt','updatedAt']],
            'awards'=>['SELECT * FROM student_badges WHERE studentId=?', ['awardedAt']],
            // Confirmed experience rows are append-only; a new award resets awardedAt.
            'experience'=>['SELECT * FROM experience_logs WHERE studentId=?', ['createdAt','confirmedAt']],
            'activities'=>['SELECT a.* FROM activities a WHERE a.id IN (SELECT activityId FROM experience_logs WHERE studentId=?)', ['createdAt','updatedAt']],
            'registrations'=>['SELECT * FROM activity_registrations WHERE studentId=?', ['registeredAt','updatedAt','cancelledAt','attendanceResolvedAt']],
            'assessment_activities'=>['SELECT a.* FROM activities a JOIN assessments e ON e.activityId=a.id WHERE e.studentId=?', ['createdAt','updatedAt']],
            'teachers'=>['SELECT t.* FROM teacher_profiles t JOIN learner_evaluations e ON e.teacherId=t.id WHERE e.studentId=?', ['createdAt','updatedAt']],
        ];
        $sources = [];
        foreach ($queries as $label=>[$sql,$dates]) {
            $rows = $this->rows($sql, [$studentId]);
            foreach ($rows as $row) {
                foreach ($dates as $date) {
                    if (!empty($row[$date]) && $this->after($row[$date], $cutoff)) {
                        throw new RuntimeException("Cannot prove historical {$label}: {$date} is after application submission.");
                    }
                }
            }
            $sources[$label] = $rows;
            $this->record($label, $rows);
        }
        // These source families need revision-aware recovery. Never silently copy or omit them.
        foreach (['learner_skill_evidence','project_submissions','learner_internship_reports'] as $table) {
            if ($this->rows("SELECT id FROM {$table} WHERE studentId=?", [$studentId]) !== []) {
                throw new RuntimeException("Historical {$table} requires its own revision reader; no snapshot was written.");
            }
        }
        if ($this->rows("SELECT id FROM internship_applications WHERE studentId=? AND status='accepted'", [$studentId]) !== []) {
            throw new RuntimeException('Historical accepted internships require application status history.');
        }
        // The group dictionary is a fixed migration-owned taxonomy, with no application editing path.
        $this->record('group_taxonomy', $this->rows('SELECT g.* FROM skill_groups g JOIN assessment_skill_group_scores gs ON gs.groupCode=g.code JOIN assessments a ON a.id=gs.assessmentId WHERE a.studentId=?', [$studentId]));

        $data = (new DatabaseApplicationSnapshotRepository($this->pdo))->forStudent($studentId, $userId);
        foreach ($data['skills'] as $skill) {
            if (($skill['item_kind'] ?? '') !== 'skill_group') {
                throw new RuntimeException('Individual skill evidence requires a historical evidence reader.');
            }
        }
        if (array_filter($data['verified_portfolio'] ?? [])) throw new RuntimeException('Historical portfolio recovery is required.');

        // Identity/contact/education/summary remain exactly what the applicant actually shared.
        $data['student'] = KeyMapper::toSnake($original['student']);
        $data['student']['id'] = $studentId;
        foreach ($data['teacher_evaluations'] as &$evaluation) {
            $source = array_values(array_filter($sources['evaluations'],
                static fn(array $row): bool => $row['id'] === $evaluation['id']))[0] ?? null;
            if ($source === null) throw new RuntimeException('Missing historical teacher evaluation.');
            $teacher = $this->rows('SELECT u.id,u.fullName,u.updatedAt,u.lastLoginAt FROM users u JOIN teacher_profiles t ON t.userId=u.id WHERE t.id=?', [$source['teacherId']])[0];
            if ($this->after($teacher['updatedAt'], $cutoff)) {
                // Login also changes users.updatedAt. Recover the name from the registration event,
                // only if the teacher profile was untouched and no admin rename intervened.
                $events = $this->rows("SELECT id,metadata,createdAt FROM audit_logs WHERE userId=? AND action='auth.teacher_registration_submitted' AND createdAt<=? ORDER BY createdAt DESC LIMIT 1", [$teacher['id'],$cutoff]);
                $name = isset($events[0]) ? (json_decode($events[0]['metadata'], true, 512, JSON_THROW_ON_ERROR)['fullName'] ?? null) : null;
                if ($name === null || $name !== $teacher['fullName'] || $teacher['updatedAt'] !== $teacher['lastLoginAt']
                    || $this->rows("SELECT id FROM audit_logs WHERE entityId=? AND action='admin.user_updated'", [$teacher['id']])) {
                    throw new RuntimeException('Cannot establish teacher identity at application time.');
                }
                $this->record('teacher_identity_'.$teacher['id'], $events);
            } else {
                $name = $teacher['fullName'];
                $this->record('teacher_identity_'.$teacher['id'], [$teacher]);
            }
            $evaluation['teacher_name'] = $evaluation['evaluator'] = $name;
        }
        unset($evaluation);
        // Original projects and experience totals provide an independent historical anchor.
        $oldIds = array_column($original['projects'] ?? [], 'projectId');
        $newIds = array_column($data['projects'], 'id');
        sort($oldIds); sort($newIds);
        if ($oldIds !== $newIds
            || (float)($original['experience']['totalConfirmedHours'] ?? 0) !== (float)$data['experience']['summary']['total_hours']
            || (int)($original['experience']['totalActivitiesAttended'] ?? 0) !== (int)$data['experience']['summary']['total_activities']) {
            throw new RuntimeException('Recovered participation disagrees with the original snapshot.');
        }
        return ['data'=>$data, 'proof'=>$this->proof];
    }

    private function rows(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function after(string $value, string $cutoff): bool
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC')) > new DateTimeImmutable($cutoff, new DateTimeZone('UTC'));
    }

    private function record(string $source, array $rows): void
    {
        $this->proof[$source] = [
            'count'=>count($rows),
            'ids'=>array_values(array_unique(array_filter(array_column($rows, 'id')))),
            'sha256'=>hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        ];
    }
}
