<?php
/**
 * TalentHub Learner - Digital Talent Passport (Hộ chiếu Năng lực Số 360°)
 * Hiển thị thẻ định danh, kết quả 4 bài đánh giá năng lực (DISC, MBTI, Holland, MI),
 * kỹ năng đã xác thực, dự án nhận bảo trợ doanh nghiệp, chứng chỉ và xác thực Giảng viên.
 */
declare(strict_types=1);

require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/project-data.php';

$pageTitle = 'Talent Passport - Hộ chiếu Năng lực Số 360°';
$currentRoute = '/app/learner/talent-passport.php';

$studentId = (string) ($student['id'] ?? learner_current_student_id());
$studentName = !empty($student['name']) ? $student['name'] : 'Học viên';
$studentClass = !empty($student['class']) ? $student['class'] : ($student['className'] ?? 'Chưa cập nhật lớp');
$studentSchool = !empty($student['school']) ? $student['school'] : ($student['schoolName'] ?? 'Chưa cập nhật trường');
// Không fallback dữ liệu giả: khi trống, giao diện hiển thị "Chưa cập nhật".
$studentEmail = trim((string) ($student['email'] ?? ''));
$studentPhone = trim((string) ($student['phone'] ?? ''));
$studentLocation = trim((string) ($student['location'] ?? ''));
$studentHeadline = trim((string) ($student['headline'] ?? ''));
$studentAvatarUrl = !empty($student['avatar_url']) ? (string) $student['avatar_url'] : (!empty($student['avatarUrl']) ? (string) $student['avatarUrl'] : '');
$studentInitials = !empty($student['initials']) ? (string) $student['initials'] : 'HV';

// 1. Dữ liệu từ Talent Passport Aggregate & Database
$talentPassport = $GLOBALS['learner_talent_passport'] ?? [];

// 2. Điểm Tổng hợp & Đánh giá của Giảng viên
$overallScore = 0.0;
$hasOverallScore = false;
$gradeClassification = 'Chưa xếp loại';
$rankingPercentile = 'Đang cập nhật';
$evalComment = '';
$evalReviewer = 'Giảng viên hướng dẫn';
$evalOrg = !empty($studentSchool) && $studentSchool !== 'Chưa cập nhật trường' ? $studentSchool : 'Đơn vị đào tạo';

if (!empty($talentPassport['teacher_evaluations'][0])) {
    $firstEval = $talentPassport['teacher_evaluations'][0];
    if (array_key_exists('overall_score', $firstEval) || array_key_exists('overallScore', $firstEval)) {
        $overallScore = (float) ($firstEval['overall_score'] ?? $firstEval['overallScore']);
        $hasOverallScore = true;
    }
    if (!empty($firstEval['classification'])) {
        $gradeClassification = (string) $firstEval['classification'];
    }
    if (!empty($firstEval['comment'])) {
        $evalComment = (string) $firstEval['comment'];
    }
    if (!empty($firstEval['teacher_name']) || !empty($firstEval['teacherName'])) {
        $evalReviewer = (string) ($firstEval['teacher_name'] ?? $firstEval['teacherName']);
    }
}

// 3. Xử lý Kết quả 4 bài Đánh giá Năng lực (DISC, MBTI, Holland, Đa trí thông minh)
$rawAssessments = $talentPassport['assessment_results'] ?? [];
$assessmentCards = [];
foreach ($rawAssessments as $index => $assessment) {
    $dimensions = $assessment['dimension_scores'] ?? $assessment['dimensionScores'] ?? [];
    if (is_string($dimensions)) {
        $decodedDimensions = json_decode($dimensions, true);
        $dimensions = is_array($decodedDimensions) ? $decodedDimensions : [];
    }
    $dimensionLabels = [];
    if (is_array($dimensions)) {
        foreach ($dimensions as $label => $score) {
            if (is_scalar($score)) {
                $dimensionLabels[] = (string) $label . ': ' . (string) $score;
            }
        }
    }
    $assessmentCards[] = [
        'name' => trim((string) ($assessment['test_name'] ?? $assessment['testName'] ?? $assessment['test_code'] ?? $assessment['testCode'] ?? ('Bài đánh giá ' . ($index + 1)))),
        'code' => trim((string) ($assessment['result_code'] ?? $assessment['resultCode'] ?? '')),
        'summary' => trim((string) ($assessment['summary'] ?? '')),
        'dimensions' => $dimensionLabels,
    ];
}

// 4. Danh sách Kỹ năng đã Xác thực
$rawSkills = !empty($talentPassport['skills']) ? $talentPassport['skills'] : ($skills ?? []);
$skillNameMap = [
    'machine_learning' => 'Học máy (Machine Learning)',
    'ai_machine_learning' => 'Trí tuệ Nhân tạo & ML',
    'ai_ml' => 'Trí tuệ Nhân tạo & ML',
    'data_analysis' => 'Phân tích dữ liệu',
    'teamwork' => 'Kỹ năng làm việc nhóm',
    'python' => 'Lập trình Python',
    'pytorch' => 'PyTorch & Deep Learning',
    'computer_vision' => 'Thị giác máy tính (CV)',
    'docker' => 'Docker & Containerization',
    'git' => 'Quản lý mã nguồn Git',
    'mysql' => 'Cơ sở dữ liệu MySQL',
    'iot' => 'IoT & Vi điều khiển ESP32',
    'communication' => 'Giao tiếp & Thuyết trình',
];

$displaySkills = [];
if (!empty($rawSkills)) {
    foreach ($rawSkills as $sk) {
        $skillVerification = strtolower((string) ($sk['verification_status'] ?? $sk['verificationStatus'] ?? ''));
        $skillStatus = strtolower((string) ($sk['skill_status'] ?? $sk['skillStatus'] ?? 'active'));
        if (($skillVerification !== 'verified' && ($sk['verified'] ?? false) !== true) || $skillStatus !== 'active') {
            continue;
        }
        $skCode = strtolower(trim((string) ($sk['code'] ?? $sk['name'] ?? '')));
        $skName = $skillNameMap[$skCode] ?? (string) ($sk['name'] ?? 'Kỹ năng chuyên môn');
        $skScore = max(0, min(100, (int) round((float) ($sk['level_score'] ?? $sk['levelScore'] ?? $sk['score'] ?? $sk['level'] ?? 0))));
        $skCategory = strtolower((string) ($sk['category'] ?? ''));
        $isSoft = in_array($skCategory, ['soft', 'general'], true) || in_array($skCode, ['teamwork', 'communication'], true);

        $displaySkills[] = [
            'name' => $skName,
            'score' => $skScore,
            'type' => $isSoft ? 'soft' : 'technical',
            'verified' => true,
        ];
    }
}

