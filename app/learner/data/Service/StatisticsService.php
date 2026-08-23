<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;
use TalentHub\Learner\Data\Domain\LevelProgression;

final class StatisticsService
{
    public const ALLOWED_PERIODS = ['week', 'month'];

    public function __construct(
        private readonly StatisticsRepository $repository,
        private readonly ?DateTimeImmutable $clock = null
    ) {}

    /**
     * @return array{
     *     period: array{id: string, label: string, from: string, to: string},
     *     kpis: list<array{id: string, label: string, value: float|int, suffix: string, tone: string, icon: string}>,
     *     experience: array{hours: list<float>, labels: list<string>, dates: list<string>},
     *     fields: list<array{category: string, hours: float, percentage: int}>,
     *     facts: array{confirmed_experience_hours: float, attended_activity_count: int, submitted_assessment_type_count: int, published_teacher_evaluation_count: int},
     *     level: array<string,mixed>
     * }
     * @throws ApiException
     */
    public function forStudentPeriod(string $studentId, string $period = 'month'): array
    {
        $period = strtolower(trim($period));
        if (!in_array($period, self::ALLOWED_PERIODS, true)) {
            throw new ApiException(422, 'INVALID_PERIOD', "Khoảng thời gian thống kê không hợp lệ. Chỉ chấp nhận: " . implode(', ', self::ALLOWED_PERIODS));
        }

        $now = ($this->clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));

        if ($period === 'week') {
            $from = $now->modify('monday this week 00:00:00');
            $to = $from->modify('+7 days 00:00:00');
            $label = 'Tuần này';
        } else {
            $from = $now->modify('first day of this month 00:00:00');
            $to = $from->modify('+1 month 00:00:00');
            $label = 'Tháng này';
        }

        $periodStats = $this->repository->periodStatistics($studentId, $from, $to);
        $lifetimeFacts = $this->repository->lifetimeFacts($studentId);
        $level = LevelProgression::fromHours((float) $lifetimeFacts['confirmed_experience_hours']);

        $kpis = [
            [
                'id' => 'hours',
                'label' => 'Giờ trải nghiệm',
                'value' => $periodStats['hours'],
                'suffix' => 'giờ',
                'tone' => 'teal',
                'icon' => 'clock',
            ],
            [
                'id' => 'activities',
                'label' => 'Hoạt động tham gia',
                'value' => $periodStats['activities'],
                'suffix' => 'hoạt động',
                'tone' => 'orange',
                'icon' => 'activity',
            ],
            [
                'id' => 'assessments',
                'label' => 'Đánh giá hoàn thành',
                'value' => $periodStats['assessments'],
                'suffix' => 'bài',
                'tone' => 'purple',
                'icon' => 'award',
            ],
            [
                'id' => 'badges',
                'label' => 'Huy hiệu đạt được',
                'value' => $periodStats['badges'],
                'suffix' => 'huy hiệu',
                'tone' => 'blue',
                'icon' => 'star',
            ],
        ];

        $chartHours = [];
        $chartLabels = [];
        $chartDates = [];
        foreach ($periodStats['experience_buckets'] as $bucket) {
            $chartHours[] = $bucket['hours'];
            $chartLabels[] = $bucket['label'];
            $chartDates[] = $bucket['date'];
        }

        $totalFieldHours = array_sum(array_column($periodStats['category_distribution'], 'hours'));
        $fields = [];
        foreach ($periodStats['category_distribution'] as $cat) {
            $catHours = (float) $cat['hours'];
            $pct = $totalFieldHours > 0 ? (int) round(($catHours / $totalFieldHours) * 100) : 0;
            $fields[] = [
                'category' => $cat['category'],
                'hours' => $catHours,
                'percentage' => $pct,
            ];
        }

        return [
            'period' => [
                'id' => $period,
                'label' => $label,
                'from' => $from->format('Y-m-d\TH:i:s\Z'),
                'to' => $to->format('Y-m-d\TH:i:s\Z'),
            ],
            'kpis' => $kpis,
            'experience' => [
                'hours' => $chartHours,
                'labels' => $chartLabels,
                'dates' => $chartDates,
            ],
            'fields' => $fields,
            'facts' => $lifetimeFacts,
            'level' => $level,
        ];
    }
}
