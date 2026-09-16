<?php
declare(strict_types=1);
namespace TalentHub\Learner\Data\ReadModel;

final class PassportCvViewModel
{
    public static function safeUrl(mixed $value): string
    {
        if (!is_string($value) || preg_match('/[\x00-\x20]/', $value)) return '';
        if (str_contains($value, '\\')) return '';
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) return $value;
        $parts = parse_url($value);
        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $value : '';
    }

    public static function text(mixed $value, int $limit): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags(is_scalar($value) ? (string)$value : '')));
        if (mb_strlen($text) <= $limit) return $text;
        return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
    }

    public static function build(array $data, string $generatedAt, bool $compact = true): array
    {
        // Student PDF keeps its one-page limits; application capture retains all CV content.
        $text = static fn(mixed $value, int $limit): string => self::text($value, $compact ? $limit : PHP_INT_MAX);
        // This aggregate is produced only by PortfolioRepository::verifiedForStudent,
        // never by browser input. No own declaration establishes verified competence.
        $portfolio=$data['verified_portfolio']??['projects'=>[],'internships'=>[],'skills'=>[]];
        $projectEvidence=[];
        foreach ($portfolio['projects'] as $row) $projectEvidence[$row['contextId']]=$row;
        $student = $data['student'] ?? [];
        $fullName = trim((string)($student['full_name'] ?? $student['fullName'] ?? ''));
        $nameParts = array_values(array_filter(preg_split('/\s+/u', $fullName) ?: []));
        $initials = 'TH';
        if (!empty($nameParts)) {
            $initials = count($nameParts) >= 2 ? mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1) : mb_substr($nameParts[0], 0, 2);
        }
        $cv = [
            'name'=>$text($fullName, PHP_INT_MAX),
            'initials'=>mb_strtoupper($initials),
            'avatar_url'=>!empty($student['avatarUrl']) ? (string)$student['avatarUrl'] : (!empty($student['avatar_url']) ? (string)$student['avatar_url'] : ''),
            'headline'=>$text($student['headline'] ?? '', 80),
            'location'=>$text($student['location'] ?? '', 50),
            'email'=>$text($student['email'] ?? '', PHP_INT_MAX),
            'phone'=>$text($student['phone'] ?? '', PHP_INT_MAX),
            'school'=>$text($student['school_name'] ?? '', 130),
            'class'=>$text($student['class_name'] ?? '', 70),
            'generated_at'=>$generatedAt,
            'skills'=>[], 'projects'=>[], 'internships'=>[], 'evaluations'=>[], 'activities'=>[],
            'badges'=>[], 'assessments'=>[], 'certificates'=>[], 'activity_summary'=>['total_hours'=>0.0,'total_activities'=>0],
            'passport_code'=>!empty($student['id']) ? ('TP-' . strtoupper(substr(str_replace('-', '', (string)$student['id']), 0, 8))) : '',
            'has_experience'=>false, 'strengths_summary'=>'', 'is_verified'=>false,
            'omitted'=>0,
        ];
        $skills = $data['skills'] ?? [];
        foreach ($portfolio['skills'] as $skill) $skills[]=[
            'name'=>$skill['name'],'verified_at'=>$skill['reviewedAt'],'verification_status'=>'verified','skill_status'=>'active',
            'source_type'=>$skill['kind']==='internship'?'internship_evaluation':'project_evaluation',
            'level_score'=>$skill['kind']==='internship'?null:($skill['score']??null),
            'evidence_label'=>$skill['kind']==='internship'?'Đã hoàn thành qua thực tập':'Dự án · Giảng viên xác nhận'];
        usort($skills, static fn($a,$b) => strcmp($b['verified_at'] ?? '',$a['verified_at'] ?? '') ?: strcmp($a['name'] ?? '',$b['name'] ?? ''));
        foreach ($skills as $skill) {
            if (($skill['verification_status'] ?? '') !== 'verified' || empty($skill['verified_at'])
                || ($skill['skill_status'] ?? '') !== 'active'
                || !in_array($skill['source_type'] ?? '', ['teacher','evaluation','project_evaluation','internship_evaluation','evidence'],true)) continue;
            $name=$text($skill['name'] ?? '',42);
            if ($name==='' || in_array($name,array_column($cv['skills'],'name'),true)) continue;
            $state = (string) ($skill['score_state'] ?? $skill['state'] ?? '');
            $rawScore = array_key_exists('level_score',$skill) ? $skill['level_score'] : ($skill['levelScore'] ?? ($skill['score'] ?? null));
            $score = $state === 'evidence_only' || $rawScore === null
                ? null
                : max(0, min(100, round((float)$rawScore, 2)));
            if ($score !== null && floor($score) === $score) $score = (int)$score;
            $cv['skills'][]=[
                'name'=>$name,
                'score'=>$score,
                'category'=>(string)($skill['category'] ?? ''),
                'source_type'=>(string)($skill['source_type']??''),
                'state'=>$state,
                'item_kind'=>(string)($skill['item_kind'] ?? 'skill'),
                'source'=>$text($skill['evidence_label'] ?? 'Giảng viên xác nhận',55),
            ];
        }
        $projects=$data['projects'] ?? [];
        foreach ($projectEvidence as $id=>$evidence) {
            if (!in_array($id,array_column($projects,'id'),true)) $projects[]=['id'=>$id,'title'=>$evidence['title'],'status'=>'completed','member_status'=>'completed','updated_at'=>$evidence['reviewedAt']];
        }
        usort($projects,static fn($a,$b)=>strcmp($b['updated_at'] ?? $b['end_at'] ?? '',$a['updated_at'] ?? $a['end_at'] ?? '') ?: strcmp($a['id']??'',$b['id']??''));
        foreach ($projects as $project) {
            if (empty($project['_snapshot_captured']) && (!in_array($project['member_status']??'', ['active','completed'],true) || !in_array($project['status']??'', ['active','in_progress','published','completed'],true))) continue;
            $evidence=$projectEvidence[$project['id']??'']??null;
            $categoryLabel = match(strtolower((string)($project['category'] ?? ''))) {
                'career_technical', 'technical', 'tech' => 'Kỹ thuật & Công nghệ',
                'career_business', 'business' => 'Kinh doanh & Đổi mới',
                'career_arts', 'arts' => 'Nghệ thuật & Thiết kế',
                'career_sports_academic', 'research' => 'Nghiên cứu & Học thuật',
                default => (string)($project['category'] ?? ''),
            };
            $cv['projects'][]=['title'=>$text($project['title']??'',95),
                'category'=>$text($categoryLabel, 40),
                'mentor'=>$text($project['mentor_name']??$project['mentorName']??'', 60),
                'role'=>$text(($project['role']??'')==='member' ? 'Thành viên' : ($project['role']??''),60),
                'status_label'=>$evidence?'Bài nộp đã được giảng viên nghiệm thu':(empty($project['status']) ? '' : ($project['status']==='completed' ? 'Dự án đã hoàn thành' : 'Đang triển khai')),
                'url'=>self::safeUrl($project['project_url'] ?? ''),
                'contribution'=>$text($evidence['notes']??$project['contribution']??$project['description']??'',180)];
        }
        // Verified experience takes precedence over its original recruitment application.
        $verifiedApplications=[];
        foreach ($portfolio['internships'] as $internship) {
            $verifiedApplications[]=$internship['contextId'];
            $cv['internships'][]=['title'=>$text($internship['title'],85),'enterprise'=>$text($internship['organization'],85),
                'status_label'=>($internship['stage']==='completed'?'Hoàn thành thực tập':'Đang thực tập').' · Giảng viên xác nhận',
                'details'=>$text(implode(' · ',array_filter([$internship['startDate'].' → '.($internship['endDate']?:'hiện tại'),(string)$internship['hours'].' giờ'])),110)];
        }
        foreach ($data['internships'] ?? [] as $internship) {
            if (($internship['status']??'')!=='accepted') continue;
            if (in_array($internship['application_id']??'', $verifiedApplications,true)) continue;
            $cv['internships'][]=['title'=>$text($internship['title']??'',85),'enterprise'=>$text($internship['enterprise_name']??'',85),
                'status_label'=>'Đã được tiếp nhận; chưa xác nhận bắt đầu'];
        }
        $generalEvaluations = [];
        $activityEvaluations = [];
        foreach ($data['teacher_evaluations'] ?? [] as $evaluation) {
            if (($evaluation['status']??'')!=='published' || empty($evaluation['published_at'])) continue;
            $comment=$text($evaluation['comment']??'',220);
            if ($comment==='') continue;
            $evalItem = [
                'comment'=>$comment,
                'teacher'=>$text($evaluation['teacher_name']??$evaluation['teacherName']??'Giảng viên hướng dẫn',70),
                'date'=>substr((string)$evaluation['published_at'],0,10),
            ];
            if (empty($evaluation['activity_id']) && ($evaluation['context_type']??'')!=='activity') {
                $generalEvaluations[] = $evalItem;
            } else {
                $activityEvaluations[] = $evalItem;
            }
        }
        $cv['evaluations'] = $compact
            ? (!empty($generalEvaluations) ? $generalEvaluations : $activityEvaluations)
            : array_merge($generalEvaluations, $activityEvaluations);
        if (!$compact) {
            foreach (array_merge($portfolio['projects'], $portfolio['internships']) as $evidence) {
                $comment = $text($evidence['feedback'] ?? '', 220);
                if ($comment === '') continue;
                $item = ['comment'=>$comment, 'teacher'=>$text($evidence['reviewerName'] ?? '', 70),
                    'date'=>substr((string)($evidence['reviewedAt'] ?? ''), 0, 10)];
                if (!in_array($item, $cv['evaluations'], true)) $cv['evaluations'][] = $item;
            }
        }
        foreach ($data['experience']['confirmed_entries'] ?? [] as $activity) {
            if (($activity['status']??'')!=='confirmed' || empty($activity['confirmed_at'])) continue;
            $title=$text($activity['activity_title']??'',100);
            if ($title==='' || in_array($title,array_column($cv['activities'],'title'),true)) continue;
            $cv['activities'][]=['title'=>$title,'date'=>substr($activity['confirmed_at'],0,10)];
        }
        foreach ($compact ? ['skills'=>6,'projects'=>2,'internships'=>1,'evaluations'=>2,'activities'=>2] : [] as $key=>$limit) {
            $cv['omitted']+=max(0,count($cv[$key])-$limit);
            $cv[$key]=array_slice($cv[$key],0,$limit);
        }
        foreach ($data['badges'] ?? [] as $b) {
            $name = $text($b['name'] ?? '', 50);
            if ($name === '') continue;
            $cv['badges'][] = [
                'name' => $name,
                'description' => $text($b['description'] ?? '', 70),
                'earned_at' => substr((string)($b['earnedAt'] ?? $b['earned_at'] ?? ''), 0, 10),
            ];
        }
        if ($compact) $cv['badges'] = array_slice($cv['badges'], 0, 3);
        foreach ($data['certificates'] ?? [] as $certificate) {
            $title=$text($certificate['title'] ?? '',95);
            if ($title==='') continue;
            $cv['certificates'][]=[
                'title'=>$title,
                'issuing_organization'=>$text($certificate['issuing_organization'] ?? $certificate['issuingOrganization'] ?? '',85),
                'issue_date'=>substr((string)($certificate['issue_date'] ?? $certificate['issueDate'] ?? ''),0,10),
                'verification_status'=>(string)($certificate['verification_status'] ?? $certificate['verificationStatus'] ?? ''),
                'url'=>self::safeUrl($certificate['credential_url'] ?? ''),
            ];
        }
        if ($compact) $cv['certificates']=array_slice($cv['certificates'],0,8);
        $seenTests = [];
        foreach ($data['assessment_results'] ?? [] as $a) {
            $testType = strtolower(trim((string)($a['test_type'] ?? $a['testType'] ?? '')));
            $baseKey = preg_replace('/_(primary|secondary|high_school|university|adult|middle|high|college)$/', '', $testType);
            if ($baseKey === '' || isset($seenTests[$baseKey])) continue;
            $seenTests[$baseKey] = true;
            $label = match($baseKey) {
                'disc' => 'DISC',
                'holland' => 'Holland',
                'multiple_intelligence', 'mi' => 'Đa trí thông minh (MI)',
                'mbti' => 'MBTI',
                default => strtoupper($baseKey),
            };
            $cv['assessments'][] = [
                'type' => $baseKey,
                'label' => $label,
                'code' => trim((string)($a['result_code'] ?? $a['resultCode'] ?? '')),
                'summary' => $text($a['summary'] ?? '', 100),
            ];
        }
        if ($compact) $cv['assessments'] = array_slice($cv['assessments'], 0, 4);
        $summary = $data['experience']['summary'] ?? [];
        $totalHours = (float)($summary['total_hours'] ?? 0);
        $totalActivities = (int)($summary['total_activities'] ?? 0);
        if ($totalActivities === 0 && !empty($cv['activities'])) {
            $totalActivities = count($cv['activities']);
        }
        $cv['activity_summary'] = [
            'total_hours' => $totalHours,
            'total_activities' => $totalActivities,
        ];
        $cv['has_experience'] = !empty($cv['projects']) || !empty($cv['internships']);
        $headline = trim((string)($student['headline'] ?? ''));
        $genericPattern = '/^(học\s*sinh|sinh\s*viên|student)(\s+(thpt|thcs|đại\s*học|cao\s*đẳng|trung\s*cấp|cấp\s*\d+|khối\s*\d+|năm\s*\d+))?$/iu';
        $genericHeadlines = ['học sinh', 'sinh viên', 'học sinh / sinh viên', 'sinh viên / học sinh', 'hoc sinh', 'sinh vien', 'student', 'pupil'];
        if ($headline === '' || in_array(mb_strtolower($headline), $genericHeadlines, true) || preg_match($genericPattern, $headline)) {
            $orientation = '';
            foreach ($cv['projects'] as $p) {
                if (!empty($p['category'])) {
                    $orientation = (string)$p['category'];
                    break;
                }
            }
            $className = trim((string)($student['class_name'] ?? $student['class'] ?? ''));
            if ($orientation !== '') {
                $headline = (str_starts_with($orientation, 'Định hướng') ? $orientation : ('Định hướng ' . $orientation));
            } elseif ($className !== '') {
                $headline = preg_match('/^\d+[A-Za-z0-9_-]*$/', $className) ? ('Lớp ' . $className) : $className;
            } else {
                $headline = '';
            }
        }
        $cv['headline'] = $text($headline, 80);
        $cv['is_verified'] = !empty($cv['skills']) || !empty($cv['evaluations']);
        $cv['strengths_summary'] = $text($student['bio'] ?? '', 500);
        $verifiedSkillNames = array_column($cv['skills'], 'name');
        if ($cv['strengths_summary'] === '' && !empty($verifiedSkillNames)) {
            $skillList = implode(', ', array_slice($verifiedSkillNames, 0, 4));
            $cv['strengths_summary'] = 'Năng lực có minh chứng: ' . $skillList . '.';
        }
        return $cv;
    }
}
