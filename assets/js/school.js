/**
 * TalentHub - School Dashboard Scripts
 * Shared interactions: sidebar toggle, toast, confirm dialog.
 */

document.addEventListener('DOMContentLoaded', () => {
    initSchoolSidebar();
    initSchoolNotifications();
    initSchoolConfirmForms();
    initSchoolAccountDropdown();
});

function initSchoolAccountDropdown() {
    const trigger = document.getElementById('school-account-trigger');
    const menu = document.getElementById('school-account-menu');
    const wrapper = document.getElementById('school-account-wrapper');

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

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    document.addEventListener('click', (e) => {
        if (wrapper && !wrapper.contains(e.target)) {
            closeMenu();
        }
    });

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
            closeMenu();
        }
    });
}

function initSchoolSidebar() {
    const toggleBtn = document.getElementById('school-sidebar-toggle');
    const sidebar = document.getElementById('school-sidebar');
    const backdrop = document.getElementById('school-sidebar-backdrop');

    if (!toggleBtn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-active');
        document.body.classList.add('school-sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-active');
        document.body.classList.remove('school-sidebar-open');
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
        if (sidebar.classList.contains('is-open')
            && !sidebar.contains(event.target)
            && !toggleBtn.contains(event.target)) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
}

function initSchoolNotifications() {
    // Notifications are handled by the shared portal-notifications module.
}

function initSchoolConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm');
            if (!message) return;
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}

let schoolToastTimeout = null;

function showSchoolToast(message, variant) {
    const toast = document.getElementById('school-toast');
    if (!toast) return;
    const messageEl = document.getElementById('toast-message');
    if (messageEl) {
        messageEl.textContent = message;
    }
    toast.classList.remove('school-toast--error');
    if (variant === 'error') {
        toast.classList.add('school-toast--error');
    }
    toast.classList.add('is-visible');

    if (schoolToastTimeout) {
        clearTimeout(schoolToastTimeout);
    }
    schoolToastTimeout = setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 4000);
}

window.showSchoolToast = showSchoolToast;