/** TalentHub learner statistics: owner-scoped period refresh with stale-response protection. */
(function (global) {
    'use strict';

    let activeController = null;
    let requestSequence = 0;

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

    function replaceExperience(experience) {
        const hours = Array.isArray(experience?.hours) ? experience.hours.map(Number) : [];
        const labels = Array.isArray(experience?.labels) ? experience.labels.map(String) : [];
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

        hours.forEach((rawHours, index) => {
            const safeHours = Number.isFinite(rawHours) ? Math.max(0, rawHours) : 0;
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
            bars.appendChild(rect);

            const text = document.createElementNS(svgNamespace, 'text');
            text.setAttribute('x', String(46 + (index * step) + (step / 2)));
            text.setAttribute('y', '222');
            text.setAttribute('text-anchor', 'middle');
            text.textContent = labels[index] || '';
            labelGroup.appendChild(text);
        });
    }

    function renderStatistics(data) {
        replaceKpis(data?.kpis);
        replaceExperience(data?.experience);
        const label = String(data?.period?.label || 'khoảng đã chọn');
        setStatus(`Đang hiển thị thống kê ${label}.`);
    }

    async function loadPeriod(period) {
        if (!['week', 'month'].includes(period)) return;
        if (activeController) activeController.abort();
        const controller = new AbortController();
        activeController = controller;
        const sequence = ++requestSequence;
        setStatus('Đang tải thống kê đã xác nhận…');

        try {
            const response = await global.fetch(`/app/learner/api/v1/statistics.php?period=${encodeURIComponent(period)}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const payload = await response.json();
            if (!response.ok || !payload || typeof payload.data !== 'object') {
                throw new Error('INVALID_STATISTICS_RESPONSE');
            }
            if (sequence !== requestSequence) return;
            renderStatistics(payload.data);
            const url = new URL(global.location.href);
            url.searchParams.set('period', period);
            global.history.replaceState({}, '', url.toString());
        } catch (error) {
            if (error?.name === 'AbortError' || sequence !== requestSequence) return;
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
        module.exports = { loadPeriod, renderStatistics };
    }
})(typeof window !== 'undefined' ? window : globalThis);
