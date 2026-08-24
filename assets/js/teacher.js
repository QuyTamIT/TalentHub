/**
 * TalentHub - Teacher Dashboard Scripts
 * Synchronized with Enterprise UI/UX Design System.
 */

document.addEventListener('DOMContentLoaded', () => {
    initTeacherSidebar();
    initTeacherNotifications();
    initTeacherAccountDropdown();
    initTeacherRoutes();
    initTeacherFocusOnLoad();
});

function initTeacherSidebar() {
    const toggleBtn = document.getElementById('teacher-sidebar-toggle');
    const sidebar = document.getElementById('teacher-sidebar');
    const backdrop = document.getElementById('teacher-sidebar-backdrop');

    if (!toggleBtn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-active');
        document.body.classList.add('teacher-sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-active');
        document.body.classList.remove('teacher-sidebar-open');
    }

    toggleBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('click', (event) => {
        if (sidebar.classList.contains('is-open') && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
}

/**
 * Teacher Profile Dropdown Menu Handler
 */
function initTeacherAccountDropdown() {
    const trigger = document.getElementById('teacher-account-trigger');
    const menu = document.getElementById('teacher-account-menu');
    const wrapper = document.getElementById('teacher-account-wrapper');

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
        const currentIndex = menuItems.indexOf(document.activeElement);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = (currentIndex + 1) % menuItems.length;
            menuItems[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = (currentIndex - 1 + menuItems.length) % menuItems.length;
            menuItems[prevIndex].focus();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeMenu(true);
        } else if (e.key === 'Tab') {
            closeMenu(false);
        }
    });
}

function initTeacherNotifications() {
    const trigger = document.getElementById('teacher-notif-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', () => {
        showTeacherToast('Chưa có thông báo mới.');
    });
}

function initTeacherRoutes() {
    const links = document.querySelectorAll('[data-route]');

    links.forEach(link => {
        link.addEventListener('click', (event) => {
            const route = link.getAttribute('data-route');
            const href = link.getAttribute('href') || '';

            if (href.trim() !== '' && href.trim() !== '#') {
                return;
            }

            event.preventDefault();
            const label = link.textContent.trim().replace(/\s+/g, ' ');
            showTeacherToast(`Tính năng "${label}" (${route}) đang được phát triển.`);
        });
    });
}

function initTeacherFocusOnLoad() {
    const target = document.querySelector('[data-focus-on-load]');
    if (!target) return;

    requestAnimationFrame(() => {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.focus({ preventScroll: true });
        target.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
    });
}

let teacherToastTimeout = null;

function showTeacherToast(message) {
    const toast = document.getElementById('teacher-toast');
    if (!toast) return;

    const messageEl = toast.querySelector('.teacher-toast__message');
    if (messageEl) {
        messageEl.textContent = message;
    }

    toast.classList.add('is-visible');

    if (teacherToastTimeout) {
        clearTimeout(teacherToastTimeout);
    }

    teacherToastTimeout = setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 4000);
}

window.showTeacherToast = showTeacherToast;
