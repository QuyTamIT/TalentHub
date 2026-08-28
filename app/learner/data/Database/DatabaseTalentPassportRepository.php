<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\TalentPassportRepository;
use TalentHub\Learner\Data\Exceptions\LearnerDataQueryException;
use TalentHub\Learner\Data\Readiness\TalentPassportOptionalSchema;
use TalentHub\Learner\Data\Support\Uuid;
use Throwable;

final class DatabaseTalentPassportRepository extends AbstractDatabaseRepository implements TalentPassportRepository
{
    private ?SchemaInspector $schemaInspector = null;

    public function aggregateForStudent(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $student = $this->student($studentId);
        $skills = $this->skills($studentId);
        $experience = $this->experience($studentId);
        $assessmentResults = $this->assessmentResults($studentId);
        $teacherEvaluations = $this->teacherEvaluations($studentId);
        $activitySummary = $this->activitySummary($studentId, $experience['confirmed_hours']);
        $optional = $this->optionalFacts($studentId);
        $timestamps = $this->sourceTimestamps($skills, $experience['confirmed_entries'], $assessmentResults, $teacherEvaluations);

        return [
            'student' => $student,
            'skills' => $skills,
            'experience' => $experience,
            'assessment_results' => $assessmentResults,
            'teacher_evaluations' => $teacherEvaluations,
            'activity_summary' => $activitySummary,
            'certificates' => $optional['certificates'],
            'projects' => $optional['projects'],
            'badges' => $optional['badges'],
            'source_timestamps' => $timestamps,
            'capabilities' => $optional['capabilities'],
        ];
    }

    /** @param list<string> $sections @return array<string,mixed> */
    public function sharedSectionsForStudent(string $studentId, array $sections): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $requested = array_fill_keys($sections, true);
        $result = [];

        if (isset($requested['skills'])) {
            $result['skills'] = $this->skills($studentId);
        }
        if (isset($requested['experience'])) {
            $result['experience'] = $this->experience($studentId);
        }
        if (isset($requested['certificates'])) {
            $result['certificates'] = [];
            if (TalentPassportOptionalSchema::status($this->inspector(), 'certificates') === 'available') {
                $result['certificates'] = $this->fetchAll(
                    'shared certificates',
                    <<<'SQL'
                        SELECT id, title, issuingOrganization, issueDate, expiryDate,
                               credentialId, credentialUrl, verificationStatus, verifiedAt, createdAt, updatedAt
                        FROM certificates
                        WHERE studentId = :student_id
                        ORDER BY createdAt DESC
                    SQL,
                    ['student_id' => $studentId],
                );
            }
        }
        if (isset($requested['projects'])) {
            $result['projects'] = [];
            if (TalentPassportOptionalSchema::status($this->inspector(), 'projects') === 'available') {
                $result['projects'] = $this->fetchAll(
                    'shared projects',
                    <<<'SQL'
                        SELECT p.id, p.title, p.category, p.description, p.projectUrl,
                               p.startAt, p.endAt, p.status, p.createdAt, p.updatedAt,
                               pm.role, pm.contribution,
                               (
                                   SELECT e.name 
                                   FROM project_sponsorships ps 
                                   JOIN enterprises e ON e.id = ps.enterpriseId 
                                   WHERE ps.projectId = p.id AND ps.status = 'paid' 
                                   ORDER BY ps.amount DESC, ps.createdAt DESC 
                                   LIMIT 1
                               ) AS sponsorName,
                               (
                                   SELECT SUM(ps.amount)
                                   FROM project_sponsorships ps
                                   WHERE ps.projectId = p.id AND ps.status = 'paid'
                               ) AS totalFundedAmount
                        FROM projects p
                        INNER JOIN project_members pm ON pm.projectId = p.id
                        WHERE pm.studentId = :student_id
                           OR pm.studentId IN (SELECT sp.id FROM student_profiles sp WHERE sp.userId = :student_id_alt1)
                           OR pm.studentId IN (SELECT sp.userId FROM student_profiles sp WHERE sp.id = :student_id_alt2)
                        ORDER BY p.createdAt DESC
                    SQL,
                    [
                        'student_id' => $studentId,
                        'student_id_alt1' => $studentId,
                        'student_id_alt2' => $studentId,
                    ],
                );
            }
        }

