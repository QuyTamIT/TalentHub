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
        $progress = $this->progress($studentId, $optional['capabilities']['badges']);
        $roadmapFeedback = $this->roadmapFeedback($studentId);
        $aiCapabilityProfile = $this->aiCapabilityProfile($studentId);
        $timestamps = $this->sourceTimestamps($skills, $experience['confirmed_entries'], $assessmentResults, $teacherEvaluations);
        $sourceAvailability = [
            'achievement' => ['status' => 'unavailable', 'reason' => 'canonical_source_not_available'],
            'certificate' => [
                'status' => $optional['capabilities']['certificates'] ? 'available' : 'unavailable',
                'reason' => $optional['capabilities']['certificates'] ? null : 'schema_not_available',
            ],
            'project' => [
                'status' => $optional['capabilities']['projects'] ? 'available' : 'unavailable',
                'reason' => $optional['capabilities']['projects'] ? null : 'schema_not_available',
            ],
            'badge' => [
                'status' => $optional['capabilities']['badges'] ? 'available' : 'unavailable',
                'reason' => $optional['capabilities']['badges'] ? null : 'schema_not_available',
            ],
            'progress' => [
                'status' => $optional['capabilities']['badges'] ? 'available' : 'unavailable',
                'reason' => $optional['capabilities']['badges'] ? null : 'schema_not_available',
            ],
            'checkin' => ['status' => 'available', 'reason' => null],
            'teacher_feedback' => ['status' => 'available', 'reason' => null],
            'mentor_evaluation' => ['status' => 'unavailable', 'reason' => 'canonical_source_not_available'],
            'roadmap_feedback' => [
                'status' => $this->inspector()->hasTable('learner_recommendation_audit_events') ? 'available' : 'unavailable',
                'reason' => $this->inspector()->hasTable('learner_recommendation_audit_events') ? null : 'schema_not_available',
            ],
        ];

        return [
            'student' => $student,
            'skills' => $skills,
            'portfolio_skills' => $this->portfolioVerifiedSkills($studentId),
            'internships' => $optional['internships'],
            'portfolio_feedback' => $this->portfolioFeedback($studentId),
            'experience' => $experience,
            'assessment_results' => $assessmentResults,
            'teacher_evaluations' => $teacherEvaluations,
            'activity_summary' => $activitySummary,
            'certificates' => $optional['certificates'],
            'projects' => $optional['projects'],
            'badges' => $optional['badges'],
            'achievements' => [],
            'progress' => $progress,
            'checkins' => $experience['confirmed_entries'],
            'teacher_feedback' => $teacherEvaluations,
            'mentor_evaluations' => [],
            'roadmap_feedback' => $roadmapFeedback,
            'ai_capability_profile' => $aiCapabilityProfile,
            'source_timestamps' => $timestamps,
            'capabilities' => $optional['capabilities'],
            'source_availability' => $sourceAvailability,
        ];
    }

    /** @return array<string,mixed>|null */
    private function aiCapabilityProfile(string $studentId): ?array
    {
        if (!$this->inspector()->hasTable('learner_ai_capability_profiles') || !$this->aiProfileConsentGranted($studentId)) return null;
        $row=$this->fetchOne('ai capability profile', <<<'SQL'
            SELECT version_number, status, talent_map_json, strengths_json, improvements_json,
                   potential_paths_json, trend_signals_json, evidence_json, snapshot_hash,
                   model_version, generated_at, stale_since
            FROM learner_ai_capability_profiles
            WHERE student_id = :student_id AND superseded_at IS NULL
            ORDER BY version_number DESC LIMIT 1
            SQL, ['student_id'=>$studentId]);
        if ($row===null) return null;
        foreach (['talent_map_json','strengths_json','improvements_json','potential_paths_json','trend_signals_json','evidence_json'] as $field) {
            $decoded=json_decode((string)($row[$field]??'[]'),true);
            $row[substr($field,0,-5)]=is_array($decoded)?$decoded:[]; unset($row[$field]);
        }
        return $row;
    }

    private function aiProfileConsentGranted(string $studentId): bool
    {
        if (!$this->inspector()->hasTable('learner_ai_consent_events')) return false;
        $row=$this->fetchOne('AI capability profile consent', <<<'SQL'
            SELECT action FROM learner_ai_consent_events
            WHERE studentId = :student_id AND scope = 'assessment'
            ORDER BY occurredAt DESC, id DESC LIMIT 1
            SQL, ['student_id'=>$studentId]);
        return ($row['action']??null)==='granted';
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
                        INNER JOIN project_members pm ON pm.projectId = p.id AND pm.status = 'active'
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

    public function skills(string $studentId): array
    {
        // Older local databases used `student_skills.level` before the canonical
        // `levelScore` column was introduced. Keep the read path compatible so
        // a learner dashboard/roadmap does not fail before migrations are run.
        $levelExpression = $this->inspector()->hasColumn('student_skills', 'levelScore')
            ? 'ss.levelScore'
            : ($this->inspector()->hasColumn('student_skills', 'level') ? 'ss.level' : 'NULL');
        $sql = <<<'SQL'
            SELECT
                ss.studentId,
                ss.skillId,
                %s AS levelScore,
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

        $sql = sprintf($sql, $levelExpression);
        return $this->mergePortfolioSkills($studentId, $this->fetchAll('skills', $sql, ['student_id' => $studentId]));
    }

    /**
     * Verified project/internship skills confirmed by a mentor. Revoked reports
     * are excluded by the status filter; duplicate skill ids keep the scored
     * student_skills row.
     *
     * @param list<array<string,mixed>> $skills
     * @return list<array<string,mixed>>
     */
    private function mergePortfolioSkills(string $studentId, array $skills): array
    {
        $seen = [];
        foreach ($skills as $row) {
            $id = (string) ($row['skill_id'] ?? '');
            if ($id !== '') {
                $seen[$id] = true;
            }
        }
        foreach ($this->portfolioVerifiedSkills($studentId) as $row) {
            $id = (string) ($row['skill_id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $skills[] = [
                'student_id' => $row['student_id'] ?? $studentId,
                'skill_id' => $id,
                'level_score' => $row['level_score'] ?? null,
                'source_type' => $row['source_type'] ?? 'project_submission',
                'verification_status' => 'verified',
                'verified_at' => $row['verified_at'] ?? null,
                'code' => $row['code'] ?? null,
                'name' => $row['name'] ?? null,
                'category' => $row['category'] ?? null,
                'skill_status' => $row['skill_status'] ?? 'active',
            ];
        }
        usort($skills, static fn (array $left, array $right): int => [
            (string) ($left['category'] ?? ''),
            (string) ($left['name'] ?? ''),
            (string) ($left['skill_id'] ?? ''),
        ] <=> [
            (string) ($right['category'] ?? ''),
            (string) ($right['name'] ?? ''),
            (string) ($right['skill_id'] ?? ''),
        ]);

        return $skills;
    }

    /** @return list<array<string,mixed>> */
    private function portfolioVerifiedSkills(string $studentId): array
    {
        $inspector = $this->inspector();
        if (!$inspector->hasTable('learner_portfolio_skills') || !$inspector->hasTable('skills')) {
            return [];
        }
        $hasProjects = $inspector->hasTable('project_submissions');
        $hasInternships = $inspector->hasTable('learner_internship_reports');
        if (!$hasProjects && !$hasInternships) {
            return [];
        }

        $unions = [];
        $params = [];
        if ($hasProjects) {
            $unions[] = "SELECT 'project' AS kind, r.id, r.studentId, r.reviewedAt FROM project_submissions r INNER JOIN project_members pm ON pm.projectId = r.projectId AND pm.studentId = r.studentId AND pm.status = 'active' WHERE r.studentId = :project_student AND r.status = 'verified'";
            $params['project_student'] = $studentId;
        }
        if ($hasInternships) {
            $unions[] = "SELECT 'internship' AS kind, r.id, r.studentId, r.reviewedAt FROM learner_internship_reports r WHERE r.studentId = :intern_student AND r.status = 'verified'";
            $params['intern_student'] = $studentId;
        }
        $unionSql = implode(' UNION ALL ', $unions);
        $sql = <<<SQL
            SELECT
                reports.studentId,
                ps.skillId,
                NULL AS levelScore,
                CASE ps.kind
                    WHEN 'project' THEN 'project_submission'
                    WHEN 'internship' THEN 'internship_report'
                    ELSE ps.kind
                END AS sourceType,
                'verified' AS verificationStatus,
                reports.reviewedAt AS verifiedAt,
                s.code,
                s.name,
                s.category,
                s.status AS skillStatus,
                ps.kind,
                ps.reportId
            FROM learner_portfolio_skills ps
            INNER JOIN skills s ON s.id = ps.skillId AND s.status = 'active'
            INNER JOIN (
                {$unionSql}
            ) reports ON reports.id = ps.reportId AND reports.kind = ps.kind
            ORDER BY s.category ASC, s.name ASC, ps.skillId ASC, ps.kind ASC, ps.reportId ASC
            SQL;

        try {
            return $this->fetchAll('portfolioSkills', $sql, $params);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Lecturer review comments on verified portfolio reports. Membership must
     * still be active; revoked reports never appear. Feedback text is evidence,
     * never a synthesized skill score.
     *
     * @return list<array<string,mixed>>
     */
    private function portfolioFeedback(string $studentId): array
    {
        $inspector = $this->inspector();
        $rows = [];
        try {
            if ($inspector->hasTable('project_submissions') && $inspector->hasTable('project_members')) {
                $projectRows = $this->fetchAll(
                    'portfolioProjectFeedback',
                    <<<'SQL'
                    SELECT r.id, 'project' AS kind, r.status, r.feedback, r.reviewedAt AS reviewed_at, r.updatedAt AS updated_at, r.projectId AS context_id
                    FROM project_submissions r
                    INNER JOIN project_members pm ON pm.projectId = r.projectId AND pm.studentId = r.studentId AND pm.status = 'active'
                    WHERE r.studentId = :student_id AND r.status = 'verified'
                    ORDER BY r.reviewedAt DESC, r.id ASC
                    SQL,
                    ['student_id' => $studentId]
                );
                $rows = array_merge($rows, $projectRows);
            }
        } catch (Throwable) {
        }
        try {
            if ($inspector->hasTable('learner_internship_reports')) {
                $hasApplications = $inspector->hasTable('internship_applications');
                $sql = $hasApplications
                    ? <<<'SQL'
                    SELECT r.id, 'internship' AS kind, r.status, r.feedback, r.reviewedAt AS reviewed_at, r.updatedAt AS updated_at, r.applicationId AS context_id
                    FROM learner_internship_reports r
                    INNER JOIN internship_applications a ON a.id = r.applicationId AND a.studentId = r.studentId AND a.status = 'accepted'
                    WHERE r.studentId = :student_id AND r.status = 'verified'
                    ORDER BY r.reviewedAt DESC, r.id ASC
                    SQL
                    : <<<'SQL'
                    SELECT r.id, 'internship' AS kind, r.status, r.feedback, r.reviewedAt AS reviewed_at, r.updatedAt AS updated_at, r.applicationId AS context_id
                    FROM learner_internship_reports r
                    WHERE r.studentId = :student_id AND r.status = 'verified'
                    ORDER BY r.reviewedAt DESC, r.id ASC
                    SQL;
                $rows = array_merge($rows, $this->fetchAll('portfolioInternshipFeedback', $sql, ['student_id' => $studentId]));
            }
        } catch (Throwable) {
        }
        if ($rows === [] || !$inspector->hasTable('learner_portfolio_skills') || !$inspector->hasTable('skills')) {
            return array_map(static function (array $row): array {
                $row['skill_codes'] = [];
                $row['skill_tags'] = [];
                return $row;
            }, $rows);
        }
        $ids = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), $rows)));
        $tagsByReport = [];
        if ($ids !== []) {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statement = $this->pdo->prepare(
                    "SELECT ps.kind, ps.reportId, s.code, s.name
                     FROM learner_portfolio_skills ps
                     INNER JOIN skills s ON s.id = ps.skillId AND s.status = 'active'
                     WHERE ps.reportId IN ({$placeholders})
                     ORDER BY s.code"
                );
                $statement->execute($ids);
                foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $tag) {
                    $code = strtolower(trim((string) ($tag['code'] ?? '')));
                    if ($code === '') {
                        continue;
                    }
                    $key = (string) $tag['kind'] . ':' . (string) $tag['reportId'];
                    if (isset($tagsByReport[$key][$code])) {
                        continue;
                    }
                    $tagsByReport[$key][$code] = ['code' => $code, 'name' => (string) ($tag['name'] ?? $code)];
                }
            } catch (Throwable) {
            }
        }
        foreach ($rows as &$row) {
            $key = (string) ($row['kind'] ?? '') . ':' . (string) ($row['id'] ?? '');
            $tags = array_values($tagsByReport[$key] ?? []);
            $row['skill_tags'] = $tags;
            $row['skill_codes'] = array_values(array_column($tags, 'code'));
        }
        unset($row);
        return $rows;
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
            ORDER BY ta.submittedAt DESC, ta.id DESC
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
                a.classId,
                a.projectId,
                cl.name AS className,
                pr.title AS projectTitle,
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
            LEFT JOIN classes cl ON cl.id = a.classId
            LEFT JOIN projects pr ON pr.id = a.projectId
            WHERE a.studentId = :student_id AND a.status = 'published' AND a.publishedAt IS NOT NULL
            ORDER BY a.publishedAt DESC, a.id DESC
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
            $eval['context_title'] = $eval['class_name'] ?? $eval['project_title'] ?? $eval['activity_title'] ?? '';
            $eval['context_label'] = $eval['class_id'] !== null ? 'Đánh giá theo lớp học phần' : ($eval['project_id'] !== null ? 'Đánh giá dự án' : 'Đánh giá hoạt động');
            $eval['classification'] = \TalentHub\Support\GradeClassifier::getClassification($eval['overall_score'] === null ? null : (float) $eval['overall_score']);
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

    /** @return list<array<string,mixed>> */
    private function progress(string $studentId, bool $badgesAvailable): array
    {
        if (!$badgesAvailable) {
            return [];
        }

        try {
            $service = new \TalentHub\Learner\Data\Service\BadgeReadService(
                new DatabaseBadgeRepository($this->pdo),
                new DatabaseStatisticsRepository($this->pdo),
                new \TalentHub\Learner\Data\Service\BadgeRuleEngine(),
            );
            $result = $service->forStudent($studentId);
            return is_array($result['progress'] ?? null) ? $result['progress'] : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function roadmapFeedback(string $studentId): array
    {
        if (!$this->inspector()->hasTable('learner_recommendation_audit_events')) {
            return [];
        }

        try {
            $rows = $this->fetchAll(
                'roadmapFeedback',
                "SELECT id, runId AS run_id, engineMetadataJson AS engine_metadata_json, createdAt AS created_at
                 FROM learner_recommendation_audit_events
                 WHERE studentId = :student_id AND action = 'roadmap_feedback' AND status = 'completed'
                 ORDER BY createdAt DESC, id ASC LIMIT 100",
                ['student_id' => $studentId],
            );
            $feedback = [];
            foreach ($rows as $row) {
                $metadata = $this->decodeJson($row['engine_metadata_json'] ?? null, 'engineMetadataJson');
                $verdict = $metadata['verdict'] ?? $row['verdict'] ?? null;
                $reason = $metadata['reason_code'] ?? $row['reason_code'] ?? null;
                if (!is_string($verdict) || !in_array($verdict, ['helpful', 'not_helpful'], true)
                    || !is_string($reason) || !in_array($reason, ['useful_direction', 'not_relevant', 'too_generic', 'too_difficult'], true)) {
                    continue;
                }
                $feedback[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'run_id' => (string) ($row['run_id'] ?? ''),
                    'verdict' => $verdict,
                    'reason_code' => $reason,
                    'updated_at' => $row['created_at'] ?? null,
                ];
            }
            return $feedback;
        } catch (Throwable) {
            return [];
        }
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
        $internships = [];
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
                    INNER JOIN project_members pm ON pm.projectId = p.id AND pm.status = 'active'
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
            $projects = $this->mergeProjectSkillEvidence($projects, $studentId);
        }

        if ($inspector->hasTable('learner_internship_reports')) {
            try {
                $internships = $this->fetchAll(
                    'internships',
                    <<<'SQL'
                    SELECT r.id, r.applicationId, r.status, r.stage, r.hours, r.startDate, r.endDate,
                           r.reviewedAt, r.updatedAt
                    FROM learner_internship_reports r
                    WHERE r.studentId = :student_id AND r.status = 'verified'
                    ORDER BY r.reviewedAt DESC, r.id ASC
                    SQL,
                    ['student_id' => $studentId]
                );
                $internships = $this->attachInternshipSkillTags($internships, $studentId);
            } catch (Throwable) {
                $internships = [];
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
            'internships' => $internships,
            'badges' => $badges,
        ];
    }

    /**
     * @param list<array<string,mixed>> $projects
     * @return list<array<string,mixed>>
     */
    private function mergeProjectSkillEvidence(array $projects, string $studentId): array
    {
        if ($projects === []) {
            return $projects;
        }
        $projectIds = array_values(array_filter(array_map(
            static fn (array $project): string => (string) ($project['id'] ?? ''),
            $projects,
        )));
        if ($projectIds === []) {
            return $projects;
        }

        $tagsByProject = [];
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        try {
            if ($this->inspector()->hasTable('project_skill_tags')) {
                $tagStatement = $this->pdo->prepare(
                    "SELECT pst.projectId, s.code, s.name FROM project_skill_tags pst INNER JOIN skills s ON s.id=pst.skillId AND s.status='active' WHERE pst.projectId IN ({$placeholders}) AND pst.verifiedAt IS NOT NULL ORDER BY s.code"
                );
                $tagStatement->execute($projectIds);
                foreach ($tagStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $tag) {
                    $tagsByProject[(string) $tag['projectId']][] = ['code' => (string) $tag['code'], 'name' => (string) $tag['name']];
                }
            }
        } catch (Throwable) {
            // Optional evidence table must never make the passport unavailable.
        }
        try {
            if ($this->inspector()->hasTable('learner_portfolio_skills') && $this->inspector()->hasTable('project_submissions')) {
                $portfolioStatement = $this->pdo->prepare(
                    "SELECT r.projectId, s.code, s.name
                     FROM learner_portfolio_skills ps
                     INNER JOIN project_submissions r ON r.id = ps.reportId AND ps.kind = 'project'
                     INNER JOIN project_members pm ON pm.projectId = r.projectId AND pm.studentId = r.studentId AND pm.status = 'active'
                     INNER JOIN skills s ON s.id = ps.skillId AND s.status = 'active'
                     WHERE r.studentId = ? AND r.status = 'verified' AND r.projectId IN ({$placeholders})
                     ORDER BY s.code"
                );
                $portfolioStatement->execute([$studentId, ...$projectIds]);
                foreach ($portfolioStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $tag) {
                    $tagsByProject[(string) $tag['projectId']][] = ['code' => (string) $tag['code'], 'name' => (string) $tag['name']];
                }
            }
        } catch (Throwable) {
            // Optional evidence table must never make the passport unavailable.
        }

        foreach ($projects as &$project) {
            $unique = [];
            foreach ($tagsByProject[(string) ($project['id'] ?? '')] ?? [] as $tag) {
                $code = strtolower(trim((string) ($tag['code'] ?? '')));
                if ($code === '' || isset($unique[$code])) {
                    continue;
                }
                $unique[$code] = ['code' => $code, 'name' => (string) ($tag['name'] ?? $code)];
            }
            $project['skill_tags'] = array_values($unique);
            $project['skill_codes'] = array_keys($unique);
        }
        unset($project);

        return $projects;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function attachInternshipSkillTags(array $rows, string $studentId): array
    {
        if ($rows === [] || !$this->inspector()->hasTable('learner_portfolio_skills')) {
            return $rows;
        }
        $ids = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), $rows)));
        if ($ids === []) {
            return $rows;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $statement = $this->pdo->prepare(
                "SELECT ps.reportId, s.code, s.name
                 FROM learner_portfolio_skills ps
                 INNER JOIN skills s ON s.id = ps.skillId AND s.status = 'active'
                 INNER JOIN learner_internship_reports r ON r.id = ps.reportId AND r.studentId = ? AND r.status = 'verified'
                 WHERE ps.kind = 'internship' AND ps.reportId IN ({$placeholders})
                 ORDER BY s.code"
            );
            $statement->execute([$studentId, ...$ids]);
            $tagsByReport = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $tag) {
                $code = strtolower(trim((string) ($tag['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $reportId = (string) $tag['reportId'];
                if (isset($tagsByReport[$reportId][$code])) {
                    continue;
                }
                $tagsByReport[$reportId][$code] = ['code' => $code, 'name' => (string) ($tag['name'] ?? $code)];
            }
            foreach ($rows as &$row) {
                $tags = array_values($tagsByReport[(string) ($row['id'] ?? '')] ?? []);
                $row['skill_tags'] = $tags;
                $row['skill_codes'] = array_values(array_column($tags, 'code'));
            }
            unset($row);
        } catch (Throwable) {
        }

        return $rows;
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
