<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Trang thông báo chỉ hỗ trợ GET. Vui lòng dùng API thông báo để cập nhật dữ liệu.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pageTitle = 'Thông báo';
$currentRoute = '/app/learner/notifications.php';
$learnerDataSource = learner_safe_runtime_diagnostics()['source'];
$boot = [
    'source' => $learnerDataSource,
    'student_id' => learner_current_student_id(),
    'csrfToken' => (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? ''),
    'apiBase' => '/app/learner/api/v1',
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Thông báo | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <!-- SweetAlert2 Stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Pulse Animation for Accept Button */
        @keyframes invitePulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        /* Fade/Slide Animation for Badges */
        @keyframes badgeFadeIn {
            from {
                opacity: 0;
                transform: translateY(4px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .learner-notification-invite-actions {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 4px;
        }

        .learner-invite-btn-accept {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            background: #10b981 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            padding: 9px 18px !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            width: auto !important;
            height: auto !important;
            min-width: fit-content !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            animation: invitePulse 2.5s infinite;
        }

        .learner-invite-btn-accept:hover {
            background: #059669 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3) !important;
        }

        .learner-invite-btn-accept:active {
            transform: translateY(0);
        }

        .learner-invite-btn-decline {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            background: #ffffff !important;
            color: #4b5563 !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            border: 1.5px solid #d1d5db !important;
            cursor: pointer !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            width: auto !important;
            height: auto !important;
            min-width: fit-content !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        .learner-invite-btn-decline:hover {
            background: #fff1f2 !important;
            border-color: #fecdd3 !important;
            color: #e11d48 !important;
            transform: translateY(-1px);
        }

        .learner-invite-badge-accepted {
            background: #ecfdf5 !important;
            color: #047857 !important;
            border: 1.5px solid #10b981 !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            padding: 7px 16px !important;
            border-radius: 999px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            width: auto !important;
            white-space: nowrap !important;
            animation: badgeFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .learner-invite-badge-declined {
            background: #fef2f2 !important;
            color: #b91c1c !important;
            border: 1.5px solid #f87171 !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            padding: 7px 16px !important;
            border-radius: 999px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            width: auto !important;
            white-space: nowrap !important;
            animation: badgeFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* SaaS Minimalist SweetAlert2 Custom Style */
        .swal2-popup.saas-modal-card {
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif !important;
            border-radius: 20px !important;
            padding: 32px 28px !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(0, 0, 0, 0.05) !important;
            width: 440px !important;
            max-width: 92vw !important;
            border: none !important;
            background: #ffffff !important;
        }
        .swal2-html-container.saas-modal-html {
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
        }
        .saas-modal-icon-badge {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px auto;
        }
        .saas-modal-icon-badge.is-success {
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            color: #059669;
        }
        .saas-modal-icon-badge.is-info {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #2563eb;
        }
        .saas-modal-icon-badge.is-warning {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #ef4444;
        }
        .saas-modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 10px 0;
            text-align: center;
            letter-spacing: -0.01em;
            line-height: 1.3;
        }
        .saas-modal-desc {
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
            margin: 0 0 24px 0;
            text-align: center;
        }
        .saas-modal-actions {
            display: flex;
            gap: 10px;
            width: 100%;
        }
        .saas-btn-primary {
            flex: 1;
            padding: 12px 18px;
            background: #059669;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .saas-btn-primary:hover {
            background: #047857;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        .saas-btn-secondary {
            flex: 1;
            padding: 12px 18px;
            background: #ffffff;
            color: #4b5563;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .saas-btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
        }
        .saas-btn-danger {
            flex: 1;
            padding: 12px 18px;
            background: #ef4444;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .saas-btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }
        .saas-btn-full {
            width: 100%;
        }
    </style>
</head>
<body class="learner-app learner-page-notifications">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content" data-notifications-page>
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-notifications-page-title',
                    'eyebrow' => 'Trung tâm cập nhật',
                    'title' => 'Thông báo',
                    'description' => 'Theo dõi thông báo hoạt động, check-in, ứng tuyển và kết quả đánh giá năng lực.',
                    'icon' => 'bell',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <div class="learner-notification-heading">
                    <div class="learner-filter-list">
                        <button class="learner-filter-button is-active" type="button" data-notification-filter="all" aria-pressed="true">
                            Tất cả
                        </button>
                        <button class="learner-filter-button" type="button" data-notification-filter="unread" aria-pressed="false">
                            Chưa đọc
                        </button>
                    </div>
                    <div class="learner-notification-heading__actions">
                        <button class="learner-btn learner-btn--ghost learner-btn--sm" id="learner-open-prefs" type="button">
                            <?= learner_icon('filter', 16); ?>
                            <span>Cài đặt thông báo</span>
                        </button>
                        <button class="learner-btn learner-btn--secondary learner-btn--sm" id="learner-mark-all-read" type="button">
                            <?= learner_icon('check', 16); ?>
                            <span>Đánh dấu tất cả đã đọc</span>
                        </button>
                    </div>
                </div>

                <section class="learner-notification-list" id="learner-notification-list" aria-live="polite">
                    <div class="learner-notification-loading">Đang tải thông báo...</div>
                </section>
                <div class="learner-notification-pagination">
                    <button class="learner-btn learner-btn--secondary learner-btn--sm" id="learner-notification-load-more" type="button" hidden>
                        Tải thêm thông báo
                    </button>
                </div>
            </main>
        </div>
    </div>

    <!-- Notification Preferences Modal -->
    <div class="learner-notification-modal" id="learner-notification-prefs-modal" role="dialog" aria-modal="true" aria-labelledby="learner-prefs-title" aria-hidden="true">
        <div class="learner-notification-modal__content">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h2 id="learner-prefs-title" style="margin: 0; font-size: 1.15rem;">Cài đặt nhận thông báo</h2>
                <button class="learner-icon-button" id="learner-prefs-close" type="button" aria-label="Đóng cài đặt">
                    <?= learner_icon('x', 20); ?>
                </button>
            </div>
            <p style="color: var(--text-secondary); font-size: 0.84rem; margin-bottom: 20px;">
                Tùy chỉnh thông báo trong ứng dụng và lưu lựa chọn email. Hệ thống chưa gửi email trong v1.
            </p>
            <div id="learner-prefs-list">
                <div>Đang tải cài đặt...</div>
            </div>
        </div>
    </div>

    <script id="learner-notifications-boot" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
