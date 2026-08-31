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
$studentName = $student['name'] ?? 'Lê Quý Tam';
$studentClass = $student['class'] ?? 'Lớp BTEC-AI-2026A';
$studentSchool = $student['school'] ?? 'Cao đẳng Quốc tế BTEC FPT';
$studentEmail = $student['email'] ?? 'tamlangtu2005@gmail.com';
$studentLocation = $student['location'] ?? 'Cần Thơ';
$passportCode = 'TLH-' . strtoupper(substr(md5($studentId . 'talenthub'), 0, 8)) . '-2026';

// 1. Dữ liệu từ Talent Passport Aggregate & Database
$talentPassport = $GLOBALS['learner_talent_passport'] ?? [];

// 2. Điểm Tổng hợp & Đánh giá của Giảng viên
$overallScore = 88.0;
$gradeClassification = 'Xuất sắc';
$rankingPercentile = 'Top 10% Chuyên ngành AI';
$evalComment = 'Sinh viên thể hiện tư duy logic và năng lực giải thuật xuất sắc; làm chủ các công nghệ Deep Learning, Computer Vision và tích cực tham gia các đề án đổi mới sáng tạo nhận bảo trợ từ doanh nghiệp.';
$evalReviewer = 'ThS. Nguyễn Văn Hùng';
$evalOrg = $studentSchool;

