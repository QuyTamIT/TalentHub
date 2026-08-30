<?php
/** Learner activity read model. Teacher-owned catalog, learner-owned registrations. */

require_once dirname(__DIR__) . '/data/bootstrap.php';

if (!function_exists('learner_activity_mock_catalog')) {
    function learner_activity_mock_catalog(): array
    {
        $common = [
            'source' => 'learner_mock', 'source_role' => 'teacher', 'school_id' => 'school-demo-nguyen-du',
            'created_by_teacher_id' => 'teacher-demo-le-huong', 'organizer_name' => 'Trường THPT Nguyễn Du',
            'organizer_contact' => 'activities@nguyendu.edu.vn', 'status' => 'published', 'cost' => 'Miễn phí',
            'registration_opens_at' => '2026-08-01T00:00:00+07:00', 'registration_closes_at' => '2026-08-25T23:59:59+07:00',
            'cancellation_closes_at' => '2026-08-26T12:00:00+07:00',
        ];
        return [
            array_merge($common, ['id'=>'iot-lab','title'=>'IoT Lab — Cảm biến thông minh','category'=>'Kỹ thuật','filter_category'=>'Kỹ thuật','tone'=>'primary','summary'=>'Thực hành cảm biến môi trường và xây dựng mô hình vườn thông minh.','description'=>'Học sinh làm việc theo nhóm để kết nối cảm biến với ESP32, đọc dữ liệu và trình bày giải pháp tự động hóa.','start_at'=>'2026-08-28T14:00:00+07:00','end_at'=>'2026-08-28T17:00:00+07:00','location'=>'Phòng B305','format'=>'Trực tiếp','participants'=>38,'capacity'=>50,'approval_mode'=>'automatic','skills'=>['IoT','Lập trình','Làm việc nhóm'],'requirements'=>['Học sinh lớp 10–12','Mang theo laptop nếu có'],'benefits'=>['3 giờ trải nghiệm chờ xác nhận','Minh chứng dự án nhóm']]),
            array_merge($common, ['id'=>'drone-workshop','title'=>'Drone Workshop','category'=>'Sáng tạo','filter_category'=>'Sáng tạo','tone'=>'secondary','summary'=>'Khám phá nguyên lý bay và điều khiển drone an toàn.','description'=>'Workshop kết hợp kiến thức vật lý với thực hành điều khiển drone trong khu vực được giám sát.','start_at'=>'2026-08-30T09:00:00+07:00','end_at'=>'2026-08-30T12:00:00+07:00','location'=>'Sân vận động','format'=>'Trực tiếp','participants'=>20,'capacity'=>20,'approval_mode'=>'automatic','skills'=>['Điều khiển','An toàn','Phối hợp nhóm'],'requirements'=>['Có mặt trước 15 phút'],'benefits'=>['3 giờ trải nghiệm','Chứng nhận tham gia']]),
            array_merge($common, ['id'=>'startup-pitch','title'=>'Startup Club — Pitch Night','category'=>'Kinh doanh','filter_category'=>'Kinh doanh','tone'=>'success','summary'=>'Trình bày ý tưởng và nhận phản hồi từ cố vấn.','description'=>'Đêm thuyết trình dành cho học sinh có ý tưởng dự án, cần đăng ký nội dung để giáo viên duyệt.','start_at'=>'2026-08-29T18:30:00+07:00','end_at'=>'2026-08-29T21:00:00+07:00','location'=>'Hall A','format'=>'Trực tiếp','participants'=>12,'capacity'=>30,'approval_mode'=>'teacher_review','skills'=>['Thuyết trình','Kinh doanh','Tư duy phản biện'],'requirements'=>['Nộp mô tả ý tưởng ngắn'],'benefits'=>['Phản hồi từ cố vấn','2.5 giờ trải nghiệm']]),
            array_merge($common, ['id'=>'ai-bootcamp','title'=>'AI Bootcamp','category'=>'Công nghệ','filter_category'=>'Kỹ thuật','tone'=>'primary','summary'=>'Làm quen dữ liệu, Python và mô hình AI cơ bản.','description'=>'Chương trình thực hành theo dự án với dữ liệu mẫu và bài toán phân loại đơn giản.','start_at'=>'2026-09-01T09:00:00+07:00','end_at'=>'2026-09-01T16:00:00+07:00','location'=>'Phòng IT','format'=>'Trực tiếp','participants'=>25,'capacity'=>40,'approval_mode'=>'teacher_review','skills'=>['Python','AI','Dữ liệu'],'requirements'=>['Biết lập trình cơ bản'],'benefits'=>['6 giờ trải nghiệm','Tài liệu thực hành']]),
            array_merge($common, ['id'=>'design-thinking','title'=>'Design Thinking Lab','category'=>'Sáng tạo','filter_category'=>'Sáng tạo','tone'=>'secondary','summary'=>'Giải quyết vấn đề bằng quan sát, ý tưởng và prototype.','description'=>'Học sinh thực hành quy trình thấu cảm, xác định vấn đề, lên ý tưởng và tạo mẫu nhanh.','start_at'=>'2026-09-02T15:00:00+07:00','end_at'=>'2026-09-02T18:00:00+07:00','location'=>'Studio C','format'=>'Trực tiếp','participants'=>9,'capacity'=>25,'approval_mode'=>'automatic','skills'=>['Thiết kế','Sáng tạo','Phỏng vấn'],'requirements'=>['Sẵn sàng làm việc nhóm'],'benefits'=>['3 giờ trải nghiệm','Prototype nhóm']]),
            array_merge($common, ['id'=>'charity-marathon','title'=>'Marathon từ thiện','category'=>'Cộng đồng','filter_category'=>'Cộng đồng','tone'=>'success','summary'=>'Chạy bộ gây quỹ và lan tỏa tinh thần cộng đồng.','description'=>'Hoạt động thể thao cộng đồng có hướng dẫn an toàn và các cự ly phù hợp cho học sinh.','start_at'=>'2026-09-06T06:00:00+07:00','end_at'=>'2026-09-06T09:00:00+07:00','location'=>'Hồ Tây','format'=>'Trực tiếp','participants'=>67,'capacity'=>100,'approval_mode'=>'automatic','skills'=>['Thể lực','Cộng đồng','Kỷ luật'],'requirements'=>['Có xác nhận sức khỏe phù hợp'],'benefits'=>['3 giờ cộng đồng','Kỷ niệm chương']]),
        ];
    }
}

