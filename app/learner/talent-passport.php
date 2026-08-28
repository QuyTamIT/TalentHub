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
$verifyUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/app/learner/shared-profile.php?passport=' . urlencode($passportCode);
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
            padding: 2.25rem 2.5rem;
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
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .passport-badge-code {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .passport-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
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
            width: 120px;
            height: 120px;
            border-radius: 16px;
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
            color: #FFFFFF;
            font-size: 2.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px rgba(29, 78, 216, 0.25);
            margin-bottom: 1.25rem;
            border: 4px solid #FFFFFF;
        }
        .passport-name {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.35rem 0;
        }
        .passport-school-tag {
            font-size: 0.875rem;
            color: #475569;
            font-weight: 600;
            margin-bottom: 1.25rem;
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
            width: 130px;
            height: 130px;
            border-radius: 8px;
            background: #FFFFFF;
        }
        .passport-qr-label {
            font-size: 0.75rem;
            color: #64748B;
            font-weight: 600;
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
            font-weight: 700;
            color: #1E293B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 0.85rem 0;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 0.5rem;
        }
        .passport-score-hero {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 1.25rem;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 12px;
            padding: 1.25rem;
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
        .passport-skills-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .passport-skill-pill {
            background: #EFF6FF;
            border: 1px solid #DBEAFE;
            color: #1D4ED8;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
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
            body { background: #FFF !important; }
            .learner-layout { display: block !important; }
            .learner-sidebar, .learner-header, .passport-action-bar { display: none !important; }
            .passport-card { box-shadow: none !important; border: 1px solid #CBD5E1 !important; }
        }
        @media (max-width: 768px) {
            .passport-grid { grid-template-columns: 1fr; }
            .passport-id-column { border-right: none; border-bottom: 1px solid #F1F5F9; padding-right: 0; padding-bottom: 1.5rem; }
            .passport-score-hero { grid-template-columns: 1fr; }
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
                                <div>
                                    <span class="passport-badge-code"><?= learner_escape($passportCode); ?></span>
                                </div>
                            </div>
                        </header>

                        <!-- Body Grid -->
                        <div class="passport-grid">
                            
                            <!-- Left Column: Identity & QR Code -->
                            <div class="passport-id-column">
                                <div class="passport-avatar" aria-hidden="true">
                                    <?= learner_escape($student['initials'] ?? 'T'); ?>
                                </div>
                                <h2 class="passport-name"><?= learner_escape($studentName); ?></h2>
                                <div class="passport-school-tag">
                                    <strong><?= learner_escape($studentClass); ?></strong><br>
                                    <span><?= learner_escape($studentSchool); ?></span>
                                </div>

                                <div style="font-size: 0.8125rem; color: #64748B; margin-bottom: 1rem; line-height: 1.4;">
                                    <span><?= learner_icon('mail', 14); ?> <?= learner_escape($studentEmail); ?></span><br>
                                    <span><?= learner_icon('map-pin', 14); ?> <?= learner_escape($studentLocation); ?></span>
                                </div>

                                <!-- QR Verification Box -->
                                <div class="passport-qr-box">
                                    <img class="passport-qr-img" src="<?= htmlspecialchars($qrCodeApiUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Mã QR định danh Talent Passport">
                                    <span class="passport-qr-label">Quét để xác thực số</span>
                                    <small style="font-size: 0.6875rem; color: #94A3B8; margin-top: 0.25rem;">Xác thực thời gian thực</small>
                                </div>
                            </div>

                            <!-- Right Column: Scores, Skills, Endorsements -->
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
                                                <strong style="color: #0891B2;">80/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 80%; height: 100%; background: #0891B2;"></div>
                                            </div>
                                        </div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                                <span>Ngoại ngữ & Giao tiếp</span>
                                                <strong style="color: #059669;">75/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 75%; height: 100%; background: #059669;"></div>
                                            </div>
                                        </div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.65rem 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin-bottom: 0.25rem;">
                                                <span>Kinh doanh & Quản lý</span>
                                                <strong style="color: #EA580C;">72/100</strong>
                                            </div>
                                            <div style="height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: 72%; height: 100%; background: #EA580C;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Top Kỹ năng đã chứng thực -->
                                <div>
                                    <h3 class="passport-section-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        Kỹ năng Chuyên môn đã Xác thực
                                    </h3>
                                    <div class="passport-skills-pills">
                                        <span class="passport-skill-pill">✓ Python</span>
                                        <span class="passport-skill-pill">✓ PyTorch</span>
                                        <span class="passport-skill-pill">✓ Machine Learning</span>
                                        <span class="passport-skill-pill">✓ Computer Vision</span>
                                        <span class="passport-skill-pill">✓ Docker & Git</span>
                                        <span class="passport-skill-pill">✓ IoT & Cảm biến</span>
                                        <span class="passport-skill-pill">✓ Thuyết trình & Làm việc nhóm</span>
                                    </div>
                                </div>

                                <!-- Section 4: Lời nhận xét chứng thực của Giảng viên & Nhà trường -->
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
</body>
</html>
