/**
 * TalentHub - School Dashboard Scripts
 * Handles mobile/tablet sidebar drawer, backdrop toggle, notification popups,
 * temporary route fallback notifications, report filters, grade section toggles,
 * and class detail modal.
 *
 * Note for Developers:
 * - When future sub-modules (filters, reports creation, deep class detail) are built,
 *   update the handlers to perform real API calls.
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileSidebar();
    initNotificationBell();
    initRouteNavigation();
    initReportFilters();
    initReportActions();
    initGradeSections();
    initClassModal();
    initAnalyticsFilters();
});

/* ==========================================================================
   1. Mobile & Tablet Sidebar Navigation Drawer Toggle
   ========================================================================== */
function initMobileSidebar() {
    const toggleBtn = document.getElementById('sch-sidebar-toggle');
    const sidebar = document.getElementById('sch-sidebar');
    const backdrop = document.getElementById('sch-sidebar-backdrop');

    if (!toggleBtn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-active');
        document.body.classList.add('sch-sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-active');
        document.body.classList.remove('sch-sidebar-open');
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
    const notifBtn = document.getElementById('sch-notif-trigger');
    if (!notifBtn) return;

    notifBtn.addEventListener('click', () => {
        showSchToast('Bạn có 5 thông báo mới: 2 báo cáo chờ duyệt, 1 hoạt động sắp hết hạn, 2 hồ sơ chờ xác minh.');
    });
}

/* ==========================================================================
   3. Temporary Routes Navigation Handler
   ========================================================================== */
function initRouteNavigation() {
    const routeLinks = document.querySelectorAll('[data-route]');

    // Set of routes that are already implemented in the School module
    const implementedRoutes = new Set([
        'index.php',
        'analytics.php',
        'reports.php',
        'classes.php',
        '/app/school',
        '/app/school/index.php',
        '/app/school/analytics.php',
        '/app/school/reports.php',
        '/app/school/classes.php'
    ]);

    routeLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const route = link.getAttribute('data-route');

            // Allow default navigation for implemented routes
            if (implementedRoutes.has(route)) {
                return;
            }

            // For pending sub-modules, display clean toast feedback
            e.preventDefault();
            const linkText = link.textContent.trim().replace(/\s+/g, ' ');
            showSchToast(`Tính năng "${linkText}" (${route}) đang được phát triển!`);
        });
    });
}

/* ==========================================================================
   4. Reports Page: Filter chips + Row-level actions
   ========================================================================== */
function initReportFilters() {
    const categoryBtns = document.querySelectorAll('.sch-report-chip[data-category]');
    const yearBtns = document.querySelectorAll('.sch-report-chip[data-year]');
    if (!categoryBtns.length && !yearBtns.length) return;

    let activeCategory = 'all';
    let activeYear = 'all';

    function applyFilters() {
        document.querySelectorAll('.sch-data-table tbody tr').forEach(row => {
            const rowCat  = row.getAttribute('data-category')  || '';
            const rowYear = row.getAttribute('data-year') || '';
            const matchCat  = activeCategory === 'all' || rowCat === activeCategory;
            const matchYear = activeYear === 'all' || rowYear === activeYear;
            row.style.display = (matchCat && matchYear) ? '' : 'none';
        });
    }

    function setActive(buttonGroup, target) {
        buttonGroup.forEach(btn => btn.classList.remove('is-active'));
        if (target) target.classList.add('is-active');
    }

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeCategory = btn.getAttribute('data-category');
            setActive(categoryBtns, btn);
            applyFilters();
        });
    });

    yearBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeYear = btn.getAttribute('data-year');
            setActive(yearBtns, btn);
            applyFilters();
        });
    });
}

function initReportActions() {
    const createBtn = document.querySelector('[data-create-report]');
    if (createBtn) {
        createBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showSchToast('Chức năng tạo báo cáo tùy chỉnh đang được phát triển!');
        });
    }

    document.querySelectorAll('[data-download-report]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-download-report');
            showSchToast(`Đang chuẩn bị tải xuống báo cáo #${id}...`);
        });
    });

    document.querySelectorAll('[data-preview-report]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-preview-report');
            showSchToast(`Đang mở bản xem trước báo cáo #${id}...`);
        });
    });
}

/* ==========================================================================
   5. Classes Page: Collapsible grade sections
   ========================================================================== */
