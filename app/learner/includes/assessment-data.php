<?php
/** Learner assessment mock provider with database-ready identifiers. */

require_once dirname(__DIR__) . '/data/bootstrap.php';

if (!function_exists('learner_assessment_likert_options')) {
    function learner_assessment_likert_options(): array
    {
        return [
            ['value' => 1, 'label' => 'Hoàn toàn không giống tôi'],
            ['value' => 2, 'label' => 'Không giống tôi'],
            ['value' => 3, 'label' => 'Phân vân'],
            ['value' => 4, 'label' => 'Khá giống tôi'],
            ['value' => 5, 'label' => 'Rất giống tôi'],
        ];
    }
}

if (!function_exists('learner_assessment_mock_catalog')) {
    function learner_assessment_mock_catalog(): array
    {
        return [[
            'id' => 'holland',
            'code' => 'holland-riasec',
            'version' => '1.0',
            'name' => 'Holland — Khám phá nhóm sở thích nghề nghiệp',
            'short_name' => 'Holland',
            'description' => 'Khám phá sáu nhóm sở thích RIASEC để hiểu môi trường học tập và trải nghiệm phù hợp với bạn.',
            'source' => 'learner_mock',
            'source_role' => 'school_expert',
            'status' => 'published',
            'question_count' => 24,
            'duration_minutes' => 12,
            'retake_days' => 30,
            'disclaimer' => 'Kết quả chỉ mang tính định hướng và không thay thế tư vấn từ giáo viên, chuyên gia hoặc cố vấn nghề nghiệp.',
        ]];
    }
}

if (!function_exists('learner_assessment_definition')) {
    function learner_assessment_definition(string $assessmentId): ?array
    {
        return \TalentHub\Learner\Data\ReadModel\AssessmentReadModel::resolve(
            learner_assessment_repository(),
            $assessmentId
        );
    }
}

if (!function_exists('learner_assessment_mock_questions')) {
    function learner_assessment_mock_questions(string $assessmentId): array
    {
        if ($assessmentId !== 'holland') {
            return [];
        }

        $statements = [
            'R' => [
                'Tôi thích lắp ráp, sửa chữa hoặc tìm hiểu cách hoạt động của máy móc.',
                'Tôi hứng thú với hoạt động ngoài trời và những công việc cần thao tác thực tế.',
                'Tôi thích tạo ra sản phẩm hữu hình bằng dụng cụ, thiết bị hoặc vật liệu.',
                'Tôi tự tin khi phải xử lý một vấn đề kỹ thuật cụ thể.',
            ],
            'I' => [
                'Tôi thích đặt câu hỏi và tìm nguyên nhân đằng sau một hiện tượng.',
                'Tôi hứng thú với thí nghiệm, phân tích dữ liệu hoặc giải bài toán khó.',
                'Tôi thường đọc thêm để hiểu sâu một chủ đề mình quan tâm.',
                'Tôi thích làm việc với ý tưởng và bằng chứng trước khi đưa ra kết luận.',
            ],
            'A' => [
                'Tôi thích sáng tạo hình ảnh, câu chuyện, âm nhạc hoặc thiết kế mới.',
                'Tôi thường tìm nhiều cách khác nhau để thể hiện một ý tưởng.',
                'Tôi thoải mái trong môi trường cho phép thử nghiệm và thể hiện cá tính.',
                'Tôi chú ý đến màu sắc, bố cục, cảm xúc hoặc trải nghiệm của người dùng.',
            ],
            'S' => [
                'Tôi cảm thấy có ý nghĩa khi hướng dẫn hoặc hỗ trợ người khác tiến bộ.',
                'Tôi thích lắng nghe và giúp bạn bè giải quyết khó khăn.',
                'Tôi dễ phối hợp trong nhóm và quan tâm đến cảm xúc của mọi người.',
                'Tôi hứng thú với hoạt động giáo dục, cộng đồng hoặc chăm sóc.',
            ],
            'E' => [
                'Tôi thích thuyết phục người khác ủng hộ một ý tưởng hoặc kế hoạch.',
                'Tôi sẵn sàng dẫn dắt nhóm và chịu trách nhiệm cho kết quả chung.',
                'Tôi hứng thú với kinh doanh, tổ chức sự kiện hoặc xây dựng dự án.',
                'Tôi tự tin trình bày ý tưởng trước nhiều người.',
            ],
            'C' => [
                'Tôi thích sắp xếp thông tin, lịch trình hoặc tài liệu một cách rõ ràng.',
                'Tôi cảm thấy thoải mái khi làm việc theo quy trình và tiêu chuẩn cụ thể.',
                'Tôi thường kiểm tra chi tiết để hạn chế sai sót.',
                'Tôi thích quản lý danh sách, số liệu hoặc kế hoạch có cấu trúc.',
            ],
        ];

        $dimensionNames = [
            'R' => 'Kỹ thuật — Thực tế',
            'I' => 'Nghiên cứu — Phân tích',
            'A' => 'Nghệ thuật — Sáng tạo',
            'S' => 'Xã hội — Hỗ trợ',
            'E' => 'Quản lý — Thuyết phục',
            'C' => 'Nghiệp vụ — Tổ chức',
        ];
        $options = learner_assessment_likert_options();
        $questions = [];
        $position = 1;
        foreach ($statements as $dimension => $items) {
            foreach ($items as $index => $prompt) {
                $questions[] = [
                    'id' => sprintf('holland-%s-%02d', strtolower($dimension), $index + 1),
                    'assessment_id' => 'holland',
                    'assessment_version' => '1.0',
                    'position' => $position++,
                    'dimension' => $dimension,
                    'dimension_name' => $dimensionNames[$dimension],
                    'question_type' => 'likert_single',
                    'prompt' => $prompt,
                    'required' => true,
                    'options' => $options,
                ];
            }
        }
        return $questions;
    }
}

