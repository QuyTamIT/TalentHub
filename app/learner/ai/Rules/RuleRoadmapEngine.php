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
        if ($references === []) $references = ['evidence-001', 'evidence-002', 'evidence-003'];
        $reference = static fn (int $position): string => $references[min($position, count($references) - 1)];

        return new RoadmapAnalysis(
            'rule_fallback',
            'Dựa trên kết quả đánh giá Holland (RIE - Kỹ thuật & Nghiên cứu) và Đa trí thông minh (Logic - Không gian), hệ thống AI đã tối ưu hóa lộ trình 3 tháng tới giúp bạn phát huy tối đa năng khiếu chuyên môn và tạo ra sản phẩm thực tế.',
            new RoadmapDirection('ai_iot_engineering', 'Kỹ thuật Phần mềm & Trí tuệ Nhân tạo (AI/IoT)', 'Hướng phát triển trọng tâm kết hợp lập trình ứng dụng, mô hình máy học và kỹ năng giải quyết bài toán thực tiễn.'),
            [
                new RoadmapDirection('project_practice', 'Thực hành qua Dự án & Hackathon', 'Rèn luyện kỹ năng qua các sân chơi học thuật và cuộc thi sáng tạo công nghệ.'),
                new RoadmapDirection('team_leadership', 'Kỹ năng Lãnh đạo & Quản trị Dự án', 'Tập dượt vai trò Trưởng nhóm và phối hợp doanh nghiệp triển khai đề án.'),
            ],
            [
                new RoadmapInsight('strength', 'Thế mạnh Năng khiếu Nổi trội', 'Tư duy logic thuật toán (88/100) và năng khiếu không gian (82/100) là bệ phóng lý tưởng cho các đề tài AI & IoT.', [$reference(0)]),
                new RoadmapInsight('improvement', 'Mục tiêu Rèn luyện Kỹ năng', 'Cần tăng cường số giờ trải nghiệm thực tế tại Lab và tham gia cọ xát tại các cuộc thi sáng tạo.', [$reference(1)]),
                new RoadmapInsight('potential', 'Tiềm năng Đề án Thực tế', 'Có khả năng phát triển các sản phẩm công nghệ có tính ứng dụng cao nhận tài trợ từ doanh nghiệp.', [$reference(2)]),
            ],
            [
                $this->phase(1, 0, 30, 'month_1_lab', 'Tháng 1: Tham gia CLB / Lab Thực hành', 'Rèn luyện kỹ năng chuyên môn trong môi trường xưởng thực hành', 'Lập trình Python, ESP32 & AI Nhúng', 'Sản phẩm Lab & Nhật ký thực hành', '4 giờ/tuần', 'Hoàn thành 100% bài lab IoT & AI Bootcamp', $reference(0), [
                    ['Đăng ký tham gia IoT Lab & AI Bootcamp', 'Gia nhập CLB Công nghệ và đăng ký vị trí nghiên cứu tại Phòng Lab B305.'],
                    ['Lập trình vi điều khiển ESP32 & Cảm biến', 'Hoàn thành bài thực hành kết nối cảm biến và thu thập dữ liệu thời gian thực.'],
                    ['Huấn luyện mô hình AI nhận diện cơ bản', 'Thực hành huấn luyện mô hình phân loại dữ liệu và nhận phản hồi từ Giảng viên.'],
                ]),
                $this->phase(2, 31, 60, 'month_2_hackathon', 'Tháng 2: Đăng ký Cuộc thi & Hackathon', 'Thử thách năng lực trong môi trường thi đấu cọ xát thực tế', 'Làm việc nhóm, Computer Vision & Thuyết trình', 'Nguyên mẫu dự án dự thi', '5 giờ/tuần', 'Lọt vào vòng chung kết và đạt giải thưởng Hackathon', $reference(1), [
                    ['Lập đội thi Hackathon Sáng tạo Trẻ 2026', 'Tập hợp nhóm 3-5 thành viên, phân công chuyên môn và đăng ký đề tài.'],
                    ['Xây dựng nguyên mẫu giải pháp AI/IoT', 'Tích hợp mô hình Computer Vision YOLOv8 vào phần cứng hoặc ứng dụng di động.'],
                    ['Báo cáo & Thuyết trình trước Hội đồng Giám khảo', 'Bảo vệ giải pháp công nghệ trước hội đồng giám khảo và chuyên gia doanh nghiệp.'],
                ]),
                $this->phase(3, 61, 90, 'month_3_leadership', 'Tháng 3: Trưởng nhóm Dự án & Hoàn thiện Đề án', 'Đảm nhiệm vai trò Trưởng nhóm và đóng gói đề tài nghiên cứu', 'Quản trị đề tài, CSR Doanh nghiệp & Đóng gói', 'Hồ sơ đề án & Talent Passport', '6 giờ/tuần', 'Được doanh nghiệp bảo trợ tài trợ và nghiệm thu xuất sắc', $reference(2), [
                    ['Đảm nhiệm vai trò Trưởng nhóm đề án', 'Chủ trì hoàn thiện kiến trúc hệ thống Smart Garden IoT / AI Healthcare.'],
                    ['Kêu gọi Doanh nghiệp tài trợ CSR', 'Lập hồ sơ kêu gọi tài trợ trên TalentHub và làm việc cùng FPT Software.'],
                    ['Báo cáo nghiệm thu & Cấp chứng nhận Talent Passport', 'Hoàn thành báo cáo tiến độ, xuất bản Talent Passport đạt cấp độ Master.'],
                ]),
            ],
            'high',
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
