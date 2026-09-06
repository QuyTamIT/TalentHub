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

// 5. Danh sách Dự án đã Bảo trợ & Tham gia
$rawProjects = !empty($talentPassport['projects']) ? $talentPassport['projects'] : (function_exists('learner_projects') ? learner_projects() : []);
$displayProjects = [];
if (!empty($rawProjects)) {
    foreach ($rawProjects as $p) {
        $sponsors = $p['sponsorships'] ?? [];
        $sponsorName = !empty($p['sponsor_name']) ? (string) $p['sponsor_name'] : (!empty($sponsors[0]['enterprise_name']) ? (string) $sponsors[0]['enterprise_name'] : '');
        $raisedAmount = (float) ($p['raised_amount'] ?? $p['raisedAmount'] ?? 0);
        $fundingGoal = (float) ($p['funding_goal'] ?? $p['fundingGoal'] ?? 0);
        $displayProjects[] = [
            'name' => (string) ($p['name'] ?? $p['title'] ?? 'Dự án nghiên cứu'),
            'role' => (string) ($p['role'] ?? 'Trưởng nhóm kỹ thuật'),
            'category' => (string) ($p['category_label'] ?? $p['category'] ?? 'Công nghệ & AI'),
            'status' => (string) ($p['status_label'] ?? 'Đang triển khai'),
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

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Talent Passport 360° - Hộ chiếu Năng lực Số của <?= learner_escape($studentName); ?> được chứng thực bởi <?= learner_escape($studentSchool); ?>.">
    <title>Talent Passport 360° | <?= learner_escape($studentName); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <style>
        /* ==========================================================================
           TALENT PASSPORT 360 - MODERN CV & CLEAN PRINT FORMAT
           ========================================================================== */
        .passport-wrapper {
            max-width: 1040px;
            margin: 0 auto;
            padding-bottom: 4rem;
        }

        /* Thanh điều khiển tùy biến xuất PDF (Chỉ hiển thị trên Web) */
        .passport-customizer-panel {
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
        }
        .passport-customizer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #E2E8F0;
        }
        .passport-customizer-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1rem;
            font-weight: 800;
            color: #0F172A;
        }
        .passport-presets-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .passport-preset-btn {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            color: #334155;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .passport-preset-btn:hover {
            background: #E2E8F0;
            color: #0F172A;
        }
        .passport-preset-btn.is-active {
            background: #2563EB;
            color: #FFFFFF;
            border-color: #1D4ED8;
        }

        .passport-checkboxes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 0.75rem;
        }
        .passport-checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 0.65rem 0.85rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .passport-checkbox-item:hover {
            border-color: #93C5FD;
            background: #EFF6FF;
        }
        .passport-checkbox-item input[type="checkbox"] {
            margin-top: 0.2rem;
            width: 16px;
            height: 16px;
            accent-color: #2563EB;
            cursor: pointer;
        }
        .passport-checkbox-item__label {
            font-size: 0.825rem;
            font-weight: 700;
            color: #1E293B;
            display: block;
        }
        .passport-checkbox-item__hint {
            font-size: 0.725rem;
            color: #64748B;
            display: block;
            margin-top: 0.15rem;
        }

        /* Action bar */
        .passport-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid #E2E8F0;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        /* Main Passport / CV Card */
        .passport-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #CBD5E1;
            overflow: hidden;
            position: relative;
        }

        /* Banner dải trên */
        .passport-header-banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 60%, #1D4ED8 100%);
            color: #FFFFFF;
            padding: 1.25rem 2.25rem;
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
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .passport-badge-code {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 0.3rem 0.8rem;
            border-radius: 9999px;
            font-family: monospace;
            font-size: 0.85rem;
            font-weight: 700;
            color: #FFFFFF;
        }

        /* ==========================================================================
           CV HEADER (Avatar & Thông tin bên trái - Mã QR góc trên bên phải)
           ========================================================================== */
        .passport-cv-header {
            background: #FFFFFF;
            border-bottom: 2px solid #F1F5F9;
            padding: 2rem 2.25rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .passport-cv-identity {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex: 1;
            min-width: 320px;
        }
        .passport-cv-avatar {
            width: 95px;
            height: 95px;
            border-radius: 16px;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #FFFFFF;
            font-size: 2.25rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 3px solid #EFF6FF;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.18);
        }
        .passport-cv-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .passport-cv-details {
            flex: 1;
        }
        .passport-cv-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.25rem 0;
            line-height: 1.25;
        }
        .passport-cv-headline {
            font-size: 0.925rem;
            font-weight: 600;
            color: #2563EB;
            margin: 0 0 0.75rem 0;
        }
        .passport-cv-contact-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.25rem;
            font-size: 0.8125rem;
            color: #475569;
        }
        .passport-cv-contact-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Khung QR Code góc trên bên phải */
        .passport-qr-cv-card {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-radius: 12px;
            padding: 0.75rem 0.95rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex-shrink: 0;
            min-width: 140px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }
        .passport-qr-cv-img {
            width: 100px;
            height: 100px;
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
            font-size: 0.6875rem;
            font-weight: 800;
            color: #1E40AF;
            background: #DBEAFE;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .passport-qr-cv-caption {
            font-size: 0.6875rem;
            color: #64748B;
            font-weight: 700;
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ==========================================================================
           BODY SECTIONS
           ========================================================================== */
        .passport-content-body {
            padding: 2rem 2.25rem;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }
        .passport-empty-state {
            margin: 0;
            padding: 0.85rem 1.1rem;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 8px;
            color: #64748B;
            font-size: 0.8rem;
        }
        .passport-section-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #1E293B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 0.85rem 0;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 0.45rem;
        }

        /* 1. Điểm tổng hợp Hero */
        .passport-score-hero {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 1.25rem;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 12px;
            padding: 1.1rem 1.25rem;
            align-items: center;
        }
        .passport-score-big {
            font-size: 2.75rem;
            font-weight: 900;
            color: #15803D;
            line-height: 1;
        }
        .passport-score-badge {
            display: inline-block;
            background: #16A34A;
            color: #FFFFFF;
            font-size: 0.8125rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            margin-bottom: 0.35rem;
        }

        /* 2. 4 Bài Test Grid */
        .passport-tests-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
        }
        .passport-test-card {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 0.95rem 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .passport-test-card__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .passport-test-card__head strong {
            font-size: 0.875rem;
            color: #0F172A;
            font-weight: 800;
        }
        .passport-test-card__code {
            font-size: 0.8rem;
            font-weight: 800;
            padding: 0.15rem 0.55rem;
            border-radius: 4px;
            color: #FFFFFF;
        }
        .passport-test-card__desc {
            font-size: 0.775rem;
            color: #334155;
            line-height: 1.45;
            margin: 0;
        }
        .passport-test-card__insights {
            font-size: 0.725rem;
            color: #64748B;
            background: #FFFFFF;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            border: 1px solid #E2E8F0;
            margin-top: 0.25rem;
            line-height: 1.35;
        }

        /* 3. Kỹ năng đã xác minh */
        .passport-skills-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .passport-skill-item {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 0.75rem 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .passport-skill-item__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.825rem;
            font-weight: 800;
            color: #0F172A;
        }
        .passport-skill-item__bar {
            height: 6px;
            background: #E2E8F0;
            border-radius: 999px;
            overflow: hidden;
        }
        .passport-skill-item__bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
        }
        .passport-skill-item__detail {
            font-size: 0.725rem;
            color: #475569;
            line-height: 1.35;
            margin-top: 0.15rem;
        }

        /* 4. Dự án bảo trợ */
        .passport-project-item {
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 0.95rem 1.15rem;
            margin-bottom: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .passport-project-item:last-child {
            margin-bottom: 0;
        }
        .passport-sponsor-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 0.775rem;
            font-weight: 700;
            background: #EEF2FF;
            color: #3730A3;
            border: 1px solid #C7D2FE;
            width: fit-content;
        }

        /* 5. Chứng chỉ */
        .passport-cert-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0.95rem;
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .passport-cert-item:last-child {
            margin-bottom: 0;
        }
        .passport-cert-badge {
            font-size: 0.725rem;
            font-weight: 700;
            color: #15803D;
            background: #DCFCE7;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        /* 6. Nhận xét & Con dấu số */
        .passport-endorsement-box {
            background: #F8FAFC;
            border-left: 4px solid #2563EB;
            padding: 1.15rem 1.35rem;
            border-radius: 0 10px 10px 0;
            border-top: 1px solid #E2E8F0;
            border-right: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
        }
        .passport-endorsement-text {
            font-size: 0.85rem;
            color: #334155;
            line-height: 1.6;
            font-style: italic;
            margin: 0 0 0.75rem 0;
        }
        .passport-endorsement-signer {
            font-size: 0.8125rem;
            color: #0F172A;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .passport-verification-seal {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.725rem;
            font-weight: 800;
            color: #15803D;
            background: #DCFCE7;
            border: 1px solid #86EFAC;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        /* Dynamic Visibility Toggle */
        .passport-section--hidden {
            display: none !important;
        }

        /* ==========================================================================
           CSS PRINT A4 OPTIMIZATION
           ========================================================================== */
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body {
                background: #FFFFFF !important;
                font-size: 10pt !important;
                color: #0F172A !important;
            }
            .learner-layout {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .learner-sidebar,
            .learner-header,
            .passport-customizer-panel,
            .passport-action-bar,
            .learner-nav {
                display: none !important;
            }
            .passport-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .passport-card {
                box-shadow: none !important;
                border: 1px solid #94A3B8 !important;
                border-radius: 6px !important;
            }
            .passport-header-banner {
                padding: 0.9rem 1.5rem !important;
            }
            .passport-cv-header {
                padding: 1.25rem 1.5rem !important;
            }
            .passport-content-body {
                padding: 1.25rem 1.5rem !important;
                gap: 1.25rem !important;
            }
            .passport-section-title,
            .passport-cv-header,
            .passport-test-card,
            .passport-project-item,
            .passport-cert-item,
            .passport-endorsement-box {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .passport-qr-cv-img {
                width: 100px !important;
                height: 100px !important;
            }
        }
        @media (max-width: 768px) {
            .passport-cv-header { flex-direction: column; align-items: stretch; }
            .passport-qr-cv-card { align-self: flex-start; }
            .passport-score-hero { grid-template-columns: 1fr; }
            .passport-tests-grid { grid-template-columns: 1fr; }
            .passport-skills-grid { grid-template-columns: 1fr; }
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

                        <!-- CV Header (Thông tin cá nhân & QR góc trên bên phải) -->
                        <div class="passport-cv-header" id="sec-identity">
                            <div class="passport-cv-identity">
                                <div class="passport-cv-avatar" aria-hidden="true">
                                    <?php if (!empty($studentAvatarUrl)): ?>
                                        <img src="<?= learner_escape($studentAvatarUrl); ?>" alt="<?= learner_escape($studentName); ?>">
                                    <?php else: ?>
                                        <?= learner_escape($studentInitials); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="passport-cv-details">
                                    <h1 class="passport-cv-name"><?= learner_escape($studentName); ?></h1>
                                    <?php if ($studentHeadline !== ''): ?>
                                        <div class="passport-cv-headline"><?= learner_escape($studentHeadline); ?></div>
                                    <?php endif; ?>
                                    <div class="passport-cv-contact-list">
                                        <span class="passport-cv-contact-item">
                                            <?= learner_icon('mail', 14); ?> <?= $studentEmail !== '' ? learner_escape($studentEmail) : 'Email: Chưa cập nhật'; ?>
                                        </span>
                                        <span class="passport-cv-contact-item">
                                            <?= learner_icon('phone', 14); ?> <?= $studentPhone !== '' ? learner_escape($studentPhone) : 'SĐT: Chưa cập nhật'; ?>
                                        </span>
                                        <span class="passport-cv-contact-item">
                                            <?= learner_icon('map-pin', 14); ?> <?= $studentLocation !== '' ? learner_escape($studentLocation) : 'Chưa cập nhật'; ?>
                                        </span>
                                        <span class="passport-cv-contact-item">
                                            <?= learner_icon('users', 14); ?> <?= learner_escape($studentSchool); ?> • <?= learner_escape($studentClass); ?>
                                        </span>
                                        <span class="passport-cv-contact-item">
                                            <?= learner_icon('calendar', 14); ?> <strong><?= (int) ($student['experience_hours'] ?? 0); ?> giờ</strong> trải nghiệm
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- QR Code Box đặt ở góc trên bên phải -->
                            <div class="passport-qr-cv-card">
                                <div class="passport-qr-cv-img" id="passport-verification-qr" role="img" aria-label="Mã QR xác thực Talent Passport"></div>
                                <span class="passport-qr-cv-badge" id="passport-qr-status">TẠO KHI XUẤT FILE</span>
                                <span class="passport-qr-cv-caption">Quét để xem hồ sơ đã đồng ý chia sẻ</span>
                            </div>
                        </div>

                        <!-- Body Content Sections -->
                        <div class="passport-content-body">

                            <!-- Section 1: Điểm Đánh giá Năng lực Tổng hợp -->
                            <div class="passport-section" id="sec-overall">
                                <h3 class="passport-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                                    1. Đánh giá Năng lực Tổng hợp
                                </h3>
                                <?php if (!$hasOverallScore): ?>
                                    <p class="passport-empty-state">Chưa có điểm đánh giá tổng hợp được ghi nhận trong hệ thống.</p>
                                <?php else: ?>
                                <div class="passport-score-hero">
                                    <div>
                                        <div class="passport-score-big"><?= (int)$overallScore; ?></div>
                                        <div style="font-size: 0.75rem; color: #166534; font-weight: 800;">THANG ĐIỂM 100</div>
                                    </div>
                                    <div>
                                        <div class="passport-score-badge"><?= learner_escape($gradeClassification); ?> • <?= learner_escape($rankingPercentile); ?></div>
                                        <p style="font-size: 0.8125rem; color: #166534; margin: 0; line-height: 1.45;">Điểm và xếp loại lấy từ đánh giá đã được ghi nhận trong tài khoản TalentHub.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Section 2: Kết quả 4 Bài Đánh giá Năng lực Chuyên sâu (DISC, MBTI, Holland, MI) -->
                            <div class="passport-section" id="sec-tests">
                                <h3 class="passport-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>
                                    2. Hồ sơ 4 Bài Đánh giá Năng khiếu &amp; Hành vi
                                </h3>
                                <?php if ($assessmentCards === []): ?>
                                    <p class="passport-empty-state">Chưa có kết quả bài đánh giá năng khiếu hoặc hành vi.</p>
                                <?php else: ?>
                                    <div class="passport-tests-grid">
                                        <?php foreach ($assessmentCards as $index => $assessmentCard): ?>
                                            <div class="passport-test-card">
                                                <div class="passport-test-card__head">
                                                    <strong><?= $index + 1; ?>. <?= learner_escape($assessmentCard['name']); ?></strong>
                                                    <?php if ($assessmentCard['code'] !== ''): ?>
                                                        <span class="passport-test-card__code" style="background: #2563EB;"><?= learner_escape($assessmentCard['code']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($assessmentCard['summary'] !== ''): ?>
                                                    <p class="passport-test-card__desc"><?= learner_escape($assessmentCard['summary']); ?></p>
                                                <?php endif; ?>
                                                <?php if ($assessmentCard['dimensions'] !== []): ?>
                                                    <div class="passport-test-card__insights"><?= learner_escape(implode(' • ', $assessmentCard['dimensions'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Section 3: Kỹ năng Chuyên môn đã Xác thực -->
                            <div class="passport-section" id="sec-skills">
                                <h3 class="passport-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    3. Kỹ năng Chuyên môn &amp; Kỹ năng Mềm đã Thẩm định
                                </h3>
                                <div class="passport-skills-grid">
                                    <?php if (empty($displaySkills)): ?>
                                        <p class="passport-empty-state" style="grid-column: 1 / -1;">Chưa có kỹ năng nào được xác thực trong hệ thống.</p>
                                    <?php endif; ?>
                                    <?php foreach ($displaySkills as $sk): ?>
                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span><?= learner_escape($sk['name']); ?></span>
                                                <strong style="color: <?= ($sk['type'] ?? '') === 'soft' ? '#059669' : '#2563EB'; ?>;"><?= (int)$sk['score']; ?>/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: <?= (int)$sk['score']; ?>%; background: <?= ($sk['type'] ?? '') === 'soft' ? '#10B981' : '#2563EB'; ?>;"></span>
                                            </div>
                                            <?php if (!empty($sk['detail'])): ?>
                                                <div class="passport-skill-item__detail">
                                                    <?= learner_escape($sk['detail']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Section 4: Dự án Đổi mới Sáng tạo & Doanh nghiệp Bảo trợ -->
                            <div class="passport-section" id="sec-projects">
                                <h3 class="passport-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                    4. Đề án Đổi mới Sáng tạo &amp; Doanh nghiệp Bảo trợ
                                </h3>

                                <?php if (empty($displayProjects)): ?>
                                    <p class="passport-empty-state">Chưa có đề án nào được ghi nhận trong hệ thống.</p>
                                <?php endif; ?>
                                <?php foreach ($displayProjects as $p): ?>
                                    <article class="passport-project-item">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; flex-wrap: wrap;">
                                            <div>
                                                <strong style="font-size: 0.875rem; color: #0F172A;"><?= learner_escape($p['name']); ?></strong>
                                                <div style="font-size: 0.775rem; color: #64748B; margin-top: 0.15rem;">
                                                    Vai trò: <strong><?= learner_escape($p['role']); ?></strong> • Lĩnh vực: <?= learner_escape($p['category']); ?>
                                                </div>
                                            </div>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #15803D; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                                <?= learner_escape($p['status']); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($p['desc'])): ?>
                                            <p style="font-size: 0.775rem; color: #334155; margin: 0; line-height: 1.45;">
                                                <?= learner_escape($p['desc']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($p['sponsor_name'])): ?>
                                            <div>
                                                <span class="passport-sponsor-badge">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                    Được bảo trợ bởi: <strong><?= learner_escape($p['sponsor_name']); ?></strong> <?= !empty($p['grant']) ? '(' . learner_escape($p['grant']) . ')' : ''; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <!-- Section 5: Chứng chỉ & Huy hiệu Đã cấp -->
                            <div class="passport-section" id="sec-certificates">
                                <h3 class="passport-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                    5. Chứng chỉ &amp; Văn bằng Đã Xác thực
                                </h3>
                                <?php if (empty($displayCertificates)): ?>
                                    <p class="passport-empty-state">Chưa có chứng chỉ nào được xác minh trong hệ thống.</p>
                                <?php endif; ?>
                                <?php foreach ($displayCertificates as $c): ?>
                                    <div class="passport-cert-item">
                                        <span style="color: #2563EB; font-size: 1.2rem; line-height: 1;">🎓</span>
                                        <div style="flex: 1;">
                                            <strong style="font-size: 0.85rem; color: #0F172A; display: block;"><?= learner_escape($c['name']); ?></strong>
                                            <span style="font-size: 0.775rem; color: #64748B;">
                                                Đơn vị cấp: <strong><?= learner_escape($c['issuer']); ?></strong> • Năm <?= learner_escape($c['year']); ?>
                                                <?php if (!empty($c['credential_id'])): ?>
                                                    • Mã tra cứu: <code style="font-family: monospace; font-weight: 700; color: #0F172A;"><?= learner_escape($c['credential_id']); ?></code>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <span class="passport-cert-badge">
                                            Đã xác thực
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Section 6: Nhận xét Chứng thực của Giảng viên & Nhà trường -->
                            <div class="passport-section" id="sec-endorsement">
                                <h3 class="passport-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
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
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                            <span><strong><?= learner_escape($evalReviewer); ?></strong> — <?= learner_escape($evalOrg); ?></span>
                                        </div>
                                        <span class="passport-verification-seal">ĐÃ GHI NHẬN</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- Footer -->
                        <footer style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 1.15rem 2.25rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.775rem; color: #64748B; flex-wrap: wrap; gap: 0.5rem;">
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
            let verificationUrl = '';
            let sharePromise = null;

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

            printBtn?.addEventListener('click', async () => {
                printBtn.disabled = true;
                try {
                    await ensureVerificationQr();
                    window.print();
                } catch (error) {
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