if (!function_exists('learner_assessment_mock_history')) {
    function learner_assessment_mock_history(string $studentId, string $assessmentId): array
    {
        if ($studentId !== 'student-demo-001' || $assessmentId !== 'holland') {
            return [];
        }
        return [
            [
                'id' => 'attempt-holland-demo-20260615',
                'student_id' => $studentId,
                'assessment_id' => 'holland',
                'assessment_version' => '1.0',
                'status' => 'submitted',
                'started_at' => '2026-06-15T08:20:00+07:00',
                'updated_at' => '2026-06-15T08:29:00+07:00',
                'expires_at' => '2026-06-15T08:32:00+07:00',
                'submitted_at' => '2026-06-15T08:29:00+07:00',
                'answers' => [],
                'result' => [
                    'code' => 'IRA',
                    'scores' => ['R' => 69, 'I' => 88, 'A' => 75, 'S' => 56, 'E' => 44, 'C' => 63],
                    'primary_dimension' => 'I',
                ],
            ],
            [
                'id' => 'attempt-holland-demo-20260110',
                'student_id' => $studentId,
                'assessment_id' => 'holland',
                'assessment_version' => '1.0',
                'status' => 'submitted',
                'started_at' => '2026-01-10T14:10:00+07:00',
                'updated_at' => '2026-01-10T14:21:00+07:00',
                'expires_at' => '2026-01-10T14:22:00+07:00',
                'submitted_at' => '2026-01-10T14:21:00+07:00',
                'answers' => [],
                'result' => [
                    'code' => 'RIA',
                    'scores' => ['R' => 81, 'I' => 75, 'A' => 69, 'S' => 50, 'E' => 38, 'C' => 56],
                    'primary_dimension' => 'R',
                ],
            ],
        ];
    }
}

