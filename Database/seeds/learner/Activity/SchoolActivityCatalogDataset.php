<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Activity;

/**
 * Immutable catalog metadata for the school-scoped learner activity rollout.
 *
 * Times are deliberately expressed as offsets. The seeder resolves them from
 * its injected clock, so this file is safe to inspect and reuse without a DB.
 */
final class SchoolActivityCatalogDataset
{
    /** @return list<array<string,mixed>> */
    public static function records(): array
    {
        $talentHub = self::school('10000000-0000-4000-8000-000000000001', 'TalentHub Test School', '10000000-0000-4000-8000-000000000022', null, null, null, 'Liên hệ đơn vị tổ chức', 'TalentHub Test School');
        $nguyenTrai = self::school('20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '20000000-0000-4000-8000-000000000052', 'c3-nguyentrai@hcm.edu.vn', '028-3863-1234', '12 Sư Vạn Hạnh, Quận 10, TP. Hồ Chí Minh', 'Liên hệ THPT Nguyễn Trãi: c3-nguyentrai@hcm.edu.vn, 028-3863-1234', 'THPT Nguyễn Trãi');
        $fpt = self::school('22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '22000000-dc34-49ed-81d4-78446b313553', null, null, null, 'Liên hệ đơn vị tổ chức', 'Đại học FPT');

        return [
            self::record($talentHub, '00000000-0000-4000-8000-000000000302', 'Workshop Lập trình Python Ứng dụng', 'career_technical', 'Kỹ thuật', 'published', 25, 21, 8, 'Thực hành Python qua các bài toán ứng dụng gần gũi.', 'Phòng máy thực hành, hướng dẫn từng bước và phần trình bày sản phẩm cuối buổi.', ['Lập trình theo nhóm', 'Sửa lỗi chương trình', 'Trình bày sản phẩm'], ['Python', 'Tư duy logic', 'Làm việc nhóm'], 'Phòng máy A201', 'talenthub-python-workshop', 'automatic', 8),
            self::record($talentHub, '31000000-0000-4000-8000-000000000001', 'STEM Robotics: Chế tạo Robot Tự hành', 'career_technical', 'Kỹ thuật', 'published', 30, 14, 4, 'Khám phá cảm biến và lắp ráp robot tự hành theo đội.', 'Người học lắp ráp mô hình, lập trình điều khiển và thử nghiệm robot trên sa bàn.', ['Lắp ráp mô hình', 'Lập trình điều khiển', 'Chạy thử trên sa bàn'], ['Robotics', 'Điện tử cơ bản', 'Hợp tác'], 'Phòng STEM A305', 'talenthub-stem-robotics', 'automatic', 4),
            self::record($talentHub, '31000000-0000-4000-8000-000000000002', 'Digital Marketing cho Dự án Học đường', 'career_business', 'Kinh doanh', 'published', 30, 16, 3, 'Lập kế hoạch truyền thông số cho một dự án học đường.', 'Các đội xác định đối tượng, xây dựng thông điệp và thử nghiệm lịch nội dung truyền thông.', ['Phân tích người xem', 'Viết thông điệp', 'Lập lịch nội dung'], ['Digital Marketing', 'Giao tiếp', 'Lập kế hoạch'], 'Phòng đa năng A102', 'talenthub-digital-marketing', 'automatic', 3),
            self::record($talentHub, '31000000-0000-4000-8000-000000000003', 'Creative Studio: Thiết kế Thương hiệu Cá nhân', 'career_arts', 'Sáng tạo', 'published', 25, 18, 3, 'Tạo bộ nhận diện cá nhân thể hiện sở thích và thế mạnh.', 'Người học thực hành chọn màu sắc, kiểu chữ và bố cục để tạo một hồ sơ thương hiệu cá nhân.', ['Xây dựng moodboard', 'Thiết kế nhận diện', 'Nhận phản hồi'], ['Thiết kế đồ họa', 'Sáng tạo', 'Tự thể hiện'], 'Xưởng sáng tạo A103', 'talenthub-creative-studio', 'automatic', 3),
            self::record($talentHub, '31000000-0000-4000-8000-000000000004', 'Dự án Cộng đồng Trường học Xanh', 'career_sports_academic', 'Cộng đồng', 'published', 35, 20, 4, 'Đề xuất một giải pháp xanh thiết thực cho khuôn viên trường.', 'Các nhóm khảo sát vấn đề, thiết kế giải pháp và trình bày kế hoạch triển khai có thể thực hiện.', ['Khảo sát thực địa', 'Thiết kế giải pháp', 'Thuyết trình dự án'], ['Phát triển bền vững', 'Nghiên cứu', 'Lãnh đạo'], 'Sân sinh hoạt chung', 'talenthub-green-school', 'teacher_review', 4),

            self::historical($nguyenTrai, '21000000-04ed-44b5-82fd-0db8f8fd3b05', '20000000-0000-4000-8000-000000000050', 'Dự án Robot cứu hộ', 'career_technical', 'Kỹ thuật', 30, -75, 33, 'Dự án robot cứu hộ đã hoàn thành của học sinh Nguyễn Trãi.', 'Hoạt động lịch sử lưu lại kết quả thiết kế, thử nghiệm và báo cáo của các đội tham gia.', ['Thiết kế robot', 'Thử nghiệm cứu hộ', 'Báo cáo kết quả'], ['Robotics', 'Giải quyết vấn đề', 'Làm việc nhóm'], 'Phòng STEM B305', 'nguyen-trai-python-robot'),
            self::record($nguyenTrai, '21000000-8e2d-4dae-8d47-ea4ac11c3dc3', 'Thử thách Doanh nhân trẻ', 'career_business', 'Kinh doanh', 'published', 30, 14, 33, 'Phát triển ý tưởng kinh doanh từ nhu cầu thực tế của học sinh.', 'Các đội xây dựng mô hình giá trị, dự toán cơ bản và thuyết trình trước hội đồng phản biện.', ['Xác định vấn đề', 'Lập mô hình kinh doanh', 'Pitching ý tưởng'], ['Khởi nghiệp', 'Tài chính cơ bản', 'Thuyết trình'], 'Hội trường B', 'nguyen-trai-young-business', 'automatic', 6),
            self::record($nguyenTrai, '31000000-0000-4000-8000-000000000005', 'Python & Robot Lab Nguyễn Trãi', 'career_technical', 'Kỹ thuật', 'published', 30, 15, 3, 'Thực hành lập trình Python và điều khiển mô hình robot.', 'Người học làm quen với Python, lắp ráp mô hình và lập trình các chuyển động cơ bản theo nhóm.', ['Lắp ráp mô hình', 'Lập trình điều khiển', 'Trình bày kết quả'], ['Python', 'Robotics', 'Làm việc nhóm'], 'Phòng STEM B305', 'nguyen-trai-python-robot', 'automatic', 3),
            self::record($nguyenTrai, '31000000-0000-4000-8000-000000000006', 'Thiết kế Poster Truyền thông Học đường', 'career_arts', 'Sáng tạo', 'published', 25, 17, 3, 'Thiết kế poster rõ thông điệp cho một chiến dịch trong trường.', 'Hoạt động hướng dẫn bố cục, màu sắc và quy trình nhận phản hồi để hoàn thiện poster truyền thông.', ['Phác thảo ý tưởng', 'Thiết kế poster', 'Phản biện sản phẩm'], ['Thiết kế đồ họa', 'Truyền thông', 'Sáng tạo'], 'Phòng nghệ thuật B204', 'nguyen-trai-poster-design', 'automatic', 3),
            self::record($nguyenTrai, '31000000-0000-4000-8000-000000000007', 'Chiến dịch Xanh hóa Sân trường', 'career_sports_academic', 'Cộng đồng', 'published', 35, 19, 4, 'Cùng khảo sát và thực hiện một sáng kiến xanh cho sân trường.', 'Các đội chọn một điểm cần cải thiện, lập kế hoạch hành động và chia sẻ tác động dự kiến.', ['Khảo sát sân trường', 'Lập kế hoạch xanh', 'Chia sẻ tác động'], ['Môi trường', 'Tổ chức dự án', 'Trách nhiệm cộng đồng'], 'Sân trường Nguyễn Trãi', 'nguyen-trai-green-campus', 'automatic', 4),
            self::record($nguyenTrai, '31000000-0000-4000-8000-000000000008', 'Hùng biện Ý tưởng Khởi nghiệp Trẻ', 'career_business', 'Kinh doanh', 'published', 30, 21, 3, 'Rèn kỹ năng bảo vệ ý tưởng khởi nghiệp trước người nghe.', 'Người học chuẩn bị luận điểm, trình bày ngắn gọn và nhận nhận xét trực tiếp từ giáo viên phụ trách.', ['Chuẩn bị luận điểm', 'Hùng biện', 'Nhận phản hồi'], ['Thuyết trình', 'Khởi nghiệp', 'Tự tin'], 'Hội trường B', 'nguyen-trai-startup-debate', 'teacher_review', 3),

            self::historical($fpt, '22000000-e945-49ac-857c-af53ffef54f0', '22000000-0b50-4a89-89bc-52db15918c03', 'FPTU Hackathon vì cộng đồng', 'career_technical', 'Kỹ thuật', 30, -70, 57, 'Hackathon cộng đồng đã hoàn thành của sinh viên Đại học FPT.', 'Hoạt động lịch sử ghi nhận quá trình phát triển giải pháp số và phần trình bày của các nhóm.', ['Xác định bài toán', 'Xây dựng nguyên mẫu', 'Trình bày giải pháp'], ['Phát triển phần mềm', 'Đổi mới sáng tạo', 'Làm việc nhóm'], 'Khu học tập sáng tạo FPT', 'fpt-ai-hacklab'),
            self::record($fpt, '22000000-b817-48d3-8ab2-6b7dc54cd16e', 'FPTU Music Studio Showcase', 'career_arts', 'Sáng tạo', 'published', 30, 20, 9, 'Trình diễn sản phẩm âm nhạc do sinh viên phối hợp thực hiện.', 'Người tham gia chuẩn bị tiết mục, hoàn thiện kỹ năng sân khấu và chia sẻ quy trình sáng tạo.', ['Chuẩn bị tiết mục', 'Biểu diễn nhóm', 'Chia sẻ hậu trường'], ['Âm nhạc', 'Biểu diễn', 'Hợp tác'], 'Studio âm nhạc FPT', 'fpt-music-showcase', 'automatic', 5),
            self::record($fpt, '31000000-0000-4000-8000-000000000009', 'AI Hacklab: Ứng dụng Trí tuệ Nhân tạo', 'career_technical', 'Kỹ thuật', 'published', 30, 14, 4, 'Khám phá cách xây dựng nguyên mẫu ứng dụng AI có trách nhiệm.', 'Các nhóm xác định bài toán, thử nghiệm công cụ AI và trình bày nguyên mẫu cùng giới hạn sử dụng.', ['Xác định bài toán', 'Tạo nguyên mẫu', 'Đánh giá tác động'], ['Trí tuệ nhân tạo', 'Lập trình', 'Tư duy phản biện'], 'Innovation Lab FPT', 'fpt-ai-hacklab', 'automatic', 4),
            self::record($fpt, '31000000-0000-4000-8000-000000000010', 'Product Sprint: Xây dựng Sản phẩm Số', 'career_business', 'Kinh doanh', 'published', 30, 16, 4, 'Đi từ nhu cầu người dùng đến nguyên mẫu sản phẩm số.', 'Hoạt động mô phỏng một product sprint ngắn với nghiên cứu người dùng, phác thảo và thử nghiệm.', ['Phỏng vấn nhu cầu', 'Phác thảo luồng dùng', 'Kiểm thử nguyên mẫu'], ['Product Management', 'Nghiên cứu người dùng', 'Giải quyết vấn đề'], 'Product Lab FPT', 'fpt-product-sprint', 'automatic', 4),
            self::record($fpt, '31000000-0000-4000-8000-000000000011', 'Green Campus: Sáng kiến Bền vững', 'career_sports_academic', 'Cộng đồng', 'published', 35, 18, 4, 'Xây dựng sáng kiến bền vững có thể thử nghiệm trong khuôn viên.', 'Các đội phân tích một thách thức môi trường, đề xuất chỉ số tác động và chia sẻ kế hoạch thử nghiệm.', ['Phân tích hiện trạng', 'Thiết kế sáng kiến', 'Đo lường tác động'], ['Phát triển bền vững', 'Phân tích dữ liệu', 'Hợp tác'], 'Green Hub FPT', 'fpt-green-campus', 'automatic', 4),
            self::record($fpt, '31000000-0000-4000-8000-000000000012', 'Startup Demo Day FPT University', 'career_business', 'Kinh doanh', 'published', 40, 22, 5, 'Trình bày sản phẩm khởi nghiệp trước hội đồng phản biện.', 'Sinh viên hoàn thiện câu chuyện sản phẩm, số liệu cốt lõi và phần trình bày trước khi nhận phản hồi.', ['Hoàn thiện pitch deck', 'Demo sản phẩm', 'Nhận phản biện'], ['Khởi nghiệp', 'Thuyết trình', 'Tư duy sản phẩm'], 'Hội trường Innovation FPT', 'fpt-startup-demo-day', 'teacher_review', 5),
        ];
    }

    /** @return array<string,string|null> */
    private static function school(string $id, string $name, string $teacherId, ?string $email, ?string $phone, ?string $address, string $contact, string $organizer): array
    {
        return compact('id', 'name', 'teacherId', 'email', 'phone', 'address', 'contact', 'organizer');
    }

    /** @return array<string,mixed> */
    private static function record(array $school, string $id, string $title, string $category, string $displayCategory, string $status, int $capacity, int $startOffsetDays, float|int $durationHours, string $summary, string $description, array $highlights, array $skills, string $locationName, string $coverSlug, string $approvalMode, float|int $confirmedHours): array
    {
        return self::baseRecord($school, $id, $title, $category, $displayCategory, $status, $capacity, $startOffsetDays, $durationHours, $summary, $description, $highlights, $skills, $locationName, $coverSlug, $school['teacherId']) + [
            'policy' => [
                'approvalMode' => $approvalMode,
                'registration_open_offset_days' => -7,
                'registration_close_offset_hours' => 24,
                'confirmedHours' => (float) $confirmedHours,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function historical(array $school, string $id, string $teacherId, string $title, string $category, string $displayCategory, int $capacity, int $startOffsetDays, float|int $durationHours, string $summary, string $description, array $highlights, array $skills, string $locationName, string $coverSlug): array
    {
        return self::baseRecord($school, $id, $title, $category, $displayCategory, 'completed', $capacity, $startOffsetDays, $durationHours, $summary, $description, $highlights, $skills, $locationName, $coverSlug, $teacherId) + ['policy' => null];
    }

    /** @return array<string,mixed> */
    private static function baseRecord(array $school, string $id, string $title, string $category, string $displayCategory, string $status, int $capacity, int $startOffsetDays, float|int $durationHours, string $summary, string $description, array $highlights, array $skills, string $locationName, string $coverSlug, string $teacherId): array
    {
        $existingActivitySnapshot = self::existingActivitySnapshot($id);
        return [
            'source' => $existingActivitySnapshot === null ? 'new' : 'existing',
            'preserveExistingFields' => $existingActivitySnapshot !== null,
            'existingActivitySnapshot' => $existingActivitySnapshot,
            'school_id' => $school['id'],
            'school_name' => $school['name'],
            'activity' => [
                'id' => $id,
                'title' => $title,
                'category' => $category,
                'status' => $status,
                'capacity' => $capacity,
                'start_offset_days' => $startOffsetDays,
                'duration_hours' => (float) $durationHours,
            ],
            'details' => [
                'responsibleTeacherId' => $teacherId,
                'audienceScope' => 'school_only',
                'displayCategory' => $displayCategory,
                'filterCategory' => $displayCategory,
                'summary' => $summary,
                'description' => $description,
                'experienceHighlights' => $highlights,
                'skillTags' => $skills,
                'eligibilityRules' => ['Đang theo học tại ' . $school['name'], 'Đăng ký trước thời hạn của hoạt động'],
                'benefitItems' => ['Xác nhận giờ trải nghiệm sau check-in', 'Minh chứng tham gia trên TalentHub'],
                'locationName' => $locationName,
                'locationAddress' => $school['address'] ?? ('Khuôn viên ' . $school['name']),
                'deliveryMode' => 'in_person',
                'organizerName' => $school['organizer'],
                'organizerContact' => $school['contact'],
                'organizerEmail' => $school['email'],
                'organizerPhone' => $school['phone'],
                'coverImageUrl' => '/app/learner/assets/activities/covers/' . $coverSlug . '.webp',
                'coverImageAlt' => 'Minh họa cho hoạt động ' . $title,
                'feeAmount' => 0,
                'currency' => 'VND',
                'targetAudience' => 'Người học đang theo học tại ' . $school['name'],
                'certificateLabel' => 'Minh chứng tham gia trên TalentHub',
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    private static function existingActivitySnapshot(string $id): ?array
    {
        return match ($id) {
            '00000000-0000-4000-8000-000000000302' => [
                'id' => $id,
                'schoolId' => '10000000-0000-4000-8000-000000000001',
                'createdByTeacherId' => '10000000-0000-4000-8000-000000000022',
                'title' => 'Workshop Lập trình Python Ứng dụng',
                'category' => 'career_technical',
                'startAt' => '2026-09-15 09:00:00.000000',
                'endAt' => '2026-09-15 17:00:00.000000',
                'capacity' => 25,
                'status' => 'published',
                'createdAt' => '2026-08-18 00:00:00.000000',
                'updatedAt' => '2026-08-18 00:00:00.000000',
            ],
            '21000000-04ed-44b5-82fd-0db8f8fd3b05' => [
                'id' => $id,
                'schoolId' => '20000000-0000-4000-8000-000000000001',
                'createdByTeacherId' => '20000000-0000-4000-8000-000000000050',
                'title' => 'Dự án Robot cứu hộ',
                'category' => 'career_technical',
                'startAt' => '2026-06-07 08:00:00.000000',
                'endAt' => '2026-06-08 17:00:00.000000',
                'capacity' => 30,
                'status' => 'completed',
                'createdAt' => '2026-08-21 10:23:57.585484',
                'updatedAt' => '2026-08-21 10:23:57.585484',
            ],
            '21000000-8e2d-4dae-8d47-ea4ac11c3dc3' => [
                'id' => $id,
                'schoolId' => '20000000-0000-4000-8000-000000000001',
                'createdByTeacherId' => '20000000-0000-4000-8000-000000000052',
                'title' => 'Thử thách Doanh nhân trẻ',
                'category' => 'career_business',
                'startAt' => '2026-09-04 08:00:00.000000',
                'endAt' => '2026-09-05 17:00:00.000000',
                'capacity' => 30,
                'status' => 'published',
                'createdAt' => '2026-08-21 10:23:57.589895',
                'updatedAt' => '2026-08-21 10:23:57.589895',
            ],
            '22000000-e945-49ac-857c-af53ffef54f0' => [
                'id' => $id,
                'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837',
                'createdByTeacherId' => '22000000-0b50-4a89-89bc-52db15918c03',
                'title' => 'FPTU Hackathon vì cộng đồng',
                'category' => 'career_technical',
                'startAt' => '2026-06-12 08:00:00.000000',
                'endAt' => '2026-06-14 17:00:00.000000',
                'capacity' => 30,
                'status' => 'completed',
                'createdAt' => '2026-08-21 10:23:57.602630',
                'updatedAt' => '2026-08-21 10:23:57.602630',
            ],
            '22000000-b817-48d3-8ab2-6b7dc54cd16e' => [
                'id' => $id,
                'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837',
                'createdByTeacherId' => '22000000-dc34-49ed-81d4-78446b313553',
                'title' => 'FPTU Music Studio Showcase',
                'category' => 'career_arts',
                'startAt' => '2026-09-10 08:00:00.000000',
                'endAt' => '2026-09-10 17:00:00.000000',
                'capacity' => 30,
                'status' => 'published',
                'createdAt' => '2026-08-21 10:23:57.609308',
                'updatedAt' => '2026-08-21 10:23:57.609308',
            ],
            default => null,
        };
    }
}
