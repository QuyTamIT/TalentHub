/**
 * TalentHub - Teacher Dashboard Scripts
 * Minimal interactions for the first Teacher Dashboard.
 */

document.addEventListener('DOMContentLoaded', () => {
    initTeacherSidebar();
    initTeacherNotifications();
    initTeacherRoutes();
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

function initTeacherNotifications() {
    const trigger = document.getElementById('teacher-notif-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', () => {
        showTeacherToast('Chưa có thông báo mới. Kết nối bằng notifications và SELECT khi có helper DB chuẩn.');
    });
}

function initTeacherRoutes() {
    const links = document.querySelectorAll('[data-route]');

    links.forEach(link => {
        link.addEventListener('click', (event) => {
            const route = link.getAttribute('data-route');

            if (route === 'index.php' || route === './' || route === '/TalentHub/app/teacher/' || route === '/TalentHub/app/teacher/index.php') {
                return;
            }

            event.preventDefault();
            const label = link.textContent.trim().replace(/\s+/g, ' ');
            showTeacherToast(`Tính năng "${label}" (${route}) đang được phát triển.`);
        });
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