if (!function_exists('learner_assessment_repository')) {
    function learner_assessment_repository(): \TalentHub\Learner\Data\Contracts\AssessmentRepository
    {
        $definitions = learner_assessment_mock_catalog();
        $questions = [];
        $attempts = [];
        foreach ($definitions as $definition) {
            $assessmentId = (string) $definition['id'];
            array_push($questions, ...learner_assessment_mock_questions($assessmentId));
            array_push($attempts, ...learner_assessment_mock_history('student-demo-001', $assessmentId));
        }

        return learner_repository_factory()->assessment($definitions, $questions, $attempts);
    }
}

if (!function_exists('learner_assessment_write_service')) {
    function learner_assessment_write_service(): \TalentHub\Learner\Data\Service\LearnerAssessmentService
    {
        return new \TalentHub\Learner\Data\Service\LearnerAssessmentService(
            learner_assessment_repository(),
            learner_repository_factory()->assessmentWrite()
        );
    }
}

if (!function_exists('learner_assessment_catalog')) {
    function learner_assessment_catalog(): array
    {
        return \TalentHub\Learner\Data\ReadModel\AssessmentReadModel::definitions(
            learner_assessment_repository()->all()
        );
    }
}

if (!function_exists('learner_assessment_questions')) {
    function learner_assessment_questions(string $assessmentId): array
    {
        $assessment = learner_assessment_definition($assessmentId);
        if ($assessment === null) {
            return [];
        }

        return \TalentHub\Learner\Data\ReadModel\AssessmentReadModel::questions(
            learner_assessment_repository()->questionsFor((string) $assessment['id'])
        );
    }
}

if (!function_exists('learner_assessment_history')) {
    function learner_assessment_history(string $studentId, string $assessmentId): array
    {
        $assessment = learner_assessment_definition($assessmentId);
        if ($assessment === null) {
            return [];
        }

        return \TalentHub\Learner\Data\ReadModel\AssessmentReadModel::completedAttempts(
            learner_assessment_repository()->attemptsFor($studentId, (string) $assessment['id'])
        );
    }
}

if (!function_exists('learner_assessment_dimension_content')) {
    function learner_assessment_dimension_content(): array
    {
        return [
            'R' => ['name' => 'Kỹ thuật — Thực tế', 'summary' => 'Ưa hành động, công cụ, máy móc và kết quả cụ thể.', 'suggestions' => ['Robot và tự động hóa', 'Kỹ thuật điện — điện tử', 'Thiết kế và chế tạo']],
            'I' => ['name' => 'Nghiên cứu — Phân tích', 'summary' => 'Thích khám phá, phân tích và giải quyết vấn đề dựa trên bằng chứng.', 'suggestions' => ['Khoa học dữ liệu', 'Nghiên cứu công nghệ', 'Y sinh và khoa học tự nhiên']],
            'A' => ['name' => 'Nghệ thuật — Sáng tạo', 'summary' => 'Đề cao trí tưởng tượng, biểu đạt và những cách tiếp cận mới.', 'suggestions' => ['Thiết kế sản phẩm', 'Truyền thông sáng tạo', 'Nghệ thuật số']],
            'S' => ['name' => 'Xã hội — Hỗ trợ', 'summary' => 'Quan tâm đến con người, giáo dục, cộng đồng và sự phát triển của người khác.', 'suggestions' => ['Giáo dục', 'Tâm lý — xã hội', 'Hoạt động cộng đồng']],
            'E' => ['name' => 'Quản lý — Thuyết phục', 'summary' => 'Thích dẫn dắt, kết nối nguồn lực và biến ý tưởng thành hành động.', 'suggestions' => ['Kinh doanh', 'Quản lý dự án', 'Khởi nghiệp']],
            'C' => ['name' => 'Nghiệp vụ — Tổ chức', 'summary' => 'Ưa cấu trúc, độ chính xác và quy trình rõ ràng.', 'suggestions' => ['Tài chính — kế toán', 'Vận hành', 'Phân tích nghiệp vụ']],
        ];
    }
}