$technicalSkills = [];
$softSkills = [];
foreach ($displaySkills as $sk) {
    if (($sk['type'] ?? '') === 'soft') {
        $softSkills[] = $sk;
    } else {
        $technicalSkills[] = $sk;
    }
}

// 5. Danh sách Dự án đã Bảo trợ & Tham gia
$rawProjects = !empty($talentPassport['projects']) ? $talentPassport['projects'] : (function_exists('learner_projects') ? learner_projects() : []);
$displayProjects = [];
if (!empty($rawProjects)) {
    foreach ($rawProjects as $p) {
        $sponsors = $p['sponsorships'] ?? [];
        $sponsorName = !empty($p['sponsor_name']) ? (string) $p['sponsor_name'] : (!empty($sponsors[0]['enterprise_name']) ? (string) $sponsors[0]['enterprise_name'] : '');
        $raisedAmount = (float) ($p['raised_amount'] ?? $p['raisedAmount'] ?? 0);
        $fundingGoal = (float) ($p['funding_goal'] ?? $p['fundingGoal'] ?? 0);
        $projectDesc = trim((string) ($p['description'] ?? $p['desc'] ?? ''));
        $displayProjects[] = [
            'name' => (string) ($p['name'] ?? $p['title'] ?? 'Dự án nghiên cứu'),
            'role' => (string) ($p['role'] ?? 'Trưởng nhóm kỹ thuật'),
            'category' => (string) ($p['category_label'] ?? $p['category'] ?? 'Công nghệ & AI'),
            'status' => (string) ($p['status_label'] ?? 'Đang triển khai'),
            'desc' => $projectDesc,
            'sponsor_name' => $sponsorName,
            'raised_amount' => $raisedAmount,
            'funding_goal' => $fundingGoal,
        ];
    }
}

// 6. Danh sách Chứng chỉ & Văn bằng
$rawCertificates = !empty($talentPassport['certificates']) ? $talentPassport['certificates'] : ($certificates ?? []);
$displayCertificates = [];
if (!empty($rawCertificates)) {
    foreach ($rawCertificates as $c) {
        $certificateVerification = strtolower((string) ($c['verification_status'] ?? $c['verificationStatus'] ?? ''));
        if ($certificateVerification !== 'verified' && ($c['verified'] ?? false) !== true) {
            continue;
        }
        $displayCertificates[] = [
            'name' => (string) ($c['name'] ?? $c['title'] ?? 'Chứng chỉ chuyên môn'),
            'issuer' => (string) ($c['issuer'] ?? $c['issuing_organization'] ?? 'Nhà trường & Đối tác'),
            'year' => (string) ($c['year'] ?? $c['issue_date'] ?? '2026'),
            'credential_id' => (string) ($c['credential_id'] ?? $c['credentialId'] ?? ''),
        ];
    }
}

// 7. Danh sách Hoạt động Trải nghiệm & Ngoại khóa đã Xác nhận
$rawActivities = !empty($talentPassport['experience']['confirmed_entries'])
    ? $talentPassport['experience']['confirmed_entries']
    : ($activities ?? []);
$displayActivities = [];
if (!empty($rawActivities)) {
    foreach ($rawActivities as $act) {
        $actTitle = trim((string) ($act['activity_title'] ?? $act['title'] ?? 'Hoạt động trải nghiệm'));
        $actCategory = trim((string) ($act['display_category'] ?? $act['category'] ?? 'Thực hành'));
        $rawTime = $act['activity_start_at'] ?? $act['time'] ?? null;
        $actTime = !empty($rawTime) && strtotime((string)$rawTime) !== false ? date('d/m/Y', strtotime((string)$rawTime)) : (string)($rawTime ?: '2026');
        $actLocation = trim((string) ($act['location_name'] ?? $act['location'] ?? 'TalentHub Lab'));
        $actHours = (float) ($act['confirmed_hours'] ?? $act['hours'] ?? $act['hours_spent'] ?? 0);
        $displayActivities[] = [
            'title' => $actTitle,
            'category' => $actCategory,
            'time' => $actTime,
            'location' => $actLocation,
            'hours' => $actHours,
        ];
    }
}

// 8. Danh sách Huy hiệu Năng lực Đạt được
$rawBadges = !empty($talentPassport['badges']) ? $talentPassport['badges'] : ($learnerBadges ?? []);
$displayBadges = [];
if (!empty($rawBadges)) {
    foreach ($rawBadges as $b) {
        $bStatus = strtolower((string) ($b['status'] ?? ''));
        if ($bStatus === 'achieved' || !empty($b['awarded_at']) || ($bStatus === 'in_progress' && ($b['current'] ?? 0) > 0)) {
            $displayBadges[] = [
                'name' => (string) ($b['name'] ?? 'Huy hiệu Năng lực'),
                'description' => (string) ($b['description'] ?? ''),
                'icon' => (string) ($b['icon'] ?? 'award'),
                'status_label' => (string) ($b['status_label'] ?? ($bStatus === 'achieved' ? 'Đã đạt' : 'Đang rèn luyện')),
                'is_achieved' => ($bStatus === 'achieved' || !empty($b['awarded_at'])),
            ];
        }
    }
}

// 9. Tóm tắt Hồ sơ Năng lực (Professional Summary)
$professionalSummary = trim((string) ($student['bio'] ?? ''));
if ($professionalSummary === '') {
    $topSkillNames = array_slice(array_column($displaySkills, 'name'), 0, 3);
    $skillsText = !empty($topSkillNames) ? implode(', ', $topSkillNames) : 'công nghệ và kỹ năng số';
    $schoolText = !empty($studentSchool) && $studentSchool !== 'Chưa cập nhật trường' ? $studentSchool : 'TalentHub';
    $hoursText = (int) ($student['experience_hours'] ?? ($talentPassport['experience']['confirmed_hours'] ?? 0));
    $headlineText = !empty($studentHeadline) ? $studentHeadline : 'Học viên đam mê nghiên cứu và đổi mới sáng tạo';
    $professionalSummary = "{$headlineText} tại {$schoolText} với hơn {$hoursText} giờ trải nghiệm thực tế. Có thế mạnh về {$skillsText}, định hướng chủ động phát triển các dự án thực tiễn và sẵn sàng tham gia nghiên cứu, thực tập trong môi trường doanh nghiệp chuyên nghiệp.";
}


