<?php
/**
 * TalentHub - Enterprise Company Profile ("Hồ sơ doanh nghiệp")
 *
 * Fully integrated Enterprise Company Profile page.
 * Loads dynamic enterprise profile from MySQL via EnterpriseAppContext and BusinessProfileService.
 * Supports viewing and live editing with CSRF protection and tenant isolation.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Bootstrap\EnterpriseAppContext;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$csrfToken  = $context['csrfToken'];

if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        if (stripos($name, 'Vinamilk') !== false || stripos($name, 'Sữa Việt Nam') !== false || stripos($name, 'VNM') !== false) {
            return 'VNM';
        }
        if (stripos($name, 'FPT') !== false || stripos($name, 'Phần mềm FPT') !== false) {
            return 'FS';
        }
        if (stripos($name, 'MB') !== false || stripos($name, 'Quân đội') !== false) {
            return 'MB';
        }
        $words = preg_split('/\s+/', trim($name));
        if (empty($words) || $words[0] === '') return 'DN';
        if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 2));
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    }
}

$companyInitials = getInitials($enterprise['name']);
$isVerified = ($enterprise['verificationStatus'] ?? 'pending') === 'verified';
$accountType = $isVerified ? 'Doanh nghiệp Đã xác thực' : 'Tài khoản Doanh nghiệp';

$enterpriseInfo = [
    'id'                => $enterprise['id'],
    'company_name'      => $enterprise['name'],
    'account_type'      => $accountType,
    'logo_initials'     => $companyInitials,
    'logo_url'          => $enterprise['logoUrl'] ?? null,
    'new_matches_count' => 86,
    'total_talents'     => 1247,
];

$pageTitle    = 'Hồ sơ doanh nghiệp';
$currentRoute = '/app/enterprise/profile.php';

$sidebarNav = [
    [
        'title'  => 'Tổng quan',
        'route'  => '/app/enterprise/index.php',
        'icon'   => 'grid',
        'active' => false,
    ],
    [
        'title'  => 'Tìm nhân tài',
        'route'  => '/app/enterprise/talents.php',
        'icon'   => 'search-users',
        'active' => false,
    ],
    [
        'title'  => 'Tuyển thực tập',
        'route'  => '/app/enterprise/internships/',
        'icon'   => 'briefcase',
        'active' => false,
    ],
    [
        'title'  => 'Tài trợ dự án',
        'route'  => '/app/enterprise/sponsorships/',
        'icon'   => 'award',
        'active' => false,
    ],
    [
        'title'  => 'Phân tích tuyển dụng',
        'route'  => '/app/enterprise/analytics.php',
        'icon'   => 'bar-chart-2',
        'active' => false,
    ],
    [
        'title'  => 'Hồ sơ doanh nghiệp',
        'route'  => '/app/enterprise/profile.php',
        'icon'   => 'building',
        'active' => true,
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hồ sơ doanh nghiệp TalentHub - Quản lý thông tin đơn vị, liên hệ và nhận diện thương hiệu tuyển dụng.">
    <title>Hồ sơ doanh nghiệp - <?= htmlspecialchars($enterprise['name']); ?> | TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/home.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/global.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/enterprise.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/enterprise.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/typeui-selects.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/typeui-selects.css'); ?>">
</head>
<body class="enterprise-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body" id="main-content">
                <div class="container-fluid" style="max-width: 1200px;">
                    
                    <!-- 1. Company Profile Header Hero Card -->
                    <section class="ent-profile-hero" aria-labelledby="profile-heading">
                        <div class="ent-profile-hero__top">
                            <div class="ent-profile-hero__main">
                                <div class="ent-profile-hero__avatar" data-bind="enterprise-initials" aria-hidden="true">
                                    <?php 
                                    $resolvedHeroLogo = !empty($enterprise['logoUrl']) ? (function_exists('resolve_logo_url') ? resolve_logo_url($enterprise['logoUrl']) : $enterprise['logoUrl']) : null;
                                    if ($resolvedHeroLogo): ?>
                                        <img src="<?= htmlspecialchars($resolvedHeroLogo); ?>" alt="Logo <?= htmlspecialchars($enterprise['name']); ?>" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:4px;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                        <span class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($companyInitials); ?></span>
                                    <?php else: ?>
                                        <span class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($companyInitials); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ent-profile-hero__identity">
                                    <div class="ent-profile-hero__name-row">
                                        <h1 id="profile-heading" class="ent-profile-hero__name" data-bind="enterprise-name"><?= htmlspecialchars($enterprise['name']); ?></h1>
                                    </div>
                                    <div class="ent-profile-hero__badges">
                                        <?php if ($isVerified): ?>
                                            <span class="ent-badge ent-badge--verified" title="Doanh nghiệp đã hoàn tất xác thực thông tin">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                </svg>
                                                Đã xác thực
                                            </span>
                                        <?php else: ?>
                                            <span class="ent-badge ent-badge--pending" title="Hồ sơ đang chờ phê duyệt xác thực">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                                </svg>
                                                Chờ xác thực
                                            </span>
                                        <?php endif; ?>

                                        <span class="ent-badge ent-badge--package">
                                            <?= htmlspecialchars($accountType); ?>
                                        </span>

                                        <span class="ent-badge ent-badge--role">
                                            Vai trò: <?= htmlspecialchars($enterprise['memberRole'] === 'admin' ? 'Quản trị viên' : 'Thành viên'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Primary Action Button -->
                            <div class="ent-profile-hero__action">
                                <button type="button" class="btn btn-primary" id="btn-open-edit-profile" aria-haspopup="dialog">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    <span>Chỉnh sửa hồ sơ</span>
                                </button>
                            </div>
                        </div>

                        <!-- Profile Completion Indicator Bar -->
                        <div class="ent-profile-hero__completion">
                            <div class="ent-completion-info">
                                <span class="ent-completion-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    Mức độ hoàn thiện:
                                </span>
                                <span class="ent-completion-percent" data-bind="completion-percent"><?= (int)($enterprise['profileCompletion'] ?? 0); ?>%</span>
                                <div class="ent-completion-track" role="progressbar" aria-valuenow="<?= (int)($enterprise['profileCompletion'] ?? 0); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="ent-completion-fill" data-bind="completion-bar" style="width: <?= (int)($enterprise['profileCompletion'] ?? 0); ?>%;"></div>
                                </div>
                            </div>
                            <p class="ent-completion-hint">Hồ sơ đầy đủ giúp thu hút nhiều ứng viên tiềm năng và tăng độ tin cậy kết nối với nhà trường.</p>
                        </div>
                    </section>

                    <!-- Main Profile Grid -->
                    <div class="ent-profile-grid">
                        
                        <!-- Left Main Column: Company Overview & Description -->
                        <div class="ent-profile-grid__col-8">
                            
                            <!-- 2. Company Overview Card -->
                            <div class="ent-section-box mb-4">
                                <div class="ent-section-box__header mb-3">
                                    <h2 class="ent-section-box__title">Tổng quan doanh nghiệp</h2>
                                    <span class="ent-badge ent-badge--role" data-bind="industry">
                                        <?= htmlspecialchars($enterprise['industry'] ?? 'Chưa cập nhật lĩnh vực'); ?>
                                    </span>
                                </div>

                                <!-- Metric highlight boxes -->
                                <div class="ent-profile-metrics">
                                    <div class="ent-profile-metric-item">
                                        <span class="ent-profile-metric-item__title">Quy mô nhân sự</span>
                                        <span class="ent-profile-metric-item__value" data-bind="companySize">
                                            <?= htmlspecialchars($enterprise['companySize'] ?? 'Chưa cập nhật'); ?>
                                        </span>
                                    </div>
                                    <div class="ent-profile-metric-item">
                                        <span class="ent-profile-metric-item__title">Năm thành lập</span>
                                        <span class="ent-profile-metric-item__value" data-bind="foundedYear">
                                            <?= htmlspecialchars($enterprise['foundedYear'] ? (string)$enterprise['foundedYear'] : 'Chưa cập nhật'); ?>
                                        </span>
                                    </div>
                                    <div class="ent-profile-metric-item">
                                        <span class="ent-profile-metric-item__title">Trạng thái</span>
                                        <span class="ent-profile-metric-item__value" style="color: #059669;">
                                            Đang hoạt động
                                        </span>
                                    </div>
                                </div>

                                <!-- Introduction & Description -->
                                <div>
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem;">Giới thiệu về doanh nghiệp</h3>
                                    <div class="ent-info-description" data-bind="description">
                                        <?php if (!empty($enterprise['description'])): ?>
                                            <?= htmlspecialchars($enterprise['description']); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-style: italic;">Doanh nghiệp chưa bổ sung mô tả giới thiệu. Hãy nhấn "Chỉnh sửa hồ sơ" để thêm thông tin thu hút nhân tài.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Contact Information Card -->
                            <div class="ent-section-box">
                                <div class="ent-section-box__header mb-3">
                                    <h2 class="ent-section-box__title">Thông tin liên hệ</h2>
                                </div>
                                <div class="ent-info-list">
                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">
                                            <svg class="ent-info-row__label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                <polyline points="22,6 12,13 2,6"></polyline>
                                            </svg>
                                            Email doanh nghiệp
                                        </span>
                                        <span class="ent-info-row__value" data-bind="email">
                                            <?php if (!empty($enterprise['email'])): ?>
                                                <a href="mailto:<?= htmlspecialchars($enterprise['email']); ?>"><?= htmlspecialchars($enterprise['email']); ?></a>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">Chưa cập nhật</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">
                                            <svg class="ent-info-row__label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                            </svg>
                                            Số điện thoại
                                        </span>
                                        <span class="ent-info-row__value" data-bind="phone">
                                            <?php if (!empty($enterprise['phone'])): ?>
                                                <a href="tel:<?= htmlspecialchars($enterprise['phone']); ?>"><?= htmlspecialchars($enterprise['phone']); ?></a>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">Chưa cập nhật</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">
                                            <svg class="ent-info-row__label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                            </svg>
                                            Website
                                        </span>
                                        <span class="ent-info-row__value" data-bind="website">
                                            <?php if (!empty($enterprise['website'])): 
                                                $webUrl = str_starts_with($enterprise['website'], 'http') ? $enterprise['website'] : ('https://' . $enterprise['website']);
                                            ?>
                                                <a href="<?= htmlspecialchars($webUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?= htmlspecialchars($enterprise['website']); ?>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                        <polyline points="15 3 21 3 21 9"></polyline>
                                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                                    </svg>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">Chưa cập nhật</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">
                                            <svg class="ent-info-row__label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            Địa chỉ trụ sở
                                        </span>
                                        <span class="ent-info-row__value" data-bind="address">
                                            <?= htmlspecialchars($enterprise['address'] ?? 'Chưa cập nhật'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Sidebar Column: Company Details & Representative -->
                        <aside class="ent-profile-grid__col-4">
                            
                            <!-- 4. Company Details Card -->
                            <div class="ent-section-box mb-4">
                                <div class="ent-section-box__header mb-3">
                                    <h2 class="ent-section-box__title">Pháp lý & Đại diện</h2>
                                </div>

                                <div class="ent-info-list">
                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">Mã số thuế</span>
                                        <span class="ent-info-row__value" data-bind="taxCode">
                                            <?= htmlspecialchars($enterprise['taxCode'] ?? 'Chưa cập nhật'); ?>
                                        </span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">Người đại diện</span>
                                        <span class="ent-info-row__value"><?= htmlspecialchars($enterprise['accountName'] ?? $user['fullName']); ?></span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">Email tài khoản</span>
                                        <span class="ent-info-row__value"><?= htmlspecialchars($enterprise['accountEmail'] ?? $user['email']); ?></span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">Vai trò tài khoản</span>
                                        <span class="ent-info-row__value">
                                            <?= htmlspecialchars($enterprise['memberRole'] === 'admin' ? 'Quản trị viên doanh nghiệp' : 'Thành viên doanh nghiệp'); ?>
                                        </span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">Xác thực hệ thống</span>
                                        <span class="ent-info-row__value">
                                            <?php if ($isVerified): ?>
                                                <span style="color: #059669; font-weight: 600;">Đã xác minh</span>
                                            <?php else: ?>
                                                <span style="color: #D97706; font-weight: 600;">Chờ xác minh</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="ent-info-row">
                                        <span class="ent-info-row__label">Gia nhập từ</span>
                                        <span class="ent-info-row__value">
                                            <?= date('d/m/Y', strtotime($enterprise['createdAt'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Tip Box
                            <div class="ent-section-box" style="background-color: #FFF7ED; border-color: rgba(249, 115, 22, 0.25);">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F97316" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    <h3 style="font-size: 0.9375rem; font-weight: 700; color: #9A3412; margin: 0;">Mẹo tối ưu hồ sơ</h3>
                                </div>
                                <p style="font-size: 0.8125rem; line-height: 1.5; color: #7C2D12; margin: 0;">
                                    Doanh nghiệp cập nhật đầy đủ thông tin giới thiệu, lĩnh vực và website chính thức sẽ có tỷ lệ học viên nộp hồ sơ thực tập cao hơn <strong>45%</strong>.
                                </p>
                            </div> -->
                        </aside>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 5. Edit Profile Modal Dialog Component -->
    <div class="ent-modal" id="ent-edit-profile-modal" role="dialog" aria-modal="true" aria-labelledby="modal-profile-title" aria-hidden="true">
        <div class="ent-modal__backdrop"></div>
        <div class="ent-modal__dialog" role="document">
            
            <div class="ent-modal__header">
                <div class="ent-modal__title-group">
                    <h2 id="modal-profile-title" class="ent-modal__title">Chỉnh sửa hồ sơ Doanh nghiệp</h2>
                    <p class="ent-modal__subtitle">Cập nhật thông tin nhận diện, quy mô và thông tin liên hệ của đơn vị.</p>
                </div>
                <button type="button" class="ent-modal__close" id="btn-close-edit-modal" aria-label="Đóng cửa sổ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <form id="ent-profile-edit-form" novalidate>
                <input type="hidden" id="field-csrf" value="<?= htmlspecialchars($csrfToken); ?>">

                <div class="ent-modal__body">
                    <div id="modal-feedback" class="ent-form-feedback" role="alert"></div>

                    <!-- Group 1: General Info -->
                    <div class="mb-4">
                        <div class="ent-form-section-title">1. Thông tin chung</div>
                        <div class="ent-form-grid">
                            <div class="ent-form-group col-12">
                                <label for="field-name" class="ent-form-label required">Tên doanh nghiệp / Đơn vị</label>
                                <input type="text" id="field-name" class="ent-form-input" value="<?= htmlspecialchars($enterprise['name']); ?>" maxlength="255" required placeholder="Ví dụ: Công ty Cổ phần Công nghệ..." autofocus>
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-industry" class="ent-form-label">Lĩnh vực hoạt động</label>
                                <input type="text" id="field-industry" class="ent-form-input" value="<?= htmlspecialchars($enterprise['industry'] ?? ''); ?>" maxlength="150" placeholder="Ví dụ: Công nghệ Thông tin, AI, Phần mềm...">
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-companySize" class="ent-form-label">Quy mô nhân sự</label>
                                <select id="field-companySize" class="ent-form-select typeui-select">
                                    <option value="">-- Chọn quy mô --</option>
                                    <?php 
                                    $sizes = ['Dưới 20 nhân viên', '20 - 50 nhân viên', '50 - 200 nhân viên', '200 - 500 nhân viên', '500 - 1000 nhân viên', 'Trên 1000 nhân viên'];
                                    foreach ($sizes as $s):
                                        $selected = (($enterprise['companySize'] ?? '') === $s) ? 'selected' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($s); ?>" <?= $selected; ?>><?= htmlspecialchars($s); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-foundedYear" class="ent-form-label">Năm thành lập</label>
                                <input type="number" id="field-foundedYear" class="ent-form-input" value="<?= htmlspecialchars($enterprise['foundedYear'] ? (string)$enterprise['foundedYear'] : ''); ?>" min="1800" max="<?= date('Y'); ?>" placeholder="Ví dụ: 2018">
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-taxCode" class="ent-form-label">Mã số thuế</label>
                                <input type="text" id="field-taxCode" class="ent-form-input" value="<?= htmlspecialchars($enterprise['taxCode'] ?? ''); ?>" maxlength="50" placeholder="Ví dụ: 0101234567">
                            </div>

                            <div class="ent-form-group col-12">
                                <label for="field-description" class="ent-form-label">Giới thiệu ngắn về doanh nghiệp</label>
                                <textarea id="field-description" class="ent-form-textarea" rows="4" maxlength="4000" placeholder="Giới thiệu tầm nhìn, môi trường làm việc, văn hóa doanh nghiệp và cơ hội phát triển cho thực tập sinh..."><?= htmlspecialchars($enterprise['description'] ?? ''); ?></textarea>
                                <div id="desc-char-count" class="ent-char-count">0 / 4000 ký tự</div>
                            </div>
                        </div>
                    </div>

                    <!-- Group 2: Contact & Identity -->
                    <div class="mb-2">
                        <div class="ent-form-section-title">2. Thông tin liên hệ & Nhận diện</div>
                        <div class="ent-form-grid">
                            <!-- Logo Uploader Block -->
                            <div class="ent-form-group col-12">
                                <label class="ent-form-label">Logo thương hiệu Doanh nghiệp</label>
                                <div class="ent-logo-uploader">
                                    <div class="ent-logo-preview-box" id="logo-preview-box">
                                        <?php if ($resolvedHeroLogo): ?>
                                            <img id="logo-preview-img" src="<?= htmlspecialchars($resolvedHeroLogo); ?>" alt="Preview" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:4px;border-radius:10px;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                            <span id="logo-preview-fallback" class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;color:#ffffff;"><?= htmlspecialchars($companyInitials); ?></span>
                                        <?php else: ?>
                                            <img id="logo-preview-img" src="" alt="Preview" style="display:none;width:100%;height:100%;object-fit:contain;background:#ffffff;padding:4px;border-radius:10px;">
                                            <span id="logo-preview-fallback" class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;color:#ffffff;"><?= htmlspecialchars($companyInitials); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ent-logo-upload-controls">
                                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                            <label for="field-logo-file" class="btn btn-secondary btn-sm" style="cursor: pointer; margin: 0; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                                <span>Tải lên logo mới</span>
                                            </label>
                                            <button type="button" class="btn btn-ghost btn-sm" id="btn-remove-logo" style="color: #EF4444;" title="Xóa logo và dùng avatar chữ">
                                                Xóa logo
                                            </button>
                                        </div>
                                        <input type="file" id="field-logo-file" accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml" style="display:none;">
                                        <input type="hidden" id="field-logoUrl" value="<?= htmlspecialchars($enterprise['logoUrl'] ?? ''); ?>">
                                        <p class="ent-form-help" style="font-size: 0.75rem; color: var(--text-secondary); margin: 0;">
                                            Hỗ trợ tệp PNG, JPG, WebP, SVG (tối đa 3MB). Logo sẽ tự động cập nhật trên Header và Hồ sơ doanh nghiệp.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-email" class="ent-form-label">Email doanh nghiệp</label>
                                <input type="email" id="field-email" class="ent-form-input" value="<?= htmlspecialchars($enterprise['email'] ?? ''); ?>" maxlength="255" placeholder="contact@company.com">
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-phone" class="ent-form-label">Số điện thoại Hotline</label>
                                <input type="tel" id="field-phone" class="ent-form-input" value="<?= htmlspecialchars($enterprise['phone'] ?? ''); ?>" maxlength="30" placeholder="024 xxxx xxxx hoặc 09xx...">
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-website" class="ent-form-label">Website</label>
                                <input type="url" id="field-website" class="ent-form-input" value="<?= htmlspecialchars($enterprise['website'] ?? ''); ?>" maxlength="500" placeholder="https://company.vn">
                            </div>

                            <div class="ent-form-group col-md-6">
                                <label for="field-logoUrl-input" class="ent-form-label">Hoặc nhập URL ảnh Logo</label>
                                <input type="url" id="field-logoUrl-input" class="ent-form-input" value="<?= htmlspecialchars($enterprise['logoUrl'] ?? ''); ?>" maxlength="500" placeholder="https://domain.com/logo.png hoặc /assets/...">
                            </div>

                            <div class="ent-form-group col-12">
                                <label for="field-address" class="ent-form-label">Địa chỉ trụ sở chính</label>
                                <input type="text" id="field-address" class="ent-form-input" value="<?= htmlspecialchars($enterprise['address'] ?? ''); ?>" maxlength="500" placeholder="Số nhà, Đường, Phường/Xã, Quận/Huyện, Tỉnh/Thành phố...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ent-modal__footer">
                    <button type="button" class="btn btn-outline" id="btn-cancel-edit-modal">Huỷ</button>
                    <button type="submit" class="btn btn-primary" id="btn-save-profile">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification Toast for feedback -->
    <div class="ent-toast" id="ent-toast" aria-live="polite" aria-atomic="true">
        <div class="ent-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9 12l2 2 4-4"></path>
            </svg>
            <span class="ent-toast__message">Cập nhật hồ sơ thành công!</span>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="../../assets/js/enterprise.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/enterprise.js'); ?>"></script>
    <script src="../../assets/js/enterprise-profile.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/enterprise-profile.js'); ?>"></script>
</body>
</html>
