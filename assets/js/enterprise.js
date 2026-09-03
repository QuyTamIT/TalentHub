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
    initAccountDropdown();
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
   2. Enterprise Account Dropdown Menu Handler
   ========================================================================== */
function initAccountDropdown() {
    const trigger = document.getElementById('ent-account-trigger');
    const menu = document.getElementById('ent-account-menu');
    const wrapper = document.getElementById('ent-account-wrapper');

    if (!trigger || !menu) return;

    const menuItems = Array.from(menu.querySelectorAll('[role="menuitem"]'));

    function openMenu() {
        trigger.setAttribute('aria-expanded', 'true');
        menu.removeAttribute('hidden');
        menu.classList.add('is-open');
    }

    function closeMenu(focusTrigger = false) {
        trigger.setAttribute('aria-expanded', 'false');
        menu.setAttribute('hidden', '');
        menu.classList.remove('is-open');
        if (focusTrigger) {
            trigger.focus();
        }
    }

    function toggleMenu() {
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        if (isExpanded) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    // Toggle on trigger click
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMenu();
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (wrapper && !wrapper.contains(e.target)) {
            closeMenu();
        }
    });

    // Keyboard navigation on trigger button
    trigger.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openMenu();
            if (menuItems.length > 0) {
                menuItems[0].focus();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            openMenu();
            if (menuItems.length > 0) {
                menuItems[menuItems.length - 1].focus();
            }
        } else if (e.key === 'Escape') {
            if (trigger.getAttribute('aria-expanded') === 'true') {
                e.preventDefault();
                closeMenu(true);
            }
        }
    });

    // Keyboard navigation inside dropdown menu
    menu.addEventListener('keydown', (e) => {
        const currentFocusedIndex = menuItems.indexOf(document.activeElement);

        if (e.key === 'Escape') {
            e.preventDefault();
            closeMenu(true);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = (currentFocusedIndex + 1) % menuItems.length;
            menuItems[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = (currentFocusedIndex - 1 + menuItems.length) % menuItems.length;
            menuItems[prevIndex].focus();
        } else if (e.key === 'Tab') {
            // Close menu when tabbing away
            closeMenu();
        }
    });
}

/* ==========================================================================
   3. Mock Notification Bell Handler
   ========================================================================== */
function initNotificationBell() {
    // Notifications are handled by the shared portal-notifications module.
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
                route.startsWith('/app/enterprise/analytics') ||
                route === '/app/enterprise/profile' ||
                route === '/app/enterprise/profile.php' ||
                route.startsWith('/app/enterprise/profile')
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
            const talentName = btn.getAttribute('data-talent-name');
            const action = btn.getAttribute('data-action');

            if (action === 'view') {
                showEntToast(`Đang mở chi tiết Hồ sơ ứng viên ${talentName || '#' + talentId}...`);
            } else if (action === 'contact') {
                showEntToast(`Đã gửi yêu cầu kết nối tới ứng viên ${talentName || '#' + talentId}.`);
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