if (!empty($talentPassport['teacher_evaluations'][0])) {
    $firstEval = $talentPassport['teacher_evaluations'][0];
    if (!empty($firstEval['overall_score']) || !empty($firstEval['overallScore'])) {
        $overallScore = (float) ($firstEval['overall_score'] ?? $firstEval['overallScore']);
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
$assessmentsByType = [];
foreach ($rawAssessments as $a) {
    $typeKey = strtolower((string) ($a['test_type'] ?? $a['testType'] ?? $a['test_code'] ?? $a['testCode'] ?? ''));
    if (str_contains($typeKey, 'disc')) {
        $assessmentsByType['disc'] = $a;
    } elseif (str_contains($typeKey, 'mbti')) {
        $assessmentsByType['mbti'] = $a;
    } elseif (str_contains($typeKey, 'holland') || str_contains($typeKey, 'riasec')) {
        $assessmentsByType['holland'] = $a;
    } elseif (str_contains($typeKey, 'multi') || str_contains($typeKey, 'intel') || str_contains($typeKey, 'mi')) {
        $assessmentsByType['mi'] = $a;
    }
}

// DISC Data
$discCode = $assessmentsByType['disc']['result_code'] ?? $assessmentsByType['disc']['resultCode'] ?? 'CD';
$discScores = $assessmentsByType['disc']['dimension_scores'] ?? ['D' => 78, 'I' => 62, 'S' => 68, 'C' => 86];

// MBTI Data
$mbtiCode = $assessmentsByType['mbti']['result_code'] ?? $assessmentsByType['mbti']['resultCode'] ?? 'INTJ';
$mbtiTitle = match ($mbtiCode) {
    'INTJ' => 'Nhà chiến lược (Architect)',
    'INTP' => 'Nhà tư duy (Logician)',
    'ENTJ' => 'Nhà điều hành (Commander)',
    'ENTP' => 'Người nhìn xa (Debater)',
    'INFJ' => 'Người cố vấn (Advocate)',
    'INFP' => 'Người lý tưởng hóa (Mediator)',
    default => 'Nhà phân tích hệ thống',
};

// Holland RIASEC Data
$hollandCode = $assessmentsByType['holland']['result_code'] ?? $assessmentsByType['holland']['resultCode'] ?? 'RIE';
$hollandScores = $assessmentsByType['holland']['dimension_scores'] ?? [
    'R' => 84, 'I' => 92, 'A' => 65, 'S' => 60, 'E' => 75, 'C' => 80
];

// Multiple Intelligences Data
$miScores = $assessmentsByType['mi']['dimension_scores'] ?? [
    'Logic - Toán học' => 92,
    'Không gian & Trực quan' => 85,
    'Ngôn ngữ & Giao tiếp' => 78,
    'Nội tâm & Tự định hướng' => 80,
];

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
        $skCode = strtolower(trim((string) ($sk['code'] ?? $sk['name'] ?? '')));
        $skName = $skillNameMap[$skCode] ?? (string) ($sk['name'] ?? 'Kỹ năng chuyên môn');
        $skScore = max(60, min(100, (int) round((float) ($sk['levelScore'] ?? $sk['score'] ?? $sk['level'] ?? 80))));
        $skCategory = strtolower((string) ($sk['category'] ?? ''));
        $isSoft = in_array($skCategory, ['soft', 'general'], true) || in_array($skCode, ['teamwork', 'communication'], true);
        $displaySkills[] = [
            'name' => $skName,
            'score' => $skScore,
            'type' => $isSoft ? 'soft' : 'technical',
            'verified' => ($sk['verificationStatus'] ?? $sk['verification_status'] ?? 'verified') === 'verified' || !empty($sk['verified']),
        ];
    }
}
if (count($displaySkills) < 4) {
    $displaySkills = [
        ['name' => 'Lập trình Python & PyTorch', 'score' => 90, 'type' => 'technical', 'verified' => true],
        ['name' => 'Học máy & Thị giác máy tính', 'score' => 88, 'type' => 'technical', 'verified' => true],
        ['name' => 'IoT & Hệ thống nhúng ESP32', 'score' => 85, 'type' => 'technical', 'verified' => true],
        ['name' => 'Docker & Quản lý mã nguồn Git', 'score' => 82, 'type' => 'technical', 'verified' => true],
        ['name' => 'Kỹ năng làm việc nhóm & Agile', 'score' => 86, 'type' => 'soft', 'verified' => true],
        ['name' => 'Giao tiếp & Thuyết trình đề án', 'score' => 80, 'type' => 'soft', 'verified' => true],
    ];
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
if (empty($displayProjects)) {
    $displayProjects = [
        [
            'name' => 'Hệ thống Vườn thông minh IoT & AI (Smart Garden ESP32)',
            'role' => 'Trưởng nhóm Kỹ thuật & Thuật toán',
            'category' => 'Internet of Things (IoT) & Machine Learning',
            'status' => 'Đã đạt mục tiêu tài trợ',
            'sponsor_name' => 'FPT Software Cần Thơ',
            'raised_amount' => 50000000,
            'funding_goal' => 50000000,
        ],
        [
            'name' => 'Thùng rác phân loại thông minh bằng AI Camera YOLOv8',
            'role' => 'Kỹ sư Huấn luyện Mô hình Computer Vision',
            'category' => 'Trí tuệ Nhân tạo & Môi trường',
            'status' => 'Đang triển khai',
            'sponsor_name' => 'Vinamilk CSR Foundation',
            'raised_amount' => 30000000,
            'funding_goal' => 30000000,
        ],
    ];
}

// 6. Danh sách Chứng chỉ & Văn bằng
$rawCertificates = !empty($talentPassport['certificates']) ? $talentPassport['certificates'] : ($certificates ?? []);
$displayCertificates = [];
if (!empty($rawCertificates)) {
    foreach ($rawCertificates as $c) {
        $displayCertificates[] = [
            'name' => (string) ($c['name'] ?? $c['title'] ?? 'Chứng chỉ chuyên môn'),
            'issuer' => (string) ($c['issuer'] ?? $c['issuing_organization'] ?? 'Nhà trường & Đối tác'),
            'year' => (string) ($c['year'] ?? $c['issue_date'] ?? '2026'),
            'credential_id' => (string) ($c['credential_id'] ?? $c['credentialId'] ?? ''),
            'verified' => true,
        ];
    }
}
if (empty($displayCertificates)) {
    $displayCertificates = [
        [
            'name' => 'Chứng chỉ Lập trình Trí tuệ Nhân tạo & Deep Learning với PyTorch',
            'issuer' => 'Cao đẳng Quốc tế BTEC FPT & Pearson UK',
            'year' => '2026',
            'credential_id' => 'BTEC-AI-CERT-2026-8892',
            'verified' => true,
        ],
        [
            'name' => 'Huy hiệu Xuất sắc: Kiến trúc Giải thuật & Hệ thống Nhúng IoT',
            'issuer' => 'Trung tâm Đổi mới Sáng tạo BTEC Lab',
            'year' => '2026',
            'credential_id' => 'TLH-BADGE-IOT-2026',
            'verified' => true,
        ],
    ];
}

// 7. QR Code URL (Verification URL)
$verifyUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/app/learner/shared-profile.php?passport=' . urlencode($passportCode);
$qrCodeApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($verifyUrl);
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
           TALENT PASSPORT 360 - PROFESSIONAL FORMAT & CUSTOMIZATION STYLES
           ========================================================================== */
        .passport-wrapper {
            max-width: 1060px;
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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

        /* Main Passport Card */
        .passport-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #CBD5E1;
            overflow: hidden;
            position: relative;
        }
        .passport-header-banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #1D4ED8 100%);
            color: #FFFFFF;
            padding: 2rem 2.5rem;
            position: relative;
        }
        .passport-header-banner::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 35%;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 10%, transparent 20%);
            background-size: 14px 14px;
            opacity: 0.6;
            pointer-events: none;
        }
        .passport-header-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        .passport-header-title h1 {
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #FFFFFF;
        }
        .passport-badge-code {
            display: inline-block;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 0.4rem 0.95rem;
            border-radius: 9999px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #FFFFFF;
        }

        /* Layout Grid */
        .passport-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2.25rem 2.5rem;
        }
        .passport-id-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border-right: 1px solid #F1F5F9;
            padding-right: 2rem;
        }
        .passport-avatar {
            width: 110px;
            height: 110px;
            border-radius: 16px;
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: #FFFFFF;
            font-size: 2.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px rgba(29, 78, 216, 0.25);
            margin-bottom: 1rem;
            border: 4px solid #FFFFFF;
        }
        .passport-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.35rem 0;
        }
        .passport-school-tag {
            font-size: 0.85rem;
            color: #475569;
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .passport-qr-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .passport-qr-img {
            width: 135px;
            height: 135px;
            border-radius: 8px;
            background: #FFFFFF;
        }
        .passport-qr-label {
            font-size: 0.75rem;
            color: #64748B;
            font-weight: 700;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .passport-main-column {
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }
        .passport-section-title {
            font-size: 0.9375rem;
            font-weight: 800;
            color: #1E293B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 0.85rem 0;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 0.5rem;
        }

        /* 1. Điểm tổng hợp Hero */
        .passport-score-hero {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 1.25rem;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 12px;
            padding: 1.15rem 1.25rem;
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

        /* 2. 4 Bài Test Grid & Detailed Cards */
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
            align-items: flex-start;
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
                font-size: 10.5pt !important;
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
                border-radius: 8px !important;
            }
            .passport-header-banner {
                padding: 1.35rem 1.75rem !important;
            }
            .passport-grid {
                padding: 1.35rem 1.75rem !important;
                gap: 1.5rem !important;
            }
            .passport-section-title,
            .passport-test-card,
            .passport-project-item,
            .passport-cert-item,
            .passport-endorsement-box {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }
        @media (max-width: 840px) {
            .passport-grid { grid-template-columns: 1fr; }
            .passport-id-column { border-right: none; border-bottom: 1px solid #F1F5F9; padding-right: 0; padding-bottom: 1.5rem; }
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

                    <!-- Bảng tùy chọn mục xuất file PDF (Interactive Customizer Controls) -->
                    <section class="passport-customizer-panel" aria-label="Tùy chọn xuất file PDF">
                        <div class="passport-customizer-header">
                            <div class="passport-customizer-title">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <span>Tùy chọn Thông tin Xuất File PDF Hồ sơ</span>
                            </div>
                            <div class="passport-presets-group">
                                <span style="font-size: 0.775rem; color: #64748B; font-weight: 600;">Mẫu cấu hình:</span>
                                <button class="passport-preset-btn is-active" type="button" data-preset="all">Hồ sơ 360° Đầy đủ</button>
                                <button class="passport-preset-btn" type="button" data-preset="technical">Chuyên môn &amp; Đề án</button>
                                <button class="passport-preset-btn" type="button" data-preset="aptitude">Năng khiếu &amp; Định hướng</button>
                            </div>
                        </div>

                        <!-- Checkboxes từng nhóm nội dung -->
                        <div class="passport-checkboxes-grid">
                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-identity" data-target-sec="sec-identity" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">🪪 Định danh &amp; Dynamic QR</span>
                                    <span class="passport-checkbox-item__hint">Mã hộ chiếu, thông tin trường lớp &amp; mã quét</span>
                                </div>
                            </label>

                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-overall" data-target-sec="sec-overall" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">🎯 Điểm Năng lực Tổng hợp</span>
                                    <span class="passport-checkbox-item__hint">Thang điểm 100, xếp loại &amp; thứ hạng ngành</span>
                                </div>
                            </label>

                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-tests" data-target-sec="sec-tests" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">🧠 4 Bài Test Năng khiếu</span>
                                    <span class="passport-checkbox-item__hint">DISC, MBTI, Holland RIASEC, Đa trí thông minh</span>
                                </div>
                            </label>

                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-skills" data-target-sec="sec-skills" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">⚡ Kỹ năng đã Xác minh</span>
                                    <span class="passport-checkbox-item__hint">Kỹ thuật &amp; kỹ năng mềm kèm mô tả cấp độ</span>
                                </div>
                            </label>

                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-projects" data-target-sec="sec-projects" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">🚀 Dự án &amp; Doanh nghiệp Bảo trợ</span>
                                    <span class="passport-checkbox-item__hint">Đề án thực tế kèm chứng nhận tài trợ</span>
                                </div>
                            </label>

                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-certificates" data-target-sec="sec-certificates" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">🎓 Chứng chỉ &amp; Văn bằng</span>
                                    <span class="passport-checkbox-item__hint">Chứng chỉ đào tạo trường cấp &amp; quốc tế</span>
                                </div>
                            </label>

                            <label class="passport-checkbox-item">
                                <input type="checkbox" id="chk-endorsement" data-target-sec="sec-endorsement" checked>
                                <div>
                                    <span class="passport-checkbox-item__label">✍️ Nhận xét của Giảng viên</span>
                                    <span class="passport-checkbox-item__hint">Đánh giá chuyên môn &amp; con dấu xác thực số</span>
                                </div>
                            </label>
                        </div>

                        <!-- Action Bar -->
                        <div class="passport-action-bar">
                            <a class="learner-btn learner-btn--outline" href="profile.php" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                <?= learner_icon('arrow-left', 16); ?> Quay lại Hồ sơ năng lực
                            </a>
                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                <button class="learner-btn learner-btn--outline" type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Đã sao chép liên kết Talent Passport trực tuyến!');">
                                    <?= learner_icon('share', 16); ?> Chia sẻ liên kết
                                </button>
                                <button class="learner-btn learner-btn--primary" id="btn-print-passport" type="button" style="background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%); color: #FFFFFF; font-weight: 800; display: inline-flex; align-items: center; gap: 0.55rem; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);">
                                    <?= learner_icon('printer', 18); ?> Xem trước &amp; In / Xuất File PDF
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Main Passport Card (Nội dung Hồ sơ chuẩn In PDF) -->
                    <article class="passport-card" id="talent-passport-card">

                        <!-- Header Banner -->
                        <header class="passport-header-banner">
                            <div class="passport-header-title">
                                <div>
                                    <div style="font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #93C5FD; margin-bottom: 0.35rem;">
                                        DIGITAL TALENT PASSPORT 360° • HỘ CHIẾU NĂNG LỰC SỐ
                                    </div>
                                    <h1>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                                        HỆ SINH THÁI TÀI NĂNG SỐ TALENTHUB
                                    </h1>
                                </div>
                                <div>
                                    <span class="passport-badge-code"><?= learner_escape($passportCode); ?></span>
                                </div>
                            </div>
                        </header>

                        <!-- Body Grid -->
                        <div class="passport-grid">

                            <!-- Left Column: Identity & QR Code (Sec 1: Identity) -->
                            <div class="passport-id-column" id="sec-identity">
                                <div class="passport-avatar" aria-hidden="true">
                                    <?= learner_escape($student['initials'] ?? 'T'); ?>
                                </div>
                                <h2 class="passport-name"><?= learner_escape($studentName); ?></h2>
                                <div class="passport-school-tag">
                                    <strong><?= learner_escape($studentClass); ?></strong><br>
                                    <span><?= learner_escape($studentSchool); ?></span>
                                </div>

                                <div style="font-size: 0.8125rem; color: #64748B; margin-bottom: 1rem; line-height: 1.45; text-align: left; width: 100%; padding: 0.75rem; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0;">
                                    <div><?= learner_icon('mail', 14); ?> <strong>Email:</strong> <?= learner_escape($studentEmail); ?></div>
                                    <div style="margin-top: 0.25rem;"><?= learner_icon('map-pin', 14); ?> <strong>Khu vực:</strong> <?= learner_escape($studentLocation); ?></div>
                                    <div style="margin-top: 0.25rem;"><?= learner_icon('calendar', 14); ?> <strong>Trải nghiệm:</strong> <?= (int) ($student['experience_hours'] ?? 64); ?> giờ xác thực</div>
                                </div>

                                <!-- QR Verification Box -->
                                <div class="passport-qr-box">
                                    <img class="passport-qr-img" src="<?= htmlspecialchars($qrCodeApiUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Mã QR định danh Talent Passport" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($verifyUrl); ?>'">
                                    <span class="passport-qr-label">Quét để xác thực số</span>
                                    <small style="font-size: 0.6875rem; color: #94A3B8; margin-top: 0.25rem;">Xác thực thời gian thực</small>
                                </div>
                            </div>

                            <!-- Right Column: Scores, 4 Tests, Skills, Projects, Credentials, Endorsements -->
                            <div class="passport-main-column">

                                <!-- Section 1: Điểm Đánh giá Năng lực Tổng hợp -->
                                <div class="passport-section" id="sec-overall">
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                                        1. Đánh giá Năng lực Tổng hợp
                                    </h3>
                                    <div class="passport-score-hero">
                                        <div>
                                            <div class="passport-score-big"><?= (int)$overallScore; ?></div>
                                            <div style="font-size: 0.75rem; color: #166534; font-weight: 800;">THANG ĐIỂM 100</div>
                                        </div>
                                        <div>
                                            <div class="passport-score-badge"><?= learner_escape($gradeClassification); ?> • <?= learner_escape($rankingPercentile); ?></div>
                                            <p style="font-size: 0.8125rem; color: #166534; margin: 0; line-height: 1.45;">
                                                Điểm năng lực được tổng hợp và chuẩn hóa dựa trên kết quả 4 bài đánh giá năng khiếu chuyên sâu, kỹ năng kỹ thuật đã được Hội đồng Giảng viên thẩm định và các đề án đổi mới sáng tạo nhận bảo trợ từ doanh nghiệp.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Kết quả 4 Bài Đánh giá Năng lực Chuyên sâu (DISC, MBTI, Holland, MI) -->
                                <div class="passport-section" id="sec-tests">
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>
                                        2. Hồ sơ 4 Bài Đánh giá Năng khiếu &amp; Hành vi
                                    </h3>
                                    <div class="passport-tests-grid">
                                        <!-- Test 1: DISC -->
                                        <div class="passport-test-card">
                                            <div class="passport-test-card__head">
                                                <strong>1. Hành vi DISC</strong>
                                                <span class="passport-test-card__code" style="background: #2563EB;"><?= learner_escape($discCode); ?></span>
                                            </div>
                                            <p class="passport-test-card__desc">
                                                Nhóm <strong>Tuân thủ (C: <?= (int)($discScores['C'] ?? 86); ?>%) &amp; Thống trị (D: <?= (int)($discScores['D'] ?? 78); ?>%)</strong>. Tư duy logic, kỷ luật kỹ thuật cao, định hướng mục tiêu rõ ràng và quyết đoán.
                                            </p>
                                            <div class="passport-test-card__insights">
                                                🔍 <strong>Phong cách làm việc:</strong> Thích hợp môi trường R&amp;D công nghệ, giải quyết bài toán phức tạp đòi hỏi độ chính xác cao và quản lý tiến độ chặt chẽ.
                                            </div>
                                        </div>

                                        <!-- Test 2: MBTI -->
                                        <div class="passport-test-card">
                                            <div class="passport-test-card__head">
                                                <strong>2. Tính cách MBTI</strong>
                                                <span class="passport-test-card__code" style="background: #7C3AED;"><?= learner_escape($mbtiCode); ?></span>
                                            </div>
                                            <p class="passport-test-card__desc">
                                                <strong><?= learner_escape($mbtiTitle); ?></strong>. Khả năng tư duy chiến lược độc lập, phân tích cấu trúc hệ thống và hoạch định tầm nhìn kiến trúc dài hạn.
                                            </p>
                                            <div class="passport-test-card__insights">
                                                💡 <strong>Điểm mạnh cốt lõi:</strong> Tự học vượt trội, khả năng chuyển hóa ý tưởng trừu tượng thành kiến trúc phần mềm thực tế với tính khả thi cao.
                                            </div>
                                        </div>

                                        <!-- Test 3: Holland RIASEC -->
                                        <div class="passport-test-card">
                                            <div class="passport-test-card__head">
                                                <strong>3. Nghề nghiệp Holland</strong>
                                                <span class="passport-test-card__code" style="background: #0891B2;"><?= learner_escape($hollandCode); ?></span>
                                            </div>
                                            <p class="passport-test-card__desc">
                                                Thiên hướng <strong>Nghiên cứu (I: <?= (int)($hollandScores['I'] ?? 92); ?>%) &amp; Kỹ thuật thực hành (R: <?= (int)($hollandScores['R'] ?? 84); ?>%)</strong>. Độ tương thích nghề nghiệp R&amp;D AI &gt; 90%.
                                            </p>
                                            <div class="passport-test-card__insights">
                                                🎯 <strong>Vị trí phù hợp:</strong> Kỹ sư AI &amp; Machine Learning, Kiến trúc sư hệ thống IoT, Chuyên gia phân tích giải thuật dữ liệu lớn.
                                            </div>
                                        </div>

                                        <!-- Test 4: Đa trí thông minh (MI) -->
                                        <div class="passport-test-card">
                                            <div class="passport-test-card__head">
                                                <strong>4. Đa trí thông minh (MI)</strong>
                                                <span class="passport-test-card__code" style="background: #059669;">LOGI 92</span>
                                            </div>
                                            <p class="passport-test-card__desc">
                                                Vượt trội ở <strong>Logic - Toán học (92/100)</strong> và <strong>Không gian trực quan (85/100)</strong>. Nền tảng vững chắc cho mô hình toán và dữ liệu.
                                            </p>
                                            <div class="passport-test-card__insights">
                                                📊 <strong>Ứng dụng thực tế:</strong> Huấn luyện mô hình Thị giác máy tính (Computer Vision), tối ưu hóa pipeline dữ liệu và cấu trúc giải thuật.
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- Section 3: Kỹ năng Chuyên môn đã Xác thực kèm Mô tả Cấp độ -->
                                <div class="passport-section" id="sec-skills">
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        3. Kỹ năng Chuyên môn &amp; Kỹ năng Mềm đã Xác minh
                                    </h3>
                                    <div class="passport-skills-grid">
                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span>Lập trình Python &amp; PyTorch</span>
                                                <strong style="color: #2563EB;">90/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: 90%; background: #2563EB;"></span>
                                            </div>
                                            <div class="passport-skill-item__detail">
                                                Xây dựng và tinh chỉnh mạng nơ-ron sâu (CNN, ResNet), làm chủ pipeline tiền xử lý dữ liệu và đánh giá mô hình.
                                            </div>
                                        </div>

                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span>Học máy &amp; Thị giác máy tính (CV)</span>
                                                <strong style="color: #2563EB;">88/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: 88%; background: #2563EB;"></span>
                                            </div>
                                            <div class="passport-skill-item__detail">
                                                Huấn luyện YOLOv8 phát hiện vật thể thời gian thực, xử lý luồng camera OpenCV và tối ưu hóa suy luận (Inference).
                                            </div>
                                        </div>

                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span>IoT &amp; Hệ thống nhúng ESP32</span>
                                                <strong style="color: #2563EB;">85/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: 85%; background: #2563EB;"></span>
                                            </div>
                                            <div class="passport-skill-item__detail">
                                                Lập trình vi điều khiển, tích hợp cảm biến môi trường, giao thức MQTT kết nối Cloud và điều khiển tự động hóa.
                                            </div>
                                        </div>

                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span>Docker &amp; Quản lý mã nguồn Git</span>
                                                <strong style="color: #2563EB;">82/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: 82%; background: #2563EB;"></span>
                                            </div>
                                            <div class="passport-skill-item__detail">
                                                Đóng gói ứng dụng container, cấu hình môi trường chuẩn hóa, quy trình cộng tác Gitflow nhóm chuyên nghiệp.
                                            </div>
                                        </div>

                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span>Làm việc nhóm Agile &amp; Scrum</span>
                                                <strong style="color: #059669;">86/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: 86%; background: #10B981;"></span>
                                            </div>
                                            <div class="passport-skill-item__detail">
                                                Phối hợp liên môn, quản trị Sprint, báo cáo tiến độ minh bạch và giải quyết xung đột kỹ thuật nhóm hiệu quả.
                                            </div>
                                        </div>

                                        <div class="passport-skill-item">
                                            <div class="passport-skill-item__header">
                                                <span>Giao tiếp &amp; Thuyết trình đề án</span>
                                                <strong style="color: #059669;">80/100</strong>
                                            </div>
                                            <div class="passport-skill-item__bar">
                                                <span style="width: 80%; background: #10B981;"></span>
                                            </div>
                                            <div class="passport-skill-item__detail">
                                                Trình bày báo cáo kỹ thuật rõ ràng, phản biện giải pháp trước Hội đồng Doanh nghiệp và đối tác tài trợ.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 4: Dự án Đổi mới Sáng tạo & Doanh nghiệp Bảo trợ -->
                                <div class="passport-section" id="sec-projects">
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                        4. Đề án Đổi mới Sáng tạo &amp; Doanh nghiệp Bảo trợ
                                    </h3>
                                    <article class="passport-project-item">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; flex-wrap: wrap;">
                                            <div>
                                                <strong style="font-size: 0.875rem; color: #0F172A;">Hệ thống Vườn thông minh IoT &amp; AI (Smart Garden ESP32)</strong>
                                                <div style="font-size: 0.775rem; color: #64748B; margin-top: 0.15rem;">
                                                    Vai trò: <strong>Trưởng nhóm Kỹ thuật &amp; Thuật toán</strong> • Lĩnh vực: Internet of Things (IoT) &amp; Machine Learning
                                                </div>
                                            </div>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #15803D; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                                Đã đạt mục tiêu tài trợ
                                            </span>
                                        </div>
                                        <p style="font-size: 0.775rem; color: #334155; margin: 0; line-height: 1.45;">
                                            Ứng dụng mạng cảm biến IoT và giải thuật dự báo nhu cầu tưới tiêu thông minh bằng AI, tiết kiệm 35% lượng nước tiêu thụ và tự động cảnh báo sâu bệnh qua ứng dụng di động.
                                        </p>
                                        <div>
                                            <span class="passport-sponsor-badge">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                Được bảo trợ bởi: <strong>FPT Software Cần Thơ</strong> (Tài trợ 50.000.000 ₫ &amp; Cố vấn kỹ thuật)
                                            </span>
                                        </div>
                                    </article>

                                    <article class="passport-project-item">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; flex-wrap: wrap;">
                                            <div>
                                                <strong style="font-size: 0.875rem; color: #0F172A;">Thùng rác phân loại thông minh bằng AI Camera YOLOv8</strong>
                                                <div style="font-size: 0.775rem; color: #64748B; margin-top: 0.15rem;">
                                                    Vai trò: <strong>Kỹ sư Huấn luyện Mô hình Computer Vision</strong> • Lĩnh vực: Trí tuệ Nhân tạo &amp; Môi trường
                                                </div>
                                            </div>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #15803D; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                                Đang triển khai
                                            </span>
                                        </div>
                                        <p style="font-size: 0.775rem; color: #334155; margin: 0; line-height: 1.45;">
                                            Nhận diện và phân loại rác thải tái chế/hữu cơ thời gian thực bằng camera AI nhúng với độ chính xác đạt 94.5%, thời gian suy luận dưới 120ms trên phần cứng biên.
                                        </p>
                                        <div>
                                            <span class="passport-sponsor-badge">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                Được bảo trợ bởi: <strong>Vinamilk CSR Foundation</strong> (Tài trợ 30.000.000 ₫)
                                            </span>
                                        </div>
                                    </article>
                                </div>

                                <!-- Section 5: Chứng chỉ & Huy hiệu Đã cấp -->
                                <div class="passport-section" id="sec-certificates">
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                        5. Chứng chỉ &amp; Văn bằng Đã Xác minh
                                    </h3>
                                    <?php foreach ($displayCertificates as $c): ?>
                                        <div class="passport-cert-item">
                                            <span style="color: #2563EB; font-size: 1.25rem; line-height: 1;">🎓</span>
                                            <div style="flex: 1;">
                                                <strong style="font-size: 0.85rem; color: #0F172A; display: block;"><?= learner_escape($c['name']); ?></strong>
                                                <span style="font-size: 0.775rem; color: #64748B;">
                                                    Đơn vị cấp: <strong><?= learner_escape($c['issuer']); ?></strong> • Năm <?= learner_escape($c['year']); ?>
                                                    <?php if (!empty($c['credential_id'])): ?>
                                                        • Mã tra cứu: <code style="font-family: monospace; font-weight: 700; color: #0F172A;"><?= learner_escape($c['credential_id']); ?></code>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #15803D; background: #DCFCE7; padding: 2px 7px; border-radius: 4px;">
                                                ✓ Đã xác minh
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
                                    <div class="passport-endorsement-box">
                                        <p class="passport-endorsement-text">
                                            "<?= learner_escape($evalComment); ?>"
                                        </p>
                                        <div class="passport-endorsement-signer">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                                <span><strong><?= learner_escape($evalReviewer); ?></strong> — <?= learner_escape($evalOrg); ?></span>
                                            </div>
                                            <span class="passport-verification-seal">
                                                ✓ VERIFIED BY TALENTHUB
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Footer -->
                        <footer style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 1.15rem 2.5rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.775rem; color: #64748B; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span>Được chứng thực số bởi <strong>Hệ sinh thái TalentHub &amp; <?= learner_escape($studentSchool); ?></strong></span>
                            </div>
                            <div>
                                <span>Thời gian cấp: <strong><?= date('d/m/Y H:i'); ?></strong> (Hiệu lực vĩnh viễn)</span>
                            </div>
                        </footer>

                    </article>

                </div>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkboxes = document.querySelectorAll('.passport-checkbox-item input[type="checkbox"]');
            const presetButtons = document.querySelectorAll('.passport-preset-btn');
            const printBtn = document.getElementById('btn-print-passport');

            // Cập nhật trạng thái hiển thị của từng phần tử
            const updateSectionVisibility = () => {
                checkboxes.forEach(chk => {
                    const targetId = chk.getAttribute('data-target-sec');
                    if (!targetId) return;
                    const secEl = document.getElementById(targetId);
                    if (secEl) {
                        if (chk.checked) {
                            secEl.classList.remove('passport-section--hidden');
                        } else {
                            secEl.classList.add('passport-section--hidden');
                        }
                    }
                });
            };

            // Lắng nghe sự kiện thay đổi của checkbox
            checkboxes.forEach(chk => {
                chk.addEventListener('change', () => {
                    presetButtons.forEach(btn => btn.classList.remove('is-active'));
                    updateSectionVisibility();
                });
            });

            // Lắng nghe sự kiện chọn Preset nhanh
            presetButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    presetButtons.forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    const presetType = btn.getAttribute('data-preset');

                    if (presetType === 'all') {
                        // Chọn tất cả
                        checkboxes.forEach(chk => chk.checked = true);
                    } else if (presetType === 'technical') {
                        // Chuyên môn & Đề án
                        document.getElementById('chk-identity').checked = true;
                        document.getElementById('chk-overall').checked = true;
                        document.getElementById('chk-tests').checked = false;
                        document.getElementById('chk-skills').checked = true;
                        document.getElementById('chk-projects').checked = true;
                        document.getElementById('chk-certificates').checked = true;
                        document.getElementById('chk-endorsement').checked = true;
                    } else if (presetType === 'aptitude') {
                        // Năng khiếu & Định hướng
                        document.getElementById('chk-identity').checked = true;
                        document.getElementById('chk-overall').checked = true;
                        document.getElementById('chk-tests').checked = true;
                        document.getElementById('chk-skills').checked = false;
                        document.getElementById('chk-projects').checked = false;
                        document.getElementById('chk-certificates').checked = false;
                        document.getElementById('chk-endorsement').checked = true;
                    }

                    updateSectionVisibility();
                });
            });

            // Nút in & xuất PDF
            printBtn?.addEventListener('click', () => {
                window.print();
            });

            // Khởi tạo ban đầu
            updateSectionVisibility();
        });
    </script>
</body>
</html>
