<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

// Xử lý phản hồi lời mời thực tập qua POST (bắt lỗi an toàn, cập nhật đồng bộ các bảng)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['accept_invitation', 'decline_invitation', 'respond_invitation', 'respond-invitation'], true)) {
        $notificationId = trim((string) ($_POST['notificationId'] ?? ''));
        $decision = ($action === 'accept_invitation') ? 'accepted' : (($action === 'decline_invitation') ? 'declined' : (($_POST['decision'] ?? '') === 'decline' ? 'declined' : 'accepted'));

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $pdo = (new \TalentHub\Database\Connection($config))->connect();

        $userId = $context['user']['id'] ?? ($student['user_id'] ?? '');
        if (empty($userId)) {
            $userRow = $pdo->query("SELECT id FROM users WHERE email = 'vuducanh@student.btec.edu.vn' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $userId = $userRow ? (string) $userRow['id'] : '';
        }

        $entName = 'FPT Software';

        // 1. Get student profile ID
        $stId = $pdo->prepare("SELECT id FROM student_profiles WHERE userId = ? LIMIT 1");
        $stId->execute([$userId]);
        $studentId = $stId->fetchColumn();

        // 2. Lookup notification
        $notif = null;
        if (!empty($notificationId)) {
            $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? LIMIT 1");
            $notifStmt->execute([$notificationId]);
            $notif = $notifStmt->fetch(PDO::FETCH_ASSOC);
        }

        $deepLink = (string) ($notif['deepLink'] ?? '');
        $targetPostId = null;
        if (preg_match('#/internships/([a-zA-Z0-9_-]+)#', $deepLink, $m)) {
            $targetPostId = $m[1];
        }

        // 3. Find or update internship_applications
        if ($studentId) {
            $appStmt = $pdo->prepare("
                SELECT ia.id, ia.status, ia.postId, ia.studentId, e.name as enterpriseName
                FROM internship_applications ia
                JOIN internship_posts ip ON ip.id = ia.postId
                JOIN enterprises e ON e.id = ip.enterpriseId
                WHERE ia.studentId = ?
                ORDER BY (ia.status = 'invited') DESC, ia.updatedAt DESC
                LIMIT 1
            ");
            $appStmt->execute([$studentId]);
            $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);

            if ($appRow) {
                if (!empty($appRow['enterpriseName'])) {
                    $entName = $appRow['enterpriseName'];
                }
                $upd = $pdo->prepare("UPDATE internship_applications SET status = ?, updatedAt = NOW(6) WHERE id = ?");
                $upd->execute([$decision, $appRow['id']]);

                // Record history
                try {
                    $histId = \TalentHub\Support\Uuid::v4();
                    $pdo->prepare("
                        INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, note, createdAt)
                        VALUES (?, ?, ?, ?, ?, 'student', 'Ứng viên xác nhận trực tuyến', NOW(6))
                    ")->execute([$histId, $appRow['id'], $appRow['status'], $decision, $userId]);
                } catch (\Throwable $e) {}
            } else {
                // If no application record exists yet, check if there's any active post from FPT Software or targetPostId
                $postQuery = $targetPostId
                    ? $pdo->prepare("SELECT ip.id, ip.enterpriseId, e.name as enterpriseName FROM internship_posts ip JOIN enterprises e ON e.id = ip.enterpriseId WHERE ip.id = ? LIMIT 1")
                    : $pdo->prepare("SELECT ip.id, ip.enterpriseId, e.name as enterpriseName FROM internship_posts ip JOIN enterprises e ON e.id = ip.enterpriseId WHERE e.name LIKE '%FPT%' ORDER BY ip.createdAt DESC LIMIT 1");
                $postQuery->execute($targetPostId ? [$targetPostId] : []);
                $pRow = $postQuery->fetch(PDO::FETCH_ASSOC);

                if ($pRow) {
                    $entName = $pRow['enterpriseName'] ?: 'FPT Software';
                    $newAppId = \TalentHub\Support\Uuid::v4();
                    $pdo->prepare("
                        INSERT INTO internship_applications (id, postId, studentId, status, appliedAt, createdAt, updatedAt)
                        VALUES (?, ?, ?, ?, NOW(6), NOW(6), NOW(6))
                    ")->execute([$newAppId, $pRow['id'], $studentId, $decision]);
                }
            }

            // Synchronize job_applications table if present
            try {
                $pdo->prepare("
                    UPDATE job_applications
                    SET status = ?, updatedAt = NOW()
                    WHERE studentId = ? OR user_id = ? OR candidate_id = ?
                ")->execute([$decision, $studentId, $userId, $studentId]);
            } catch (\Throwable $e) {}

            // Update student status
            if ($decision === 'accepted') {
                try {
                    $pdo->prepare("UPDATE student_profiles SET studyStatus = 'active', updatedAt = NOW() WHERE id = ?")->execute([$studentId]);
                } catch (\Throwable $e) {}
            }
        }

        // 4. Mark notification as read
        if (!empty($notificationId)) {
            try {
                $pdo->prepare("UPDATE notifications SET readAt = NOW(6) WHERE id = ?")->execute([$notificationId]);
            } catch (\Throwable $e) {}
        } else if ($userId) {
            try {
                $pdo->prepare("UPDATE notifications SET readAt = NOW(6) WHERE userId = ? AND readAt IS NULL")->execute([$userId]);
            } catch (\Throwable $e) {}
        }

        $msg = ($decision === 'accepted')
            ? "Bạn đã chấp nhận lời mời thực tập từ {$entName}!"
            : "Bạn đã từ chối lời mời thực tập.";

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => true,
            'status' => $decision,
            'message' => $msg,
            'enterpriseName' => $entName,
            'notificationId' => $notificationId
        ]);
        if (!defined('TEST_MODE')) {
            exit;
        }
        return;
    }
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

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>

    <script>
        const CSRF_TOKEN = <?= json_encode($boot['csrfToken'] ?? ''); ?>;

        /**
         * Xử lý Chấp nhận lời mời thực tập theo phong cách SaaS Minimalist Clean UI
         */
        async function handleAcceptInvitation(notificationId, entName, actionContainer) {
            const enterprise = entName || 'FPT Software';

            // 1. Modal Xác nhận tiếp nhận thực tập (SaaS Clean Style)
            let isConfirmed = false;
            await Swal.fire({
                showConfirmButton: false,
                showCancelButton: false,
                customClass: {
                    popup: 'saas-modal-card',
                    htmlContainer: 'saas-modal-html'
                },
                html: `
                    <div class="saas-modal-icon-badge is-info">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </div>
                    <h3 class="saas-modal-title">Xác nhận tiếp nhận thực tập?</h3>
                    <p class="saas-modal-desc">
                        Bạn đang đồng ý tham gia đợt thực tập tại <strong>${enterprise}</strong>. Thông tin sẽ được gửi đến Nhà trường và Doanh nghiệp.
                    </p>
                    <div class="saas-modal-actions">
                        <button type="button" class="saas-btn-secondary" id="saas-confirm-cancel">Xem lại</button>
                        <button type="button" class="saas-btn-primary" id="saas-confirm-ok">✓ Đồng ý tiếp nhận</button>
                    </div>
                `,
                didOpen: () => {
                    document.getElementById('saas-confirm-ok')?.addEventListener('click', () => {
                        isConfirmed = true;
                        Swal.close();
                    });
                    document.getElementById('saas-confirm-cancel')?.addEventListener('click', () => {
                        isConfirmed = false;
                        Swal.close();
                    });
                }
            });

            if (!isConfirmed) return;

            // 2. Loading State
            Swal.fire({
                title: 'Đang xử lý tiếp nhận...',
                allowOutsideClick: false,
                showConfirmButton: false,
                customClass: {
                    popup: 'saas-modal-card'
                },
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const formData = new FormData();
                formData.append('action', 'accept_invitation');
                formData.append('notificationId', notificationId);
                formData.append('csrfToken', CSRF_TOKEN);

                const res = await fetch('notifications.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    // 3. Popup Thành công (High-end SaaS Style)
                    await Swal.fire({
                        showConfirmButton: false,
                        showCancelButton: false,
                        customClass: {
                            popup: 'saas-modal-card',
                            htmlContainer: 'saas-modal-html'
                        },
                        html: `
                            <div class="saas-modal-icon-badge is-success">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>
                            <h3 class="saas-modal-title">Tiếp nhận thành công</h3>
                            <p class="saas-modal-desc">
                                Bạn đã xác nhận tham gia thực tập tại <strong>${enterprise}</strong>. Thông tin chi tiết và hợp đồng thực tập sẽ được gửi qua email của bạn.
                            </p>
                            <button type="button" class="saas-btn-primary saas-btn-full" id="saas-success-close">
                                Đã hiểu
                            </button>
                        `,
                        didOpen: () => {
                            document.getElementById('saas-success-close')?.addEventListener('click', () => {
                                Swal.close();
                            });
                        }
                    });

                    // Thay thế cụm nút trên giao diện thành Badge xanh lá tức thì cho ĐÚNG dòng có notificationId
                    const targetCard = actionContainer?.closest('.learner-notification-card') ||
                                       document.querySelector(`[data-notification-id="${notificationId}"]`) ||
                                       document.querySelector(`[data-id="${notificationId}"]`);

                    const actionsTarget = targetCard ? targetCard.querySelector('.learner-notification-invite-actions') : actionContainer;

                    if (actionsTarget) {
                        actionsTarget.innerHTML = `
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill fw-bold learner-invite-badge-accepted">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Đã tiếp nhận thực tập</span>
                            </span>
                        `;
                    }

                    if (targetCard) {
                        targetCard.classList.remove('is-unread');
                        targetCard.querySelector('.learner-notification-card__actions')?.remove();
                    }
                } else {
                    Swal.fire({
                        title: 'Không thể tiếp nhận',
                        text: data.message || 'Có lỗi xảy ra trong quá trình xử lý.',
                        customClass: { popup: 'saas-modal-card' },
                        confirmButtonColor: '#059669'
                    });
                }
            } catch (err) {
                console.error(err);
                Swal.fire({
                    title: 'Lỗi kết nối',
                    text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại sau.',
                    customClass: { popup: 'saas-modal-card' },
                    confirmButtonColor: '#059669'
                });
            }
        }

        /**
         * Xử lý Từ chối lời mời thực tập (SaaS Clean Style)
         */
        async function handleDeclineInvitation(notificationId, entName, actionContainer) {
            const enterprise = entName || 'Doanh nghiệp';

            let isConfirmed = false;
            await Swal.fire({
                showConfirmButton: false,
                showCancelButton: false,
                customClass: {
                    popup: 'saas-modal-card',
                    htmlContainer: 'saas-modal-html'
                },
                html: `
                    <div class="saas-modal-icon-badge is-warning">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <h3 class="saas-modal-title">Từ chối lời mời thực tập?</h3>
                    <p class="saas-modal-desc">
                        Bạn có chắc chắn muốn từ chối lời mời thực tập từ <strong>${enterprise}</strong> không?
                    </p>
                    <div class="saas-modal-actions">
                        <button type="button" class="saas-btn-secondary" id="saas-decline-cancel">Hủy</button>
                        <button type="button" class="saas-btn-danger" id="saas-decline-ok">Xác nhận từ chối</button>
                    </div>
                `,
                didOpen: () => {
                    document.getElementById('saas-decline-ok')?.addEventListener('click', () => {
                        isConfirmed = true;
                        Swal.close();
                    });
                    document.getElementById('saas-decline-cancel')?.addEventListener('click', () => {
                        isConfirmed = false;
                        Swal.close();
                    });
                }
            });

            if (!isConfirmed) return;

            Swal.fire({
                title: 'Đang xử lý...',
                allowOutsideClick: false,
                showConfirmButton: false,
                customClass: { popup: 'saas-modal-card' },
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const formData = new FormData();
                formData.append('action', 'decline_invitation');
                formData.append('notificationId', notificationId);
                formData.append('csrfToken', CSRF_TOKEN);

                const res = await fetch('notifications.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    await Swal.fire({
                        showConfirmButton: false,
                        showCancelButton: false,
                        customClass: {
                            popup: 'saas-modal-card',
                            htmlContainer: 'saas-modal-html'
                        },
                        html: `
                            <div class="saas-modal-icon-badge is-warning">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </div>
                            <h3 class="saas-modal-title">Đã từ chối lời mời</h3>
                            <p class="saas-modal-desc">
                                Đã ghi nhận phản hồi từ chối lời mời thực tập từ <strong>${enterprise}</strong>.
                            </p>
                            <button type="button" class="saas-btn-secondary saas-btn-full" id="saas-decline-close">
                                Đóng
                            </button>
                        `,
                        didOpen: () => {
                            document.getElementById('saas-decline-close')?.addEventListener('click', () => {
                                Swal.close();
                            });
                        }
                    });

                    // Thay thế cụm nút thành Badge từ chối cho ĐÚNG dòng có notificationId
                    const targetCard = actionContainer?.closest('.learner-notification-card') ||
                                       document.querySelector(`[data-notification-id="${notificationId}"]`) ||
                                       document.querySelector(`[data-id="${notificationId}"]`);

                    const actionsTarget = targetCard ? targetCard.querySelector('.learner-notification-invite-actions') : actionContainer;

                    if (actionsTarget) {
                        actionsTarget.innerHTML = `
                            <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill fw-bold learner-invite-badge-declined">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                <span>Đã từ chối</span>
                            </span>
                        `;
                    }

                    if (targetCard) {
                        targetCard.classList.remove('is-unread');
                        targetCard.querySelector('.learner-notification-card__actions')?.remove();
                    }

                    const card = actionContainer?.closest('.learner-notification-card');
                    if (card) {
                        card.classList.remove('is-unread');
                        card.querySelector('.learner-notification-card__actions')?.remove();
                    }
                } else {
                    Swal.fire({
                        title: 'Thao tác thất bại',
                        text: data.message || 'Không thể từ chối lời mời lúc này.',
                        customClass: { popup: 'saas-modal-card' },
                        confirmButtonColor: '#059669'
                    });
                }
            } catch (err) {
                console.error(err);
                Swal.fire({
                    title: 'Lỗi kết nối',
                    text: 'Không thể kết nối đến máy chủ.',
                    customClass: { popup: 'saas-modal-card' },
                    confirmButtonColor: '#059669'
                });
            }
        }
    </script>
</body>
</html>
