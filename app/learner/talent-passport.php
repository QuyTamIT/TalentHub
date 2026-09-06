<?php
/**
 * TalentHub Learner - Digital Talent Passport (Hộ chiếu Năng lực Số)
 * Hiển thị thẻ định danh, điểm đánh giá năng lực, kỹ năng đã xác thực, huy hiệu và xác nhận của Giảng viên.
 */
declare(strict_types=1);

require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Talent Passport - Hộ chiếu Năng lực Số';
$currentRoute = '/app/learner/talent-passport.php';

$studentId = (string) ($student['id'] ?? learner_current_student_id());
$studentName = $student['name'] ?? 'Lê Quý Tam';
$studentClass = $student['class'] ?? 'Lớp BTEC-AI-2026A';
$studentSchool = $student['school'] ?? 'Cao đẳng Quốc tế BTEC FPT';
$studentEmail = $student['email'] ?? 'tamlangtu2005@gmail.com';
$studentLocation = $student['location'] ?? 'Cần Thơ';
$studentPhone = $student['phone'] ?? '';
$passportCode = 'TLH-' . strtoupper(substr(md5($studentId . 'talenthub'), 0, 8)) . '-2026';

// Evaluation Score & Classification
$overallScore = 85.0;
$gradeClassification = 'Giỏi';
$rankingPercentile = 'Top 15% Chuyên ngành';
$evalComment = 'Lê Quý Tam thể hiện tư duy logic xuất sắc, làm chủ các công nghệ AI & IoT và tích cực tham gia các đề án nghiên cứu thực tế tại phòng Lab.';
$evalReviewer = 'ThS. Nguyễn Văn Hùng';
$evalOrg = $studentSchool;

if (isset($GLOBALS['learner_talent_passport']['teacher_evaluations'][0])) {
    $firstEval = $GLOBALS['learner_talent_passport']['teacher_evaluations'][0];
    if (!empty($firstEval['overall_score'])) {
        $overallScore = (float) $firstEval['overall_score'];
    }
    if (!empty($firstEval['classification'])) {
        $gradeClassification = (string) $firstEval['classification'];
    }
    if (!empty($firstEval['comment'])) {
        $evalComment = (string) $firstEval['comment'];
    }
    if (!empty($firstEval['teacher_name'])) {
        $evalReviewer = (string) $firstEval['teacher_name'];
    }
}

