/**
 * TalentHub - School Dashboard Scripts
 * Shared interactions: sidebar toggle, toast, confirm dialog.
 */

document.addEventListener('DOMContentLoaded', () => {
    initSchoolSidebar();
    initSchoolNotifications();
    initSchoolConfirmForms();
});

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
    const trigger = document.getElementById('school-notif-trigger');
    if (!trigger) return;
    trigger.addEventListener('click', () => {
        showSchoolToast('Chưa có thông báo mới. Tính năng đang phát triển.');
    });
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