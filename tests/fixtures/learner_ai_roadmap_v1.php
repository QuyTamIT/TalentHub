<?php

declare(strict_types=1);

/** @return array<string,mixed> */
function learner_ai_roadmap_provider_fixture(): array
{
    $phase = static function (
        int $position,
        int $startDay,
        int $endDay,
        string $code,
        string $title,
        string $goal,
        string $skillFocus,
        string $deliverable,
        string $effortLabel,
        string $metricLabel,
        string $evidenceId,
        array $taskTitles,
    ): array {
        $tasks = [];
        foreach (array_values($taskTitles) as $index => $taskTitle) {
            $tasks[] = [
                'position' => $index + 1,
                'title' => $taskTitle,
                'description' => 'Hoàn thành nhiệm vụ và ghi lại kết quả để nhận phản hồi.',
                'estimated_minutes' => 45 + ($index * 15),
                'action' => ['type' => 'self_task'],
                'evidence_ref_ids' => [$evidenceId],
            ];
        }

        return [
            'position' => $position,
            'start_day' => $startDay,
            'end_day' => $endDay,
            'code' => $code,
            'title' => $title,
            'goal' => $goal,
            'skill_focus' => $skillFocus,
            'deliverable' => $deliverable,
            'effort_label' => $effortLabel,
            'metric_label' => $metricLabel,
            'evidence_ref_ids' => [$evidenceId],
            'tasks' => $tasks,
        ];
    };

    return [
        'executive_summary' => 'Bạn có tiềm năng phát triển theo hướng xây dựng sản phẩm công nghệ và giải quyết vấn đề thực tế.',
        'primary_direction' => [
            'code' => 'technology_product',
            'label' => 'Công nghệ sản phẩm',
            'rationale' => 'Hướng này cho phép bạn kiểm chứng năng lực bằng sản phẩm và phản hồi thực tế.',
        ],
        'alternative_directions' => [
            [
                'code' => 'automation',
                'label' => 'Tự động hóa',
                'rationale' => 'Phù hợp để thử nghiệm qua dự án kỹ thuật có đầu ra rõ ràng.',
            ],
            [
                'code' => 'data_analysis',
                'label' => 'Phân tích dữ liệu',
                'rationale' => 'Phù hợp để phát triển tư duy phân tích và kiểm chứng giả thuyết.',
            ],
        ],
        'insights' => [
            [
                'category' => 'strength',
                'title' => 'Lợi thế nên phát huy',
                'summary' => 'Bạn có thể chuyển ý tưởng thành thử nghiệm nhỏ và quan sát kết quả.',
                'evidence_ref_ids' => ['evidence-001'],
            ],
            [
                'category' => 'improvement',
                'title' => 'Điểm nghẽn cần cải thiện',
                'summary' => 'Bạn cần luyện cách trình bày quyết định và tiếp nhận phản hồi có cấu trúc.',
                'evidence_ref_ids' => ['evidence-002'],
            ],
            [
                'category' => 'potential',
                'title' => 'Tiềm năng cần kiểm chứng',
                'summary' => 'Một dự án nhóm ngắn sẽ giúp kiểm chứng khả năng phối hợp và dẫn dắt sản phẩm.',
                'evidence_ref_ids' => ['evidence-003'],
            ],
        ],
        'talent_map' => [
            ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.82, 'evidence_ref_ids' => ['evidence-001']],
            ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.74, 'evidence_ref_ids' => ['evidence-002']],
            ['field' => 'Tổ chức & Điều phối', 'score' => 0.68, 'evidence_ref_ids' => ['evidence-003']],
        ],
        'phases' => [
            $phase(1, 0, 30, 'discover', 'Khám phá', 'Hoàn thành mini project', 'Tư duy sản phẩm', 'Bản demo đầu tiên', '3 giờ/tuần', 'Một bản demo nhận ít nhất hai phản hồi', 'evidence-001', [
                'Chọn một vấn đề thực tế',
                'Phác thảo giải pháp ban đầu',
                'Nhận phản hồi từ hai người dùng',
            ]),
            $phase(2, 31, 60, 'practice', 'Thực hành', 'Tham gia dự án nhóm', 'Giao tiếp và cộng tác', 'Prototype có phản hồi', '2 buổi/tuần', 'Hoàn thành một vòng thử nghiệm nhóm', 'evidence-002', [
                'Thống nhất vai trò trong nhóm',
                'Xây dựng prototype cùng thành viên',
                'Tổng hợp phản hồi và cải tiến',
            ]),
            $phase(3, 61, 90, 'breakthrough', 'Bứt phá', 'Trình bày sản phẩm', 'Thuyết trình', 'Portfolio hoàn chỉnh', '2 giờ/tuần', 'Hoàn thành một bài trình bày có đánh giá', 'evidence-003', [
                'Hoàn thiện sản phẩm đầu ra',
                'Chuẩn bị câu chuyện trình bày',
                'Trình bày và ghi nhận đánh giá',
            ]),
        ],
        'recommended_activity_source_ids' => [],
    ];
}
/** @return array<string,string> */
function learner_ai_roadmap_model_metadata(): array
{
    return [
        'origin' => 'model',
        'provider' => '9router_gemini',
        'model_version' => 'ag/gemini-3.7-flash-high',
        'prompt_version' => 'learner-roadmap-prompt-1.4.0',
        'confidence_band' => 'high',
    ];
}
