<?php
/**
 * Enterprise Dashboard - Hero Welcome Banner Component
 *
 * Standardized clean 2-column layout:
 * - Left: Tag, Greeting Title, Subtitle, and Primary/Secondary Buttons
 * - Right: Enterprise Building SVG Vector Graphic
 */

$enterpriseIndustry = (string) ($enterprise['industry'] ?? '');
$enterpriseName = (string) ($enterprise['name'] ?? ($enterpriseInfo['company_name'] ?? ''));
$companyDisplayName = $companyDisplayName ?? ($enterpriseInfo['company_name'] ?? ($enterpriseName ?: 'Doanh nghiệp'));

$isVinamilk = (stripos($enterpriseName, 'Vinamilk') !== false || stripos($enterpriseName, 'Sữa Việt Nam') !== false || stripos($enterpriseIndustry, 'FMCG') !== false);
$isFpt = (stripos($enterpriseName, 'FPT') !== false || stripos($enterpriseName, 'Phần mềm FPT') !== false);
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

        <!-- Big Bold Title: "Xin chào, {Company Name} 🏢" -->
        <h1 class="ent-hero-banner__title">
            <span>Xin chào, <?= htmlspecialchars($companyDisplayName); ?></span>
            <span class="ent-hero-banner__icon" role="img" aria-label="Biểu tượng doanh nghiệp"><?= $isVinamilk ? '🥛' : '🏢'; ?></span>
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

    <!-- Right Column: Brand Badge / Illustration Graphic -->
    <?php if ($isVinamilk): ?>
        <div class="ent-hero-banner__graphic ent-hero-banner__graphic--vinamilk" aria-hidden="true">
            <svg class="ent-hero-banner__brand-svg" viewBox="0 0 220 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="vnmGlowPastel" x1="110" y1="12" x2="110" y2="148" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#EFF6FF"/>
                        <stop offset="0.65" stop-color="#DBEAFE" stop-opacity="0.8"/>
                        <stop offset="1" stop-color="#F0FDF4" stop-opacity="0.4"/>
                    </linearGradient>
                    <linearGradient id="vnmCardGrad" x1="40" y1="20" x2="180" y2="140" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#FFFFFF"/>
                        <stop offset="100%" stop-color="#F8FAFC"/>
                    </linearGradient>
                    <linearGradient id="vnmBadgeGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#003B7A"/>
                        <stop offset="100%" stop-color="#002244"/>
                    </linearGradient>
                    <linearGradient id="vnmAccent" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#0284C7"/>
                        <stop offset="100%" stop-color="#003B7A"/>
                    </linearGradient>
                    <filter id="vnmShadow" x="15" y="15" width="190" height="135" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="#003B7A" flood-opacity="0.12"/>
                    </filter>
                </defs>

                <!-- Background ambient pastel glow circle -->
                <circle cx="110" cy="80" r="68" fill="url(#vnmGlowPastel)" opacity="0.95"/>

                <!-- Floating Card Badge Container -->
                <g filter="url(#vnmShadow)">
                    <rect x="25" y="24" width="170" height="112" rx="16" fill="url(#vnmCardGrad)" stroke="#E2E8F0" stroke-width="1.5"/>
                </g>

                <!-- Top Header Accent Line -->
                <path d="M26 40 C26 27.8 35.8 25 41 25 H179 C184.2 25 194 27.8 194 40" stroke="url(#vnmAccent)" stroke-width="3" stroke-linecap="round"/>

                <!-- Vinamilk Brand Logo Container -->
                <g transform="translate(42, 38)">
                    <!-- Vinamilk Shield / Emblem -->
                    <rect x="0" y="2" width="40" height="40" rx="10" fill="url(#vnmBadgeGrad)"/>
                    <!-- Milk drop / Nutrition leaf -->
                    <path d="M20,8 C20,8 10,19 10,25.5 C10,31 14.5,35 20,35 C25.5,35 30,31 30,25.5 C30,19 20,8 20,8 Z" fill="#FFFFFF"/>
                    <path d="M20,16 C20,16 14.5,23 14.5,27 C14.5,30 17,32 20,32 C23,32 25.5,30 25.5,27 C25.5,23 20,16 20,16 Z" fill="#003B7A" opacity="0.18"/>
                    <circle cx="17" cy="22" r="2" fill="#FFFFFF" opacity="0.9"/>

                    <!-- Vinamilk Typography -->
                    <text x="48" y="27" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="900" font-size="19" fill="#003B7A" letter-spacing="0.5px">VINAMILK</text>
                    <text x="49" y="39" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="700" font-size="8.5" fill="#64748B" letter-spacing="0.6px">VIETNAM DAIRY PRODUCTS</text>
                </g>

                <!-- Bottom Pill Badges: FMCG & Chuỗi cung ứng -->
                <g transform="translate(38, 95)">
                    <!-- Tag 1: FMCG & Dinh dưỡng -->
                    <rect x="0" y="0" width="70" height="22" rx="11" fill="#EFF6FF" stroke="#BFDBFE" stroke-width="1"/>
                    <circle cx="10" cy="11" r="3.5" fill="#0284C7"/>
                    <text x="18" y="14.5" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="700" font-size="8.5" fill="#0369A1">FMCG Leader</text>

                    <!-- Tag 2: Top Doanh nghiệp -->
                    <rect x="76" y="0" width="68" height="22" rx="11" fill="#F0FDF4" stroke="#BBF7D0" stroke-width="1"/>
                    <circle cx="86" cy="11" r="3.5" fill="#16A34A"/>
                    <text x="94" y="14.5" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="700" font-size="8.5" fill="#15803D">Verified Biz</text>
                </g>

                <!-- Decorative Organic Floating Nodes -->
                <circle cx="20" cy="45" r="4" fill="#0284C7" opacity="0.3"/>
                <circle cx="202" cy="115" r="5" fill="#003B7A" opacity="0.25"/>
                <circle cx="198" cy="35" r="3" fill="#16A34A" opacity="0.35"/>
            </svg>
        </div>
    <?php elseif ($isFpt): ?>
        <div class="ent-hero-banner__graphic ent-hero-banner__graphic--fpt" aria-hidden="true">
            <svg class="ent-hero-banner__brand-svg" viewBox="0 0 220 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="fptGlowPastel" x1="110" y1="12" x2="110" y2="148" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#FFF7ED"/>
                        <stop offset="0.65" stop-color="#FFEDD5" stop-opacity="0.75"/>
                        <stop offset="1" stop-color="#EFF6FF" stop-opacity="0.35"/>
                    </linearGradient>
                    <linearGradient id="fptCardGrad" x1="40" y1="20" x2="180" y2="140" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#FFFFFF"/>
                        <stop offset="100%" stop-color="#F8FAFC"/>
                    </linearGradient>
                    <linearGradient id="fptBluePill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#0072BC"/>
                        <stop offset="100%" stop-color="#00549A"/>
                    </linearGradient>
                    <linearGradient id="fptOrangePill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#F58220"/>
                        <stop offset="100%" stop-color="#E65A00"/>
                    </linearGradient>
                    <linearGradient id="fptGreenPill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#58B947"/>
                        <stop offset="100%" stop-color="#388E3C"/>
                    </linearGradient>
                    <filter id="fptShadow" x="15" y="15" width="190" height="135" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="#F97316" flood-opacity="0.12"/>
                    </filter>
                </defs>

                <!-- Background ambient pastel glow circle -->
                <circle cx="110" cy="80" r="68" fill="url(#fptGlowPastel)" opacity="0.95"/>

                <!-- Floating Card Badge Container -->
                <g filter="url(#fptShadow)">
                    <rect x="25" y="24" width="170" height="112" rx="16" fill="url(#fptCardGrad)" stroke="#E2E8F0" stroke-width="1.5"/>
                </g>

                <!-- Top Header Accent Line -->
                <path d="M26 40 C26 27.8 35.8 25 41 25 H179 C184.2 25 194 27.8 194 40" stroke="#F97316" stroke-width="3" stroke-linecap="round"/>

                <!-- FPT Software Logo Container -->
                <g transform="translate(36, 40)">
                    <!-- F Pill -->
                    <g transform="translate(0, 0)">
                        <rect x="0" y="2" width="26" height="34" rx="9" fill="url(#fptBluePill)" transform="skewX(-13)"/>
                        <text x="11" y="25" font-family="'Segoe UI', Roboto, sans-serif" font-weight="900" font-size="18" fill="#FFFFFF" text-anchor="middle" font-style="italic">F</text>
                    </g>
                    <!-- P Pill -->
                    <g transform="translate(23, 0)">
                        <rect x="0" y="2" width="26" height="34" rx="9" fill="url(#fptOrangePill)" transform="skewX(-13)"/>
                        <text x="11" y="25" font-family="'Segoe UI', Roboto, sans-serif" font-weight="900" font-size="18" fill="#FFFFFF" text-anchor="middle" font-style="italic">P</text>
                    </g>
                    <!-- T Pill -->
                    <g transform="translate(46, 0)">
                        <rect x="0" y="2" width="26" height="34" rx="9" fill="url(#fptGreenPill)" transform="skewX(-13)"/>
                        <text x="11" y="25" font-family="'Segoe UI', Roboto, sans-serif" font-weight="900" font-size="18" fill="#FFFFFF" text-anchor="middle" font-style="italic">T</text>
                    </g>
                    <!-- Wordmark Software -->
                    <text x="79" y="26" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="800" font-size="17" fill="#0F172A" letter-spacing="-0.3px">Software</text>
                    <text x="80" y="37" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="700" font-size="8.5" fill="#64748B" letter-spacing="0.5px">GLOBAL IT & AI</text>
                </g>

                <!-- Bottom Pill Badges: Tech & AI Leader -->
                <g transform="translate(38, 95)">
                    <!-- Tag 1: IT & AI -->
                    <rect x="0" y="0" width="70" height="22" rx="11" fill="#FFF7ED" stroke="#FED7AA" stroke-width="1"/>
                    <circle cx="10" cy="11" r="3.5" fill="#EA580C"/>
                    <text x="18" y="14.5" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="700" font-size="8.5" fill="#C2410C">AI & Cloud</text>

                    <!-- Tag 2: Top Employer -->
                    <rect x="76" y="0" width="68" height="22" rx="11" fill="#EFF6FF" stroke="#BFDBFE" stroke-width="1"/>
                    <circle cx="86" cy="11" r="3.5" fill="#2563EB"/>
                    <text x="94" y="14.5" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-weight="700" font-size="8.5" fill="#1D4ED8">Top Tech Biz</text>
                </g>

                <!-- Tech Circuit Nodes -->
                <circle cx="20" cy="45" r="4" fill="#0072BC" opacity="0.3"/>
                <circle cx="202" cy="115" r="5" fill="#F58220" opacity="0.3"/>
                <circle cx="198" cy="35" r="3.5" fill="#58B947" opacity="0.35"/>
            </svg>
        </div>
    <?php else: ?>
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
    <?php endif; ?>
</section>
