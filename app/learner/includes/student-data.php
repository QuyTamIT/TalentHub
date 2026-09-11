<?php
require_once __DIR__ . '/auth-guard.php';
/**
 * TalentHub Learner page data.
 *
 * The remaining mock domain arrays keep deterministic rendering available to
 * the test suite. Authenticated production pages use the shared application
 * context below.
 */

$repositoryRoot = dirname(__DIR__, 3);
require_once $repositoryRoot . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/data/bootstrap.php';

if (!function_exists('learner_escape')) {
    function learner_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$studentMock = [
    'id' => '',
    'school_id' => '',
    'class_id' => '',
    'user_id' => '',
    'study_status' => 'active',
    'name' => '',
    'initials' => 'HV',
    'class' => 'Chưa cập nhật lớp',
    'school' => 'Chưa cập nhật trường',
    'email' => '',
    'location' => '',
    'verified' => false,
    'streak_days' => 0,
    'experience_hours' => 0,
];
$appEnvironment = strtolower((string) (getenv('APP_ENV') ?: ''));
$learnerSource = strtolower((string) (getenv('TALENTHUB_LEARNER_SOURCE') ?: 'database'));
$useMock = $appEnvironment === 'test' && $learnerSource === 'mock';

if ($useMock) {
    $learnerDataConfig = learner_data_config();
    learner_configure_data(['source' => 'mock']);
    try {
        $studentRecord = learner_repository_factory()->student([$studentMock])->findById($studentMock['id']);
    } finally {
        learner_configure_data($learnerDataConfig);
    }
    $student = \TalentHub\Learner\Data\ReadModel\StudentReadModel::fromRecord($studentRecord ?? []);
} else {
    try {
        $context = (new \TalentHub\Bootstrap\StudentAppContext())->boot();
    } catch (\TalentHub\Database\Exception\DatabaseConnectionException) {
        require __DIR__ . '/runtime-unavailable.php';
        exit;
    }
    $student = \TalentHub\Learner\Data\Support\SharedStudentAdapter::toView(
        $context['student'],
        $context['dashboard']
    );
    $GLOBALS['learner_page_context'] = $context;

    learner_configure_authenticated_student_context($context);
    $authenticatedStudentId = learner_current_student_id();

    // Talent passport aggregation is now lazy-loaded on demand via learner_talent_passport() to optimize page TTFB
}
$learnerNav = [
    ['label' => 'Tổng quan', 'route' => '/app/learner/index.php', 'icon' => 'grid', 'implemented' => true],
    ['label' => 'Hồ sơ năng lực', 'route' => '/app/learner/profile.php', 'icon' => 'user', 'implemented' => true],
    ['label' => 'Khám phá năng khiếu', 'route' => '/app/learner/discover.php', 'icon' => 'compass', 'implemented' => true],
    ['label' => 'Hoạt động', 'route' => '/app/learner/activities.php', 'icon' => 'calendar', 'implemented' => true],
    ['label' => 'Check-in QR', 'route' => '/app/learner/checkin.php', 'icon' => 'qr', 'implemented' => true],
    ['label' => 'Đánh giá', 'route' => '/app/learner/evaluation.php', 'icon' => 'clipboard', 'implemented' => true],
    ['label' => 'AI gợi ý', 'route' => '/app/learner/ai-recommendations.php', 'icon' => 'sparkles', 'implemented' => true],
    ['label' => 'Hệ sinh thái & Dự án', 'route' => '/app/learner/ecosystem.php', 'icon' => 'ecosystem', 'implemented' => true],
    ['label' => 'Huy hiệu', 'route' => '/app/learner/badges.php', 'icon' => 'award', 'implemented' => true],
    ['label' => 'Thống kê', 'route' => '/app/learner/statistics.php', 'icon' => 'chart', 'implemented' => true],
];
$onboardingNavigation = $GLOBALS['learner_page_context']['onboarding'] ?? ['required' => false];
if (($onboardingNavigation['required'] ?? false) === true) {
    $allowedOnboardingRoutes = match ($onboardingNavigation['status'] ?? '') {
        'pending' => ['/app/learner/index.php'],
        'accepted' => ['/app/learner/index.php', '/app/learner/discover.php'],
        default => null,
    };
    if (is_array($allowedOnboardingRoutes)) {
        $learnerNav = array_values(array_filter(
            $learnerNav,
            static fn (array $item): bool => in_array($item['route'], $allowedOnboardingRoutes, true),
        ));
    }
}

$level = [
    'name' => 'Explorer',
    'number' => 1,
    'currentHours' => 0.0,
    'targetHours' => 10.0,
    'nextLevel' => 'Innovator',
    'remainingHours' => 10.0,
    'progressPercent' => 0,
    'progress' => 0,
    'target' => 10,
    'next_level' => 'Innovator',
];

$isDatabaseMode = !$useMock && learner_repository_factory()->source() === 'database';
$aiCapabilityProfile = null;
$deferTalentPassport = ($learnerDeferTalentPassport ?? false) === true;
$tp = \TalentHub\Learner\Data\ReadModel\TalentPassportReadModel::fromAggregate([]);

if (!function_exists('learner_talent_passport')) {
    /** @return array<string,mixed> */
    function learner_talent_passport(): array
    {
        if (isset($GLOBALS['learner_talent_passport']) && is_array($GLOBALS['learner_talent_passport'])) {
            return $GLOBALS['learner_talent_passport'];
        }
        $authenticatedStudentId = learner_current_student_id();
        $passportRepo = learner_repository_factory()->talentPassport();
        $rawPassport = $passportRepo->aggregateForStudent($authenticatedStudentId);
        $tp = \TalentHub\Learner\Data\ReadModel\TalentPassportReadModel::fromAggregate($rawPassport);
        $GLOBALS['learner_talent_passport'] = $tp;
        return $tp;
    }
}

if ($isDatabaseMode && !$deferTalentPassport) {
    $tp = learner_talent_passport();
    $aiCapabilityProfile = is_array($tp['ai_capability_profile'] ?? null) ? $tp['ai_capability_profile'] : null;
    if (!empty($tp['student']['full_name'])) {
        $student['name'] = $tp['student']['full_name'];
    }
    $confirmedHours = (float) ($tp['experience']['confirmed_hours'] ?? 0.0);
    $hoursValue = $confirmedHours > 0 ? (rtrim(rtrim((string) $confirmedHours, '0'), '.') . 'h') : '0h';

    $phase9DashboardError = false;
    $badgeOverview = null;
    try {
        $badgeOverview = learner_repository_factory()->badgeReadService()->forStudent($authenticatedStudentId);
    } catch (Throwable) {
        $phase9DashboardError = true;
    }
    $level = $badgeOverview['level'] ?? \TalentHub\Learner\Data\Domain\LevelProgression::fromHours($confirmedHours);
    $awardedBadgeCount = count($badgeOverview['badges'] ?? $tp['badges']);

    $verifiedSkillScores = [];
    $allSkillScores = [];
    $skills = [];
    foreach ($tp['skills'] as $dbSkill) {
        $skillStatus = (string) ($dbSkill['skillStatus'] ?? $dbSkill['skill_status'] ?? 'active');
        $verificationStatus = (string) ($dbSkill['verificationStatus'] ?? $dbSkill['verification_status'] ?? '');
        if ($skillStatus !== 'active' || $verificationStatus === 'rejected') {
            continue;
        }

        $rawScore = (float) ($dbSkill['levelScore'] ?? $dbSkill['level_score'] ?? 0);
        $score = max(0, min(100, (int) round($rawScore)));
        $allSkillScores[] = $score;
        if ($verificationStatus === 'verified') {
            $verifiedSkillScores[] = $score;
        }

        $levelLabel = match (true) {
            $score >= 85 => 'Rất tốt',
            $score >= 70 => 'Tốt',
            $score >= 50 => 'Trung bình',
            default => 'Cơ bản',
        };
        $tone = match ($dbSkill['category'] ?? '') {
            'technical' => 'primary',
            'soft' => 'secondary',
            'creative' => 'warning',
            default => 'success',
        };
        $skills[] = [
            'name' => (string) ($dbSkill['name'] ?? ''),
            'short_name' => (string) ($dbSkill['code'] ?? $dbSkill['name'] ?? ''),
            'score' => $score,
            'level' => $levelLabel,
            'tone' => $tone,
            'icon' => 'sparkles',
            'verified' => $verificationStatus === 'verified',
        ];
    }
    usort($skills, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

    $competencyScores = $verifiedSkillScores !== [] ? $verifiedSkillScores : $allSkillScores;
    $competencyScore = $competencyScores === []
        ? null
        : (int) round(array_sum($competencyScores) / count($competencyScores));
    $competencyValue = $competencyScore === null ? 'Chưa có dữ liệu' : $competencyScore . '/100';

    $dashboardKpis = [
        ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => $competencyValue, 'icon' => 'star', 'tone' => 'primary'],
        ['id' => 'experience', 'label' => 'Giờ trải nghiệm', 'value' => $hoursValue, 'icon' => 'clock', 'tone' => 'secondary'],
        ['id' => 'badges', 'label' => 'Huy hiệu đạt được', 'value' => (string) $awardedBadgeCount, 'icon' => 'trophy', 'tone' => 'success'],
    ];

    $certificates = $tp['certificates'];
    $projects = array_values(array_filter($tp['projects'] ?? [], static function (array $project): bool {
        $status = strtolower((string)($project['status'] ?? ''));
        return in_array($status, ['completed', 'đã hoàn thành'], true);
    }));
    $learnerBadges = $badgeOverview['badges'] ?? $tp['badges'];

    $profileKpis = [
        ['label' => 'Điểm năng lực', 'value' => $competencyValue],
        ['label' => 'Huy hiệu', 'value' => (string) count($learnerBadges)],
        ['label' => 'Dự án', 'value' => (string) count($projects)],
    ];
} elseif ($isDatabaseMode) {
    $dashboardKpis = [
        ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => 'Chưa tải', 'icon' => 'star', 'tone' => 'primary'],
        ['id' => 'experience', 'label' => 'Giờ trải nghiệm', 'value' => 'Chưa tải', 'icon' => 'clock', 'tone' => 'secondary'],
        ['id' => 'badges', 'label' => 'Huy hiệu đạt được', 'value' => 'Chưa tải', 'icon' => 'trophy', 'tone' => 'success'],
    ];
    $profileKpis = [];
    $skills = [];
    $certificates = [];
    $projects = [];
    $learnerBadges = [];
} else {
    $dashboardKpis = [
        ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => 'Chưa có dữ liệu', 'icon' => 'star', 'tone' => 'primary'],
        ['id' => 'experience', 'label' => 'Giờ trải nghiệm', 'value' => '0h', 'icon' => 'clock', 'tone' => 'secondary'],
        ['id' => 'badges', 'label' => 'Huy hiệu đạt được', 'value' => '0', 'icon' => 'trophy', 'tone' => 'success'],
    ];
    $skills = [];
    $certificates = [];
    $projects = [];
    $profileKpis = [
        ['label' => 'Điểm năng lực', 'value' => 'Chưa có dữ liệu'],
        ['label' => 'Huy hiệu', 'value' => '0'],
        ['label' => 'Dự án', 'value' => '0'],
    ];
    $learnerBadges = [];
}

