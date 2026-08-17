<?php
/**
 * Enterprise Dashboard - Welcome Banner Component
 * 
 * Refined with modern enterprise gradient styling and FPT building tower SVG illustration.
 */
?>
<section class="ent-welcome">
    <div class="ent-welcome__body">
        <div class="ent-welcome__content">
            <div class="ent-welcome__tag">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Dashboard Doanh nghiệp</span>
            </div>
            <h2 class="ent-welcome__title">Xin chào, <?= htmlspecialchars($enterpriseInfo['company_name']); ?></h2>
            <p class="ent-welcome__description">
                Hệ thống vừa tự động phân tích và kết nối <strong style="color: var(--text-primary); font-weight: 700;"><?= htmlspecialchars($enterpriseInfo['new_matches_count']); ?> hồ sơ năng lực mới</strong> phù hợp với nhu cầu tuyển dụng của doanh nghiệp trong tuần này.
            </p>
            <div class="ent-welcome__actions">
                <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="btn btn-primary" data-route="/app/enterprise/talents.php">
                    Xem nhân tài
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="<?= app_href('/app/enterprise/internships/'); ?>" class="btn btn-secondary" data-route="/app/enterprise/internships/">
                    Đăng tin tuyển dụng
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </a>
            </div>
        </div>
        
        <!-- FPT Building/Tower Visual Illustration -->
        <div class="ent-welcome__graphic" aria-hidden="true">
            <svg class="ent-welcome__building-svg" viewBox="0 0 220 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Background ambient glow -->
                <circle cx="110" cy="80" r="65" fill="url(#fptGlow)" opacity="0.65"/>
                
                <defs>
                    <linearGradient id="fptGlow" x1="110" y1="15" x2="110" y2="145" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#F97316" stop-opacity="0.15"/>
                        <stop offset="1" stop-color="#2563EB" stop-opacity="0.04"/>
                    </linearGradient>
                    <linearGradient id="towerGradMain" x1="100" y1="20" x2="100" y2="150" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#FFFFFF"/>
                        <stop offset="1" stop-color="#F8FAFC"/>
                    </linearGradient>
                    <linearGradient id="glassFacet" x1="110" y1="30" x2="140" y2="140" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#EFF6FF" stop-opacity="0.8"/>
                        <stop offset="1" stop-color="#DBEAFE" stop-opacity="0.4"/>
                    </linearGradient>
                    <linearGradient id="brandOrangeAccent" x1="0" y1="0" x2="0" y2="1" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#F97316"/>
                        <stop offset="1" stop-color="#EA580C"/>
                    </linearGradient>
                </defs>

                <!-- Base Plaza Line -->
                <path d="M15 146 H205" stroke="#E2E8F0" stroke-width="2" stroke-linecap="round"/>
                <path d="M40 146 H180" stroke="#F97316" stroke-width="2" stroke-opacity="0.3" stroke-linecap="round"/>

                <!-- Background Side Building (Secondary Wing) -->
                <rect x="45" y="70" width="34" height="76" rx="3" fill="#F1F5F9" stroke="#CBD5E1" stroke-width="1.5"/>
                <!-- Windows Wing Left -->
                <line x1="53" y1="82" x2="71" y2="82" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
                <line x1="53" y1="94" x2="71" y2="94" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
                <line x1="53" y1="106" x2="71" y2="106" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
                <line x1="53" y1="118" x2="71" y2="118" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>

                <!-- Right Side Secondary Wing -->
                <rect x="141" y="80" width="32" height="66" rx="3" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5"/>
                <line x1="149" y1="92" x2="165" y2="92" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
                <line x1="149" y1="104" x2="165" y2="104" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>
                <line x1="149" y1="116" x2="165" y2="116" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 2"/>

                <!-- Central FPT Tower Main Structure -->
                <!-- Spire / Antenna -->
                <line x1="110" y1="12" x2="110" y2="28" stroke="#F97316" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="110" cy="11" r="2.5" fill="#EA580C"/>

                <!-- Tower Top Crown Tier -->
                <path d="M96 28 H124 L121 38 H99 Z" fill="#1E293B" stroke="#0F172A" stroke-width="1"/>
                <rect x="103" y="31" width="14" height="4" rx="1" fill="#F97316"/>

                <!-- Upper Tower Block -->
                <rect x="85" y="38" width="50" height="42" rx="2" fill="url(#towerGradMain)" stroke="#94A3B8" stroke-width="1.5"/>
                <rect x="110" y="38" width="25" height="42" fill="url(#glassFacet)"/>

                <!-- Upper Glass Windows Grid -->
                <line x1="92" y1="48" x2="128" y2="48" stroke="#3B82F6" stroke-opacity="0.4" stroke-width="1.5"/>
                <line x1="92" y1="58" x2="128" y2="58" stroke="#3B82F6" stroke-opacity="0.4" stroke-width="1.5"/>
                <line x1="92" y1="68" x2="128" y2="68" stroke="#3B82F6" stroke-opacity="0.4" stroke-width="1.5"/>

                <!-- Middle Tier Transition Band (Orange Accent) -->
                <rect x="82" y="80" width="56" height="5" rx="1.5" fill="url(#brandOrangeAccent)"/>

                <!-- Lower Main Tower Body -->
                <rect x="80" y="85" width="60" height="61" rx="2" fill="url(#towerGradMain)" stroke="#64748B" stroke-width="1.5"/>
                <rect x="110" y="85" width="30" height="61" fill="url(#glassFacet)"/>

                <!-- Facade Architectural Columns -->
                <line x1="95" y1="85" x2="95" y2="146" stroke="#CBD5E1" stroke-width="1.2"/>
                <line x1="110" y1="85" x2="110" y2="146" stroke="#94A3B8" stroke-width="1.5"/>
                <line x1="125" y1="85" x2="125" y2="146" stroke="#CBD5E1" stroke-width="1.2"/>

                <!-- Horizontal Floor Lines -->
                <line x1="84" y1="97" x2="136" y2="97" stroke="#94A3B8" stroke-width="1.2"/>
                <line x1="84" y1="109" x2="136" y2="109" stroke="#94A3B8" stroke-width="1.2"/>
                <line x1="84" y1="121" x2="136" y2="121" stroke="#94A3B8" stroke-width="1.2"/>
                <line x1="84" y1="133" x2="136" y2="133" stroke="#94A3B8" stroke-width="1.2"/>

                <!-- Grand Enterprise Entrance Canopy -->
                <path d="M96 146 V137 H124 V146" fill="#1E293B"/>
                <rect x="94" y="136" width="32" height="3" fill="#F97316"/>
                <rect x="104" y="140" width="12" height="6" fill="#60A5FA" opacity="0.8"/>

                <!-- Decorative Tech Floating Nodes -->
                <circle cx="32" cy="50" r="3" fill="#2563EB" opacity="0.3"/>
                <circle cx="188" cy="45" r="4" fill="#F97316" opacity="0.3"/>
                <path d="M25 50 L32 50 L40 62" stroke="#2563EB" stroke-opacity="0.2" stroke-width="1" stroke-dasharray="2 2"/>
                <path d="M195 45 L188 45 L175 60" stroke="#F97316" stroke-opacity="0.2" stroke-width="1" stroke-dasharray="2 2"/>
            </svg>
        </div>
    </div>
</section>
