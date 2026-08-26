<?php
/**
 * Enterprise Dashboard - Hero Welcome Banner Component
 * 
 * Standardized clean 2-column layout:
 * - Left: Tag, Greeting Title, Subtitle, and Primary/Secondary Buttons
 * - Right: Enterprise Building SVG Vector Graphic
 */

$companyDisplayName = !empty($enterpriseInfo['company_name']) ? $enterpriseInfo['company_name'] : 'FPT Software';
?>
<section class="ent-hero-banner" aria-label="Chào mừng Doanh nghiệp">
    <!-- Left Column: Content -->
    <div class="ent-hero-banner__content">
        <!-- Small Top Tag: [DASHBOARD DOANH NGHIỆP] -->
        <div class="ent-hero-banner__tag">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>DASHBOARD DOANH NGHIỆP</span>
        </div>

        <!-- Big Bold Title: "Xin chào, FPT Software 🏢" -->
        <h1 class="ent-hero-banner__title">
            <span>Xin chào, <?= htmlspecialchars($companyDisplayName); ?></span>
            <span class="ent-hero-banner__icon" role="img" aria-label="Tòa nhà doanh nghiệp">🏢</span>
        </h1>

        <!-- High-Contrast Subtitle -->
        <p class="ent-hero-banner__subtitle">
            Hệ thống ghi nhận tin tuyển dụng và hồ sơ ứng tuyển mới sẵn sàng xử lý.
        </p>

        <!-- Action Buttons -->
        <div class="ent-hero-banner__actions">
            <a href="<?= app_href('/app/enterprise/internships/create.php'); ?>" class="ent-hero-btn ent-hero-btn--primary" data-route="/app/enterprise/internships/">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <span>Đăng tin tuyển dụng</span>
            </a>

            <a href="<?= app_href('/app/enterprise/internships/applicants.php'); ?>" class="ent-hero-btn ent-hero-btn--outline" data-route="/app/enterprise/internships/">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <span>Quản lý hồ sơ</span>
            </a>
        </div>
    </div>

    <!-- Right Column: Enterprise Building SVG Vector Graphic -->
    <div class="ent-hero-banner__graphic" aria-hidden="true">
        <svg class="ent-hero-banner__building-svg" viewBox="0 0 220 160" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Background ambient pastel glow circle -->
            <circle cx="110" cy="80" r="68" fill="url(#fptGlowPastel)" opacity="0.9"/>
            
            <defs>
                <linearGradient id="fptGlowPastel" x1="110" y1="12" x2="110" y2="148" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#FFF7ED"/>
                    <stop offset="0.65" stop-color="#FFEDD5" stop-opacity="0.65"/>
                    <stop offset="1" stop-color="#EFF6FF" stop-opacity="0.35"/>
                </linearGradient>
                <linearGradient id="towerGrad" x1="100" y1="20" x2="100" y2="150" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#FFFFFF"/>
                    <stop offset="1" stop-color="#F1F5F9"/>
                </linearGradient>
                <linearGradient id="glassFacet" x1="110" y1="30" x2="140" y2="140" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#EFF6FF" stop-opacity="0.9"/>
                    <stop offset="1" stop-color="#DBEAFE" stop-opacity="0.5"/>
                </linearGradient>
                <linearGradient id="brandOrange" x1="0" y1="0" x2="0" y2="1" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#F97316"/>
                    <stop offset="1" stop-color="#EA580C"/>
                </linearGradient>
            </defs>

            <!-- Base Ground Line -->
            <path d="M15 146 H205" stroke="#E2E8F0" stroke-width="2" stroke-linecap="round"/>
            <path d="M45 146 H175" stroke="#F97316" stroke-width="2.5" stroke-opacity="0.45" stroke-linecap="round"/>

            <!-- Left Wing Building -->
            <rect x="44" y="70" width="34" height="76" rx="3" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5"/>
            <line x1="52" y1="82" x2="70" y2="82" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
            <line x1="52" y1="94" x2="70" y2="94" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
            <line x1="52" y1="106" x2="70" y2="106" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
            <line x1="52" y1="118" x2="70" y2="118" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>

            <!-- Right Wing Building -->
            <rect x="142" y="80" width="32" height="66" rx="3" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5"/>
            <line x1="150" y1="92" x2="166" y2="92" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
            <line x1="150" y1="104" x2="166" y2="104" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
            <line x1="150" y1="116" x2="166" y2="116" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>

            <!-- Central Main Tower Spire -->
            <line x1="110" y1="12" x2="110" y2="28" stroke="#F97316" stroke-width="2.5" stroke-linecap="round"/>
            <circle cx="110" cy="11" r="2.5" fill="#EA580C"/>

            <!-- Tower Top Crown -->
            <path d="M96 28 H124 L121 38 H99 Z" fill="#1E293B" stroke="#0F172A" stroke-width="1"/>
            <rect x="103" y="31" width="14" height="4" rx="1" fill="#F97316"/>

            <!-- Upper Tower Block -->
            <rect x="85" y="38" width="50" height="42" rx="2" fill="url(#towerGrad)" stroke="#94A3B8" stroke-width="1.5"/>
            <rect x="110" y="38" width="25" height="42" fill="url(#glassFacet)"/>

            <line x1="92" y1="48" x2="128" y2="48" stroke="#3B82F6" stroke-opacity="0.35" stroke-width="1.5"/>
            <line x1="92" y1="58" x2="128" y2="58" stroke="#3B82F6" stroke-opacity="0.35" stroke-width="1.5"/>
            <line x1="92" y1="68" x2="128" y2="68" stroke="#3B82F6" stroke-opacity="0.35" stroke-width="1.5"/>

            <!-- Middle Band (Orange Accent) -->
            <rect x="82" y="80" width="56" height="5" rx="1.5" fill="url(#brandOrange)"/>

            <!-- Lower Main Tower Body -->
            <rect x="80" y="85" width="60" height="61" rx="2" fill="url(#towerGrad)" stroke="#64748B" stroke-width="1.5"/>
            <rect x="110" y="85" width="30" height="61" fill="url(#glassFacet)"/>

            <line x1="95" y1="85" x2="95" y2="146" stroke="#CBD5E1" stroke-width="1.2"/>
            <line x1="110" y1="85" x2="110" y2="146" stroke="#94A3B8" stroke-width="1.5"/>
            <line x1="125" y1="85" x2="125" y2="146" stroke="#CBD5E1" stroke-width="1.2"/>

            <line x1="84" y1="97" x2="136" y2="97" stroke="#94A3B8" stroke-width="1.2"/>
            <line x1="84" y1="109" x2="136" y2="109" stroke="#94A3B8" stroke-width="1.2"/>
            <line x1="84" y1="121" x2="136" y2="121" stroke="#94A3B8" stroke-width="1.2"/>
            <line x1="84" y1="133" x2="136" y2="133" stroke="#94A3B8" stroke-width="1.2"/>

            <!-- Entrance Canopy -->
            <path d="M96 146 V137 H124 V146" fill="#1E293B"/>
            <rect x="94" y="136" width="32" height="3" fill="#F97316"/>
            <rect x="104" y="140" width="12" height="6" fill="#60A5FA" opacity="0.8"/>

            <!-- Tech Decorative Nodes -->
            <circle cx="32" cy="50" r="3" fill="#2563EB" opacity="0.35"/>
            <circle cx="188" cy="45" r="4" fill="#F97316" opacity="0.35"/>
            <path d="M25 50 L32 50 L40 62" stroke="#2563EB" stroke-opacity="0.25" stroke-width="1" stroke-dasharray="2 2"/>
            <path d="M195 45 L188 45 L175 60" stroke="#F97316" stroke-opacity="0.25" stroke-width="1" stroke-dasharray="2 2"/>
        </svg>
    </div>
</section>