function initGradeSections() {
    const headers = document.querySelectorAll('[data-toggle-grade]');
    if (!headers.length) return;

    headers.forEach(header => {
        header.addEventListener('click', (e) => {
            // Prevent toggle when clicking inside other interactive child elements
            if (e.target.closest('.sch-class-card')) return;
            const section = header.closest('.sch-grade-section');
            const open = section.classList.toggle('is-open');
            header.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
}

/* ==========================================================================
   6. Classes Page: Modal with top 10 students of a class
   ========================================================================== */
function initClassModal() {
    const modal    = document.getElementById('sch-class-modal');
    const closeBtn = document.getElementById('sch-modal-close');
    const bodyEl   = document.getElementById('sch-modal-body');
    const titleEl  = document.getElementById('modal-class-name');
    const majorEl  = document.getElementById('modal-class-major');
    if (!modal || !bodyEl) return;

    const classCards = document.querySelectorAll('.sch-class-card');
    classCards.forEach(card => {
        card.addEventListener('click', () => {
            const className = card.getAttribute('data-class-name');
            const major     = card.getAttribute('data-major');

            titleEl.textContent = className;
            majorEl.textContent = major;

            const data = (window.SCHOOL_TOP_STUDENTS && window.SCHOOL_TOP_STUDENTS[className])
                || generateFallbackStudents(className);

            bodyEl.innerHTML = renderStudentRows(data);
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('sch-sidebar-open');
        });
    });

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sch-sidebar-open');
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
}

function renderStudentRows(students) {
    if (!students || students.length === 0) {
        return '<p style="text-align: center; color: var(--text-secondary); padding: 2rem 0;">Chưa có dữ liệu học sinh cho lớp này.</p>';
    }
    return students.map((stu, i) => {
        const lastName = stu.name.split(' ').pop();
        const initial = lastName.charAt(0).toUpperCase();
        return `
            <div class="sch-modal__student-row">
                <span class="sch-modal__student-row__rank">${i + 1}</span>
                <div class="sch-modal__student-row__avatar">${initial}</div>
                <div class="sch-modal__student-row__info">
                    <div class="sch-modal__student-row__name">${escapeHtml(stu.name)}</div>
                    <div class="sch-modal__student-row__field">${escapeHtml(stu.primary_field || '')}</div>
                </div>
                <span class="sch-modal__student-row__score">${stu.talent_score} điểm</span>
            </div>
        `;
    }).join('');
}

function generateFallbackStudents(className) {
    // Generate 10 synthetic students for classes that don't have mock data
    const surnames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ'];
    const middles  = ['Văn', 'Thị', 'Hồng', 'Quốc', 'Minh', 'Thanh', 'Kim', 'Bảo', 'Hà', 'Mỹ'];
    const names    = ['An', 'Bình', 'Châu', 'Duy', 'Giang', 'Hà', 'Khang', 'Long', 'Minh', 'Ngọc'];
    const fields   = ['Kỹ thuật', 'Học thuật', 'Nghệ thuật', 'Kinh doanh', 'Thể thao'];
    const out = [];
    for (let i = 0; i < 10; i++) {
        const name = `${surnames[i]} ${middles[i]} ${names[i]}`;
        out.push({
            name,
            talent_score: 95 - i * 2,
            primary_field: fields[i % fields.length]
        });
    }
    return out;
}

/* ==========================================================================
   7. Analytics Page: Filter selects (UI mock only - charts are static)
   ========================================================================== */
function initAnalyticsFilters() {
    const selects = document.querySelectorAll('.sch-filter-bar__select');
    if (!selects.length) return;

    selects.forEach(sel => {
        sel.addEventListener('change', () => {
            const grade = document.getElementById('filter-grade').value;
            const field = document.getElementById('filter-field').value;
            const cls   = document.getElementById('filter-class').value;
            showSchToast(`Đã áp dụng bộ lọc: Khối ${grade === 'all' ? 'tất cả' : grade}, Lĩnh vực ${field === 'all' ? 'tất cả' : field}, Lớp ${cls === 'all' ? 'tất cả' : cls}. (Tính năng lọc động đang phát triển.)`);
        });
    });
}

/* ==========================================================================
   8. Toast Notification System
   ========================================================================== */
let schToastTimeout = null;

function showSchToast(message) {
    const toast = document.getElementById('sch-toast');
    if (!toast) return;

    const messageEl = toast.querySelector('.sch-toast__message');
    if (messageEl) {
        messageEl.textContent = message;
    }

    toast.classList.add('is-visible');

    if (schToastTimeout) {
        clearTimeout(schToastTimeout);
    }

    schToastTimeout = setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 4000);
}

window.showSchToast = showSchToast;

/* ==========================================================================
   9. Utilities
   ========================================================================== */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}