$schoolCredentialError = false;
$schoolCredentialData = [
    'ready' => false,
    'analysis_completed' => false,
    'roadmap_analysis' => null,
    'completed_test_count' => 0,
    'required_test_count' => 4,
    'school' => null,
    'featured' => [],
    'badges' => [],
    'certificates' => [],
];
if ($isDatabaseMode) {
    try {
        $schoolCredentialData = learner_repository_factory()
            ->schoolCredentialService()
            ->forStudent($authenticatedStudentId);
    } catch (Throwable) {
        $schoolCredentialError = true;
    }
} else {
    $schoolCredentialData = [
        'ready' => false,
        'analysis_completed' => false,
        'roadmap_analysis' => null,
        'completed_test_count' => 0,
        'required_test_count' => 4,
        'school' => null,
        'featured' => [],
        'badges' => [],
        'certificates' => [],
    ];
}

$dashboardSkillsFromAssessment = false;
if ($isDatabaseMode && $skills === []) {
    $roadmapAnalysis = is_array($schoolCredentialData['roadmap_analysis'] ?? null)
        ? $schoolCredentialData['roadmap_analysis']
        : null;
    $skillAnalysis = is_array($aiCapabilityProfile) ? $aiCapabilityProfile : $roadmapAnalysis;
    $talentMap = is_array($skillAnalysis['talent_map'] ?? null) ? $skillAnalysis['talent_map'] : [];
    $skillTones = ['primary', 'secondary', 'success', 'warning'];

    foreach ($talentMap as $index => $talent) {
        if (!is_array($talent)) {
            continue;
        }
        $name = trim((string) ($talent['field'] ?? $talent['label'] ?? $talent['name'] ?? ''));
        if ($name === '' || !is_numeric($talent['score'] ?? null)) {
            continue;
        }
        $score = (float) $talent['score'];
        if ($score <= 1) {
            $score *= 100;
        }
        $score = max(0, min(100, (int) round($score)));
        $skills[] = [
            'name' => $name,
            'short_name' => $name,
            'score' => $score,
            'level' => match (true) {
                $score >= 85 => 'Rất tốt',
                $score >= 70 => 'Tốt',
                $score >= 50 => 'Khá',
                default => 'Đang phát triển',
            },
            'tone' => $skillTones[$index % count($skillTones)],
            'icon' => 'sparkles',
            'verified' => false,
            'source' => 'ai_assessment',
        ];
    }

    if ($skills !== []) {
        usort($skills, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $dashboardSkillsFromAssessment = true;
        $competencyScore = (int) round(array_sum(array_column($skills, 'score')) / count($skills));
        $competencyValue = $competencyScore . '/100';
        foreach ($dashboardKpis as &$dashboardKpi) {
            if (($dashboardKpi['id'] ?? '') === 'competency') {
                $dashboardKpi['value'] = $competencyValue;
            }
        }
        unset($dashboardKpi);
        foreach ($profileKpis as &$profileKpi) {
            if (($profileKpi['label'] ?? '') === 'Điểm năng lực') {
                $profileKpi['value'] = $competencyValue;
            }
        }
        unset($profileKpi);
    }
}

$activityCategories = ['Tất cả', 'Kỹ thuật', 'Kinh doanh', 'Sáng tạo', 'Cộng đồng'];

$activityCatalog = [
    ['id' => 'iot-lab', 'category' => 'Kỹ thuật', 'filter_category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'IoT Lab — Cảm biến thông minh', 'time' => 'Th 6, 14:00', 'location' => 'Phòng B305', 'participants' => 38, 'capacity' => 50],
    ['id' => 'drone-workshop', 'category' => 'Sáng tạo', 'filter_category' => 'Sáng tạo', 'tone' => 'secondary', 'title' => 'Drone Workshop', 'time' => 'CN, 09:00', 'location' => 'Sân vận động', 'participants' => 18, 'capacity' => 20],
    ['id' => 'startup-pitch', 'category' => 'Kinh doanh', 'filter_category' => 'Kinh doanh', 'tone' => 'success', 'title' => 'Startup Club — Pitch Night', 'time' => 'Th 7, 18:30', 'location' => 'Hall A', 'participants' => 12, 'capacity' => 30],
    ['id' => 'ai-bootcamp', 'category' => 'Công nghệ', 'filter_category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'AI Bootcamp', 'time' => 'T2, 09:00', 'location' => 'Phòng IT', 'participants' => 25, 'capacity' => 40],
    ['id' => 'design-thinking', 'category' => 'Sáng tạo', 'filter_category' => 'Sáng tạo', 'tone' => 'secondary', 'title' => 'Design Thinking Lab', 'time' => 'T4, 15:00', 'location' => 'Studio C', 'participants' => 9, 'capacity' => 25],
    ['id' => 'charity-marathon', 'category' => 'Cộng đồng', 'filter_category' => 'Cộng đồng', 'tone' => 'success', 'title' => 'Marathon từ thiện', 'time' => 'CN, 06:00', 'location' => 'Hồ Tây', 'participants' => 67, 'capacity' => 100],
];

$activities = array_slice($activityCatalog, 0, 3);
if ($isDatabaseMode) {
    $activities = array_map(
        static function (array $entry): array {
            $title = trim((string) ($entry['activity_title'] ?? '')) ?: 'Hoạt động đã xác nhận';
            $displayCategory = trim((string) ($entry['display_category'] ?? ''));
            $canonicalCategory = trim((string) ($entry['activity_category'] ?? ''));
            $category = $displayCategory !== ''
                ? $displayCategory
                : learner_activity_category_label($canonicalCategory);
            $location = trim((string) ($entry['location_name'] ?? '')) ?: 'Chưa cập nhật';
            $cover = trim((string) ($entry['cover_image_url'] ?? ''));
            if (str_contains($cover, '..') || preg_match('#\A(?:/app/learner/)?(assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg))\z#i', $cover, $matches) !== 1) {
                $cover = 'assets/activities/illustrations/hero-detail.svg';
            } else {
                $cover = is_file(dirname(__DIR__) . '/' . $matches[1]) ? $matches[1] : 'assets/activities/illustrations/hero-detail.svg';
            }
            $coverAlt = trim((string) ($entry['cover_image_alt'] ?? '')) ?: 'Ảnh hoạt động ' . $title;

            return [
                'id' => (string) ($entry['activity_id'] ?? ''),
                'category' => $category,
                'canonical_category' => $canonicalCategory,
                'tone' => 'neutral',
                'title' => $title,
                'start_at' => $entry['activity_start_at'] ?? null,
                'time' => $entry['activity_start_at'] ?? null,
                'location' => $location,
                'cover_image_url' => $cover,
                'cover_image_alt' => $coverAlt,
            ];
        },
        array_slice($tp['experience']['confirmed_entries'] ?? [], 0, 3)
    );
}

$checkinHistory = [];

if ($isDatabaseMode) {
    $evaluationTerms = [];
    $defaultEvaluationTerm = '';
} else {
    $defaultEvaluationTerm = '';
    $evaluationTerms = [];
}

$assessments = [];
$assessmentResults = [];
$radarScores = [];
$careerDirections = [];
$learnerLevels = [];

$learnerBadgeFilters = [
    ['id' => 'all', 'label' => 'Tất cả'],
    ['id' => 'achieved', 'label' => 'Đã đạt'],
    ['id' => 'in_progress', 'label' => 'Đang tiến hành'],
    ['id' => 'locked', 'label' => 'Chưa đạt'],
];

$defaultStatisticsPeriod = 'six-months';
$learnerStatisticsPeriods = [];
