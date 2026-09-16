<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

/** Read-only adapter. No repositories, live profile lookups or persistence. */
final class ApplicationSnapshotCvAdapter
{
    public static function build(array $snapshot, ?string $createdAt = null): array
    {
        $stamp = '';
        $capturedAt = self::value($snapshot, 'capturedAt', 'captured_at') ?: $createdAt;
        if (is_string($capturedAt) && $capturedAt !== '') {
            try {
                $stamp = (new \DateTimeImmutable($capturedAt, new \DateTimeZone('UTC')))
                    ->setTimezone(new \DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
            } catch (\Exception) { /* Do not invent a capture date for a legacy record. */ }
        }
        $legacy = self::legacyModel($snapshot, $stamp);
        $storedCv = self::value($snapshot, 'passportCv', 'passport_cv');
        if (!is_array($storedCv)) $storedCv = [];

        // A stored CV is authoritative. Recover additional captured items only from
        // this same payload, never from the current Student profile or job requirements.
        $frozen = array_intersect_key($storedCv, $legacy);
        $cv = array_replace($legacy, $frozen);
        foreach (['name','initials','avatar_url','headline','location','email','phone','school','class',
            'generated_at','passport_code','strengths_summary','date_of_birth','study_status','objective'] as $key) {
            $cv[$key] = self::completeText($cv[$key], $legacy[$key]);
        }
        $cv['avatar_url'] = PassportCvViewModel::safeUrl($cv['avatar_url']) ?: $legacy['avatar_url'];
        $recovered = 0;
        foreach (['skills'=>'name', 'projects'=>'title', 'internships'=>'title', 'evaluations'=>'comment',
            'activities'=>'title', 'badges'=>'name', 'assessments'=>'type', 'certificates'=>'title'] as $key=>$identity) {
            $storedRows = self::rows($frozen[$key] ?? []);
            $cv[$key] = self::normalizeRows($key, self::mergeRows($storedRows, $legacy[$key], $identity));
            if (in_array($key, ['skills','projects','internships','evaluations','activities'], true)) {
                $recovered += count($cv[$key]) - count($storedRows);
            }
        }
        $cv['omitted'] = max(0, (int)($frozen['omitted'] ?? 0) - $recovered);
        $cv['activity_summary'] = array_replace($legacy['activity_summary'],
            is_array($frozen['activity_summary'] ?? null) ? $frozen['activity_summary'] : []);
        $cv['has_experience'] = !empty($cv['projects']) || !empty($cv['internships']);
        $cv['is_verified'] = !empty($cv['skills']) || !empty($cv['evaluations']);
        $cv['unverified_skills'] = array_values(array_filter($legacy['unverified_skills'],
            static fn(array $skill): bool => !in_array($skill['name'], array_column($cv['skills'], 'name'), true)));
        return $cv;
    }

    /** Translate the legacy snapshot contract into the Student renderer's input. */
    private static function legacyModel(array $snapshot, string $stamp): array
    {
        $student = is_array($snapshot['student'] ?? null) ? $snapshot['student'] : [];
        $data = ['student' => [
            'id' => self::value($student, 'studentProfileId', 'id'),
            'full_name' => self::value($student, 'fullName', 'full_name'),
            'email' => $student['email'] ?? '', 'phone' => $student['phone'] ?? '',
            'location' => $student['location'] ?? '',
            'school_name' => self::value($student, 'schoolName', 'school_name', 'school'),
            'class_name' => self::value($student, 'className', 'class_name', 'class'),
            'headline' => $student['headline'] ?? '', 'bio' => $student['bio'] ?? '',
            'avatarUrl' => PassportCvViewModel::safeUrl(self::value($student, 'avatarUrl', 'avatar_url')),
        ], 'skills'=>[], 'projects'=>[], 'certificates'=>[], 'badges'=>[],
            'assessment_results'=>[], 'teacher_evaluations'=>[], 'internships'=>[]];

        $unverifiedSkills = [];
        foreach (self::rows($snapshot['skills'] ?? []) as $skill) {
            $provenance = array_replace($skill, is_array($skill['provenance'] ?? null) ? $skill['provenance'] : []);
            $state = self::value($skill, 'state', 'score_state', 'scoreState');
            $source = self::value($provenance, 'source_type', 'sourceType');
            $assessedAt = self::value($provenance, 'assessed_at', 'assessedAt', 'verified_at', 'verifiedAt');
            $reference = self::value($provenance, 'source_id', 'sourceId', 'assessment_id', 'assessmentId');
            $evidence = self::value($provenance, 'evidence_refs', 'evidenceRefs');
            $score = self::value($skill, 'score', 'level_score', 'levelScore');
            if (!in_array($state, ['scored', 'evidence_only'], true)
                || !in_array($source, ['teacher','evaluation','project_evaluation','internship_evaluation','evidence'], true)
                || empty($assessedAt) || (empty($reference) && empty($evidence))
                || ($state === 'scored' && !is_numeric($score))) {
                $name = self::text(self::value($skill, 'skillName', 'skill_name', 'name'));
                if ($name !== '') $unverifiedSkills[] = [
                    'name'=>$name, 'level'=>self::text($skill['level'] ?? ''),
                    'score'=>$state !== 'evidence_only' && is_numeric($score) ? $score : null,
                ];
                continue;
            }
            $data['skills'][] = [
                'name'=>self::value($skill, 'skillName', 'skill_name', 'name'),
                'category'=>$skill['category'] ?? '', 'state'=>$state,
                'item_kind'=>self::value($skill, 'itemKind', 'item_kind') ?: 'skill',
                'level_score'=>$state === 'scored' ? $score : null,
                'verified_at'=>$assessedAt, 'source_type'=>$source,
                'evidence_label'=>self::value($skill, 'evidenceLabel', 'evidence_label', 'source'),
                'verification_status'=>'verified', 'skill_status'=>'active',
            ];
        }
        foreach (self::rows($snapshot['projects'] ?? []) as $project) {
            $data['projects'][] = [
                '_snapshot_captured'=>true, 'id'=>self::value($project, 'projectId', 'id'),
                'title'=>$project['title'] ?? '', 'category'=>$project['category'] ?? '',
                'role'=>$project['role'] ?? '', 'status'=>$project['status'] ?? '',
                'mentor_name'=>self::value($project, 'mentorName', 'mentor_name', 'mentor'),
                'description'=>self::value($project, 'summary', 'description'),
                'contribution'=>self::value($project, 'contribution', 'summary', 'description'),
                'project_url'=>self::value($project, 'link', 'projectUrl', 'project_url', 'url'),
                'updated_at'=>self::value($project, 'updatedAt', 'updated_at'),
            ];
        }
        foreach (self::rows($snapshot['certificates'] ?? []) as $certificate) {
            $data['certificates'][] = [
                'title'=>self::value($certificate, 'name', 'title'),
                'issuing_organization'=>self::value($certificate, 'issuingOrganization', 'issuing_organization'),
                'issue_date'=>self::value($certificate, 'issueDate', 'issue_date'),
                'credential_url'=>self::value($certificate, 'credentialUrl', 'credential_url', 'url'),
                'verification_status'=>self::value($certificate, 'verificationStatus', 'verification_status'),
            ];
        }
        foreach (self::rows($snapshot['badges'] ?? []) as $badge) {
            $data['badges'][] = ['name'=>$badge['name'] ?? '', 'description'=>$badge['description'] ?? '',
                'earned_at'=>self::value($badge, 'earnedAt', 'earned_at', 'awardedAt')];
        }
        foreach (self::rows(self::value($snapshot, 'assessmentResults', 'assessment_results', 'assessments')) as $assessment) {
            $data['assessment_results'][] = [
                'test_type'=>self::value($assessment, 'testType', 'test_type', 'type'),
                'result_code'=>self::value($assessment, 'resultCode', 'result_code', 'code'),
                'summary'=>$assessment['summary'] ?? '',
            ];
        }
        foreach (self::rows(self::value($snapshot, 'teacherEvaluations', 'teacher_evaluations')) as $evaluation) {
            $data['teacher_evaluations'][] = [
                'status'=>$evaluation['status'] ?? '', 'comment'=>$evaluation['comment'] ?? '',
                'published_at'=>self::value($evaluation, 'publishedAt', 'published_at'),
                'teacher_name'=>self::value($evaluation, 'teacherName', 'teacher_name', 'evaluator'),
                'activity_id'=>self::value($evaluation, 'activityId', 'activity_id'),
                'context_type'=>self::value($evaluation, 'contextType', 'context_type'),
            ];
        }
        $experience = is_array($snapshot['experience'] ?? null) ? $snapshot['experience'] : [];
        $summary = is_array($experience['summary'] ?? null) ? $experience['summary'] : [];
        $data['experience'] = ['confirmed_entries'=>[], 'summary'=>[
            'total_hours'=>$experience['totalConfirmedHours'] ?? $experience['total_confirmed_hours'] ?? $summary['total_hours'] ?? 0,
            'total_activities'=>$experience['totalActivitiesAttended'] ?? $experience['total_activities_attended'] ?? $summary['total_activities'] ?? 0,
        ]];
        foreach (self::rows(self::value($experience, 'confirmedEntries', 'confirmed_entries')) as $activity) {
            $data['experience']['confirmed_entries'][] = [
                'activity_title'=>self::value($activity, 'activityTitle', 'activity_title'),
                'status'=>$activity['status'] ?? '',
                'confirmed_at'=>self::value($activity, 'confirmedAt', 'confirmed_at'),
            ];
        }
        // Portfolio data was already verified and frozen by the capture repository.
        // Reuse the Student mapper, without querying the student's current portfolio.
        $portfolio = self::value($snapshot, 'verifiedPortfolio', 'verified_portfolio');
        if (is_array($portfolio)) {
            $data['verified_portfolio'] = [
                'projects'=>self::rows($portfolio['projects'] ?? []),
                'internships'=>self::rows($portfolio['internships'] ?? []),
                'skills'=>self::rows($portfolio['skills'] ?? []),
            ];
        }
        $cv = PassportCvViewModel::build($data, $stamp, compact: false);
        // Do not derive an applicant's objective or summary from skills/projects.
        $cv['headline'] = self::text($student['headline'] ?? '');
        $cv['strengths_summary'] = self::text(self::value($student, 'bio', 'strengths_summary', 'summary')
            ?: self::value($snapshot, 'summary', 'strengths_summary'));
        $cv['objective'] = self::text(self::value($student, 'objective', 'careerObjective', 'career_objective')
            ?: self::value($snapshot, 'objective', 'careerObjective', 'career_objective'));
        $cv['date_of_birth'] = self::text(self::value($student, 'dateOfBirth', 'date_of_birth'));
        $cv['study_status'] = self::text(self::value($student, 'studyStatus', 'study_status'));
        $cv['unverified_skills'] = $unverifiedSkills;
        // Retain both the project description and the applicant's own contribution.
        foreach ($cv['projects'] as &$project) {
            $matches = array_values(array_filter($data['projects'], static fn(array $row): bool => $row['title'] === $project['title']));
            if (count($matches) === 1) $project['description'] = self::text($matches[0]['description']);
        }
        unset($project);
        // The Student PDF model only carries an activity's title/date. Keep the full
        // captured activity here, including repeated participation on different dates.
        $cv['activities'] = [];
        foreach (self::rows(self::value($experience, 'confirmedEntries', 'confirmed_entries')) as $activity) {
            $title = self::text(self::value($activity, 'activityTitle', 'activity_title', 'title'));
            if ($title === '') continue;
            $cv['activities'][] = [
                'title'=>$title,
                'date'=>substr((string)self::value($activity, 'confirmedAt', 'confirmed_at', 'date'), 0, 10),
                'hours'=>$activity['hours'] ?? null,
                'start_at'=>self::value($activity, 'startAt', 'start_at'),
                'end_at'=>self::value($activity, 'endAt', 'end_at'),
                'status'=>$activity['status'] ?? '',
            ];
        }
        // Do not turn an absent total into a computed count or a synthetic zero.
        $cv['activity_summary'] = [
            'total_hours'=>$experience['totalConfirmedHours'] ?? $experience['total_confirmed_hours'] ?? $summary['total_hours'] ?? null,
            'total_activities'=>$experience['totalActivitiesAttended'] ?? $experience['total_activities_attended'] ?? $summary['total_activities'] ?? null,
        ];
        // A captured internship may have progressed beyond its original accepted application.
        $capturedInternships = [];
        foreach (self::rows($snapshot['internships'] ?? []) as $internship) {
            $title = self::text($internship['title'] ?? '');
            if ($title === '') continue;
            $status = self::text($internship['status'] ?? '');
            $capturedInternships[] = [
                'title'=>$title,
                'enterprise'=>self::text(self::value($internship, 'enterpriseName', 'enterprise_name', 'enterprise')),
                'status_label'=>self::text(self::value($internship, 'statusLabel', 'status_label')) ?: match ($status) {
                    'accepted'=>'Đã được tiếp nhận; chưa xác nhận bắt đầu',
                    'in_progress'=>'Đang thực tập', 'completed'=>'Đã hoàn thành', default=>$status,
                },
                'details'=>self::text($internship['details'] ?? ''),
            ];
        }
        $cv['internships'] = self::mergeRows($cv['internships'], $capturedInternships, 'title');
        $cv['has_experience'] = !empty($cv['projects']) || !empty($cv['internships']);
        $passportCode = self::value($snapshot, 'passportCode', 'passport_code')
            ?: self::value($student, 'passportCode', 'passport_code');
        // A legacy student ID is not a captured Passport code. The stored CV, when
        // present, supplies its actual code in build().
        $cv['passport_code'] = self::text($passportCode);
        return $cv;
    }

    private static function value(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== []) return $row[$key];
        }
        return '';
    }

    private static function mergeRows(array $preferred, array $fallback, string $identity): array
    {
        // Recover fields only from an unambiguous item in this same snapshot.
        $result = $preferred;
        foreach ($fallback as $row) {
            if (empty($row[$identity])) continue;
            $matches = [];
            foreach ($result as $index => $stored) {
                if (self::completeText($stored[$identity] ?? '', $row[$identity]) !== (string)$row[$identity]
                    || empty($stored[$identity])) continue;
                foreach (['teacher','date','enterprise','issuing_organization','issue_date','role'] as $context) {
                    if (!empty($stored[$context]) && !empty($row[$context]) && $stored[$context] !== $row[$context]) continue 2;
                }
                $matches[] = $index;
            }
            if (count($matches) !== 1) {
                $result[] = $row;
                continue;
            }
            $index = $matches[0];
            foreach ($row as $key => $value) {
                // An explicit null score means no score; never fill it from another representation.
                if ($key === 'score' && array_key_exists($key, $result[$index])) continue;
                if (!array_key_exists($key, $result[$index])) $result[$index][$key] = $value;
                elseif ($key !== 'score' && ($result[$index][$key] === '' || $result[$index][$key] === null)) $result[$index][$key] = $value;
                elseif (is_string($value)) $result[$index][$key] = self::completeText($result[$index][$key], $value);
            }
        }
        return $result;
    }

    private static function text(mixed $value): string
    {
        return PassportCvViewModel::text($value, PHP_INT_MAX);
    }

    private static function completeText(mixed $stored, mixed $fallback): string
    {
        $stored = self::text($stored);
        $fallback = self::text($fallback);
        if ($stored === '') return $fallback;
        if (str_ends_with($stored, '…') && str_starts_with($fallback, mb_substr($stored, 0, -1))) return $fallback;
        return $stored;
    }

    private static function normalizeRows(string $section, array $rows): array
    {
        $defaults = match ($section) {
            'skills' => ['name'=>'', 'score'=>null, 'state'=>'', 'source_type'=>'', 'source'=>'', 'category'=>'', 'item_kind'=>'skill'],
            'projects' => ['title'=>'', 'category'=>'', 'role'=>'', 'mentor'=>'', 'status_label'=>'', 'contribution'=>'', 'description'=>'', 'url'=>''],
            'internships' => ['title'=>'', 'enterprise'=>'', 'status_label'=>'', 'details'=>''],
            'evaluations' => ['comment'=>'', 'teacher'=>'', 'date'=>''],
            'activities' => ['title'=>'', 'date'=>'', 'hours'=>null, 'start_at'=>'', 'end_at'=>'', 'status'=>''],
            'badges' => ['name'=>'', 'description'=>'', 'earned_at'=>''],
            'assessments' => ['type'=>'', 'label'=>'', 'code'=>'', 'summary'=>''],
            'certificates' => ['title'=>'', 'issuing_organization'=>'', 'issue_date'=>'', 'verification_status'=>'', 'url'=>''],
        };
        return array_map(static function (array $row) use ($defaults): array {
            $row = array_replace($defaults, array_intersect_key($row, $defaults));
            foreach ($row as $key => $value) {
                $row[$key] = in_array($key, ['score','hours'], true) ? (is_numeric($value) ? $value : null) : self::text($value);
            }
            if (($row['state'] ?? '') === 'evidence_only') $row['score'] = null;
            return $row;
        }, $rows);
    }

    public static function render(array $snapshot, ?string $createdAt = null): string
    {
        $cv = self::build($snapshot, $createdAt);
        $isApplicationSnapshot = true;
        $verificationUrl = self::verificationUrl($snapshot, $cv);
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/includes/passport-cv-template.php';
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }

    /** Same Passport destination as Student export; only the captured owner's code is eligible. */
    private static function verificationUrl(array $snapshot, array $cv): string
    {
        $student = is_array($snapshot['student'] ?? null) ? $snapshot['student'] : [];
        $studentId = self::text(self::value($student, 'studentProfileId', 'student_profile_id', 'id'));
        $code = self::text($cv['passport_code'] ?? '');
        if (!preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/i', $studentId)
            || $code !== 'TP-' . strtoupper(substr(str_replace('-', '', $studentId), 0, 8))) {
            return '';
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/i', $host)) return '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443 ? 'https' : 'http';
        $path = function_exists('app_href') ? app_href('/app/learner/shared-profile.php') : '/app/learner/shared-profile.php';
        // Rendering the QR performs no Passport lookup. Scanning it uses the existing
        // sharing/consent checks; its response is never merged into this application CV.
        return $scheme . '://' . $host . $path . '?code=' . rawurlencode($code);
    }

    private static function rows(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