// QR Code URL (Verification URL)
$verifyUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/app/learner/shared-profile.php?token=' . urlencode($passportCode);
$qrCodeApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Talent Passport - Hộ chiếu Năng lực Số của <?= learner_escape($studentName); ?> được chứng thực bởi <?= learner_escape($studentSchool); ?>.">
    <title>Talent Passport | <?= learner_escape($studentName); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <style>
        .passport-wrapper {
            max-width: 960px;
            margin: 0 auto;
            padding-bottom: 3rem;
        }
        .passport-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .passport-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #E2E8F0;
            overflow: hidden;
            position: relative;
        }
        .passport-header-banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #1D4ED8 100%);
            color: #FFFFFF;
            padding: 1.5rem 2rem 1.2rem;
            position: relative;
        }
        .passport-header-banner::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 30%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 10%, transparent 20%);
            background-size: 15px 15px;
            opacity: 0.6;
        }
        .passport-header-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .passport-header-title h1 {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .passport-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.5rem;
            padding: 1.5rem 2rem;
        }
        .passport-id-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border-right: 1px solid #F1F5F9;
            padding-right: 1.5rem;
        }
        .passport-avatar {
            width: 108px;
            height: 108px;
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
            font-size: 1.2rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.3rem 0;
        }
        .passport-school-tag {
            font-size: 0.875rem;
            color: #475569;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .passport-avatar-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .passport-avatar-edit {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 32px;
            height: 32px;
            background: #FFFFFF;
            border: 2px solid #E2E8F0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            color: #475569;
        }
        .passport-avatar-edit:hover {
            background: #EFF6FF;
            border-color: #2563EB;
            color: #2563EB;
        }
        .passport-contact-list {
            text-align: left;
            margin-top: 0.35rem;
        }
        .passport-contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #475569;
            padding: 0.22rem 0;
            line-height: 1.4;
        }
        .passport-contact-item svg {
            flex-shrink: 0;
            color: #94A3B8;
        }
        .passport-qr-cv-box {
            display: flex;
            justify-content: flex-end;
            margin: 0;
        }
        .passport-qr-cv-inner {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 0.6rem 0.75rem;
            display: inline-flex;
            flex-direction: row;
            align-items: center;
            gap: 0.75rem;
        }
        .passport-qr-cv-img {
            width: 84px;
            height: 84px;
            border-radius: 6px;
            background: #FFFFFF;
        }
        .passport-qr-cv-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2rem;
        }
        .passport-qr-cv-label {
            font-size: 0.625rem;
            color: #94A3B8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .passport-qr-cv-code {
            font-family: 'Courier New', monospace;
            font-size: 0.6875rem;
            font-weight: 700;
            color: #0F172A;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .passport-main-column {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .passport-section-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #1E293B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 0.6rem 0;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 0.5rem;
        }
        .passport-score-hero {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 1.1rem;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 12px;
            padding: 1rem 1.15rem;
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
            background: #15803D;
            color: #FFFFFF;
            font-size: 0.8125rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            margin-bottom: 0.35rem;
        }
        .passport-skills-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .passport-skill-pill {
            background: #EFF6FF;
            border: 1px solid #DBEAFE;
            color: #1D4ED8;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.3rem 0.65rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .passport-skills-sidebar {
            width: 100%;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid #E2E8F0;
            text-align: left;
        }
        .passport-skills-sidebar .passport-section-title {
            font-size: 0.8125rem;
            margin-bottom: 0.5rem;
            border-bottom: none;
            padding-bottom: 0;
        }
        .passport-skills-sidebar .passport-section-title svg {
            width: 15px;
            height: 15px;
        }
        .passport-skills-sidebar .passport-skills-pills {
            padding-left: 0.5rem;
        }
        .passport-endorsement-box {
            background: #F8FAFC;
            border-left: 4px solid #2563EB;
            padding: 1rem 1.25rem;
            border-radius: 0 8px 8px 0;
        }
        .passport-endorsement-text {
            font-size: 0.875rem;
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
            align-items: center;
            gap: 0.5rem;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            html, body { margin: 0 !important; padding: 0 !important; background: #FFF !important; }
            body * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
            .learner-layout { display: block !important; margin: 0 !important; padding: 0 !important; }
            .learner-main, .learner-content, #main-content, .passport-wrapper { margin: 0 !important; padding: 0 !important; }
            .learner-sidebar, .learner-header, .passport-action-bar { display: none !important; }

            .passport-card {
                box-shadow: none !important;
                border: 1px solid #CBD5E1 !important;
                border-radius: 0 !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw;
                height: 100vh;
                max-width: 210mm;
                max-height: 297mm;
                margin: 0 !important;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            .passport-header-banner { padding: 1.1rem 1.8rem 0.9rem !important; }
            .passport-header-banner h1 { font-size: 1.15rem !important; }
            .passport-qr-cv-box { padding: 0.45rem 0.6rem !important; }
            .passport-qr-cv-img { width: 62px !important; height: 62px !important; }
            .passport-qr-cv-code { font-size: 0.65rem !important; }

            .passport-grid { grid-template-columns: 220px 1fr !important; gap: 1.1rem !important; padding: 1.1rem 1.8rem !important; flex: 1; align-content: start; }
            .passport-id-column { padding-right: 1.1rem !important; }
            .passport-avatar { width: 92px !important; height: 92px !important; font-size: 2rem !important; margin-bottom: 0.7rem !important; }
            .passport-name { font-size: 1.05rem !important; margin-bottom: 0.2rem !important; }
            .passport-school-tag { margin-bottom: 0.9rem !important; }
            .passport-section-title { font-size: 0.8rem !important; margin-bottom: 0.5rem !important; padding-bottom: 0.35rem !important; }
            .passport-skills-sidebar { margin-top: 0.9rem !important; padding-top: 0.75rem !important; }
            .passport-score-hero { gap: 0.8rem !important; }
            .passport-score-big { font-size: 2rem !important; }

            .passport-avatar-edit, #avatar-file-input { display: none !important; }
        }
        @media (max-width: 768px) {
            .passport-grid { grid-template-columns: 1fr; }
            .passport-id-column { border-right: none; border-bottom: 1px solid #F1F5F9; padding-right: 0; padding-bottom: 1.5rem; }
            .passport-score-hero { grid-template-columns: 1fr; }
            .passport-skills-sidebar { margin-top: 1rem; padding-top: 0.75rem; }
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
                    
                    <!-- Action Bar -->
                    <div class="passport-action-bar">
                        <a class="learner-btn learner-btn--outline" href="profile.php">
                            <?= learner_icon('arrow-left', 16); ?> Quay lại hồ sơ
                        </a>
                        <div style="display: flex; gap: 0.75rem;">
                            <button class="learner-btn learner-btn--outline" type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Đã sao chép liên kết Talent Passport!');">
                                <?= learner_icon('share', 16); ?> Chia sẻ
                            </button>
                            <button class="learner-btn learner-btn--primary" type="button" onclick="window.print();" style="background: #2563EB; color: #FFFFFF;">
                                <?= learner_icon('printer', 16); ?> In / Tải PDF Hộ chiếu
                            </button>
                        </div>
                    </div>

                    <!-- Main Passport Card -->
                    <article class="passport-card" id="talent-passport-card">
                        
                        <!-- Header Banner -->
                        <header class="passport-header-banner">
                            <div class="passport-header-title">
                                <div>
                                    <div style="font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #93C5FD; margin-bottom: 0.35rem;">
                                        DIGITAL TALENT PASSPORT • HỘ CHIẾU NĂNG LỰC SỐ
                                    </div>
                                    <h1>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                                        HỆ SINH THÁI TÀI NĂNG SỐ FTALENTHUB
                                    </h1>
                                </div>
                                <div class="passport-qr-cv-box">
                                    <div class="passport-qr-cv-inner">
                                        <img class="passport-qr-cv-img" src="<?= htmlspecialchars($qrCodeApiUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Mã QR định danh Talent Passport">
                                        <div class="passport-qr-cv-meta">
                                            <span class="passport-qr-cv-label">Quét để xác thực số</span>
                                            <span class="passport-qr-cv-code"><?= learner_escape($passportCode); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </header>

                        <!-- Body Grid -->
                        <div class="passport-grid">
                            
                            <!-- Left Column: Identity & Contact -->
                            <div class="passport-id-column">
                                <div class="passport-avatar-wrapper">
                                    <div class="passport-avatar" id="passport-avatar-el" aria-hidden="true">
                                        <?= learner_escape($student['initials'] ?? 'T'); ?>
                                    </div>
                                    <label class="passport-avatar-edit" for="avatar-file-input" title="Thay đổi ảnh đại diện">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    </label>
                                    <input type="file" id="avatar-file-input" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                                </div>
                                <h2 class="passport-name"><?= learner_escape($studentName); ?></h2>
                                <div class="passport-school-tag">
                                    <strong><?= learner_escape($studentClass); ?></strong><br>
                                    <span><?= learner_escape($studentSchool); ?></span>
                                </div>

                                <div class="passport-contact-list">
                                    <?php if ($studentEmail !== ''): ?>
                                    <div class="passport-contact-item">
                                        <?= learner_icon('mail', 14); ?>
                                        <span><?= learner_escape($studentEmail); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($studentPhone !== ''): ?>
                                    <div class="passport-contact-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                        <span><?= learner_escape($studentPhone); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($studentLocation !== ''): ?>
                                    <div class="passport-contact-item">
                                        <?= learner_icon('map-pin', 14); ?>
                                        <span><?= learner_escape($studentLocation); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Skills (sidebar – fill empty space below) -->
                                <div class="passport-skills-sidebar">
                                    <h3 class="passport-section-title">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        Kỹ năng Chuyên môn đã Xác thực
                                    </h3>
                                    <div class="passport-skills-pills">
                                        <span class="passport-skill-pill">Python</span>
                                        <span class="passport-skill-pill">PyTorch</span>
                                        <span class="passport-skill-pill">Machine Learning</span>
                                        <span class="passport-skill-pill">Computer Vision</span>
                                        <span class="passport-skill-pill">Docker & Git</span>
                                        <span class="passport-skill-pill">IoT & Cảm biến</span>
                                        <span class="passport-skill-pill">Thuyết trình & Làm việc nhóm</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Scores, Endorsements -->
                            <div class="passport-main-column">
                                
                                <!-- Section 1: Điểm Đánh giá Năng lực -->
                                <div>
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                                        Đánh giá Năng lực Tổng hợp
                                    </h3>
                                    <div class="passport-score-hero">
                                        <div>
                                            <div class="passport-score-big"><?= (int)$overallScore; ?></div>
                                            <div style="font-size: 0.75rem; color: #166534; font-weight: 600;">THANG ĐIỂM 100</div>
                                        </div>
                                        <div>
                                            <div class="passport-score-badge"><?= learner_escape($gradeClassification); ?> • <?= learner_escape($rankingPercentile); ?></div>
                                            <p style="font-size: 0.8125rem; color: #166534; margin: 0; line-height: 1.4;">
                                                Điểm năng lực được tổng hợp từ kết quả bài kiểm tra Đa trí thông minh, phân tích chuyên môn và đánh giá xưởng thực hành của Giảng viên.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: 5 Miền Năng lực Trọng tâm -->
                                <div>
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><polygon points="12 2 19 21 12 17 5 21 12 2"></polygon></svg>
                                        Chỉ số 5 Miền Năng khiếu
                                    </h3>
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                                <span>Kỹ thuật & AI</span>
                                                <strong style="color: #2563EB;">85/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 85%; height: 100%; background: #2563EB;"></div>
                                            </div>
                                        </div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                                <span>Logic - Toán học</span>
                                                <strong style="color: #0E7490;">80/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 80%; height: 100%; background: #0891B2;"></div>
                                            </div>
                                        </div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                                <span>Ngoại ngữ & Giao tiếp</span>
                                                <strong style="color: #047857;">75/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 75%; height: 100%; background: #059669;"></div>
                                            </div>
                                        </div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                                <span>Kinh doanh & Quản lý</span>
                                                <strong style="color: #C2410C;">72/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 72%; height: 100%; background: #EA580C;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Lời nhận xét chứng thực của Giảng viên & Nhà trường -->
                                <div>
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                        Nhận xét Chứng thực của Giảng viên
                                    </h3>
                                    <div class="passport-endorsement-box">
                                        <p class="passport-endorsement-text">
                                            "<?= learner_escape($evalComment); ?>"
                                        </p>
                                        <div class="passport-endorsement-signer">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                            <span><strong><?= learner_escape($evalReviewer); ?></strong> — <?= learner_escape($evalOrg); ?></span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Footer -->
                        <footer style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 1rem 2.5rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #64748B; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span>Được chứng thực kỹ thuật số bởi <strong>Hệ sinh thái TalentHub</strong></span>
                            </div>
                            <div>
                                <span>Thời gian cấp: <strong><?= date('d/m/Y H:i'); ?></strong></span>
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
        function previewAvatar(input) {
            var avatarEl = document.getElementById('passport-avatar-el');
            if (!avatarEl || !input.files || !input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                avatarEl.style.backgroundImage = 'url(' + e.target.result + ')';
                avatarEl.style.backgroundSize = 'cover';
                avatarEl.style.backgroundPosition = 'center';
                avatarEl.style.color = 'transparent';
                avatarEl.textContent = '';
            };
            reader.readAsDataURL(input.files[0]);
        }
    </script>
</body>
</html>
