<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
$path = dirname(__DIR__).'/app/learner/data/ReadModel/PassportCvViewModel.php';
if (!is_file($path)) throw new RuntimeException('CV view model has not been implemented');
require $path;
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;
function cvCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo '[PASS] '.$message.PHP_EOL; }
$data = ['student'=>['full_name'=>'Nguyễn Minh Anh','school_name'=>'Đại học Kiểm thử'],
 'skills'=>[
  ['name'=>'Attendance skill','source_type'=>'activity','verification_status'=>'verified','verified_at'=>'2026-09-01','skill_status'=>'active'],
  ['name'=>'Python','source_type'=>'teacher','verification_status'=>'verified','verified_at'=>'2026-09-01','skill_status'=>'active'],
 ],
 'projects'=>[['title'=>'Dự án thật','status'=>'completed','member_status'=>'active','role'=>'member','contribution'=>'Viết kiểm thử','end_at'=>'2026-09-01']],
 'internships'=>[['title'=>'Thực tập lập trình','enterprise_name'=>'Công ty thử nghiệm','status'=>'accepted']],
 'teacher_evaluations'=>[['status'=>'draft','comment'=>'Không in nháp'],['status'=>'published','published_at'=>'2026-09-01','comment'=>'Có tiến bộ','teacher_name'=>'Giảng viên']],
 'experience'=>['confirmed_entries'=>[['activity_title'=>'Hoạt động trường','status'=>'confirmed','confirmed_at'=>'2026-09-01']]],
];
$cv = PassportCvViewModel::build($data, '2026-09-09 10:00');
cvCheck(count($cv['skills'])===1 && $cv['skills'][0]['name']==='Python', 'activity participation never becomes verified professional skill');
cvCheck($cv['projects'][0]['status_label']==='Dự án đã hoàn thành', 'completed project status is explicit, not invented personal achievement');
cvCheck($cv['internships'][0]['status_label']==='Đã được tiếp nhận; chưa xác nhận bắt đầu', 'accepted application is not completed internship');
cvCheck(count($cv['evaluations'])===1 && $cv['evaluations'][0]['comment']==='Có tiến bộ', 'draft evaluation excluded');
$data['projects']=array_fill(0,20,['title'=>str_repeat('Dự án dài ',50),'status'=>'active','member_status'=>'active','contribution'=>str_repeat('Mô tả ',300)]);
$cv=PassportCvViewModel::build($data,'now');
cvCheck(count($cv['projects'])===2 && mb_strlen($cv['projects'][0]['contribution'])<=180, 'large histories select bounded representative content');
cvCheck(!str_contains(json_encode($cv,JSON_UNESCAPED_UNICODE),'Trưởng nhóm kỹ thuật'), 'no fabricated role');