?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Talent Passport 360° - Hộ chiếu Năng lực Số của <?= learner_escape($studentName); ?> được chứng thực bởi <?= learner_escape($studentSchool); ?>.">
    <title>Talent Passport 360° | <?= learner_escape($studentName); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/global.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/brand-component.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/brand-component.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/polish.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/polish.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/learner.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/learner.css'); ?>">
    <style>
        /* ==========================================================================
           TALENT PASSPORT 360° - MODERN 2-COLUMN PROFESSIONAL CV LAYOUT
           ========================================================================== */
        .passport-wrapper {
            max-width: 1060px;
            margin: 0 auto;
            padding-bottom: 3.5rem;
        }

        /* Action Toolbar */
        .passport-top-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Main CV Card */
        .passport-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
            border: 1px solid #CBD5E1;
            overflow: hidden;
            position: relative;
        }

        /* Top Header Banner */
        .passport-header-banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 60%, #1D4ED8 100%);
            color: #FFFFFF;
            padding: 0.9rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .passport-header-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .passport-badge-code {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-family: monospace;
            font-size: 0.775rem;
            font-weight: 700;
            color: #FFFFFF;
        }

        /* ==========================================================================
           2-COLUMN CV BODY
           ========================================================================== */
        .passport-cv-body {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            background: #FFFFFF;
        }

        /* --------------------------------------------------------------------------
           LEFT SIDEBAR (~34%)
           -------------------------------------------------------------------------- */
        .passport-cv-sidebar {
            width: 34%;
            flex: 0 0 34%;
            max-width: 34%;
            background: #F8FAFC;
            border-right: 1px solid #E2E8F0;
            padding: 1.75rem 1.35rem;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 1.35rem;
        }

        .passport-sidebar-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.75rem;
        }
        .passport-cv-avatar {
            width: 92px;
            height: 92px;
            border-radius: 16px;
            background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
            color: #FFFFFF;
            font-size: 2.25rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid #EFF6FF;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.16);
        }
        .passport-cv-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .passport-verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #DCFCE7;
            color: #15803D;
            border: 1px solid #86EFAC;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.725rem;
            font-weight: 700;
        }

        .passport-sidebar-section {
            display: flex;
            flex-direction: column;
        }
        .passport-sidebar-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin: 0 0 0.65rem 0;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 0.35rem;
        }

        /* Contact Details */
        .passport-sidebar-contact-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .passport-sidebar-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.7875rem;
            color: #475569;
            line-height: 1.35;
            word-break: break-word;
        }
        .passport-sidebar-contact-item .contact-icon {
            color: #2563EB;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Overall Score Card */
        .passport-score-card {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            text-align: center;
        }
        .passport-score-main {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .passport-score-number {
            font-size: 2.35rem;
            font-weight: 900;
            color: #15803D;
            line-height: 1;
        }
        .passport-score-scale {
            font-size: 0.6875rem;
            font-weight: 800;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.2rem;
        }
        .passport-score-badge {
            display: inline-block;
            background: #16A34A;
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.65rem;
            border-radius: 6px;
            margin: 0.4rem 0 0.25rem 0;
        }
        .passport-score-hint {
            font-size: 0.7rem;
            color: #166534;
            line-height: 1.35;
            margin: 0;
        }

        /* 4 Assessments */
        .passport-tests-compact-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .passport-test-compact-item {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 0.6rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .passport-test-compact-item .test-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.35rem;
        }
        .passport-test-compact-item .test-name {
            font-size: 0.775rem;
            font-weight: 800;
            color: #0F172A;
        }
        .passport-test-compact-item .test-code {
            font-size: 0.7rem;
            font-weight: 800;
            color: #FFFFFF;
            background: #2563EB;
            padding: 1px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .passport-test-compact-item .test-summary {
            font-size: 0.7125rem;
            color: #334155;
            line-height: 1.35;
        }
        .passport-test-compact-item .test-dim {
            font-size: 0.675rem;
            color: #64748B;
            background: #F8FAFC;
            border: 1px solid #F1F5F9;
            border-radius: 4px;
            padding: 2px 5px;
            line-height: 1.25;
        }

        /* Skills */
        .skills-subgroup-title {
            font-size: 0.7rem;
            font-weight: 800;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.35rem;
        }
        .passport-skills-compact {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .passport-skill-row {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        .passport-skill-row .skill-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7625rem;
            font-weight: 700;
            color: #1E293B;
        }
        .passport-skill-row .skill-val {
            font-size: 0.725rem;
            font-weight: 800;
            color: #2563EB;
        }
        .passport-skill-row .skill-bar {
            height: 5px;
            background: #E2E8F0;
            border-radius: 999px;
            overflow: hidden;
        }
        .passport-skill-row .skill-bar span {
            display: block;
            height: 100%;
            background: #2563EB;
            border-radius: inherit;
        }

        /* QR Verification Box */
        .passport-sidebar-qr-box {
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 0.85rem 0.75rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }
        .passport-qr-cv-img {
            width: 95px;
            height: 95px;
            border-radius: 6px;
            background: #FFFFFF;
            padding: 2px;
            border: 1px solid #E2E8F0;
        }
        .passport-qr-cv-img canvas,
        .passport-qr-cv-img img,
        .passport-qr-cv-img svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
        .passport-qr-cv-badge {
            margin-top: 0.4rem;
            font-size: 0.675rem;
            font-weight: 800;
            color: #1E40AF;
            background: #DBEAFE;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .passport-qr-cv-caption {
            font-size: 0.675rem;
            color: #64748B;
            font-weight: 700;
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* --------------------------------------------------------------------------
           RIGHT MAIN CONTENT (~66%)
           -------------------------------------------------------------------------- */
        .passport-cv-main {
            width: 66%;
            flex: 0 0 66%;
            max-width: 66%;
            background: #FFFFFF;
            padding: 1.75rem 2rem;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .passport-main-header {
            display: flex;
            flex-direction: column;
        }
        .passport-cv-fullname {
            font-size: 1.85rem;
            font-weight: 900;
            color: #0F172A;
            margin: 0 0 0.25rem 0;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .passport-cv-target-headline {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2563EB;
            margin: 0 0 0.65rem 0;
        }
        .passport-summary-box {
            background: #F8FAFC;
            border-left: 3px solid #2563EB;
            padding: 0.75rem 1rem;
            border-radius: 0 8px 8px 0;
            border-top: 1px solid #F1F5F9;
            border-right: 1px solid #F1F5F9;
            border-bottom: 1px solid #F1F5F9;
        }
        .passport-summary-box p {
            font-size: 0.8125rem;
            color: #334155;
            line-height: 1.55;
            margin: 0;
        }

        .passport-main-section {
            display: flex;
            flex-direction: column;
        }
        .passport-main-section-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 0.75rem 0;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 0.4rem;
        }

        /* Projects */
        .passport-projects-list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .passport-cv-project-card {
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .passport-cv-project-card .proj-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .passport-cv-project-card .proj-title {
            font-size: 0.875rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0;
        }
        .passport-cv-project-card .proj-sub {
            font-size: 0.75rem;
            color: #64748B;
            margin-top: 0.15rem;
        }
        .passport-cv-project-card .proj-status-badge {
            font-size: 0.725rem;
            font-weight: 700;
            color: #15803D;
            background: #DCFCE7;
            padding: 2px 8px;
            border-radius: 9999px;
            white-space: nowrap;
        }
        .passport-cv-project-card .proj-desc {
            font-size: 0.775rem;
            color: #334155;
            line-height: 1.45;
            margin: 0;
        }
        .passport-cv-project-card .proj-sponsor-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #EEF2FF;
            color: #3730A3;
            border: 1px solid #C7D2FE;
            padding: 2px 8px;
            border-radius: 5px;
            width: fit-content;
        }

        /* Activities */
        .passport-activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.65rem;
        }
        .passport-activity-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .passport-activity-card .act-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.35rem;
        }
        .passport-activity-card .act-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: #0F172A;
        }
        .passport-activity-card .act-category-pill {
            font-size: 0.675rem;
            font-weight: 700;
            color: #1D4ED8;
            background: #DBEAFE;
            padding: 1px 6px;
            border-radius: 4px;
        }
        .passport-activity-card .act-meta {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.725rem;
            color: #64748B;
            flex-wrap: wrap;
        }
        .passport-activity-card .act-hours-pill {
            font-weight: 700;
            color: #059669;
        }

        /* Certs & Badges */
        .passport-certs-grid {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .passport-cv-cert-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.85rem;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
        }
        .passport-cv-cert-item .cert-icon {
            font-size: 1.15rem;
            line-height: 1;
        }
        .passport-cv-cert-item .cert-details {
            flex: 1;
        }
        .passport-cv-cert-item .cert-name {
            font-size: 0.825rem;
            font-weight: 800;
            color: #0F172A;
            display: block;
        }
        .passport-cv-cert-item .cert-meta {
            font-size: 0.7375rem;
            color: #64748B;
        }
        .passport-cv-cert-item .cert-code {
            font-family: monospace;
            font-weight: 700;
            color: #0F172A;
        }
        .passport-cv-cert-item .cert-status-badge {
            font-size: 0.675rem;
            font-weight: 700;
            color: #15803D;
            background: #DCFCE7;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .passport-badges-inline-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.5rem;
        }
        .passport-badge-cv-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 8px;
        }
        .passport-badge-cv-item .badge-icon {
            font-size: 1.1rem;
        }
        .passport-badge-cv-item .badge-name {
            font-size: 0.775rem;
            font-weight: 800;
            color: #92400E;
            display: block;
        }
        .passport-badge-cv-item .badge-desc {
            font-size: 0.6875rem;
            color: #B45309;
            line-height: 1.25;
            display: block;
        }

        /* Endorsement */
        .passport-endorsement-box {
            background: #F8FAFC;
            border-left: 3px solid #2563EB;
            padding: 0.85rem 1.15rem;
            border-radius: 0 8px 8px 0;
            border-top: 1px solid #E2E8F0;
            border-right: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
        }
        .passport-endorsement-text {
            font-size: 0.825rem;
            color: #334155;
            line-height: 1.55;
            font-style: italic;
            margin: 0 0 0.55rem 0;
        }
        .passport-endorsement-signer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.4rem;
            font-size: 0.775rem;
        }
        .passport-endorsement-signer .signer-info {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #0F172A;
        }
        .passport-verification-seal {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.675rem;
            font-weight: 800;
            color: #15803D;
            background: #DCFCE7;
            border: 1px solid #86EFAC;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        /* Footer */
        .passport-footer {
            background: #F8FAFC;
            border-top: 1px solid #E2E8F0;
            padding: 0.85rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: #64748B;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .passport-empty-state {
            margin: 0;
            padding: 0.75rem 1rem;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 8px;
            color: #64748B;
            font-size: 0.775rem;
        }
        .passport-empty-state-sm {
            margin: 0;
            padding: 0.5rem 0.65rem;
            background: #FFFFFF;
            border: 1px dashed #CBD5E1;
            border-radius: 6px;
            color: #64748B;
            font-size: 0.725rem;
        }

        /* Responsive */
        @media (max-width: 840px) {
            .passport-cv-body {
                flex-direction: column;
            }
            .passport-cv-sidebar {
                width: 100%;
                max-width: 100%;
                border-right: none;
                border-bottom: 1px solid #E2E8F0;
                padding: 1.5rem;
            }
            .passport-cv-main {
                width: 100%;
                max-width: 100%;
                padding: 1.5rem;
            }
        }

        /* ==========================================================================
           CSS PRINT A4 OPTIMIZATION
           ========================================================================== */
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        @media print {
            html,
            body {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #FFFFFF !important;
                color: #0F172A !important;
            }
            body.learner-page-passport * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body.learner-page-passport .learner-sidebar,
            body.learner-page-passport .learner-sidebar-backdrop,
            body.learner-page-passport .learner-header,
            body.learner-page-passport .learner-toast,
            body.learner-page-passport .passport-top-toolbar,
            body.learner-page-passport .passport-customizer-panel,
            body.learner-page-passport .passport-action-bar,
            body.learner-page-passport .learner-nav,
            body.learner-page-passport .skip-link {
                display: none !important;
            }
            body.learner-page-passport .learner-layout,
            body.learner-page-passport .learner-main,
            body.learner-page-passport .learner-content,
            body.learner-page-passport .passport-wrapper {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                min-width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            body.learner-page-passport .passport-card {
                box-sizing: border-box !important;
                width: 100% !important;
                margin: 0 !important;
                border: 1px solid #CBD5E1 !important;
                border-radius: 3mm !important;
                box-shadow: none !important;
                overflow: visible !important;
            }
            body.learner-page-passport .passport-header-banner {
                padding: 2.5mm 5mm !important;
                gap: 2mm !important;
            }
            body.learner-page-passport .passport-header-title {
                font-size: 8pt !important;
            }
            body.learner-page-passport .passport-badge-code {
                padding: 0.5mm 2mm !important;
                font-size: 6pt !important;
            }
            body.learner-page-passport .passport-cv-body {
                display: flex !important;
                flex-direction: row !important;
                width: 100% !important;
                align-items: stretch !important;
            }
            body.learner-page-passport .passport-cv-sidebar {
                width: 34% !important;
                flex: 0 0 34% !important;
                max-width: 34% !important;
                background: #F8FAFC !important;
                border-right: 1px solid #CBD5E1 !important;
                padding: 3mm 4mm !important;
                gap: 2.5mm !important;
                box-sizing: border-box !important;
            }
            body.learner-page-passport .passport-cv-main {
                width: 66% !important;
                flex: 0 0 66% !important;
                max-width: 66% !important;
                background: #FFFFFF !important;
                padding: 3.5mm 5mm !important;
                gap: 2.8mm !important;
                box-sizing: border-box !important;
            }
            body.learner-page-passport .passport-sidebar-profile {
                gap: 1.5mm !important;
            }
            body.learner-page-passport .passport-cv-avatar {
                width: 15mm !important;
                height: 15mm !important;
                border-radius: 2.5mm !important;
                font-size: 14pt !important;
                border-width: 1.5px !important;
            }
            body.learner-page-passport .passport-verified-pill {
                font-size: 5.5pt !important;
                padding: 0.5mm 2mm !important;
            }
            body.learner-page-passport .passport-sidebar-title {
                font-size: 6.2pt !important;
                margin-bottom: 1.5mm !important;
                padding-bottom: 0.8mm !important;
            }
            body.learner-page-passport .passport-sidebar-title svg {
                width: 3mm !important;
                height: 3mm !important;
            }
            body.learner-page-passport .passport-sidebar-contact-item {
                font-size: 5.8pt !important;
                gap: 1.5mm !important;
                margin-bottom: 1mm !important;
                line-height: 1.25 !important;
            }
            body.learner-page-passport .passport-sidebar-contact-item svg {
                width: 2.8mm !important;
                height: 2.8mm !important;
            }
            body.learner-page-passport .passport-score-card {
                padding: 1.5mm 2mm !important;
                border-radius: 1.5mm !important;
            }
            body.learner-page-passport .passport-score-number {
                font-size: 16pt !important;
            }
            body.learner-page-passport .passport-score-scale {
                font-size: 5.2pt !important;
            }
            body.learner-page-passport .passport-score-badge {
                font-size: 5.5pt !important;
                padding: 0.5mm 1.5mm !important;
                margin: 0.8mm 0 !important;
            }
            body.learner-page-passport .passport-score-hint {
                font-size: 5.2pt !important;
                line-height: 1.2 !important;
            }
            body.learner-page-passport .passport-test-compact-item {
                padding: 1.2mm 1.8mm !important;
                border-radius: 1.5mm !important;
                gap: 0.8mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-test-compact-item .test-name {
                font-size: 5.8pt !important;
            }
            body.learner-page-passport .passport-test-compact-item .test-code {
                font-size: 5.2pt !important;
                padding: 0.3mm 1mm !important;
            }
            body.learner-page-passport .passport-test-compact-item .test-summary,
            body.learner-page-passport .passport-test-compact-item .test-dim {
                font-size: 5.2pt !important;
                line-height: 1.2 !important;
            }
            body.learner-page-passport .skills-subgroup-title {
                font-size: 5.5pt !important;
                margin-bottom: 0.8mm !important;
            }
            body.learner-page-passport .passport-skill-row {
                margin-bottom: 1mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-skill-row .skill-meta {
                font-size: 5.6pt !important;
            }
            body.learner-page-passport .passport-skill-row .skill-val {
                font-size: 5.4pt !important;
            }
            body.learner-page-passport .passport-skill-row .skill-bar {
                height: 1mm !important;
            }
            body.learner-page-passport .passport-sidebar-qr-box {
                padding: 1.5mm !important;
                border-radius: 1.5mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-qr-cv-img {
                width: 17mm !important;
                height: 17mm !important;
            }
            body.learner-page-passport .passport-qr-cv-badge,
            body.learner-page-passport .passport-qr-cv-caption {
                font-size: 5pt !important;
                line-height: 1.15 !important;
            }
            body.learner-page-passport .passport-cv-fullname {
                font-size: 13pt !important;
                margin-bottom: 0.8mm !important;
            }
            body.learner-page-passport .passport-cv-target-headline {
                font-size: 7.2pt !important;
                margin-bottom: 1.2mm !important;
            }
            body.learner-page-passport .passport-summary-box {
                padding: 1.5mm 2.5mm !important;
                border-radius: 0 1.5mm 1.5mm 0 !important;
                margin-bottom: 1mm !important;
            }
            body.learner-page-passport .passport-summary-box p {
                font-size: 6pt !important;
                line-height: 1.3 !important;
            }
            body.learner-page-passport .passport-main-section-title {
                font-size: 6.8pt !important;
                margin-bottom: 1.5mm !important;
                padding-bottom: 0.8mm !important;
                gap: 1.2mm !important;
            }
            body.learner-page-passport .passport-main-section-title svg {
                width: 3.2mm !important;
                height: 3.2mm !important;
            }
            body.learner-page-passport .passport-cv-project-card {
                padding: 1.5mm 2mm !important;
                margin-bottom: 1.2mm !important;
                border-radius: 1.5mm !important;
                gap: 0.8mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-cv-project-card .proj-title {
                font-size: 6pt !important;
            }
            body.learner-page-passport .passport-cv-project-card .proj-sub,
            body.learner-page-passport .passport-cv-project-card .proj-desc,
            body.learner-page-passport .passport-cv-project-card .proj-sponsor-tag {
                font-size: 5.4pt !important;
                line-height: 1.25 !important;
            }
            body.learner-page-passport .passport-cv-project-card .proj-status-badge {
                font-size: 5.2pt !important;
                padding: 0.3mm 1.5mm !important;
            }
            body.learner-page-passport .passport-activities-grid {
                gap: 1.2mm !important;
            }
            body.learner-page-passport .passport-activity-card {
                padding: 1.2mm 1.8mm !important;
                border-radius: 1.5mm !important;
                gap: 0.6mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-activity-card .act-title {
                font-size: 5.8pt !important;
            }
            body.learner-page-passport .passport-activity-card .act-category-pill,
            body.learner-page-passport .passport-activity-card .act-meta {
                font-size: 5.2pt !important;
                line-height: 1.2 !important;
            }
            body.learner-page-passport .passport-cv-cert-item {
                padding: 1.2mm 1.8mm !important;
                border-radius: 1.5mm !important;
                gap: 1.5mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-cv-cert-item .cert-name {
                font-size: 5.8pt !important;
            }
            body.learner-page-passport .passport-cv-cert-item .cert-meta {
                font-size: 5.2pt !important;
            }
            body.learner-page-passport .passport-cv-cert-item .cert-status-badge {
                font-size: 5pt !important;
                padding: 0.3mm 1.2mm !important;
            }
            body.learner-page-passport .passport-badge-cv-item {
                padding: 1mm 1.5mm !important;
                border-radius: 1.5mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-badge-cv-item .badge-name {
                font-size: 5.6pt !important;
            }
            body.learner-page-passport .passport-badge-cv-item .badge-desc {
                font-size: 5pt !important;
                line-height: 1.15 !important;
            }
            body.learner-page-passport .passport-endorsement-box {
                padding: 1.5mm 2.2mm !important;
                border-radius: 0 1.5mm 1.5mm 0 !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            body.learner-page-passport .passport-endorsement-text {
                font-size: 5.6pt !important;
                line-height: 1.25 !important;
                margin-bottom: 1mm !important;
            }
            body.learner-page-passport .passport-endorsement-signer,
            body.learner-page-passport .passport-verification-seal {
                font-size: 5.4pt !important;
            }
            body.learner-page-passport .passport-footer {
                padding: 1.5mm 5mm !important;
                font-size: 5.2pt !important;
                gap: 2mm !important;
            }
        }
    </style>
</head>
<body class="learner-app learner-page-passport">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <div class="passport-wrapper">

                    <!-- Top Action Toolbar (Giao diện gọn gàng, không có các ô để tick) -->
                    <div class="passport-top-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                        <a class="learner-btn learner-btn--outline" href="profile.php" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; text-decoration: none;">
                            <?= learner_icon('arrow-left', 16); ?> Quay lại Hồ sơ năng lực
                        </a>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <button class="learner-btn learner-btn--outline" id="btn-copy-passport-link" type="button" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                <?= learner_icon('share', 16); ?> Chia sẻ liên kết
                            </button>
                            <button class="learner-btn learner-btn--primary" id="btn-print-passport" type="button" style="background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%); color: #FFFFFF; font-weight: 800; display: inline-flex; align-items: center; gap: 0.55rem; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.25);">
                                <?= learner_icon('printer', 18); ?> In / Xuất File PDF
                            </button>
                        </div>
                    </div>

                    <!-- Main Passport / CV Card (Nội dung Hồ sơ chuẩn In PDF) -->
                    <article class="passport-card" id="talent-passport-card">

                        <!-- Header Banner -->
                        <header class="passport-header-banner">
                            <div class="passport-header-title">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                                <span>DIGITAL TALENT PASSPORT 360° • HỆ SINH THÁI TALENTHUB</span>
                            </div>
                            <div>
                                <span class="passport-badge-code">LIÊN KẾT XÁC THỰC CÓ THỂ THU HỒI</span>
                            </div>
                        </header>

                        <!-- 2-Column CV Main Body -->
                        <div class="passport-cv-body">

                            <!-- ================= LEFT SIDEBAR (~34%) ================= -->
                            <aside class="passport-cv-sidebar" id="sec-cv-sidebar">

                                <!-- 1. Profile Picture & Basic ID -->
                                <div class="passport-sidebar-profile">
                                    <div class="passport-cv-avatar" aria-hidden="true">
                                        <?php if (!empty($studentAvatarUrl)): ?>
                                            <img src="<?= learner_escape($studentAvatarUrl); ?>" alt="<?= learner_escape($studentName); ?>">
                                        <?php else: ?>
                                            <?= learner_escape($studentInitials); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="passport-sidebar-status">
                                        <span class="passport-verified-pill">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                            Sinh viên Xác thực TalentHub
                                        </span>
                                    </div>
                                </div>

                                <!-- 2. Contact Information -->
                                <div class="passport-sidebar-section">
                                    <h4 class="passport-sidebar-title">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        Thông tin liên hệ
                                    </h4>
                                    <div class="passport-sidebar-contact-list">
                                        <div class="passport-sidebar-contact-item">
                                            <span class="contact-icon"><?= learner_icon('mail', 13); ?></span>
                                            <span class="contact-text"><?= $studentEmail !== '' ? learner_escape($studentEmail) : 'Email: Chưa cập nhật'; ?></span>
                                        </div>
                                        <div class="passport-sidebar-contact-item">
                                            <span class="contact-icon"><?= learner_icon('phone', 13); ?></span>
                                            <span class="contact-text"><?= $studentPhone !== '' ? learner_escape($studentPhone) : 'SĐT: Chưa cập nhật'; ?></span>
                                        </div>
                                        <div class="passport-sidebar-contact-item">
                                            <span class="contact-icon"><?= learner_icon('map-pin', 13); ?></span>
                                            <span class="contact-text"><?= $studentLocation !== '' ? learner_escape($studentLocation) : 'Việt Nam'; ?></span>
                                        </div>
                                        <div class="passport-sidebar-contact-item">
                                            <span class="contact-icon"><?= learner_icon('users', 13); ?></span>
                                            <span class="contact-text"><?= learner_escape($studentSchool); ?> • <?= learner_escape($studentClass); ?></span>
                                        </div>
                                        <div class="passport-sidebar-contact-item">
                                            <span class="contact-icon"><?= learner_icon('calendar', 13); ?></span>
                                            <span class="contact-text"><strong><?= (int) ($student['experience_hours'] ?? ($talentPassport['experience']['confirmed_hours'] ?? 0)); ?> giờ</strong> trải nghiệm</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Overall Score Card -->
                                <div class="passport-sidebar-section" id="sec-overall">
                                    <h4 class="passport-sidebar-title">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                                        1. Đánh giá Năng lực Tổng hợp
                                    </h4>
                                    <?php if (!$hasOverallScore): ?>
                                        <p class="passport-empty-state-sm">Chưa có điểm đánh giá tổng hợp.</p>
                                    <?php else: ?>
                                        <div class="passport-score-card">
                                            <div class="passport-score-main">
                                                <span class="passport-score-number"><?= (int)$overallScore; ?></span>
                                                <span class="passport-score-scale">THANG ĐIỂM 100</span>
                                            </div>
                                            <div class="passport-score-badge"><?= learner_escape($gradeClassification); ?> • <?= learner_escape($rankingPercentile); ?></div>
                                            <p class="passport-score-hint">Điểm và xếp loại lấy từ đánh giá đã được ghi nhận trong tài khoản TalentHub.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- 4. 4 Psychometric Tests -->
                                <div class="passport-sidebar-section" id="sec-tests">
                                    <h4 class="passport-sidebar-title">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 19 21 12 17 5 21 12 2"/></svg>
                                        2. Hồ sơ 4 Bài Đánh giá Năng khiếu &amp; Hành vi
                                    </h4>
                                    <div class="passport-tests-compact-list">
                                        <?php if (empty($assessmentCards)): ?>
                                            <p class="passport-empty-state-sm">Chưa có kết quả bài đánh giá.</p>
                                        <?php else: ?>
                                            <?php foreach ($assessmentCards as $index => $ac): ?>
                                                <div class="passport-test-compact-item">
                                                    <div class="test-head">
                                                        <span class="test-name"><?= $index + 1; ?>. <?= learner_escape($ac['name']); ?></span>
                                                        <?php if (!empty($ac['code'])): ?>
                                                            <span class="test-code"><?= learner_escape($ac['code']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($ac['summary'])): ?>
                                                        <div class="test-summary"><?= learner_escape($ac['summary']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($ac['dimensions'])): ?>
                                                        <div class="test-dim"><?= learner_escape(implode(' • ', array_slice($ac['dimensions'], 0, 4))); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- 5. Core Skills -->
                                <div class="passport-sidebar-section" id="sec-skills">
                                    <h4 class="passport-sidebar-title">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        3. Kỹ năng Chuyên môn &amp; Kỹ năng Mềm đã Thẩm định
                                    </h4>

                                    <?php if (empty($displaySkills)): ?>
                                        <p class="passport-empty-state-sm">Chưa có kỹ năng nào được xác thực.</p>
                                    <?php else: ?>
                                        <?php if (!empty($technicalSkills)): ?>
                                            <div class="skills-subgroup-title">Kỹ năng Chuyên môn</div>
                                            <div class="passport-skills-compact">
                                                <?php foreach ($technicalSkills as $sk): ?>
                                                    <div class="passport-skill-row">
                                                        <div class="skill-meta">
                                                            <span><?= learner_escape($sk['name']); ?></span>
                                                            <span class="skill-val"><?= (int)$sk['score']; ?>/100</span>
                                                        </div>
                                                        <div class="skill-bar">
                                                            <span style="width: <?= (int)$sk['score']; ?>%;"></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($softSkills)): ?>
                                            <div class="skills-subgroup-title" style="margin-top: 0.6rem;">Kỹ năng Mềm</div>
                                            <div class="passport-skills-compact">
                                                <?php foreach ($softSkills as $sk): ?>
                                                    <div class="passport-skill-row">
                                                        <div class="skill-meta">
                                                            <span><?= learner_escape($sk['name']); ?></span>
                                                            <span class="skill-val" style="color: #059669;"><?= (int)$sk['score']; ?>/100</span>
                                                        </div>
                                                        <div class="skill-bar">
                                                            <span style="width: <?= (int)$sk['score']; ?>%; background: #10B981;"></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- 6. QR Code Verification -->
                                <div class="passport-sidebar-section passport-sidebar-qr-box">
                                    <div class="passport-qr-cv-img" id="passport-verification-qr" role="img" aria-label="Mã QR xác thực Talent Passport"></div>
                                    <span class="passport-qr-cv-badge" id="passport-qr-status">TẠO KHI XUẤT FILE</span>
                                    <span class="passport-qr-cv-caption">Quét để xem hồ sơ đã đồng ý chia sẻ</span>
                                </div>

                            </aside>

                            <!-- ================= RIGHT MAIN CONTENT (~66%) ================= -->
                            <main class="passport-cv-main" id="sec-cv-main">

                                <!-- Personal Header & Professional Summary -->
                                <header class="passport-main-header" id="sec-identity">
                                    <h1 class="passport-cv-fullname"><?= learner_escape($studentName); ?></h1>
                                    <?php if ($studentHeadline !== ''): ?>
                                        <div class="passport-cv-target-headline"><?= learner_escape($studentHeadline); ?></div>
                                    <?php endif; ?>
                                    <div class="passport-summary-box">
                                        <p><?= learner_escape($professionalSummary); ?></p>
                                    </div>
                                </header>

                                <!-- Section: Projects & Innovation Initiatives -->
                                <section class="passport-main-section" id="sec-projects">
                                    <h3 class="passport-main-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                        4. Đề án Đổi mới Sáng tạo &amp; Doanh nghiệp Bảo trợ
                                    </h3>
                                    <?php if (empty($displayProjects)): ?>
                                        <p class="passport-empty-state">Chưa có đề án nào được ghi nhận trong hệ thống.</p>
                                    <?php else: ?>
                                        <div class="passport-projects-list">
                                            <?php foreach ($displayProjects as $p): ?>
                                                <article class="passport-cv-project-card">
                                                    <div class="proj-header">
                                                        <div>
                                                            <h4 class="proj-title"><?= learner_escape($p['name']); ?></h4>
                                                            <div class="proj-sub">
                                                                Vai trò: <strong><?= learner_escape($p['role']); ?></strong> • Lĩnh vực: <?= learner_escape($p['category']); ?>
                                                            </div>
                                                        </div>
                                                        <span class="proj-status-badge"><?= learner_escape($p['status']); ?></span>
                                                    </div>
                                                    <?php if (!empty($p['desc'])): ?>
                                                        <p class="proj-desc"><?= learner_escape($p['desc']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($p['sponsor_name'])): ?>
                                                        <div class="proj-sponsor-tag">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                            Doanh nghiệp bảo trợ: <strong><?= learner_escape($p['sponsor_name']); ?></strong>
                                                        </div>
                                                    <?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <!-- Section: Practical & Extracurricular Activities -->
                                <?php if (!empty($displayActivities)): ?>
                                <section class="passport-main-section" id="sec-activities">
                                    <h3 class="passport-main-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        Hoạt động Trải nghiệm &amp; Ngoại khóa Thực tế
                                    </h3>
                                    <div class="passport-activities-grid">
                                        <?php foreach ($displayActivities as $act): ?>
                                            <div class="passport-activity-card">
                                                <div class="act-head">
                                                    <strong class="act-title"><?= learner_escape($act['title']); ?></strong>
                                                    <span class="act-category-pill"><?= learner_escape($act['category']); ?></span>
                                                </div>
                                                <div class="act-meta">
                                                    <span><?= learner_icon('map-pin', 12); ?> <?= learner_escape($act['location']); ?></span>
                                                    <span><?= learner_icon('clock', 12); ?> <?= learner_escape($act['time']); ?></span>
                                                    <?php if ($act['hours'] > 0): ?>
                                                        <span class="act-hours-pill"><?= (float)$act['hours']; ?> giờ xác nhận</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                                <?php endif; ?>

                                <!-- Section: Certifications & Honorary Badges -->
                                <section class="passport-main-section" id="sec-certificates">
                                    <h3 class="passport-main-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                                        5. Chứng chỉ &amp; Huy hiệu Đã Xác thực
                                    </h3>

                                    <!-- Certificates -->
                                    <?php if (!empty($displayCertificates)): ?>
                                        <div class="passport-certs-grid">
                                            <?php foreach ($displayCertificates as $c): ?>
                                                <div class="passport-cv-cert-item">
                                                    <span class="cert-icon">🎓</span>
                                                    <div class="cert-details">
                                                        <strong class="cert-name"><?= learner_escape($c['name']); ?></strong>
                                                        <span class="cert-meta">
                                                            Đơn vị cấp: <strong><?= learner_escape($c['issuer']); ?></strong> • Năm <?= learner_escape($c['year']); ?>
                                                            <?php if (!empty($c['credential_id'])): ?>
                                                                • Mã tra cứu: <code class="cert-code"><?= learner_escape($c['credential_id']); ?></code>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <span class="cert-status-badge">ĐÃ XÁC THỰC</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Badges -->
                                    <?php if (!empty($displayBadges)): ?>
                                        <div class="passport-badges-inline-list" style="margin-top: 0.65rem;">
                                            <?php foreach ($displayBadges as $b): ?>
                                                <div class="passport-badge-cv-item">
                                                    <span class="badge-icon">🎖️</span>
                                                    <div>
                                                        <strong class="badge-name"><?= learner_escape($b['name']); ?></strong>
                                                        <?php if (!empty($b['description'])): ?>
                                                            <span class="badge-desc"><?= learner_escape($b['description']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (empty($displayCertificates) && empty($displayBadges)): ?>
                                        <p class="passport-empty-state">Chưa có chứng chỉ hoặc huy hiệu được ghi nhận.</p>
                                    <?php endif; ?>
                                </section>

                                <!-- Section: Teacher Endorsement -->
                                <section class="passport-main-section" id="sec-endorsement">
                                    <h3 class="passport-main-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        6. Nhận xét Chứng thực của Giảng viên Hướng dẫn
                                    </h3>
                                    <?php if ($evalComment === ''): ?>
                                        <p class="passport-empty-state">Chưa có nhận xét từ giảng viên được ghi nhận.</p>
                                    <?php else: ?>
                                        <div class="passport-endorsement-box">
                                            <p class="passport-endorsement-text">
                                                "<?= learner_escape($evalComment); ?>"
                                            </p>
                                            <div class="passport-endorsement-signer">
                                                <div class="signer-info">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                    <span><strong><?= learner_escape($evalReviewer); ?></strong> — <?= learner_escape($evalOrg); ?></span>
                                                </div>
                                                <span class="passport-verification-seal">ĐÃ GHI NHẬN</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </section>

                            </main>

                        </div>

                        <!-- Footer -->
                        <footer class="passport-footer">
                            <div>
                                <span>Được xuất từ <strong>Hệ sinh thái TalentHub &amp; <?= learner_escape($studentSchool); ?></strong></span>
                            </div>
                            <div>
                                <span>Thời gian xuất: <strong><?= date('d/m/Y H:i'); ?></strong> (QR hiệu lực 30 ngày)</span>
                            </div>
                        </footer>

                    </article>

                </div>
            </main>
        </div>
    </div>

    <script src="../../assets/vendor/qrcodejs/qrcode.min.js"></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const printBtn = document.getElementById('btn-print-passport');
            const copyBtn = document.getElementById('btn-copy-passport-link');
            const qrContainer = document.getElementById('passport-verification-qr');
            const qrStatus = document.getElementById('passport-qr-status');
            const passportCard = document.getElementById('talent-passport-card');
            let verificationUrl = '';
            let sharePromise = null;

            const prepareSinglePagePrint = () => {
                document.body.classList.add('passport-print-preparing');
            };

            const resetSinglePagePrint = () => {
                document.body.classList.remove('passport-print-preparing');
            };

            const fullShareFields = [
                'fullName', 'headline', 'bio', 'location', 'school', 'class',
                'email', 'phone', 'skills', 'experience', 'projects', 'certificates'
            ];

            const ensureVerificationQr = async () => {
                if (verificationUrl) return verificationUrl;
                if (sharePromise) return sharePromise;

                sharePromise = (async () => {
                    const bootNode = document.getElementById('learner-session-boot');
                    const boot = JSON.parse(bootNode?.textContent || '{}');
                    const client = window.TalentHubLearnerApi?.createLearnerApiClient?.({
                        baseUrl: boot.apiBase || '/app/learner/api/v1',
                        csrfToken: boot.csrfToken || '',
                    });
                    if (!client || typeof window.QRCode !== 'function') {
                        throw new Error('Không thể khởi tạo QR xác thực.');
                    }

                    const result = await client.send('POST', '/profile-shares.php', {
                        sharedFields: fullShareFields,
                        expiresInDays: 30,
                    });
                    const shareUrl = String(result?.share?.shareUrl || '');
                    if (!shareUrl) throw new Error('Máy chủ không trả về liên kết xác thực.');

                    verificationUrl = new URL(shareUrl, window.location.origin).toString();
                    if (qrContainer) {
                        qrContainer.replaceChildren();
                        new window.QRCode(qrContainer, {
                            text: verificationUrl,
                            width: 200,
                            height: 200,
                            colorDark: '#0F172A',
                            colorLight: '#FFFFFF',
                            correctLevel: window.QRCode.CorrectLevel.M,
                        });
                    }
                    if (qrStatus) {
                        qrStatus.textContent = 'HIỆU LỰC 30 NGÀY';
                    }
                    return verificationUrl;
                })();

                try {
                    return await sharePromise;
                } finally {
                    sharePromise = null;
                }
            };

            window.addEventListener('beforeprint', prepareSinglePagePrint);
            window.addEventListener('afterprint', resetSinglePagePrint);

            printBtn?.addEventListener('click', async () => {
                printBtn.disabled = true;
                try {
                    await ensureVerificationQr();
                    await document.fonts?.ready;
                    prepareSinglePagePrint();
                    window.print();
                } catch (error) {
                    resetSinglePagePrint();
                    alert(error?.message || 'Không thể tạo QR xác thực.');
                } finally {
                    printBtn.disabled = false;
                }
            });

            copyBtn?.addEventListener('click', async () => {
                copyBtn.disabled = true;
                try {
                    const url = await ensureVerificationQr();
                    await navigator.clipboard.writeText(url);
                    alert('Đã sao chép liên kết xác thực Talent Passport!');
                } catch (error) {
                    alert(error?.message || 'Không thể tạo liên kết xác thực.');
                } finally {
                    copyBtn.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
