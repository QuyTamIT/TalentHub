<?php
/**
 * School Dashboard - Welcome Banner Component
 */
?>
<section class="school-welcome">
    <div class="school-welcome__body">
        <div class="school-welcome__content">
            <span class="school-welcome__tag">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                Khu vực Nhà trường
            </span>
            <h2 class="school-welcome__title">Xin chào, Ban Giám hiệu <?= htmlspecialchars($schoolInfo['name']); ?>!</h2>
            <p class="school-welcome__description">
                Theo dõi tổng quan hoạt động đào tạo của trường, quản lý hồ sơ học sinh / sinh viên và xem báo cáo chi tiết về tiềm năng phát triển tài năng trong năm học <?= htmlspecialchars($schoolInfo['academic_year']); ?>.
            </p>
            <div class="school-welcome__actions">
                <a href="./analytics.php" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    Xem phân tích
                </a>
                <a href="./reports.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    Tạo báo cáo
                </a>
            </div>
        </div>
        <div class="school-welcome__graphic">
            <svg width="180" height="140" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- School Building Illustration -->
                <rect x="20" y="60" width="140" height="70" rx="4" fill="#EFF6FF" stroke="#3B82F6" stroke-width="2"/>
                <!-- Windows -->
                <rect x="35" y="75" width="25" height="20" rx="2" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.5"/>
                <rect x="70" y="75" width="25" height="20" rx="2" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.5"/>
                <rect x="105" y="75" width="25" height="20" rx="2" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.5"/>
                <!-- Door -->
                <rect x="77" y="105" width="26" height="25" rx="2" fill="#3B82F6"/>
                <circle cx="97" cy="118" r="2" fill="#EFF6FF"/>
                <!-- Roof -->
                <path d="M10 62 L90 20 L170 62" stroke="#3B82F6" stroke-width="2" fill="#DBEAFE"/>
                <!-- Flag pole -->
                <line x1="90" y1="20" x2="90" y2="5" stroke="#3B82F6" stroke-width="2"/>
                <circle cx="90" cy="5" r="3" fill="#3B82F6"/>
                <!-- Small decorative elements -->
                <circle cx="40" cy="25" r="4" fill="#93C5FD" opacity="0.6"/>
                <circle cx="140" cy="35" r="6" fill="#93C5FD" opacity="0.4"/>
                <circle cx="155" cy="15" r="3" fill="#BFDBFE" opacity="0.5"/>
            </svg>
        </div>
    </div>
</section>
