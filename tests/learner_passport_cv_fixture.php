<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/learner/data/ReadModel/PassportCvViewModel.php';
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;
$mode=$argv[1]??'normal';
$data=['student'=>['full_name'=>'Nguyễn Minh Anh','email'=>'minhanh@example.test','phone'=>'0900 000 000','school_name'=>'Trường Đại học Công nghệ','class_name'=>'Kỹ thuật phần mềm · Khóa 2023 - 2027'],
 'skills'=>[], 'projects'=>[
 ['id'=>'p1','title'=>'Nền tảng quản lý học tập','status'=>'completed','member_status'=>'active','role'=>'Lập trình viên backend','contribution'=>'Xây dựng API quản lý khóa học, viết kiểm thử tự động và tài liệu hướng dẫn tích hợp.','updated_at'=>'2026-09-09'],
 ['id'=>'p2','title'=>'Ứng dụng theo dõi tiến độ dự án','status'=>'active','member_status'=>'active','role'=>'Thành viên phát triển','contribution'=>'Thiết kế giao diện theo dõi công việc và phối hợp kiểm tra trải nghiệm người dùng.','updated_at'=>'2026-09-08']],
 'internships'=>[['title'=>'Thực tập sinh phát triển phần mềm','enterprise_name'=>'Công ty Công nghệ Minh Họa','status'=>'accepted']],
 'teacher_evaluations'=>[['status'=>'published','published_at'=>'2026-09-08','comment'=>'Chủ động làm rõ yêu cầu và giải thích được các quyết định kỹ thuật. Cần tiếp tục cải thiện cách tổ chức mã nguồn và kiểm thử các trường hợp biên.','teacher_name'=>'Nguyễn Hoài Nam']],
 'experience'=>['confirmed_entries'=>[['activity_title'=>'Ngày hội kết nối sinh viên và doanh nghiệp','status'=>'confirmed','confirmed_at'=>'2026-08-20'],['activity_title'=>'Hoạt động tình nguyện tại trường','status'=>'confirmed','confirmed_at'=>'2026-07-15']]]];
foreach (['PHP','SQL','Kiểm thử phần mềm','Git','Phân tích yêu cầu','Làm việc nhóm'] as $name) $data['skills'][]=['name'=>$name,'source_type'=>'teacher','skill_status'=>'active','verification_status'=>'verified','verified_at'=>'2026-09-08'];
if ($mode==='sparse') $data=['student'=>$data['student']];
if ($mode==='portfolio') {
    $data['internships'][0]['application_id']='application-fixture';
    $data['verified_portfolio']=[
        'projects'=>[['contextId'=>'p1','title'=>'Nền tảng quản lý học tập','notes'=>'Bài nộp cá nhân đã được giảng viên kiểm tra và nghiệm thu.','reviewedAt'=>'2026-09-09']],
        'internships'=>[['contextId'=>'application-fixture','title'=>'Thực tập sinh phát triển phần mềm','organization'=>'Công ty Công nghệ Minh Họa','stage'=>'completed','startDate'=>'2026-06-01','endDate'=>'2026-08-31','hours'=>160,'reviewedAt'=>'2026-09-09']],
        'skills'=>[['name'=>'PHP','kind'=>'internship','reviewedAt'=>'2026-09-09']]
    ];
}
if ($mode==='long') {
    $data['student']['full_name']=str_repeat('Nguyễn Minh Anh ',12);
    $data['student']['school_name']=str_repeat('Trường đại học ',25);
    $data['student']['email']=str_repeat('email',25).'@example.test';
    foreach ($data['projects'] as &$p) { $p['title']=str_repeat('Dự án dài ',30); $p['contribution']=str_repeat('Đóng góp vào phát triển và kiểm thử ',80); $p['role']=str_repeat('Vai trò ',20); } unset($p);
    $data['projects']=array_merge($data['projects'],$data['projects'],$data['projects']);
    foreach ($data['skills'] as &$s) $s['name']=str_repeat($s['name'].' ',18); unset($s);
    $data['teacher_evaluations'][0]['comment']=str_repeat('Nhận xét có bằng chứng từ giảng viên. ',30);
}
$cv=PassportCvViewModel::build($data,'09/09/2026 10:00:00');
require dirname(__DIR__).'/app/learner/includes/passport-cv-template.php';
