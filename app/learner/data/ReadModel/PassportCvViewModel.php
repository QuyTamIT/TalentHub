<?php
declare(strict_types=1);
namespace TalentHub\Learner\Data\ReadModel;

final class PassportCvViewModel
{
    public static function text(mixed $value, int $limit): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags(is_scalar($value) ? (string)$value : '')));
        if (mb_strlen($text) <= $limit) return $text;
        return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
    }

    public static function build(array $data, string $generatedAt): array
    {
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
            'name'=>self::text($fullName, PHP_INT_MAX),
            'initials'=>mb_strtoupper($initials),
            'avatar_url'=>!empty($student['avatarUrl']) ? (string)$student['avatarUrl'] : (!empty($student['avatar_url']) ? (string)$student['avatar_url'] : ''),
            'headline'=>self::text($student['headline'] ?? '', 80),
            'location'=>self::text($student['location'] ?? 'Việt Nam', 50),
            'email'=>self::text($student['email'] ?? '', PHP_INT_MAX),
            'phone'=>self::text($student['phone'] ?? '', PHP_INT_MAX),
            'school'=>self::text($student['school_name'] ?? '', 130),
            'class'=>self::text($student['class_name'] ?? '', 70),
            'generated_at'=>$generatedAt,
            'skills'=>[], 'projects'=>[], 'internships'=>[], 'evaluations'=>[], 'activities'=>[],
            'badges'=>[], 'assessments'=>[], 'activity_summary'=>['total_hours'=>0.0,'total_activities'=>0],
            'passport_code'=>!empty($student['id']) ? ('TP-' . strtoupper(substr(str_replace('-', '', (string)$student['id']), 0, 8))) : 'PASSPORT-TEST-002',
            'has_experience'=>false, 'strengths_summary'=>'',
            'omitted'=>0,
        ];
        $skills = $data['skills'] ?? [];
        foreach ($portfolio['skills'] as $skill) $skills[]=[
            'name'=>$skill['name'],'verified_at'=>$skill['reviewedAt'],'verification_status'=>'verified','skill_status'=>'active',
            'source_type'=>$skill['kind']==='internship'?'internship_evaluation':'project_evaluation',
            'evidence_label'=>$skill['kind']==='internship'?'Thực tập · Giảng viên xác nhận':'Dự án · Giảng viên xác nhận'];
        usort($skills, static fn($a,$b) => strcmp($b['verified_at'] ?? '',$a['verified_at'] ?? '') ?: strcmp($a['name'] ?? '',$b['name'] ?? ''));
        foreach ($skills as $skill) {
            if (($skill['verification_status'] ?? '') !== 'verified' || empty($skill['verified_at'])
                || ($skill['skill_status'] ?? '') !== 'active'
                || !in_array($skill['source_type'] ?? '', ['teacher','project_evaluation','internship_evaluation'],true)) continue;
            $name=self::text($skill['name'] ?? '',42);
            if ($name==='' || in_array($name,array_column($cv['skills'],'name'),true)) continue;
            $score = isset($skill['level_score']) || isset($skill['levelScore'])
                ? max(0, min(100, (int) round((float) ($skill['level_score'] ?? $skill['levelScore']))))
                : (isset($skill['score']) ? max(0, min(100, (int) round((float)$skill['score']))) : null);
            $cv['skills'][]=[
                'name'=>$name,
                'score'=>$score,
                'category'=>(string)($skill['category'] ?? ''),
                'source'=>self::text($skill['evidence_label'] ?? 'Giảng viên xác nhận',55),
            ];
        }
        $projects=$data['projects'] ?? [];
        foreach ($projectEvidence as $id=>$evidence) {
            if (!in_array($id,array_column($projects,'id'),true)) $projects[]=['id'=>$id,'title'=>$evidence['title'],'status'=>'completed','member_status'=>'completed','updated_at'=>$evidence['reviewedAt']];
        }
        usort($projects,static fn($a,$b)=>strcmp($b['updated_at'] ?? $b['end_at'] ?? '',$a['updated_at'] ?? $a['end_at'] ?? '') ?: strcmp($a['id']??'',$b['id']??''));
        foreach ($projects as $project) {
            if (!in_array($project['member_status']??'', ['active','completed'],true) || !in_array($project['status']??'', ['active','in_progress','published','completed'],true)) continue;
            $evidence=$projectEvidence[$project['id']??'']??null;
            $categoryLabel = match(strtolower((string)($project['category'] ?? ''))) {
                'career_technical', 'technical', 'tech' => 'Kỹ thuật & Công nghệ',
                'career_business', 'business' => 'Kinh doanh & Đổi mới',
                'career_arts', 'arts' => 'Nghệ thuật & Thiết kế',
                'career_sports_academic', 'research' => 'Nghiên cứu & Học thuật',
                default => (string)($project['category'] ?? ''),
            };
            $cv['projects'][]=['title'=>self::text($project['title']??'',95),
                'category'=>self::text($categoryLabel, 40),
                'mentor'=>self::text($project['mentor_name']??$project['mentorName']??'', 60),
                'role'=>self::text(($project['role']??'')==='member' ? 'Thành viên' : ($project['role']??''),60),
                'status_label'=>$evidence?'Bài nộp đã được giảng viên nghiệm thu':(($project['status']??'')==='completed' ? 'Dự án đã hoàn thành' : 'Đang triển khai'),
                'contribution'=>self::text($evidence['notes']??$project['contribution']??$project['description']??'',180)];
        }
        // Verified experience takes precedence over its original recruitment application.
        $verifiedApplications=[];
        foreach ($portfolio['internships'] as $internship) {
            $verifiedApplications[]=$internship['contextId'];
            $cv['internships'][]=['title'=>self::text($internship['title'],85),'enterprise'=>self::text($internship['organization'],85),
                'status_label'=>($internship['stage']==='completed'?'Hoàn thành thực tập':'Đang thực tập').' · Giảng viên xác nhận',
                'details'=>self::text(implode(' · ',array_filter([$internship['startDate'].' → '.($internship['endDate']?:'hiện tại'),(string)$internship['hours'].' giờ'])),110)];
        }
        foreach ($data['internships'] ?? [] as $internship) {
            if (($internship['status']??'')!=='accepted') continue;
            if (in_array($internship['application_id']??'', $verifiedApplications,true)) continue;
            $cv['internships'][]=['title'=>self::text($internship['title']??'',85),'enterprise'=>self::text($internship['enterprise_name']??'',85),
                'status_label'=>'Đã được tiếp nhận; chưa xác nhận bắt đầu'];
        }
        $generalEvaluations = [];
        $activityEvaluations = [];
        foreach ($data['teacher_evaluations'] ?? [] as $evaluation) {
            if (($evaluation['status']??'')!=='published' || empty($evaluation['published_at'])) continue;
            $comment=self::text($evaluation['comment']??'',220);
            if ($comment==='') continue;
            $evalItem = [
                'comment'=>$comment,
                'teacher'=>self::text($evaluation['teacher_name']??$evaluation['teacherName']??'Giảng viên hướng dẫn',70),
                'date'=>substr((string)$evaluation['published_at'],0,10),
            ];
            if (empty($evaluation['activity_id']) && ($evaluation['context_type']??'')!=='activity') {
                $generalEvaluations[] = $evalItem;
            } else {
                $activityEvaluations[] = $evalItem;
            }
        }
        $cv['evaluations'] = !empty($generalEvaluations) ? $generalEvaluations : $activityEvaluations;
        foreach ($data['experience']['confirmed_entries'] ?? [] as $activity) {
            if (($activity['status']??'')!=='confirmed' || empty($activity['confirmed_at'])) continue;
            $title=self::text($activity['activity_title']??'',100);
            if ($title==='' || in_array($title,array_column($cv['activities'],'title'),true)) continue;
            $cv['activities'][]=['title'=>$title,'date'=>substr($activity['confirmed_at'],0,10)];
        }
        foreach (['skills'=>6,'projects'=>2,'internships'=>1,'evaluations'=>2,'activities'=>2] as $key=>$limit) {
            $cv['omitted']+=max(0,count($cv[$key])-$limit);
            $cv[$key]=array_slice($cv[$key],0,$limit);
        }
        foreach ($data['badges'] ?? [] as $b) {
            $name = self::text($b['name'] ?? '', 50);
            if ($name === '') continue;
            $cv['badges'][] = [
                'name' => $name,
                'description' => self::text($b['description'] ?? '', 70),
                'earned_at' => substr((string)($b['earnedAt'] ?? $b['earned_at'] ?? ''), 0, 10),
            ];
        }
        $cv['badges'] = array_slice($cv['badges'], 0, 3);
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
                'summary' => self::text($a['summary'] ?? '', 100),
            ];
        }
        $cv['assessments'] = array_slice($cv['assessments'], 0, 4);
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
        $cv['headline'] = self::text($headline, 80);
        $cv['strengths_summary'] = '';
        $verifiedSkillNames = array_column($cv['skills'], 'name');
        if (!empty($verifiedSkillNames)) {
            $skillList = implode(', ', array_slice($verifiedSkillNames, 0, 4));
            $cv['strengths_summary'] = 'Tôi có năng lực và thế mạnh đã được chứng thực về: ' . $skillList . '. Tôi luôn có tinh thần học hỏi nghiêm túc, tư duy chủ động và sẵn sàng cống hiến, tham gia các đề án thực tế cũng như chương trình thực tập tại doanh nghiệp.';
        } elseif (!empty($cv['evaluations'])) {
            $cv['strengths_summary'] = 'Tôi được thầy cô ghi nhận và đánh giá cao về tinh thần học hỏi, tư duy giải quyết vấn đề và sự tích cực trong các hoạt động rèn luyện chuyên môn.';
        } else {
            $cv['strengths_summary'] = 'Tôi đang tích cực trau dồi kiến thức chuyên môn, hoàn thiện hồ sơ năng lực và sẵn sàng đón nhận các cơ hội thử thách trong môi trường làm việc thực tế.';
        }
        return $cv;
    }
}
