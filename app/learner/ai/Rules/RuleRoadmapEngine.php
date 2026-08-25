<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rules;

use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Domain\RoadmapDirection;
use TalentHub\Learner\Ai\Domain\RoadmapInsight;
use TalentHub\Learner\Ai\Domain\RoadmapPhase;
use TalentHub\Learner\Ai\Domain\RoadmapTask;

final class RuleRoadmapEngine implements RoadmapEngine
{
    public const VERSION = 'learner-roadmap-rules-1.0.0';

    public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis
    {
        unset($context);
        $references = [];
        foreach (array_keys($input->evidenceReferences()) as $index) $references[] = sprintf('evidence-%03d', $index + 1);
        if ($references === []) throw new \RuntimeException('Rule roadmap requires assessment evidence.');
        $reference = static fn (int $position): string => $references[min($position, count($references) - 1)];

        return new RoadmapAnalysis(
            'rule_fallback',
            'Dựa trên dữ liệu đã hoàn thành, bạn có thể bắt đầu bằng các thử nghiệm nhỏ để kiểm chứng hướng phát triển phù hợp.',
            new RoadmapDirection('structured_exploration', 'Khám phá có cấu trúc', 'Hướng này giúp bạn kiểm chứng sở thích và cách làm việc qua sản phẩm cụ thể.'),
            [
                new RoadmapDirection('project_practice', 'Thực hành qua dự án', 'Các nhiệm vụ ngắn giúp bạn quan sát tiến bộ và điều chỉnh cách học.'),
                new RoadmapDirection('communication_growth', 'Phát triển giao tiếp', 'Phản hồi có cấu trúc giúp bạn diễn đạt quyết định và phối hợp tốt hơn.'),
            ],
            [
                new RoadmapInsight('strength', 'Nền tảng nên phát huy', 'Bạn đã có dữ liệu ban đầu để chuyển sang giai đoạn thử nghiệm thực tế.', [$reference(0)]),
                new RoadmapInsight('improvement', 'Điểm cần rèn luyện', 'Bạn nên ghi lại quyết định và phản hồi để nhận biết điểm cần cải thiện.', [$reference(1)]),
                new RoadmapInsight('potential', 'Tiềm năng cần kiểm chứng', 'Một dự án ngắn sẽ giúp bạn kiểm chứng khả năng học hỏi và cộng tác.', [$reference(2)]),
            ],
            [
                $this->phase(1, 0, 30, 'discover', 'Khám phá', 'Hoàn thành một thử nghiệm nhỏ', 'Quan sát và đặt câu hỏi', 'Nhật ký thử nghiệm', '3 giờ/tuần', 'Hoàn thành ba nhiệm vụ và ghi lại phản hồi', $reference(0), [
                    ['Chọn một vấn đề gần gũi', 'Mô tả người dùng, nhu cầu và kết quả mong đợi.'],
                    ['Tạo phương án thử nghiệm', 'Phác thảo một giải pháp nhỏ có thể hoàn thành trong tuần.'],
                    ['Thu thập phản hồi', 'Xin phản hồi từ ít nhất hai người và ghi lại điều học được.'],
                ]),
                $this->phase(2, 31, 60, 'practice', 'Thực hành', 'Cải tiến sản phẩm thử nghiệm', 'Hợp tác và phản hồi', 'Phiên bản cải tiến', '3 giờ/tuần', 'Hoàn thành một vòng cải tiến có bằng chứng', $reference(1), [
                    ['Chọn phản hồi ưu tiên', 'Phân loại phản hồi và chọn một thay đổi quan trọng.'],
                    ['Thực hiện vòng cải tiến', 'Cập nhật sản phẩm và ghi lại lý do cho từng quyết định.'],
                    ['Đánh giá kết quả', 'So sánh trước và sau để rút ra một bài học cụ thể.'],
                ]),
                $this->phase(3, 61, 90, 'breakthrough', 'Bứt phá', 'Trình bày kết quả học tập', 'Trình bày và tự đánh giá', 'Hồ sơ sản phẩm ngắn', '2 giờ/tuần', 'Hoàn thành một bài trình bày nhận được đánh giá', $reference(2), [
                    ['Hoàn thiện đầu ra', 'Tổng hợp phiên bản tốt nhất cùng bằng chứng tiến bộ.'],
                    ['Chuẩn bị câu chuyện', 'Trình bày vấn đề, cách thử nghiệm, kết quả và bài học.'],
                    ['Nhận đánh giá cuối kỳ', 'Xin nhận xét có cấu trúc và chọn mục tiêu tiếp theo.'],
                ]),
            ],
            count($references) >= 4 ? 'high' : 'medium',
            [],
            ['rule_version' => self::VERSION, 'fallback_reason' => 'rule_only'],
        );
    }

    /** @param list<array{0:string,1:string}> $taskCopy */
    private function phase(int $position, int $start, int $end, string $code, string $title, string $goal, string $skill, string $deliverable, string $effort, string $metric, string $evidence, array $taskCopy): RoadmapPhase
    {
        $tasks = [];
        foreach ($taskCopy as $index => [$taskTitle, $description]) {
            $tasks[] = new RoadmapTask($index + 1, $taskTitle, $description, 45, ['type' => 'self_task'], [$evidence]);
        }
        return new RoadmapPhase($position, $start, $end, $code, $title, $goal, $skill, $deliverable, $effort, $metric, [$evidence], $tasks);
    }
}
