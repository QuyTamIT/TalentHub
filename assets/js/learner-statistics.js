/** TalentHub learner statistics: owner-scoped period refresh with stale-response protection. */
(function (global) {
    'use strict';

    let activeController = null;
    let requestSequence = 0;

    function selectAxisLabelIndexes(count, maximumLabels = 7) {
        const normalizedCount = Math.max(0, Math.floor(Number(count) || 0));
        const normalizedMaximum = Math.max(0, Math.floor(Number(maximumLabels) || 0));
        const selected = new Set();
        if (normalizedCount === 0 || normalizedMaximum === 0) return selected;
        if (normalizedCount <= normalizedMaximum) {
            for (let index = 0; index < normalizedCount; index += 1) selected.add(index);
            return selected;
        }
        if (normalizedMaximum === 1) {
            selected.add(0);
            return selected;
        }
        for (let slot = 0; slot < normalizedMaximum; slot += 1) {
            selected.add(Math.round(slot * (normalizedCount - 1) / (normalizedMaximum - 1)));
        }
        return selected;
    }

    function createStatisticsClient() {
        if (!global.TalentHubLearnerApi) return null;
        const bootNode = document.getElementById('learner-session-boot');
        let csrfToken = '';
        try {
            csrfToken = JSON.parse(bootNode?.textContent || '{}').csrfToken || '';
        } catch {
            csrfToken = '';
        }
        return global.TalentHubLearnerApi.createLearnerApiClient({
            baseUrl: '/app/learner/api/v1',
            csrfToken,
        });
    }

    function setStatus(message) {
        const status = document.querySelector('[data-statistics-status]');
        if (status) status.textContent = message;
    }

    function replaceKpis(kpis) {
        const byId = new Map((Array.isArray(kpis) ? kpis : []).map(item => [String(item.id), item]));
        document.querySelectorAll('[data-statistics-kpi]').forEach(card => {
            const item = byId.get(card.getAttribute('data-kpi-id') || '');
            if (!item) return;
            const value = card.querySelector('[data-kpi-value]');
            const suffix = card.querySelector('[data-kpi-suffix]');
            if (value) value.textContent = String(item.value ?? 0);
            if (suffix) suffix.textContent = String(item.suffix ?? '');
        });
    }

    function replaceExperience(experience, periodLabel = 'khoảng đã chọn') {
        const hours = Array.isArray(experience?.hours) ? experience.hours.map(Number) : [];
        const labels = Array.isArray(experience?.labels) ? experience.labels.map(String) : [];
        const dates = Array.isArray(experience?.dates) ? experience.dates.map(String) : [];
        const bars = document.querySelector('[data-experience-bars]');
        const labelGroup = document.querySelector('[data-experience-labels]');
        if (!bars || !labelGroup) return;

        bars.replaceChildren();
        labelGroup.replaceChildren();
        const maximum = Math.max(10, ...hours);
        const width = 550;
        const height = 170;
        const step = width / Math.max(1, hours.length);
        const barWidth = Math.min(36, step * 0.55);
        const svgNamespace = 'http://www.w3.org/2000/svg';
        const visibleLabelIndexes = selectAxisLabelIndexes(hours.length);

        hours.forEach((rawHours, index) => {
            const safeHours = Number.isFinite(rawHours) ? Math.max(0, rawHours) : 0;
            const date = dates[index] || labels[index] || `Mốc ${index + 1}`;
            const accessibleTitle = `Ngày ${date}: ${safeHours} giờ`;
            const barHeight = (safeHours / maximum) * height;
            const x = 46 + (index * step) + ((step - barWidth) / 2);
            const y = 24 + height - barHeight;
            const rect = document.createElementNS(svgNamespace, 'rect');
            rect.setAttribute('x', String(x));
            rect.setAttribute('y', String(y));
            rect.setAttribute('width', String(barWidth));
            rect.setAttribute('height', String(barHeight));
            rect.setAttribute('rx', '5');
            rect.setAttribute('class', 'learner-experience-chart__bar');
            rect.setAttribute('role', 'listitem');
            rect.setAttribute('aria-label', accessibleTitle);
            const title = document.createElementNS(svgNamespace, 'title');
            title.textContent = accessibleTitle;
            rect.appendChild(title);
            bars.appendChild(rect);

            if (!visibleLabelIndexes.has(index)) return;
            const text = document.createElementNS(svgNamespace, 'text');
            text.setAttribute('x', String(46 + (index * step) + (step / 2)));
            text.setAttribute('y', '222');
            text.setAttribute('text-anchor', 'middle');
            text.textContent = labels[index] || '';
            labelGroup.appendChild(text);
        });

        const chartTitle = document.querySelector('[data-experience-chart-title]');
        if (chartTitle) chartTitle.textContent = `Biểu đồ giờ trải nghiệm cá nhân ${periodLabel}`;
    }

    function replaceLifetimeFacts(facts) {
        const values = [
            ['[data-lifetime-hours]', facts?.confirmed_experience_hours],
            ['[data-lifetime-activities]', facts?.attended_activity_count],
            ['[data-lifetime-assessments]', facts?.submitted_assessment_type_count],
            ['[data-lifetime-evaluations]', facts?.published_teacher_evaluation_count],
        ];
        values.forEach(([selector, value]) => {
            const node = document.querySelector(selector);
            if (node) node.textContent = String(value ?? 0);
        });
    }

    function renderStatistics(data) {
        const label = String(data?.period?.label || 'khoảng đã chọn');
        replaceKpis(data?.kpis);
        replaceExperience(data?.experience, label);
        replaceLifetimeFacts(data?.facts);
        const periodTitle = document.querySelector('[data-period-kpi-title]');
        if (periodTitle) periodTitle.textContent = `Chỉ số trong ${label}`;
        const experienceTitle = document.querySelector('[data-experience-period-title]');
        if (experienceTitle) experienceTitle.textContent = `Giờ trải nghiệm (${label})`;
        setStatus(`Đang hiển thị thống kê ${label}.`);
    }

    async function loadPeriod(period, client = null) {
        if (!['week', 'month'].includes(period)) return;
        if (activeController) activeController.abort();
        const controller = new AbortController();
        activeController = controller;
        const sequence = ++requestSequence;
        setStatus('Đang tải thống kê đã xác nhận…');

        try {
            const api = client || createStatisticsClient();
            if (!api) throw new Error('STATISTICS_API_UNAVAILABLE');
            const data = await api.get(`/statistics.php?period=${encodeURIComponent(period)}`, {
                signal: controller.signal,
            });
            if (!data || typeof data !== 'object') throw new Error('INVALID_STATISTICS_RESPONSE');
            if (sequence !== requestSequence) return;
            renderStatistics(data);
            const url = new URL(global.location.href);
            url.searchParams.set('period', period);
            global.history.replaceState({}, '', url.toString());
        } catch (error) {
            if (error?.code === 'REQUEST_ABORTED' || sequence !== requestSequence) return;
            setStatus('Không thể tải thống kê. Vui lòng chọn lại khoảng thời gian để thử lại.');
        } finally {
            if (activeController === controller) activeController = null;
        }
    }

    function initStatistics() {
        const periodSelect = document.getElementById('learner-statistics-period');
        if (!periodSelect) return;

        periodSelect.addEventListener('change', () => {
            loadPeriod(periodSelect.value);
        });
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStatistics);
        } else {
            initStatistics();
        }
    }
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { createStatisticsClient, loadPeriod, renderStatistics, selectAxisLabelIndexes };
    }
})(typeof window !== 'undefined' ? window : globalThis);
