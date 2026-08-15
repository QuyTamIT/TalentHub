/**
 * TalentHub Enterprise - Recruitment Analytics Module Interactive Controller
 */

document.addEventListener('DOMContentLoaded', function () {
    // Check if on Enterprise Analytics page
    const analyticsPage = document.querySelector('.enterprise-analytics-page');
    if (!analyticsPage) return;

    // Filter elements
    const filterTime = document.getElementById('ana-filter-time');
    const filterPost = document.getElementById('ana-filter-post');
    const filterStatus = document.getElementById('ana-filter-status');
    const resetBtn = document.getElementById('ana-btn-reset');
    const tableSearch = document.getElementById('ana-table-search');

    // KPI Elements
    const kpiTotal = document.getElementById('kpi-total-applicants');
    const kpiQualified = document.getElementById('kpi-qualified-candidates');
    const kpiInterviewing = document.getElementById('kpi-interviewing');
    const kpiPassRate = document.getElementById('kpi-pass-rate');

    // Funnel Elements
    const funnelAppliedCount = document.getElementById('funnel-applied-count');
    const funnelQualifiedCount = document.getElementById('funnel-qualified-count');
    const funnelInterviewedCount = document.getElementById('funnel-interviewed-count');
    const funnelPassedCount = document.getElementById('funnel-passed-count');

    const funnelQualifiedBar = document.getElementById('funnel-qualified-bar');
    const funnelInterviewedBar = document.getElementById('funnel-interviewed-bar');
    const funnelPassedBar = document.getElementById('funnel-passed-bar');

    // Table Tbody
    const tableTbody = document.getElementById('job-performance-tbody');

    // Filter Change Event Listeners
    if (filterTime) filterTime.addEventListener('change', applyAnalyticsFilters);
    if (filterPost) filterPost.addEventListener('change', applyAnalyticsFilters);
    if (filterStatus) filterStatus.addEventListener('change', applyAnalyticsFilters);

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (filterTime) filterTime.value = '30_days';
            if (filterPost) filterPost.value = 'all';
            if (filterStatus) filterStatus.value = 'all';
            if (tableSearch) tableSearch.value = '';
            applyAnalyticsFilters();
        });
    }

    if (tableSearch) {
        tableSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            filterTableRows(query);
        });
    }

    function applyAnalyticsFilters() {
        const timeVal = filterTime ? filterTime.value : '30_days';
        const postVal = filterPost ? filterPost.value : 'all';
        const statusVal = filterStatus ? filterStatus.value : 'all';

        let multiplier = 1.0;
        if (timeVal === 'q3_2026') multiplier = 0.85;
        if (timeVal === '6_months') multiplier = 2.4;
        if (timeVal === 'y2026') multiplier = 4.2;

        if (postVal !== 'all') multiplier *= 0.35;

        // Base Numbers
        let baseTotal = Math.round(1482 * multiplier);
        let baseQualified = Math.round(964 * multiplier);
        let baseInterviewing = Math.round(142 * multiplier);
        let passRateVal = 74.2;

        if (statusVal === 'qualified') {
            baseTotal = baseQualified;
        } else if (statusVal === 'interviewing') {
            baseTotal = baseInterviewing;
        }

        // Update KPIs with smooth UI transition
        if (kpiTotal) kpiTotal.textContent = baseTotal.toLocaleString('vi-VN');
        if (kpiQualified) kpiQualified.textContent = baseQualified.toLocaleString('vi-VN');
        if (kpiInterviewing) kpiInterviewing.textContent = baseInterviewing.toLocaleString('vi-VN');
        if (kpiPassRate) kpiPassRate.textContent = passRateVal.toFixed(1) + '%';

        // Update Funnel Stage Counts
        if (funnelAppliedCount) funnelAppliedCount.textContent = baseTotal.toLocaleString('vi-VN');
        if (funnelQualifiedCount) funnelQualifiedCount.textContent = baseQualified.toLocaleString('vi-VN');
        
        let interviewedVal = Math.round(318 * multiplier);
        let passedVal = Math.round(236 * multiplier);
        
        if (funnelInterviewedCount) funnelInterviewedCount.textContent = interviewedVal.toLocaleString('vi-VN');
        if (funnelPassedCount) funnelPassedCount.textContent = passedVal.toLocaleString('vi-VN');

        // Update Funnel Progress Bars
        const qualPct = Math.min(100, Math.round((baseQualified / Math.max(1, baseTotal)) * 100));
        const intPct = Math.min(100, Math.round((interviewedVal / Math.max(1, baseTotal)) * 100));
        const passPct = Math.min(100, Math.round((passedVal / Math.max(1, baseTotal)) * 100));

        if (funnelQualifiedBar) funnelQualifiedBar.style.width = qualPct + '%';
        if (funnelInterviewedBar) funnelInterviewedBar.style.width = intPct + '%';
        if (funnelPassedBar) funnelPassedBar.style.width = passPct + '%';

        // Filter Table Rows by selected Post
        renderFilteredTable(postVal);
    }

    function renderFilteredTable(selectedPostKey) {
        if (!tableTbody || !window.JOB_PERFORMANCE_DATA) return;

        const rows = window.JOB_PERFORMANCE_DATA.filter(item => {
            if (selectedPostKey === 'all') return true;
            return item.post_key === selectedPostKey;
        });

        tableTbody.innerHTML = '';

        if (rows.length === 0) {
            tableTbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        Không tìm thấy dữ liệu tin tuyển dụng phù hợp với bộ lọc.
                    </td>
                </tr>
            `;
            return;
        }

        rows.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="ana-job-title">${item.position}</div>
                    <div class="ana-job-code">Mã: ${item.code}</div>
                </td>
                <td class="text-center">
                    <span class="ana-dept-badge">${item.department}</span>
                </td>
                <td class="text-center">
                    <span class="font-semibold text-dark">${item.applicants.toLocaleString('vi-VN')}</span>
                </td>
                <td class="text-center">
                    <span class="font-semibold text-accent">${item.qualified.toLocaleString('vi-VN')}</span>
                    <span class="ana-qual-pct">(${Math.round((item.qualified/item.applicants)*100)}%)</span>
                </td>
                <td class="text-center">
                    <span class="font-medium text-secondary">${item.interviewed}</span>
                </td>
                <td class="text-center">
                    <span class="font-semibold text-primary">${item.passed}</span>
                </td>
                <td class="text-center">
                    <div class="ana-match-cell">
                        <span class="ana-match-val">${item.avg_match}</span>
                        <div class="ana-match-bar-track">
                            <div class="ana-match-bar-fill" style="width: ${item.avg_match}%;"></div>
                        </div>
                    </div>
                </td>
            `;
            tableTbody.appendChild(tr);
        });
    }

    function filterTableRows(query) {
        if (!tableTbody) return;
        const trs = tableTbody.querySelectorAll('tr');
        trs.forEach(tr => {
            const text = tr.textContent.toLowerCase();
            if (text.includes(query)) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
        });
    }
});
