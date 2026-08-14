/**
 * TalentHub - Enterprise Dashboard Scripts
 * Handles mobile/tablet sidebar drawer, backdrop toggle, notification popups,
 * talent action buttons, and temporary route fallback notifications.
 * 
 * Note for Junior Developers:
 * - When future sub-modules (/app/enterprise/talents, /app/enterprise/internships, etc.) are built,
 *   update the navigation handlers to perform real route redirection.
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileSidebar();
    initNotificationBell();
    initRouteNavigation();
    initTalentActions();
});

/* ==========================================================================
   1. Mobile & Tablet Sidebar Navigation Drawer Toggle
   ========================================================================== */
function initMobileSidebar() {
    const toggleBtn = document.getElementById('ent-sidebar-toggle');
    const sidebar = document.getElementById('ent-sidebar');
    const backdrop = document.getElementById('ent-sidebar-backdrop');

    if (!toggleBtn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-active');
        document.body.classList.add('ent-sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-active');
        document.body.classList.remove('ent-sidebar-open');
    }

    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // Close sidebar when clicking outside on smaller screens
    document.addEventListener('click', (e) => {
        if (sidebar.classList.contains('is-open') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            closeSidebar();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
}

/* ==========================================================================
   2. Mock Notification Bell Handler
   ========================================================================== */
function initNotificationBell() {
    const notifBtn = document.getElementById('ent-notif-trigger');
    if (!notifBtn) return;

    notifBtn.addEventListener('click', () => {
        showEntToast('Bạn có 3 thông báo mới: 2 ứng viên ứng tuyển & 1 cập nhật tài trợ.');
    });
}

/* ==========================================================================
   3. Temporary Routes Navigation Handler
   ========================================================================== */
function initRouteNavigation() {
    const routeLinks = document.querySelectorAll('[data-route]');

    routeLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const route = link.getAttribute('data-route');
            
            // Allow default navigation if clicking implemented pages
            if (
                route === '/app/enterprise' || 
                route === '/app/enterprise/index.php' ||
                route === '/app/enterprise/talents' ||
                route === '/app/enterprise/talents.php' ||
                route === '/app/enterprise/talents/' ||
                route === '/app/enterprise/talents/index.php' ||
                route.includes('/app/enterprise/talents/detail.php') ||
                route === '/app/enterprise/internships' ||
                route === '/app/enterprise/internships/' ||
                route === '/app/enterprise/internships/index.php' ||
                route.startsWith('/app/enterprise/internships') ||
                route === '/app/enterprise/sponsorships' ||
                route === '/app/enterprise/sponsorships/' ||
                route === '/app/enterprise/sponsorships.php' ||
                route === '/app/enterprise/sponsorships/index.php' ||
                route.startsWith('/app/enterprise/sponsorships') ||
                route === '/app/enterprise/analytics' ||
                route === '/app/enterprise/analytics.php' ||
                route.startsWith('/app/enterprise/analytics')
            ) {
                return;
            }

            // For pending sub-modules, display clean toast feedback
            e.preventDefault();
            const linkText = link.textContent.trim().replace(/\s+/g, ' ');
            showEntToast(`Tính năng "${linkText}" (${route}) đang được phát triển!`);
        });
    });
}

/* ==========================================================================
   4. Featured Talent Card Action Buttons
   ========================================================================== */
function initTalentActions() {
    const talentBtns = document.querySelectorAll('.ent-talent-btn');

    talentBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const talentId = btn.getAttribute('data-talent-id');
            const action = btn.getAttribute('data-action');

            if (action === 'view') {
                showEntToast(`Đang mở chi tiết Hồ sơ ứng viên #${talentId}...`);
            } else if (action === 'contact') {
                showEntToast(`Đã gửi yêu cầu kết nối tới Ứng viên #${talentId}.`);
            }
        });
    });
}

/* ==========================================================================
   5. Toast Notification System
   ========================================================================== */
let entToastTimeout = null;

function showEntToast(message) {
    const toast = document.getElementById('ent-toast');
    if (!toast) return;

    const messageEl = toast.querySelector('.ent-toast__message');
    if (messageEl) {
        messageEl.textContent = message;
    }

    toast.classList.add('is-visible');

    entToastTimeout = setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 4000);
}

window.showEntToast = showEntToast;