if (!function_exists('learner_activity_find')) {
    function learner_activity_find(string $id): ?array
    {
        return \TalentHub\Learner\Data\ReadModel\ActivityReadModel::resolveForStudent(
            learner_activity_repository(),
            learner_current_student_id(),
            $id
        );
    }
}

if (!function_exists('learner_activity_mock_registration_history')) {
    function learner_activity_mock_registration_history(string $studentId): array
    {
        if ($studentId !== 'student-demo-001') return [];
        $base = ['student_id'=>$studentId,'source'=>'learner_mock','created_at'=>'2026-08-10T09:00:00+07:00','updated_at'=>'2026-08-10T09:00:00+07:00','cancelled_at'=>null,'checkin_id'=>null,'experience_hours'=>null,'feedback'=>null];
        return [
            array_merge($base,['id'=>'registration-demo-registered','activity_id'=>'iot-lab','status'=>'approved']),
            array_merge($base,['id'=>'registration-demo-pending','activity_id'=>'startup-pitch','status'=>'pending']),
            array_merge($base,['id'=>'registration-demo-waitlisted','activity_id'=>'drone-workshop','status'=>'pending']),
            array_merge($base,['id'=>'registration-demo-checked','activity_id'=>'ai-bootcamp','status'=>'attended','checkin_id'=>'checkin-demo-ai']),
            array_merge($base,['id'=>'registration-demo-completed','activity_id'=>'design-thinking','status'=>'attended','checkin_id'=>'checkin-demo-design','experience_hours'=>3,'feedback'=>['rating'=>5,'comment'=>'Hoạt động hữu ích và dễ áp dụng.']]),
            array_merge($base,['id'=>'registration-demo-cancelled','activity_id'=>'charity-marathon','status'=>'cancelled','cancelled_at'=>'2026-08-11T10:00:00+07:00']),
        ];
    }
}

if (!function_exists('learner_activity_repository')) {
    function learner_activity_repository(): \TalentHub\Learner\Data\Contracts\ActivityRepository
    {
        return learner_repository_factory()->activity(
            learner_activity_mock_catalog(),
            learner_activity_mock_registration_history('student-demo-001')
        );
    }
}

if (!function_exists('learner_activity_catalog')) {
    function learner_activity_catalog(): array
    {
        return \TalentHub\Learner\Data\ReadModel\ActivityReadModel::activities(
            learner_activity_repository()->discoverForStudent(
                learner_current_student_id(),
                new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh')),
            )
        );
    }
}

if (!function_exists('learner_activity_registration_history')) {
    function learner_activity_registration_history(string $studentId): array
    {
        return \TalentHub\Learner\Data\ReadModel\ActivityReadModel::registrations(
            learner_activity_repository()->registrationTimelineFor($studentId)
        );
    }
}

if (!function_exists('learner_activity_active_registrations')) {
    /** Registrations that still belong on the current student's registered page. */
    function learner_activity_active_registrations(string $studentId): array
    {
        $timeline = \TalentHub\Learner\Data\ReadModel\ActivityReadModel::registrations(
            learner_activity_repository()->registrationTimelineFor($studentId)
        );

        return array_values(array_filter(
            $timeline,
            static fn (array $registration): bool => in_array(
                (string) ($registration['status'] ?? ''),
                ['pending', 'approved', 'waitlisted'],
                true
            )
        ));
    }
}

if (!function_exists('learner_activity_attendance_history')) {
    /** Attendance-resolved history, already scoped by student and school in the repository. */
    function learner_activity_attendance_history(string $studentId): array
    {
        $timeline = \TalentHub\Learner\Data\ReadModel\ActivityReadModel::registrations(
            learner_activity_repository()->registrationTimelineFor($studentId)
        );
        $history = array_values(array_filter(
            $timeline,
            static fn (array $registration): bool => in_array(
                (string) ($registration['status'] ?? ''),
                ['attended', 'no_show'],
                true
            )
        ));
        foreach ($history as &$registration) {
            if (($registration['status'] ?? '') === 'no_show') {
                $registration['experience_hours'] = 0.0;
                $registration['checked_in_at'] = null;
            }
        }
        unset($registration);
        usort($history, static function (array $left, array $right): int {
            $timestamp = static function (array $item): int {
                foreach (['attendance_resolved_at', 'checked_in_at', 'end_at', 'updated_at'] as $field) {
                    $value = strtotime((string) ($item[$field] ?? ''));
                    if ($value !== false) return $value;
                }
                return 0;
            };
            return $timestamp($right) <=> $timestamp($left);
        });
        return $history;
    }
}
