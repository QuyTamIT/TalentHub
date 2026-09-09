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
        // Identity/contact must remain complete; only descriptive sections are shortened.
        $cv = ['name'=>self::text($student['full_name'] ?? '', PHP_INT_MAX),
            'email'=>self::text($student['email'] ?? '', PHP_INT_MAX), 'phone'=>self::text($student['phone'] ?? '', PHP_INT_MAX),
            'school'=>self::text($student['school_name'] ?? '', 130), 'class'=>self::text($student['class_name'] ?? '', 70),
            'generated_at'=>$generatedAt, 'skills'=>[], 'projects'=>[], 'internships'=>[], 'evaluations'=>[], 'activities'=>[],
            'omitted'=>0];
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
            $cv['skills'][]=['name'=>$name,'source'=>self::text($skill['evidence_label'] ?? 'Giảng viên xác nhận',55)];
        }
        $projects=$data['projects'] ?? [];
        foreach ($projectEvidence as $id=>$evidence) {
            if (!in_array($id,array_column($projects,'id'),true)) $projects[]=['id'=>$id,'title'=>$evidence['title'],'status'=>'completed','member_status'=>'completed','updated_at'=>$evidence['reviewedAt']];
        }
        usort($projects,static fn($a,$b)=>strcmp($b['updated_at'] ?? $b['end_at'] ?? '',$a['updated_at'] ?? $a['end_at'] ?? '') ?: strcmp($a['id']??'',$b['id']??''));
        foreach ($projects as $project) {
            if (!in_array($project['member_status']??'', ['active','completed'],true) || !in_array($project['status']??'', ['active','in_progress','published','completed'],true)) continue;
            $evidence=$projectEvidence[$project['id']??'']??null;
            $cv['projects'][]=['title'=>self::text($project['title']??'',95),
                'role'=>self::text(($project['role']??'')==='member' ? 'Thành viên' : ($project['role']??''),60),
                'status_label'=>$evidence?'Bài nộp đã được giảng viên nghiệm thu':(($project['status']??'')==='completed' ? 'Dự án đã hoàn thành' : 'Đang tham gia'),
                'contribution'=>self::text($evidence['notes']??$project['contribution']??'',150)];
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
        foreach ($data['teacher_evaluations'] ?? [] as $evaluation) {
            if (($evaluation['status']??'')!=='published' || empty($evaluation['published_at']) || !empty($evaluation['activity_id']) || ($evaluation['context_type']??'')==='activity') continue;
            $comment=self::text($evaluation['comment']??'',220);
            if ($comment==='') continue;
            $cv['evaluations'][]=['comment'=>$comment,'teacher'=>self::text($evaluation['teacher_name']??'',70),'date'=>substr($evaluation['published_at'],0,10)];
        }
        foreach ($data['experience']['confirmed_entries'] ?? [] as $activity) {
            if (($activity['status']??'')!=='confirmed' || empty($activity['confirmed_at'])) continue;
            $title=self::text($activity['activity_title']??'',100);
            if ($title==='' || in_array($title,array_column($cv['activities'],'title'),true)) continue;
            $cv['activities'][]=['title'=>$title,'date'=>substr($activity['confirmed_at'],0,10)];
        }
        foreach (['skills'=>4,'projects'=>2,'internships'=>1,'evaluations'=>1,'activities'=>2] as $key=>$limit) {
            $cv['omitted']+=max(0,count($cv[$key])-$limit);
            $cv[$key]=array_slice($cv[$key],0,$limit);
        }
        return $cv;
    }
}
