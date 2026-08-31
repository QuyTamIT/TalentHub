<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

final class SchoolAiProjectCatalogDataset
{
    public const CANONICAL_SKILL_CODES = [
        'research',
        'problem_solving',
        'leadership',
        'teamwork',
        'sports_discipline',
        'entrepreneurship',
        'creative_design',
        'communication',
        'python',
        'data_analysis',
    ];

    public const HERO_STUDENTS = [
        'thpt' => '20000000-0000-4000-8000-000000000060',
        'fpt' => '22000000-53d8-4897-8d68-ab3f78db0ce9',
        'btec' => '95542f8b-6b6a-5cef-9b36-9416a08ead3c',
    ];

    private const SKILL_LABELS = [
        'research' => 'Nghiên cứu',
        'problem_solving' => 'Giải quyết vấn đề',
        'leadership' => 'Lãnh đạo',
        'teamwork' => 'Làm việc nhóm',
        'sports_discipline' => 'Rèn luyện thể chất',
        'entrepreneurship' => 'Khởi nghiệp',
        'creative_design' => 'Thiết kế sáng tạo',
        'communication' => 'Giao tiếp',
        'python' => 'Lập trình Python',
        'data_analysis' => 'Phân tích dữ liệu',
    ];

    /** @return array<string,array{id:string,name:string,provider_name:string,location:string,mentor_id:string,band:string}> */
    public static function schools(): array
    {
        return [
            'thpt' => [
                'id' => '20000000-0000-4000-8000-000000000001',
                'name' => 'THPT Nguyễn Trãi',
                'provider_name' => 'THPT Nguyễn Trãi',
                'location' => '12 Sư Vạn Hạnh, Quận 10, TP. Hồ Chí Minh',
                'mentor_id' => '20000000-0000-4000-8000-000000000053',
                'band' => 'high',
            ],
            'fpt' => [
                'id' => '22000000-b512-4ede-852b-f4a508f3e837',
                'name' => 'Đại học FPT',
                'provider_name' => 'Đại học FPT',
                'location' => 'Khuôn viên Đại học FPT',
                'mentor_id' => '22000000-a084-4652-8a62-805d1613cf38',
                'band' => 'college',
            ],
            'btec' => [
                'id' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783',
                'name' => 'Cao đẳng Quốc tế BTEC FPT (Dữ liệu demo)',
                'provider_name' => 'Cao đẳng Quốc tế BTEC FPT',
                'location' => 'Khuôn viên BTEC FPT',
                'mentor_id' => '24000000-0000-4000-8000-000000000011',
                'band' => 'college',
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function projects(): array
    {
        $projects = [];
        foreach (self::definitions() as $definition) {
            $school = self::schools()[$definition['school_key']];
            $requiredSkills = [];
            foreach ($definition['required'] as [$code, $minimumScore]) {
                $requiredSkills[] = self::skill($code, $minimumScore);
            }
            $learningOutcomes = [];
            foreach ($definition['outcomes'] as $code) {
                $learningOutcomes[] = ['code' => $code, 'label' => self::SKILL_LABELS[$code]];
            }
            $description = sprintf(
                'Vấn đề: %s Người dùng: %s Hạng mục chính: %s Sản phẩm bàn giao: %s',
                $definition['problem'],
                $definition['users'],
                implode(', ', $definition['work_packages']),
                $definition['product'],
            );
            $projects[] = array_merge($definition, [
                'school_id' => $school['id'],
                'mentor_id' => $school['mentor_id'],
                'catalog_id' => $definition['id'],
                'provider_name' => $school['provider_name'],
                'location' => $school['location'],
                'education_bands' => [$school['band']],
                'description' => $description,
                'summary' => $description,
                'status' => 'in_progress',
                'publish_status' => 'published',
                'start_at' => '2026-09-01 08:00:00',
                'deadline_at' => '2027-12-31 17:00:00',
                'project_url' => 'https://github.com/talenthub-demo/' . $definition['slug'],
                'canonical_url' => '/app/learner/project.php?id=' . rawurlencode($definition['id']),
                'capacity' => 12,
                'enrolled_count' => 0,
                'required_skills' => $requiredSkills,
                'learning_outcomes' => $learningOutcomes,
            ]);
        }
        return $projects;
    }

    /** @return list<string> */
    public static function managedProjectIds(): array
    {
        return array_column(self::projects(), 'id');
    }

    /** @return array<string,array{student_id:string,project_id:string,skills:array<string,int>,assessment_dimensions:array<string,float>,experience_tags:list<string>}> */
    public static function heroPaths(): array
    {
        return [
            'thpt' => [
                'student_id' => self::HERO_STUDENTS['thpt'],
                'project_id' => '51000000-0000-4000-8000-000000000001',
                'skills' => ['python' => 85, 'leadership' => 90, 'data_analysis' => 81, 'teamwork' => 73, 'communication' => 72],
                'assessment_dimensions' => ['a' => 100.0],
                'experience_tags' => [],
            ],
            'fpt' => [
                'student_id' => self::HERO_STUDENTS['fpt'],
                'project_id' => '52000000-0000-4000-8000-000000000004',
                'skills' => ['creative_design' => 80, 'communication' => 90, 'leadership' => 90, 'teamwork' => 65, 'data_analysis' => 70],
                'assessment_dimensions' => ['a' => 100.0],
                'experience_tags' => [],
            ],
            'btec' => [
                'student_id' => self::HERO_STUDENTS['btec'],
                'project_id' => '53000000-0000-4000-8000-000000000001',
                'skills' => self::btecHeroSkills(),
                'assessment_dimensions' => ['logi' => 100.0],
                'experience_tags' => [],
            ],
        ];
    }

    /** @return array<string,int> */
    public static function btecHeroSkills(): array
    {
        return [
            'problem_solving' => 88,
            'research' => 82,
            'teamwork' => 74,
            'communication' => 70,
        ];
    }

    /** @return array{code:string,minimum_score:int,label:string} */
    private static function skill(string $code, int $minimumScore): array
    {
        return ['code' => $code, 'minimum_score' => $minimumScore, 'label' => self::SKILL_LABELS[$code]];
    }

    /** @return list<array<string,mixed>> */
    private static function definitions(): array
    {
        return [
            self::definition('50000000-0000-4000-8000-000000000004', 'thpt', 'edushield-safety', 'Hệ thống Cảnh báo Sớm An toàn Mạng & Phòng chống Bắt nạt Học đường (EduShield)', 'career_technical', 'problem_solving', 'beginner', 20000000, [['problem_solving', 55], ['communication', 55]], ['research', 'leadership'], 'Các dấu hiệu bắt nạt và lừa đảo trực tuyến thường được phát hiện quá muộn trong môi trường học đường.', 'học sinh, giáo viên chủ nhiệm và bộ phận tư vấn học đường của THPT Nguyễn Trãi.', ['khảo sát tình huống rủi ro', 'xây dựng bộ quy tắc cảnh báo', 'thử nghiệm quy trình tiếp nhận an toàn'], 'một nguyên mẫu cảnh báo sớm kèm cẩm nang phản hồi và báo cáo thử nghiệm bảo vệ quyền riêng tư.'),
            self::definition('51000000-0000-4000-8000-000000000001', 'thpt', 'nguyen-trai-ai-study-assistant', 'Trợ lý AI Ôn tập Cá nhân hóa cho Học sinh THPT', 'career_technical', 'a', 'beginner', 18000000, [['python', 70], ['data_analysis', 65], ['communication', 60]], ['research', 'problem_solving'], 'Học sinh chưa có một công cụ giúp nhận biết phần kiến thức yếu và sắp xếp nội dung ôn tập theo tiến độ cá nhân.', 'học sinh lớp 10 đến lớp 12 và giáo viên bộ môn tại THPT Nguyễn Trãi.', ['phân tích nhu cầu học tập', 'xây dựng logic gợi ý bằng Python', 'đánh giá lời giải thích với người dùng'], 'một trợ lý ôn tập có bảng tiến độ, gợi ý bài học và báo cáo kiểm thử tính hữu ích, minh bạch.'),
            self::definition('51000000-0000-4000-8000-000000000002', 'thpt', 'nguyen-trai-learning-dashboard', 'Bảng Điều khiển Dữ liệu Học tập và Chuyên cần', 'career_technical', 'a', 'beginner', 16000000, [['data_analysis', 65], ['python', 70], ['teamwork', 60]], ['research', 'communication'], 'Dữ liệu điểm số và chuyên cần đang phân tán nên giáo viên khó nhận biết sớm học sinh cần được hỗ trợ.', 'ban học tập, giáo viên chủ nhiệm và học sinh THPT Nguyễn Trãi.', ['chuẩn hóa dữ liệu mẫu', 'xây dựng chỉ số học tập', 'thiết kế dashboard và kiểm tra khả năng đọc hiểu'], 'một dashboard bảo vệ dữ liệu cá nhân, có bộ lọc lớp, cảnh báo xu hướng và tài liệu giải thích chỉ số.'),
            self::definition('51000000-0000-4000-8000-000000000003', 'thpt', 'nguyen-trai-green-school-map', 'GreenSchool Map — Bản đồ Sống xanh trong Khuôn viên', 'career_sports_academic', 'research', 'introductory', 12000000, [['research', 45], ['teamwork', 55]], ['data_analysis', 'leadership'], 'Nhà trường chưa có bản đồ trực quan ghi nhận điểm xanh, nơi phân loại rác và khu vực cần cải thiện môi trường.', 'toàn thể học sinh, câu lạc bộ môi trường và tổ quản trị cơ sở vật chất.', ['khảo sát thực địa theo nhóm', 'chuẩn hóa vị trí và ảnh minh chứng', 'thiết kế bản đồ cùng đề xuất cải thiện'], 'một bản đồ số tương tác, bộ dữ liệu đã kiểm chứng và kế hoạch hành động xanh theo từng khu vực.'),
            self::definition('51000000-0000-4000-8000-000000000004', 'thpt', 'nguyen-trai-safenet-student', 'SafeNet Student — Cẩm nang Nhận diện Lừa đảo Trực tuyến', 'career_technical', 'problem_solving', 'introductory', 10000000, [['communication', 55], ['research', 45]], ['problem_solving', 'leadership'], 'Học sinh tiếp xúc với nhiều thông điệp lừa đảo nhưng thiếu tình huống thực hành để nhận diện và phản ứng an toàn.', 'học sinh THPT, phụ huynh và giáo viên phụ trách kỹ năng số.', ['thu thập mẫu tình huống đã ẩn danh', 'phân loại dấu hiệu lừa đảo', 'thiết kế nội dung hướng dẫn dễ hiểu'], 'một cẩm nang số có câu hỏi tình huống, cây quyết định phản ứng và bộ tài liệu truyền thông học đường.'),
            self::definition('51000000-0000-4000-8000-000000000005', 'thpt', 'nguyen-trai-digital-museum', 'Ký ức Nguyễn Trãi — Bảo tàng Số Lịch sử Nhà trường', 'career_arts', 'creative_design', 'beginner', 15000000, [['creative_design', 45], ['communication', 55]], ['research', 'teamwork'], 'Tư liệu lịch sử của nhà trường chưa được kể lại theo một trải nghiệm số hấp dẫn và dễ tiếp cận với học sinh mới.', 'học sinh, cựu học sinh, giáo viên và khách tham quan THPT Nguyễn Trãi.', ['nghiên cứu và xác minh tư liệu', 'xây dựng câu chuyện theo mốc thời gian', 'thiết kế triển lãm số có chú thích'], 'một bảo tàng số responsive gồm bộ sưu tập đã cấp quyền, dòng thời gian và hướng dẫn đóng góp tư liệu.'),
            self::definition('51000000-0000-4000-8000-000000000006', 'thpt', 'nguyen-trai-smartclass-iot', 'SmartClass IoT — Giám sát Chất lượng Không khí Lớp học', 'career_technical', 'logi', 'beginner', 22000000, [['python', 60], ['data_analysis', 55]], ['problem_solving', 'research'], 'Lớp học chưa có dữ liệu liên tục về nhiệt độ, độ ẩm và chất lượng không khí để điều chỉnh thông gió hợp lý.', 'học sinh, giáo viên và tổ quản trị phòng học của THPT Nguyễn Trãi.', ['lắp cảm biến mẫu an toàn', 'thu thập và làm sạch dữ liệu', 'xây dựng cảnh báo cùng dashboard theo thời gian'], 'một trạm đo thử nghiệm, dashboard Python và báo cáo khuyến nghị vận hành lớp học dựa trên dữ liệu.'),
            self::definition('51000000-0000-4000-8000-000000000007', 'thpt', 'nguyen-trai-youth-startup-fair', 'Youth Startup Fair — Gian hàng Khởi nghiệp Học sinh', 'career_business', 'entrepreneurship', 'introductory', 14000000, [['entrepreneurship', 45], ['communication', 55], ['teamwork', 55]], ['leadership', 'data_analysis'], 'Nhiều ý tưởng học sinh chưa được kiểm chứng với người dùng hoặc trình bày bằng mô hình tài chính cơ bản.', 'các nhóm học sinh, giáo viên cố vấn và khách tham quan ngày hội khởi nghiệp.', ['khảo sát nhu cầu', 'xây dựng mô hình giá trị và ngân sách', 'chuẩn bị gian hàng cùng bài thuyết trình'], 'một gian hàng thử nghiệm, hồ sơ mô hình kinh doanh và báo cáo phản hồi khách hàng có số liệu.'),

            self::definition('50000000-0000-4000-8000-000000000001', 'fpt', 'ecosmart-ai', 'Ứng dụng AI phân loại rác & Tái chế thông minh trong học đường (EcoSmart AI)', 'career_technical', 'data_analysis', 'intermediate', 25000000, [['python', 55], ['data_analysis', 55], ['teamwork', 55]], ['problem_solving', 'leadership'], 'Việc phân loại rác trong khuôn viên còn thiếu phản hồi tức thời và dữ liệu để đo hiệu quả thay đổi hành vi.', 'sinh viên, đơn vị vận hành khuôn viên và câu lạc bộ môi trường Đại học FPT.', ['xây dựng tập dữ liệu hình ảnh có kiểm soát', 'thử nghiệm mô hình phân loại', 'thiết kế cơ chế tích điểm và đo lường'], 'một nguyên mẫu AI phân loại rác, dashboard tác động và tài liệu nêu rõ giới hạn của mô hình.'),
            self::definition('50000000-0000-4000-8000-000000000003', 'fpt', 'agri-bridge-ecom', 'Nền tảng Sàn kết nối Nông sản số cho Hợp tác xã Thanh niên Khởi nghiệp (AgriBridge)', 'career_business', 'entrepreneurship', 'intermediate', 40000000, [['entrepreneurship', 55], ['communication', 60], ['teamwork', 55]], ['data_analysis', 'leadership'], 'Sản phẩm OCOP của các hợp tác xã thanh niên khó tiếp cận khách hàng thành thị và thiếu dữ liệu truy xuất dễ hiểu.', 'hợp tác xã thanh niên, người mua thành thị và nhóm vận hành sàn.', ['nghiên cứu hành trình mua hàng', 'thiết kế gian hàng và QR truy xuất', 'thử nghiệm mô hình doanh thu cùng đối tác'], 'một sàn thương mại điện tử mẫu, bộ hồ sơ sản phẩm minh bạch và dashboard theo dõi chuyển đổi.'),
            self::definition('52000000-0000-4000-8000-000000000001', 'fpt', 'fpt-smart-campus', 'FPT Smart Campus — Tối ưu Năng lượng bằng IoT và Dữ liệu', 'career_technical', 'data_analysis', 'intermediate', 30000000, [['data_analysis', 60], ['problem_solving', 55], ['teamwork', 55]], ['python', 'research'], 'Mức tiêu thụ điện tại các khu học tập chưa được phân tích theo khung giờ để phát hiện điểm lãng phí và cơ hội tối ưu.', 'ban vận hành, sinh viên và các nhóm nghiên cứu phát triển bền vững Đại học FPT.', ['xây dựng mô hình dữ liệu cảm biến', 'phân tích tải tiêu thụ', 'thử nghiệm cảnh báo và kịch bản tiết kiệm'], 'một dashboard năng lượng, mô hình cảnh báo bất thường và báo cáo tác động với giả định được công khai.'),
            self::definition('52000000-0000-4000-8000-000000000002', 'fpt', 'fpt-ai-career-mentor', 'AI Career Mentor — Trợ lý Định hướng Nghề nghiệp Sinh viên', 'career_technical', 'a', 'introductory', 24000000, [['communication', 70], ['creative_design', 65], ['leadership', 70]], ['research', 'problem_solving'], 'Sinh viên cần một trải nghiệm hướng nghiệp giải thích được gợi ý thay vì chỉ đưa ra danh sách nghề nghiệp chung chung.', 'sinh viên các năm học và chuyên viên dịch vụ nghề nghiệp Đại học FPT.', ['nghiên cứu nhu cầu và nguyên tắc an toàn', 'thiết kế hội thoại cùng cách giải thích bằng chứng', 'kiểm thử trải nghiệm với nhiều hồ sơ'], 'một trợ lý hướng nghiệp mẫu, bộ tiêu chí minh bạch và báo cáo kiểm thử thiên lệch, tính hữu ích.'),
            self::definition('52000000-0000-4000-8000-000000000003', 'fpt', 'fpt-finguard-lab', 'FinGuard Lab — Phát hiện Giao dịch Bất thường', 'career_technical', 'data_analysis', 'intermediate', 32000000, [['data_analysis', 65], ['problem_solving', 55], ['research', 50]], ['python', 'communication'], 'Các mẫu giao dịch bất thường khó nhận biết bằng quy tắc đơn giản và cần được giải thích rõ để người phân tích kiểm tra.', 'sinh viên fintech, giảng viên hướng dẫn và nhóm phân tích rủi ro mô phỏng.', ['tạo dữ liệu giao dịch ẩn danh', 'xây dựng đặc trưng và mô hình phát hiện', 'thiết kế màn hình giải thích cảnh báo'], 'một phòng lab dữ liệu, mô hình baseline có chỉ số đánh giá và dashboard giải thích từng cảnh báo.'),
            self::definition('52000000-0000-4000-8000-000000000004', 'fpt', 'fpt-inclusive-campus-ux', 'Inclusive Campus UX — Thiết kế Trải nghiệm Số Tiếp cận', 'career_arts', 'a', 'introductory', 20000000, [['creative_design', 70], ['communication', 70], ['teamwork', 60]], ['research', 'problem_solving'], 'Một số luồng dịch vụ số trong trường còn khó sử dụng với người có nhu cầu tiếp cận khác nhau hoặc thiết bị hạn chế.', 'sinh viên, cán bộ hỗ trợ và người dùng có nhu cầu tiếp cận tại Đại học FPT.', ['phỏng vấn người dùng có đồng thuận', 'đánh giá rào cản theo tiêu chí tiếp cận', 'thiết kế và kiểm thử nguyên mẫu'], 'một prototype truy cập được, thư viện thành phần cơ bản và báo cáo nghiên cứu nêu rõ phát hiện, giới hạn.'),
            self::definition('52000000-0000-4000-8000-000000000005', 'fpt', 'mekong-travel-intelligence', 'Mekong Travel Intelligence — Phân tích Dữ liệu Du lịch', 'career_business', 'data_analysis', 'intermediate', 28000000, [['data_analysis', 65], ['communication', 60], ['research', 50]], ['creative_design', 'leadership'], 'Dữ liệu du lịch vùng Mekong đến từ nhiều nguồn và chưa được chuyển thành insight dễ hành động cho đơn vị địa phương.', 'doanh nghiệp du lịch nhỏ, nhà quản lý điểm đến và sinh viên nghiên cứu thị trường.', ['thu thập dữ liệu mở có nguồn', 'phân tích xu hướng và phân khúc', 'thiết kế câu chuyện dữ liệu cho đối tác'], 'một dashboard du lịch, sổ tay chất lượng dữ liệu và bộ khuyến nghị được gắn với bằng chứng định lượng.'),
            self::definition('52000000-0000-4000-8000-000000000006', 'fpt', 'fpt-social-commerce-analytics', 'Social Commerce Analytics — Đo lường Hiệu quả Kinh doanh Số', 'career_business', 'a', 'intermediate', 26000000, [['data_analysis', 65], ['communication', 70], ['leadership', 70]], ['entrepreneurship', 'creative_design'], 'Nhóm bán hàng xã hội thường theo dõi lượt tương tác nhưng thiếu cách liên kết nội dung với chuyển đổi và lợi nhuận.', 'nhóm sinh viên khởi nghiệp, cửa hàng nhỏ và cố vấn kinh doanh số.', ['xác định phễu đo lường', 'chuẩn hóa dữ liệu chiến dịch', 'phân tích thử nghiệm nội dung và trình bày insight'], 'một dashboard hiệu quả thương mại, từ điển chỉ số và báo cáo đề xuất chiến dịch dựa trên dữ liệu.'),

            self::definition('50000000-0000-4000-8000-000000000002', 'btec', 'heritage-quest-3d', 'Game Giáo dục 3D: Hành trình Khám phá Di sản Lịch sử Việt Nam (HeritageQuest)', 'career_arts', 'creative_design', 'intermediate', 35000000, [['creative_design', 55], ['research', 50], ['teamwork', 55]], ['communication', 'problem_solving'], 'Nội dung lịch sử cần một hình thức tương tác hấp dẫn nhưng vẫn phải tôn trọng tính chính xác và bối cảnh văn hóa.', 'sinh viên, giảng viên và người học trẻ quan tâm đến di sản Việt Nam.', ['nghiên cứu nguồn lịch sử', 'thiết kế nhiệm vụ và môi trường 3D', 'kiểm thử trải nghiệm cùng độ chính xác nội dung'], 'một vertical slice game 3D, hồ sơ nguồn tham khảo và báo cáo kiểm thử trải nghiệm học tập.'),
            self::definition('53000000-0000-4000-8000-000000000001', 'btec', 'btec-campus-event-hub', 'Campus Event Hub — Nền tảng Quản lý Sự kiện Sinh viên', 'career_technical', 'logi', 'intermediate', 22000000, [['problem_solving', 70], ['research', 65], ['teamwork', 60]], ['communication', 'leadership'], 'Thông tin sự kiện và đăng ký tham gia đang phân tán khiến sinh viên khó theo dõi lịch, trạng thái và minh chứng tham dự.', 'sinh viên, câu lạc bộ và cán bộ phụ trách hoạt động tại BTEC FPT.', ['khảo sát quy trình hiện tại', 'thiết kế backend đăng ký và phân quyền', 'xây dựng giao diện cùng kiểm thử luồng chính'], 'một ứng dụng full-stack quản lý sự kiện, tài liệu API, bộ kiểm thử và báo cáo phản hồi người dùng.'),
            self::definition('53000000-0000-4000-8000-000000000002', 'btec', 'btec-commerce-studio', 'BTEC Commerce Studio — Thiết kế lại Trải nghiệm Mua sắm', 'career_business', 'creative_design', 'introductory', 18000000, [['creative_design', 55], ['communication', 55]], ['research', 'entrepreneurship'], 'Một cửa hàng trực tuyến mẫu có luồng tìm kiếm và thanh toán chưa rõ ràng, gây khó khăn cho người mua trên di động.', 'người mua trẻ, chủ cửa hàng nhỏ và sinh viên học thiết kế sản phẩm.', ['đánh giá hành trình hiện tại', 'phỏng vấn và xây dựng persona', 'thiết kế prototype rồi kiểm thử khả dụng'], 'một prototype thương mại responsive, design rationale và báo cáo kiểm thử với đề xuất ưu tiên cải tiến.'),
            self::definition('53000000-0000-4000-8000-000000000003', 'btec', 'btec-data-storytelling', 'Data Storytelling Dashboard — Kể chuyện bằng Dữ liệu', 'career_technical', 'data_analysis', 'intermediate', 19000000, [['data_analysis', 55], ['communication', 55], ['creative_design', 50]], ['research', 'problem_solving'], 'Bảng số liệu hoạt động sinh viên chưa truyền đạt được xu hướng, nguyên nhân và thông điệp chính cho người ra quyết định.', 'sinh viên phân tích dữ liệu, giảng viên và cán bộ quản lý chương trình BTEC.', ['làm sạch bộ dữ liệu mẫu', 'chọn biểu đồ và xây dựng narrative', 'kiểm thử khả năng đọc hiểu với người dùng'], 'một dashboard kể chuyện bằng dữ liệu, data dictionary và bản thuyết trình giải thích insight có nguồn.'),
            self::definition('53000000-0000-4000-8000-000000000004', 'btec', 'btec-cyberlab-starter', 'CyberLab Starter — Phòng Thực hành An toàn Ứng dụng Web', 'career_technical', 'logi', 'intermediate', 23000000, [['problem_solving', 65], ['research', 60]], ['python', 'communication'], 'Sinh viên cần môi trường an toàn để thực hành nhận diện lỗi ứng dụng web mà không tác động đến hệ thống thật.', 'sinh viên phát triển phần mềm và giảng viên hướng dẫn an toàn thông tin BTEC.', ['xây dựng ứng dụng lab cô lập', 'soạn bài thực hành theo mức độ', 'thiết kế cơ chế reset và ghi nhận kết quả'], 'một cyberlab cục bộ, bốn kịch bản thực hành có hướng dẫn và báo cáo biện pháp phòng vệ tương ứng.'),
            self::definition('53000000-0000-4000-8000-000000000005', 'btec', 'btec-habitflow-mobile', 'HabitFlow Mobile — Ứng dụng Theo dõi Thói quen Học tập', 'career_technical', 'problem_solving', 'introductory', 17000000, [['problem_solving', 55], ['creative_design', 50], ['teamwork', 55]], ['data_analysis', 'communication'], 'Sinh viên khó duy trì thói quen học đều khi mục tiêu, nhắc việc và phản hồi tiến độ nằm ở nhiều công cụ khác nhau.', 'sinh viên BTEC muốn xây dựng nhịp học tập bền vững và cố vấn học tập.', ['nghiên cứu hành vi và quyền riêng tư', 'thiết kế luồng mobile', 'xây dựng theo dõi tiến độ và kiểm thử thông báo'], 'một ứng dụng mobile mẫu, prototype tương tác, bộ dữ liệu thử nghiệm và báo cáo đánh giá trải nghiệm.'),
            self::definition('53000000-0000-4000-8000-000000000006', 'btec', 'btec-brand-launch-360', 'Brand Launch 360 — Chiến dịch Ra mắt Thương hiệu Số', 'career_business', 'creative_design', 'introductory', 16000000, [['creative_design', 55], ['communication', 60], ['teamwork', 55]], ['entrepreneurship', 'leadership'], 'Một thương hiệu sinh viên mới chưa có định vị, hệ thống nội dung và chỉ số để đánh giá hoạt động ra mắt trên kênh số.', 'nhóm sinh viên kinh doanh, khách hàng mục tiêu và cố vấn truyền thông BTEC.', ['nghiên cứu phân khúc', 'xây dựng nhận diện và thông điệp', 'lập lịch nội dung cùng bộ chỉ số chiến dịch'], 'một bộ nhận diện số, kế hoạch ra mắt đa kênh và báo cáo đo lường thử nghiệm có bài học rút ra.'),
            self::definition('53000000-0000-4000-8000-000000000007', 'btec', 'btec-cloudship', 'CloudShip — Tự động hóa Triển khai Ứng dụng Sinh viên', 'career_technical', 'logi', 'intermediate', 25000000, [['problem_solving', 70], ['research', 65], ['teamwork', 60]], ['python', 'leadership'], 'Các dự án sinh viên thường triển khai thủ công nên khó lặp lại, dễ sai cấu hình và thiếu bằng chứng chất lượng trước khi phát hành.', 'nhóm phát triển phần mềm, giảng viên chấm dự án và người vận hành môi trường lab BTEC.', ['khảo sát quy trình triển khai', 'xây dựng pipeline kiểm thử và đóng gói', 'thiết kế môi trường staging cùng giám sát cơ bản'], 'một pipeline CI/CD chạy được, cấu hình hạ tầng mẫu, runbook phục hồi và báo cáo kiểm chứng từng bước.'),
        ];
    }

    /** @return array<string,mixed> */
    private static function definition(
        string $id,
        string $schoolKey,
        string $slug,
        string $title,
        string $category,
        string $aiCategory,
        string $difficulty,
        int $fundingGoal,
        array $required,
        array $outcomes,
        string $problem,
        string $users,
        array $workPackages,
        string $product,
    ): array {
        return compact(
            'id',
            'schoolKey',
            'slug',
            'title',
            'category',
            'aiCategory',
            'difficulty',
            'fundingGoal',
            'required',
            'outcomes',
            'problem',
            'users',
            'workPackages',
            'product',
        ) + [
            'school_key' => $schoolKey,
            'ai_category' => $aiCategory,
            'funding_goal' => $fundingGoal,
            'work_packages' => $workPackages,
        ];
    }
}
