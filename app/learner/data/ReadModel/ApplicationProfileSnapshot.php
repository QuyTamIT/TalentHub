<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

use DateTimeImmutable;
use DateTimeZone;

/** One serialization contract for new applications and proven historical repairs. */
final class ApplicationProfileSnapshot
{
    public const VERSION = '2.2.0';

    public static function build(array $data, string $consentId, string $capturedAt): array
    {
        $stamp = (new DateTimeImmutable($capturedAt, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
        $cv = PassportCvViewModel::build($data, $stamp, compact: false);
        $cv['avatar_url'] = PassportCvViewModel::safeUrl($cv['avatar_url']);
        $student = $data['student'];
        $skills = [];
        foreach ($data['skills'] ?? [] as $skill) {
            if (!in_array($skill['state'] ?? '', ['scored', 'evidence_only'], true)) continue;
            $skills[] = [
                'skillId'=>$skill['skill_id'], 'skillName'=>$skill['name'] ?? $skill['skill_name'],
                'category'=>$skill['category'], 'itemKind'=>$skill['item_kind'] ?? 'skill',
                'groupCode'=>$skill['group_code'] ?? null, 'state'=>$skill['state'],
                'score'=>$skill['state'] === 'scored' ? $skill['score'] : null,
                'maxScore'=>$skill['max_score'] ?? 100,
                'evidenceLabel'=>$skill['evidence_label'] ?? '',
                'provenance'=>array_intersect_key($skill, array_flip([
                    'source_type', 'source_id', 'source_version', 'assessment_id',
                    'assessed_at', 'score_method', 'evidence_refs',
                ])),
            ];
        }
        $certificates = array_map(static fn(array $c): array => [
            'certificateId'=>$c['id'], 'name'=>$c['title'],
            'issuingOrganization'=>$c['issuing_organization'], 'issueDate'=>$c['issue_date'],
            'verificationStatus'=>$c['verification_status'],
            'credentialUrl'=>PassportCvViewModel::safeUrl($c['credential_url'] ?? ''),
        ], $data['certificates'] ?? []);
        $projects = array_map(static fn(array $p): array => [
            'projectId'=>$p['id'], 'title'=>$p['title'], 'category'=>$p['category'] ?? '',
            'role'=>$p['role'] ?? '', 'summary'=>$p['description'] ?? '',
            'contribution'=>$p['contribution'] ?? '', 'status'=>$p['status'],
            'memberStatus'=>$p['member_status'] ?? '', 'endAt'=>$p['end_at'] ?? null,
            'updatedAt'=>$p['updated_at'] ?? null,
            'link'=>PassportCvViewModel::safeUrl($p['project_url'] ?? ''),
        ], $data['projects'] ?? []);

        return [
            'schemaVersion'=>self::VERSION,
            'capturedAt'=>(new DateTimeImmutable($capturedAt, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'consentId'=>$consentId, 'passportCv'=>$cv,
            'student'=>[
                'studentProfileId'=>$student['id'], 'fullName'=>$student['full_name'],
                'email'=>$student['email'] ?? null, 'phone'=>$student['phone'] ?? null,
                'dateOfBirth'=>$student['date_of_birth'] ?? null, 'studyStatus'=>$student['study_status'] ?? null,
                'className'=>$student['class_name'] ?? null, 'schoolName'=>$student['school_name'] ?? null,
                'headline'=>$student['headline'] ?? null, 'location'=>$student['location'] ?? null,
                'bio'=>$student['bio'] ?? null,
                'avatarUrl'=>PassportCvViewModel::safeUrl($student['avatarUrl'] ?? $student['avatar_url'] ?? ''),
            ],
            'summary'=>$cv['strengths_summary'], 'skills'=>$skills,
            'certificates'=>$certificates, 'projects'=>$projects,
            'assessmentResults'=>$data['assessment_results'] ?? [],
            'badges'=>$data['badges'] ?? [], 'teacherEvaluations'=>$data['teacher_evaluations'] ?? [],
            'internships'=>$data['internships'] ?? [],
            'verifiedPortfolio'=>$data['verified_portfolio'] ?? [],
            'experience'=>[
                'totalConfirmedHours'=>$data['experience']['summary']['total_hours'] ?? 0,
                'totalActivitiesAttended'=>$data['experience']['summary']['total_activities'] ?? 0,
                'confirmedEntries'=>$data['experience']['confirmed_entries'] ?? [],
            ],
        ];
    }
}