        return $result;
    }

    private function student(string $studentId): array
    {
        $sql = <<<'SQL'
            SELECT
                sp.id,
                sp.userId,
                sp.classId,
                sp.studyStatus,
                u.email,
                u.fullName,
                u.status AS userStatus,
                c.name AS className,
                c.gradeLevel,
                c.academicYear,
                s.name AS schoolName,
                s.status AS schoolStatus
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            WHERE sp.id = :student_id
            LIMIT 1
            SQL;

        $row = $this->fetchOne('student', $sql, ['student_id' => $studentId]);
        if ($row === null) {
            throw new LearnerDataQueryException('Authenticated learner profile was not found.');
        }

        return $row;
    }

    private function skills(string $studentId): array
    {
        $sql = <<<'SQL'
            SELECT
                ss.studentId,
                ss.skillId,
                ss.levelScore,
                ss.sourceType,
                ss.verificationStatus,
                ss.verifiedAt,
                s.code,
                s.name,
                s.category,
                s.status AS skillStatus
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :student_id
            ORDER BY s.category ASC, s.name ASC, ss.skillId ASC
            SQL;

        return $this->fetchAll('skills', $sql, ['student_id' => $studentId]);
    }

    private function experience(string $studentId): array
    {
        $inspector = $this->inspector();
        $hasDetails = $inspector->hasTable('activity_details');
        $detail = function (string $column) use ($hasDetails): string {
            return $hasDetails && $this->inspector()->hasColumn('activity_details', $column)
                ? "ad.{$column}"
                : 'NULL';
        };
        $activityStartAt = $inspector->hasColumn('activities', 'startAt') ? 'a.startAt' : 'NULL';
        $detailsJoin = $hasDetails ? 'LEFT JOIN activity_details ad ON ad.activityId = a.id' : '';
        $displayCategory = $detail('displayCategory');
        $filterCategory = $detail('filterCategory');
        $locationName = $detail('locationName');
        $coverImageUrl = $detail('coverImageUrl');
        $coverImageAlt = $detail('coverImageAlt');

        $sql = <<<SQL
            SELECT
                el.id,
                el.studentId,
                el.activityId,
                el.checkinId,
                el.hours,
                el.status,
                el.confirmedAt,
                a.title AS activityTitle,
                a.category AS activityCategory,
                {$displayCategory} AS displayCategory,
                {$filterCategory} AS filterCategory,
                {$activityStartAt} AS activityStartAt,
                {$locationName} AS locationName,
                {$coverImageUrl} AS coverImageUrl,
                {$coverImageAlt} AS coverImageAlt
            FROM experience_logs el
            INNER JOIN checkins ci ON ci.id = el.checkinId
                AND ci.status = 'confirmed'
                AND ci.confirmedAt IS NOT NULL
            INNER JOIN activity_registrations ar ON ar.id = ci.registrationId
                AND ar.studentId = el.studentId
                AND ar.activityId = el.activityId
                AND ar.status = 'attended'
            INNER JOIN activities a ON a.id = el.activityId
            {$detailsJoin}
            WHERE el.studentId = :student_id
              AND el.status = 'confirmed'
              AND el.confirmedAt IS NOT NULL
            ORDER BY el.confirmedAt DESC, el.id ASC
            SQL;

        $entries = $this->fetchAll('experience', $sql, ['student_id' => $studentId]);
        $totalHours = 0.0;
        foreach ($entries as $entry) {
            $totalHours += (float) ($entry['hours'] ?? 0.0);
        }

        return [
            'confirmed_hours' => round($totalHours, 2),
            'confirmed_entries' => $entries,
        ];
    }

    private function assessmentResults(string $studentId): array
    {
        $sql = <<<'SQL'
            SELECT
                ta.id AS attemptId,
                ta.testId,
                ta.studentId,
                ta.status,
                ta.startedAt,
                ta.submittedAt,
                tt.code AS testCode,
                tt.name AS testName,
                tt.type AS testType,
                tr.resultCode,
                tr.summary,
                tr.dimensionScoresJson,
                tr.scoringVersion,
                tr.createdAt AS resultCreatedAt
            FROM test_attempts ta
            INNER JOIN talent_tests tt ON tt.id = ta.testId
            INNER JOIN test_results tr ON tr.attemptId = ta.id
            WHERE ta.studentId = :student_id AND ta.status = 'submitted'
            ORDER BY ta.submittedAt DESC, ta.id ASC
            SQL;

        $rows = $this->fetchAll('assessmentResults', $sql, ['student_id' => $studentId]);
        foreach ($rows as &$row) {
            if (isset($row['dimension_scores_json'])) {
                $row['dimension_scores'] = $this->decodeJson($row['dimension_scores_json'], 'dimension_scores_json');
            } else {
                $row['dimension_scores'] = [];
            }
        }
        unset($row);

        return $rows;
    }

    private function teacherEvaluations(string $studentId): array
    {
        $sql = <<<'SQL'
            SELECT
                a.id,
                a.teacherId,
                a.studentId,
                a.activityId,
                a.overallScore,
                a.comment,
                a.status,
                a.publishedAt,
                a.version,
                u.fullName AS teacherName,
                act.title AS activityTitle
            FROM assessments a
            LEFT JOIN teacher_profiles tp ON tp.id = a.teacherId
            LEFT JOIN users u ON u.id = tp.userId
            LEFT JOIN activities act ON act.id = a.activityId
            WHERE a.studentId = :student_id AND a.status = 'published'
            ORDER BY a.publishedAt DESC, a.id ASC
            SQL;

        $evaluations = $this->fetchAll('teacherEvaluations', $sql, ['student_id' => $studentId]);
        if ($evaluations === []) {
            return [];
        }

        $assessmentIds = array_column($evaluations, 'id');
        $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));

        $scoresSql = <<<SQL
            SELECT
                asc_score.assessmentId,
                asc_score.criteriaId,
                asc_score.score,
                ac.code AS criteriaCode,
                ac.name AS criteriaName,
                ac.minScore,
                ac.maxScore,
                ac.displayOrder
            FROM assessment_scores asc_score
            INNER JOIN assessment_criteria ac ON ac.id = asc_score.criteriaId
            WHERE asc_score.assessmentId IN ({$placeholders})
            ORDER BY ac.displayOrder ASC, ac.id ASC
            SQL;

        $scores = $this->fetchAll('assessmentScores', $scoresSql, $assessmentIds);
        $scoresByEval = [];
        foreach ($scores as $scoreRow) {
            $evalId = (string) $scoreRow['assessment_id'];
            $scoresByEval[$evalId][] = $scoreRow;
        }

        foreach ($evaluations as &$eval) {
            $evalId = (string) $eval['id'];
            $eval['criteria_scores'] = $scoresByEval[$evalId] ?? [];
        }
        unset($eval);

        return $evaluations;
    }

    private function activitySummary(string $studentId, float $confirmedHours): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(ar.id) AS registeredCount,
                SUM(CASE WHEN ar.status IN ('pending', 'approved', 'waitlisted') THEN 1 ELSE 0 END) AS activeRegisteredCount,
                SUM(CASE WHEN ar.status = 'attended' THEN 1 ELSE 0 END) AS attendedCount
            FROM activity_registrations ar
            WHERE ar.studentId = :student_id
            SQL;

        $row = $this->fetchOne('activitySummary', $sql, ['student_id' => $studentId]);

        return [
            'registered_count' => (int) ($row['registered_count'] ?? 0),
            'active_registered_count' => (int) ($row['active_registered_count'] ?? 0),
            'attended_count' => (int) ($row['attended_count'] ?? 0),
            'confirmed_hours' => $confirmedHours,
        ];
    }

    private function optionalFacts(string $studentId): array
    {
        $inspector = $this->inspector();
        $capabilities = [
            'certificates' => false,
            'projects' => false,
            'badges' => false,
        ];
        $certificates = [];
        $projects = [];
        $badges = [];

        if (TalentPassportOptionalSchema::status($inspector, 'certificates') === 'available') {
            $capabilities['certificates'] = true;
            try {
                $certificates = $this->fetchAll(
                    'certificates',
                    'SELECT * FROM certificates WHERE studentId = :student_id ORDER BY createdAt DESC',
                    ['student_id' => $studentId]
                );
            } catch (Throwable) {
                $capabilities['certificates'] = false;
                $certificates = [];
            }
        }

        if (TalentPassportOptionalSchema::status($inspector, 'projects') === 'available') {
            $capabilities['projects'] = true;
            try {
                $projects = $this->fetchAll(
                    'projects',
                    <<<'SQL'
                    SELECT p.id, p.title, p.category, p.description, p.fundingGoal, p.projectUrl, p.startAt, p.endAt, p.status,
                           pm.role, pm.contribution,
                           (
                               SELECT e.name 
                               FROM project_sponsorships ps 
                               JOIN enterprises e ON e.id = ps.enterpriseId 
                               WHERE ps.projectId = p.id AND ps.status = 'paid' 
                               ORDER BY ps.amount DESC, ps.createdAt DESC 
                               LIMIT 1
                           ) AS sponsorName,
                           (
                               SELECT e.logoUrl 
                               FROM project_sponsorships ps 
                               JOIN enterprises e ON e.id = ps.enterpriseId 
                               WHERE ps.projectId = p.id AND ps.status = 'paid' 
                               ORDER BY ps.amount DESC, ps.createdAt DESC 
                               LIMIT 1
                           ) AS sponsorLogo,
                           (
                               SELECT SUM(ps.amount)
                               FROM project_sponsorships ps
                               WHERE ps.projectId = p.id AND ps.status = 'paid'
                           ) AS totalFundedAmount
                    FROM projects p 
                    INNER JOIN project_members pm ON pm.projectId = p.id 
                    WHERE pm.studentId = :student_id 
                       OR pm.studentId IN (SELECT sp.id FROM student_profiles sp WHERE sp.userId = :student_id_alt1)
                       OR pm.studentId IN (SELECT sp.userId FROM student_profiles sp WHERE sp.id = :student_id_alt2)
                    ORDER BY p.createdAt DESC
                    SQL,
                    [
                        'student_id' => $studentId,
                        'student_id_alt1' => $studentId,
                        'student_id_alt2' => $studentId,
                    ]
                );
            } catch (Throwable) {
                $capabilities['projects'] = false;
                $projects = [];
            }
        }

        if (TalentPassportOptionalSchema::status($inspector, 'badges') === 'available') {
            $capabilities['badges'] = true;
            $badges = $this->fetchAll(
                'badges',
                <<<'SQL'
                    SELECT b.id, b.code, b.name, b.category, b.description,
                           b.iconUrl AS icon_url, b.level, b.status,
                           sb.ruleDefinitionId AS rule_definition_id,
                           sb.awardedAt AS awarded_at,
                           sb.awardedBy AS awarded_by,
                           sb.awardContext AS award_context
                    FROM badges b
                    INNER JOIN student_badges sb ON sb.badgeId = b.id
                    WHERE sb.studentId = :student_id
                    ORDER BY sb.awardedAt DESC, b.code ASC
                SQL,
                ['student_id' => $studentId]
            );
        }

        return [
            'capabilities' => $capabilities,
            'certificates' => $certificates,
            'projects' => $projects,
            'badges' => $badges,
        ];
    }

    private function sourceTimestamps(
        array $skills,
        array $experienceEntries,
        array $assessmentResults,
        array $teacherEvaluations,
    ): array {
        $timestamps = [];

        $skillTimes = array_filter(array_column($skills, 'verified_at'));
        if ($skillTimes !== []) {
            rsort($skillTimes);
            $timestamps['skills'] = $skillTimes[0];
        }

        $expTimes = array_filter(array_column($experienceEntries, 'confirmed_at'));
        if ($expTimes !== []) {
            rsort($expTimes);
            $timestamps['experience'] = $expTimes[0];
        }

        $testTimes = array_filter(array_column($assessmentResults, 'submitted_at'));
        if ($testTimes !== []) {
            rsort($testTimes);
            $timestamps['assessments'] = $testTimes[0];
        }

        $evalTimes = array_filter(array_column($teacherEvaluations, 'published_at'));
        if ($evalTimes !== []) {
            rsort($evalTimes);
            $timestamps['evaluations'] = $evalTimes[0];
        }

        return $timestamps;
    }

    private function inspector(): SchemaInspector
    {
        if ($this->schemaInspector === null) {
            $driver = strtolower((string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            $schema = $driver === 'sqlite' ? 'main' : (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            $this->schemaInspector = new SchemaInspector($this->pdo, $schema);
        }

        return $this->schemaInspector;
    }
}
