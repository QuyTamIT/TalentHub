<?php
/**
 * Enterprise Dashboard - Header Component
 * 
 * Note for Junior Developers:
 * - Header displays current page title, mobile toggle, mock notifications bell, and enterprise account dropdown.
 * - Notification bell is UI mock only (no API/database).
 * - Account dropdown provides quick company identity, profile navigation, and secure logout.
 */

// Ensure app_href() is available regardless of whether the caller loaded bootstrap.
if (!function_exists('app_href') && is_file(__DIR__ . '/../../../bin/bootstrap.php')) {
    require_once __DIR__ . '/../../../bin/bootstrap.php';
}

$basePrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/TalentHub') !== false) ? '/TalentHub' : '';
$profileRoute = '/app/enterprise/profile.php';
$logoutUrl = function_exists('app_href') ? app_href('/logout.php') : ($basePrefix . '/logout.php');
$profileUrl = function_exists('app_href') ? app_href($profileRoute) : ($basePrefix . $profileRoute);
?>
<header class="ent-header">
    <div class="ent-header__left">
        <!-- Mobile Sidebar Toggle -->
        <button class="ent-header__toggle" id="ent-sidebar-toggle" aria-label="Mở danh mục điều hướng">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <h1 class="ent-header__title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tổng quan Doanh nghiệp'; ?></h1>
    </div>

    <div class="ent-header__right">
        <!-- Notification Bell (UI Mock Only) -->
        <div class="ent-header__notif" id="ent-notif-trigger" title="Thông báo mới (Mock UI)">
            <button class="ent-header__icon-btn" aria-label="Thông báo mới">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="ent-header__badge">3</span>
            </button>
        </div>

        <!-- Enterprise Account Area with Dropdown -->
        <div class="ent-header__account-wrapper" id="ent-account-wrapper">
            <button 
                type="button" 
                class="ent-header__account" 
                id="ent-account-trigger" 
                aria-haspopup="menu" 
                aria-expanded="false" 
                aria-controls="ent-account-menu"
                aria-label="Tài khoản doanh nghiệp: <?= htmlspecialchars($enterpriseInfo['company_name'] ?? 'FPT Software'); ?>"
            >
                <div class="ent-header__avatar" aria-hidden="true">
                    <?php 
                    $resolvedHeaderLogo = !empty($enterpriseInfo['logo_url']) ? (function_exists('resolve_logo_url') ? resolve_logo_url($enterpriseInfo['logo_url']) : $enterpriseInfo['logo_url']) : null;
                    if ($resolvedHeaderLogo): ?>
                        <img src="<?= htmlspecialchars($resolvedHeaderLogo); ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:2px;border-radius:inherit;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                        <span class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($enterpriseInfo['logo_initials'] ?? 'DN'); ?></span>
                    <?php else: ?>
                        <span class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($enterpriseInfo['logo_initials'] ?? 'DN'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="ent-header__user-info">
                    <span class="ent-header__company-name"><?= htmlspecialchars($enterpriseInfo['company_name'] ?? 'Doanh nghiệp'); ?></span>
                    <span class="ent-header__package-name"><?= htmlspecialchars($enterpriseInfo['account_type'] ?? 'Tài khoản Doanh nghiệp'); ?></span>
                </div>
                <span class="ent-header__chevron" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </button>

            <!-- Account Dropdown Menu -->
            <div 
                class="ent-account-menu" 
                id="ent-account-menu" 
                role="menu" 
                aria-labelledby="ent-account-trigger"
                hidden
            >
                <!-- Company Identity Header -->
                <div class="ent-account-menu__identity" role="none">
                    <div class="ent-account-menu__avatar" aria-hidden="true">
                        <?php if ($resolvedHeaderLogo): ?>
                            <img src="<?= htmlspecialchars($resolvedHeaderLogo); ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:2px;border-radius:inherit;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                            <span class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($enterpriseInfo['logo_initials'] ?? 'DN'); ?></span>
                        <?php else: ?>
                            <span class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($enterpriseInfo['logo_initials'] ?? 'DN'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ent-account-menu__details">
                        <span class="ent-account-menu__company-name"><?= htmlspecialchars($enterpriseInfo['company_name'] ?? 'Doanh nghiệp'); ?></span>
                        <span class="ent-account-menu__badge"><?= htmlspecialchars($enterpriseInfo['account_type'] ?? 'Tài khoản Doanh nghiệp'); ?></span>
                    </div>
                </div>

                <div class="ent-account-menu__divider" role="separator"></div>

                <!-- Navigation List -->
                <ul class="ent-account-menu__list" role="none">
                    <li role="none">
                        <a 
                            href="<?= htmlspecialchars($profileUrl); ?>" 
                            class="ent-account-menu__item" 
                            role="menuitem"
                            data-route="<?= htmlspecialchars($profileRoute); ?>"
                            tabindex="-1"
                        >
                            <svg class="ent-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>Hồ sơ doanh nghiệp</span>
                        </a>
                    </li>
                </ul>

                <div class="ent-account-menu__divider" role="separator"></div>

                <!-- Logout Link -->
                <ul class="ent-account-menu__list" role="none">
                    <li role="none">
                        <a 
                            href="<?= htmlspecialchars($logoutUrl); ?>" 
                            class="ent-account-menu__item ent-account-menu__item--logout" 
                            role="menuitem"
                            tabindex="-1"
                        >
                            <svg class="ent-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Đăng xuất</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
