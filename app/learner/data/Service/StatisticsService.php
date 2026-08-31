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
    public const ALLOWED_PERIODS = ['week', 'month', 'semester', 'year', 'all'];

    private const EPOCH_START = '2020-01-01 00:00:00';

    private const FIELD_LABELS = [
        'technology' => 'Công nghệ & Kỹ thuật',
        'career' => 'Hướng nghiệp & Thực tập',
        'academic' => 'Học thuật & Nghiên cứu',
        'personal' => 'Kỹ năng mềm',
        'sports' => 'Thể thao & Sức khỏe',
        'arts' => 'Nghệ thuật',
        'general' => 'Khác',
    ];

    private const FIELD_TONES = [
        'technology' => 'primary',
        'career' => 'secondary',
        'academic' => 'accent',
        'personal' => 'warning',
        'sports' => 'teal',
        'arts' => 'purple',
        'general' => 'neutral',
    ];

    private const SKILL_CATEGORY_TONES = [
        'technical' => 'primary',
        'soft' => 'success',
        'creative' => 'warning',
        'academic' => 'accent',
        'business' => 'secondary',
        'sports' => 'teal',
    ];

    private const HOLLAND_TRAITS = [
        'R' => 'Thực tế',
        'I' => 'Nghiên cứu',
        'A' => 'Nghệ thuật',
        'S' => 'Xã hội',
        'E' => 'Quản trị',
        'C' => 'Quy củ',
    ];

    private const DISC_STYLES = [
        'D' => 'Thống lĩnh (Dominance)',
        'I' => 'Ảnh hưởng (Influence)',
        'S' => 'Kiên định (Steadiness)',
        'C' => 'Tuân thủ (Conscientiousness)',
    ];

    private const GARDNER_DOMAINS = [
        'LOGI' => 'Logic & Toán học',
        'LING' => 'Ngôn ngữ',
        'SPAT' => 'Không gian & Thị giác',
        'BODY' => 'Vận động cơ thể',
        'MUSIC' => 'Âm nhạc',
        'INTER' => 'Giao tiếp - Xã hội',
        'INTRA' => 'Tự nhận thức',
        'NAT' => 'Tự nhiên',
    ];

    private const MBTI_NAMES = [
        'INTJ' => 'Nhà chiến lược', 'INTP' => 'Nhà tư duy logic', 'ENTJ' => 'Nhà chỉ huy', 'ENTP' => 'Người nhìn xa',
        'INFJ' => 'Người bảo vệ ý tưởng', 'INFP' => 'Nhà lý tưởng hóa', 'ENFJ' => 'Người truyền cảm hứng', 'ENFP' => 'Nhà sáng tạo tự do',
        'ISTJ' => 'Người đáng tin cậy', 'ISFJ' => 'Người bảo hộ', 'ESTJ' => 'Người điều hành', 'ESFJ' => 'Người quan tâm',
        'ISTP' => 'Người thợ tài hoa', 'ISFP' => 'Nghệ sĩ thầm lặng', 'ESTP' => 'Người hành động', 'ESFP' => 'Người biểu diễn',
    ];

    public function __construct(
        private readonly StatisticsRepository $repository,
        private readonly ?DateTimeImmutable $clock = null
    ) {}

    /**
     * @return array{
     *     period: array{id: string, label: string, from: string, to: string},
     *     kpis: list<array{id: string, label: string, value: float|int|string, suffix: string, tone: string, icon: string}>,
     *     experience: array{hours: list<float>, labels: list<string>, dates: list<string>},
     *     skills: list<array{name: string, score: int, level: string, category: string, tone: string}>,
     *     psychometrics: array{holland: array<string,mixed>, mbti: array<string,mixed>, disc: array<string,mixed>, gardner: array<string,mixed>},
     *     evaluations: array<string,mixed>,
     *     fields: list<array{category: string, label: string, tone: string, hours: float, percentage: int}>,
     *     projects: array{total: int, completed: int, in_progress: int, leader_roles: int, featured: list<array{name: string, role: string, status: string, tone: string}>},
     *     ai_insights: array{executive_summary: string, strengths: list<string>, recommendations: list<string>},
     *     facts: array{confirmed_experience_hours: float, attended_activity_count: int, submitted_assessment_type_count: int, published_teacher_evaluation_count: int},
     *     level: array<string,mixed>
     * }
     * @throws ApiException
     */
    public function forStudentPeriod(string $studentId, string $period = 'semester'): array
    {
        $period = strtolower(trim($period));
        if (!in_array($period, self::ALLOWED_PERIODS, true)) {
            throw new ApiException(422, 'INVALID_PERIOD', "Khoảng thời gian thống kê không hợp lệ. Chỉ chấp nhận: " . implode(', ', self::ALLOWED_PERIODS));
        }

        $now = ($this->clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        [$from, $to, $label] = $this->resolveRange($period, $now);

        $periodStats = $this->repository->periodStatistics($studentId, $from, $to);
        $lifetimeFacts = $this->repository->lifetimeFacts($studentId);
        $level = LevelProgression::fromHours((float) $lifetimeFacts['confirmed_experience_hours']);

        $streak = $this->repository->checkinStreakDays($studentId, $now);
        $skills = $this->repository->skillCompetencies($studentId);
        $psychometrics = $this->repository->psychometricResults($studentId);
        $evaluation = $this->repository->latestPublishedEvaluation($studentId);
        $projects = $this->repository->projectStatistics($studentId);

        $competency = $this->computeCompetencyScore($skills, $evaluation);

        $kpis = [
            [
                'id' => 'competency',
                'label' => 'Điểm năng lực',
                'value' => $competency . '/100',
                'suffix' => 'điểm',
                'tone' => 'primary',
                'icon' => 'star',
            ],
            [
                'id' => 'hours',
                'label' => 'Giờ trải nghiệm',
                'value' => $periodStats['hours'],
                'suffix' => 'giờ',
                'tone' => 'teal',
                'icon' => 'clock',
            ],
            [
                'id' => 'streak',
                'label' => 'Chuỗi rèn luyện',
                'value' => $streak,
                'suffix' => 'ngày',
                'tone' => 'orange',
                'icon' => 'flame',
            ],
            [
                'id' => 'activities',
                'label' => 'Hoạt động hoàn thành',
                'value' => $periodStats['activities'],
                'suffix' => 'hoạt động',
                'tone' => 'success',
                'icon' => 'activity',
            ],
            [
                'id' => 'projects',
                'label' => 'Dự án tham gia',
                'value' => $projects['total'],
                'suffix' => 'dự án',
                'tone' => 'purple',
                'icon' => 'folder',
            ],
            [
                'id' => 'badges',
                'label' => 'Huy hiệu đạt được',
                'value' => $periodStats['badges'],
                'suffix' => 'huy hiệu',
                'tone' => 'blue',
                'icon' => 'award',
            ],
        ];

        $experience = $this->buildExperience($period, $periodStats['experience_buckets']);

        $skillCards = $this->buildSkillCards($skills);
        $psychometricCards = $this->buildPsychometrics($psychometrics);
        $evaluationCard = $this->buildEvaluation($evaluation, $now);
        $fields = $this->buildFields($periodStats['category_distribution']);
        $projectCard = $this->buildProjects($projects);

        $aiInsights = $this->buildAiInsights([
            'fields' => $fields,
            'skills' => $skillCards,
            'psychometrics' => $psychometricCards,
            'evaluation' => $evaluationCard,
            'projects' => $projects,
            'facts' => $lifetimeFacts,
            'streak' => $streak,
            'period_hours' => $periodStats['hours'],
            'competency' => $competency,
        ]);

        return [
            'period' => [
                'id' => $period,
                'label' => $label,
                'from' => $from->format('Y-m-d\TH:i:s\Z'),
                'to' => $to->format('Y-m-d\TH:i:s\Z'),
            ],
            'kpis' => $kpis,
            'experience' => $experience,
            'skills' => $skillCards,
            'psychometrics' => $psychometricCards,
            'evaluations' => $evaluationCard,
            'fields' => $fields,
            'projects' => $projectCard,
            'ai_insights' => $aiInsights,
            'facts' => $lifetimeFacts,
            'level' => $level,
        ];
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string}
     */
    private function resolveRange(string $period, DateTimeImmutable $now): array
    {
        $utc = new DateTimeZone('UTC');
        $year = (int) $now->format('Y');
        $monthDay = $now->format('m-d');

        switch ($period) {
            case 'week':
                $from = $now->modify('monday this week 00:00:00');
                $to = $from->modify('+7 days 00:00:00');
                $label = 'Tuần này';
                break;

            case 'month':
                $from = $now->modify('first day of this month 00:00:00');
                $to = $from->modify('+1 month 00:00:00');
                $label = 'Tháng này';
                break;

            case 'semester':
                // Semester 1: Sep 1 -> Jan 15 (next calendar year). Semester 2: Jan 16 -> Aug 31.
                if ($monthDay >= '09-01') {
                    $from = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year), $utc);
                    $to = new DateTimeImmutable(sprintf('%04d-01-16 00:00:00', $year + 1), $utc);
                } elseif ($monthDay <= '01-15') {
                    $from = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year - 1), $utc);
                    $to = new DateTimeImmutable(sprintf('%04d-01-16 00:00:00', $year), $utc);
                } else {
                    $from = new DateTimeImmutable(sprintf('%04d-01-16 00:00:00', $year), $utc);
                    $to = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year), $utc);
                }
                $label = 'Học kỳ này';
                break;

            case 'year':
                // Academic year: Sep 1 -> Aug 31 next calendar year.
                if ($monthDay >= '09-01') {
                    $from = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year), $utc);
                    $to = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year + 1), $utc);
                } else {
                    $from = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year - 1), $utc);
                    $to = new DateTimeImmutable(sprintf('%04d-09-01 00:00:00', $year), $utc);
                }
                $label = 'Năm học này';
                break;

            case 'all':
            default:
                $from = new DateTimeImmutable(self::EPOCH_START, $utc);
                $to = $now;
                $label = 'Toàn bộ quá trình';
                break;
        }

        return [$from, $to, $label];
    }

    /**
     * @param list<array{date: string, label: string, hours: float}> $buckets
     * @return array{hours: list<float>, labels: list<string>, dates: list<string>}
     */
    private function buildExperience(string $period, array $buckets): array
    {
        $hours = [];
        $labels = [];
        $dates = [];

        if ($period === 'semester' || $period === 'year') {
            $monthly = [];
            foreach ($buckets as $bucket) {
                $monthKey = substr((string) $bucket['date'], 0, 7);
                $monthly[$monthKey] = ($monthly[$monthKey] ?? 0.0) + (float) $bucket['hours'];
            }
            foreach ($monthly as $monthKey => $monthHours) {
                $monthNumber = (int) substr($monthKey, 5, 2);
                $hours[] = round($monthHours, 2);
                $labels[] = 'Tháng ' . $monthNumber;
                $dates[] = $monthKey;
            }

            return ['hours' => $hours, 'labels' => $labels, 'dates' => $dates];
        }

        if ($period === 'all') {
            $yearly = [];
            foreach ($buckets as $bucket) {
                $yearKey = substr((string) $bucket['date'], 0, 4);
                $yearly[$yearKey] = ($yearly[$yearKey] ?? 0.0) + (float) $bucket['hours'];
            }
            foreach ($yearly as $yearKey => $yearHours) {
                $hours[] = round($yearHours, 2);
                $labels[] = (string) $yearKey;
                $dates[] = (string) $yearKey;
            }

            return ['hours' => $hours, 'labels' => $labels, 'dates' => $dates];
        }

        foreach ($buckets as $bucket) {
            $hours[] = (float) $bucket['hours'];
            $labels[] = (string) $bucket['label'];
            $dates[] = (string) $bucket['date'];
        }

        return ['hours' => $hours, 'labels' => $labels, 'dates' => $dates];
    }

    /**
     * @param list<array{name: string, category: string, score: float}> $skills
     * @return list<array{name: string, score: int, level: string, category: string, tone: string}>
     */
    private function buildSkillCards(array $skills): array
    {
        $cards = [];
        foreach ($skills as $skill) {
            $score = (int) max(0, min(100, round((float) $skill['score'])));
            $cards[] = [
                'name' => (string) $skill['name'],
                'score' => $score,
                'level' => $this->skillLevel($score),
                'category' => (string) $skill['category'],
                'tone' => self::SKILL_CATEGORY_TONES[$skill['category']] ?? 'secondary',
            ];
        }

        return $cards;
    }

    private function skillLevel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Rất tốt',
            $score >= 75 => 'Tốt',
            $score >= 65 => 'Khá',
            default => 'Đang phát triển',
        };
    }

    /**
     * @param array<string, array{type: string, result_code: string, summary: string, dimension_scores: array<string,float>, submitted_at: string}|null> $results
     * @return array{holland: array<string,mixed>, mbti: array<string,mixed>, disc: array<string,mixed>, gardner: array<string,mixed>}
     */
    private function buildPsychometrics(array $results): array
    {
        $holland = $results['holland'] ?? null;
        $mbti = $results['mbti'] ?? null;
        $disc = $results['disc'] ?? null;
        $gardner = $results['gardner'] ?? null;

        $hollandCard = ['code' => '', 'label' => '', 'status' => 'not_started', 'top_traits' => []];
        if ($holland !== null) {
            $code = strtoupper(trim((string) $holland['result_code']));
            $hollandCard['status'] = $this->psychometricStatus($holland);
            $hollandCard['code'] = $code;
            $hollandCard['label'] = $this->hollandLabel($code);
            $hollandCard['top_traits'] = $this->topDimensionTraits(
                $holland['dimension_scores'],
                self::HOLLAND_TRAITS,
                3
            );
        }

        $mbtiCard = ['type' => '', 'name' => '', 'status' => 'not_started', 'badge' => ''];
        if ($mbti !== null) {
            $type = strtoupper(trim((string) $mbti['result_code']));
            $mbtiCard['status'] = $this->psychometricStatus($mbti);
            $mbtiCard['type'] = $type;
            $mbtiCard['name'] = self::MBTI_NAMES[$type] ?? 'Kiểu tính cách ' . $type;
            $mbtiCard['badge'] = $type !== '' ? $type . '-A' : '';
        }

        $discCard = ['primary' => '', 'label' => '', 'status' => 'not_started'];
        if ($disc !== null) {
            $code = strtoupper(trim((string) $disc['result_code']));
            $primary = $code !== '' ? $code[0] : '';
            $discCard['status'] = $this->psychometricStatus($disc);
            $discCard['primary'] = $primary;
            $discCard['label'] = self::DISC_STYLES[$primary] ?? ($primary !== '' ? $primary : '');
        }

        $gardnerCard = ['primary' => '', 'label' => '', 'score' => 0, 'secondary' => '', 'status' => 'not_started'];
        if ($gardner !== null) {
            $code = strtoupper(trim((string) $gardner['result_code']));
            $segments = array_values(array_filter(explode('-', $code), static fn (string $s): bool => $s !== ''));
            $primarySegment = $segments[0] ?? '';
            $secondarySegment = $segments[1] ?? '';
            $scores = $gardner['dimension_scores'];
            $gardnerCard['status'] = $this->psychometricStatus($gardner);
            $gardnerCard['primary'] = $primarySegment;
            $gardnerCard['label'] = self::GARDNER_DOMAINS[$primarySegment] ?? $primarySegment;
            $gardnerCard['score'] = (int) round((float) ($scores[$primarySegment] ?? 0));
            $gardnerCard['secondary'] = self::GARDNER_DOMAINS[$secondarySegment] ?? $secondarySegment;
        }

        return [
            'holland' => $hollandCard,
            'mbti' => $mbtiCard,
            'disc' => $discCard,
            'gardner' => $gardnerCard,
        ];
    }

    /**
     * @param array{result_code: string, dimension_scores: array<string,float>} $result
     */
    private function psychometricStatus(array $result): string
    {
        if (trim((string) $result['result_code']) !== '' && $result['dimension_scores'] !== []) {
            return 'completed';
        }

        return 'submitted';
    }

    /**
     * @param array<string,float> $scores
     * @param array<string,string> $labelMap
     * @return list<string>
     */
    private function topDimensionTraits(array $scores, array $labelMap, int $limit): array
    {
        arsort($scores);
        $traits = [];
        foreach (array_slice($scores, 0, $limit, true) as $key => $value) {
            $label = $labelMap[(string) $key] ?? (string) $key;
            $traits[] = sprintf('%s (%d%%)', $label, (int) round((float) $value));
        }

        return $traits;
    }

    private function hollandLabel(string $code): string
    {
        $letters = str_split($code);
        $labels = [];
        foreach ($letters as $letter) {
            $label = self::HOLLAND_TRAITS[$letter] ?? null;
            if ($label !== null) {
                $labels[] = $label;
            }
        }

        return implode(' - ', $labels);
    }

    /**
     * @param array{total_score: float|null, comment: string, published_at: string|null, criteria: list<array{code: string, name: string, score: float, max: float, percentage: int}>} $evaluation
     * @return array<string,mixed>
     */
    private function buildEvaluation(array $evaluation, DateTimeImmutable $now): array
    {
        $totalScore = $evaluation['total_score'];
        $publishedAt = $evaluation['published_at'];
        $reference = $publishedAt !== null && $publishedAt !== ''
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $publishedAt, new DateTimeZone('UTC')) ?: $now
            : $now;

        $classification = 'Chưa có đánh giá';
        $ranking = '';
        if ($totalScore !== null) {
            $classification = match (true) {
                $totalScore >= 90 => 'Xuất sắc',
                $totalScore >= 80 => 'Tốt',
                $totalScore >= 70 => 'Khá',
                default => 'Đạt',
            };
            $ranking = match (true) {
                $totalScore >= 90 => 'Top 10% học sinh tiêu biểu',
                $totalScore >= 80 => 'Top 15% học sinh tiêu biểu',
                $totalScore >= 70 => 'Top 30% học sinh tiêu biểu',
                default => 'Trong nhóm tiến bộ vượt bậc',
            };
        }

        return [
            'term' => $this->semesterTerm($reference),
            'total_score' => $totalScore,
            'ranking' => $ranking,
            'classification' => $classification,
            'criteria' => $evaluation['criteria'],
            'teacher_comment' => $evaluation['comment'],
        ];
    }

    private function semesterTerm(DateTimeImmutable $date): string
    {
        $year = (int) $date->format('Y');
        $monthDay = $date->format('m-d');

        if ($monthDay >= '09-01' || $monthDay <= '01-15') {
            $startYear = $monthDay >= '09-01' ? $year : $year - 1;

            return sprintf('Học kỳ I · %04d–%04d', $startYear, $startYear + 1);
        }

        return sprintf('Học kỳ II · %04d–%04d', $year - 1, $year);
    }

    /**
     * @param list<array{category: string, hours: float}> $categories
     * @return list<array{category: string, label: string, tone: string, hours: float, percentage: int}>
     */
    private function buildFields(array $categories): array
    {
        $totalHours = array_sum(array_map(static fn (array $cat): float => (float) $cat['hours'], $categories));
        $fields = [];
        foreach ($categories as $cat) {
            $category = (string) ($cat['category'] ?? 'general');
            $hours = round((float) ($cat['hours'] ?? 0.0), 2);
            $fields[] = [
                'category' => $category,
                'label' => self::FIELD_LABELS[$category] ?? ucfirst($category),
                'tone' => self::FIELD_TONES[$category] ?? 'neutral',
                'hours' => $hours,
                'percentage' => $totalHours > 0 ? (int) round(($hours / $totalHours) * 100) : 0,
            ];
        }

        return $fields;
    }

    /**
     * @param array{total: int, completed: int, in_progress: int, leader_roles: int, featured: list<array{name: string, role: string, status: string}>} $projects
     * @return array{total: int, completed: int, in_progress: int, leader_roles: int, featured: list<array{name: string, role: string, status: string, tone: string}>}
     */
    private function buildProjects(array $projects): array
    {
        $featured = [];
        foreach ($projects['featured'] as $project) {
            $status = (string) $project['status'];
            $featured[] = [
                'name' => (string) $project['name'],
                'role' => $this->isLeaderRoleName((string) $project['role']) ? 'Trưởng nhóm' : 'Thành viên',
                'status' => $status === 'completed' ? 'Hoàn thành' : 'Đang triển khai',
                'tone' => $status === 'completed' ? 'success' : 'warning',
            ];
        }

        return [
            'total' => $projects['total'],
            'completed' => $projects['completed'],
            'in_progress' => $projects['in_progress'],
            'leader_roles' => $projects['leader_roles'],
            'featured' => $featured,
        ];
    }

    private function isLeaderRoleName(string $role): bool
    {
        $normalized = strtolower(trim($role));

        return str_contains($normalized, 'lead') || str_contains($normalized, 'truong') || str_contains($normalized, 'trưởng');
    }

    /**
     * @param list<array{name: string, category: string, score: float}> $skills
     * @param array{total_score: float|null, criteria: list<array<string,mixed>>} $evaluation
     */
    private function computeCompetencyScore(array $skills, array $evaluation): int
    {
        if ($skills !== []) {
            $sum = 0.0;
            foreach ($skills as $skill) {
                $sum += (float) $skill['score'];
            }

            return (int) max(0, min(100, round($sum / count($skills))));
        }

        if ($evaluation['total_score'] !== null) {
            return (int) max(0, min(100, round((float) $evaluation['total_score'])));
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array{executive_summary: string, strengths: list<string>, recommendations: list<string>}
     */
    private function buildAiInsights(array $ctx): array
    {
        /** @var list<array{category: string, label: string, tone: string, hours: float, percentage: int}> $fields */
        $fields = $ctx['fields'];
        /** @var list<array{name: string, score: int, level: string, category: string, tone: string}> $skills */
        $skills = $ctx['skills'];
        /** @var array<string,mixed> $psychometrics */
        $psychometrics = $ctx['psychometrics'];
        /** @var array{total_score: float|null, classification: string} $evaluation */
        $evaluation = $ctx['evaluation'];
        /** @var array{total: int} $projects */
        $projects = $ctx['projects'];
        /** @var array{confirmed_experience_hours: float, submitted_assessment_type_count: int} $facts */
        $facts = $ctx['facts'];
        $streak = (int) $ctx['streak'];
        $competency = (int) $ctx['competency'];

        $strengths = [];
        $recommendations = [];

        $topField = null;
        foreach ($fields as $field) {
            if ($field['hours'] > 0 && ($topField === null || $field['percentage'] > $topField['percentage'])) {
                $topField = $field;
            }
        }

        if ($topField !== null) {
            $summary = sprintf(
                'Bạn phát triển vượt trội ở khối ngành %s với %d%% tổng giờ rèn luyện (%s giờ đã xác nhận).',
                mb_strtolower($topField['label']),
                $topField['percentage'],
                number_format((float) $facts['confirmed_experience_hours'], 1, '.', '')
            );
        } elseif ((float) $facts['confirmed_experience_hours'] > 0) {
            $summary = sprintf(
                'Bạn đã tích lũy %s giờ trải nghiệm đã xác nhận. Hãy tiếp tục tham gia hoạt động để AI phân tích sâu hơn về định hướng của bạn.',
                number_format((float) $facts['confirmed_experience_hours'], 1, '.', '')
            );
        } else {
            $summary = 'Bạn đang ở bước khởi đầu hành trình. Hãy hoàn thành một hoạt động và check-in để AI bắt đầu phân tích năng lực của bạn.';
        }

        if ($competency >= 80) {
            $summary .= sprintf(' Điểm năng lực tổng thể %d/100 đạt chuẩn %s.', $competency, mb_strtolower($evaluation['classification'] === 'Chưa có đánh giá' ? 'tốt' : $evaluation['classification']));
        }

        $topSkills = array_slice(
            array_filter($skills, static fn (array $skill): bool => $skill['score'] >= 75),
            0,
            2
        );
        foreach ($topSkills as $skill) {
            $strengths[] = sprintf('%s (điểm %d - mức %s).', $skill['name'], $skill['score'], mb_strtolower($skill['level']));
        }

        if ($streak >= 3) {
            $strengths[] = sprintf('Duy trì chuỗi %d ngày check-in rèn luyện liên tục.', $streak);
        }

        if ($evaluation['total_score'] !== null && $evaluation['total_score'] >= 80) {
            $strengths[] = 'Được giảng viên đánh giá mức ' . mb_strtolower($evaluation['classification']) . '.';
        }

        if ($strengths === []) {
            $strengths[] = 'Bạn đã sẵn sàng bắt đầu tích lũy năng lực qua hoạt động thực tế.';
        }

        foreach ($psychometrics as $key => $card) {
            if (($card['status'] ?? 'not_started') === 'not_started' && count($recommendations) < 3) {
                $testNames = [
                    'holland' => 'Holland (RIASEC)',
                    'mbti' => 'MBTI',
                    'disc' => 'DISC',
                    'gardner' => 'Đa trí thông minh',
                ];
                $recommendations[] = sprintf(
                    'Hoàn thành bài đánh giá %s để AI cá nhân hóa lộ trình hướng nghiệp chuẩn xác hơn.',
                    $testNames[$key] ?? $key
                );
            }
        }

        if ($topField !== null && $topField['percentage'] >= 60) {
            $weakest = null;
            foreach ($fields as $field) {
                if ($field['category'] !== $topField['category'] && ($weakest === null || $field['percentage'] < $weakest['percentage'])) {
                    $weakest = $field;
                }
            }
            if ($weakest !== null) {
                $recommendations[] = sprintf(
                    'Cân đối thêm hoạt động nhóm %s để bộ hồ sơ năng lực đa chiều hơn.',
                    mb_strtolower($weakest['label'])
                );
            }
        }

        if ($projects['total'] === 0) {
            $recommendations[] = 'Tham gia một dự án thực tế hoặc nghiên cứu cùng giảng viên để chứng minh năng lực qua sản phẩm.';
        }

        if ($streak === 0) {
            $recommendations[] = 'Duy trì check-in hằng ngày để xây dựng chuỗi rèn luyện và nhận huy hiệu kỷ luật.';
        }

        if (count($recommendations) < 2) {
            $recommendations[] = 'Tiếp tục tham gia hoạt động CLB và sự kiện hướng nghiệp để mở rộng mạng lưới kết nối.';
        }

        return [
            'executive_summary' => $summary,
            'strengths' => array_slice($strengths, 0, 3),
            'recommendations' => array_slice($recommendations, 0, 3),
        ];
    }
}
