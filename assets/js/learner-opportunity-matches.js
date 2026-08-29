/**
 * Inline AI opportunity matching for the learner ecosystem page.
 */
(function initLearnerOpportunityMatches(global) {
    'use strict';

    const ENDPOINT = '/opportunity-matches.php';
    const STATE_MAP = Object.freeze({
        not_generated: 'not-generated',
        consent_required: 'consent-required',
        insufficient_data: 'insufficient-data',
        catalog_insufficient: 'catalog-insufficient',
        pending: 'loading',
        ready_model: 'ready-model',
        stale_model: 'stale-model',
        provider_unavailable: 'source-error',
        rate_limited: 'source-error',
        invalid_response: 'source-error',
    });

    const COPY = Object.freeze({
        'not-generated': 'Bấm “AI gợi ý dự án phù hợp” để Gemini đối chiếu hồ sơ năng lực và điểm đánh giá của bạn.',
        loading: 'Gemini đang đối chiếu dữ liệu đã được bạn cho phép…',
        'consent-required': 'Bạn cần cho phép sử dụng dữ liệu học tập trước khi nhận đề xuất cá nhân hóa.',
        'insufficient-data': 'Hồ sơ chưa đủ dữ liệu để phân tích. Hãy bổ sung kỹ năng hoặc hoàn thành một bài đánh giá.',
        'catalog-insufficient': 'Hiện chưa có đủ ba dự án đang mở phù hợp để AI xếp hạng.',
        'source-error': 'Chưa thể hoàn tất phân tích lúc này. Bạn có thể thử lại sau.',
        'ready-model': 'Phân tích vừa xong',
        'stale-model': 'Đang hiển thị phân tích gần nhất vì Gemini tạm thời chưa phản hồi.',
    });

    function mapOpportunityMatchState(state) {
        return STATE_MAP[String(state || '')] || 'source-error';
    }

    function isSafeInternalOpportunityUrl(value) {
        if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) return false;
        const pathOnly = value.split(/[?#]/, 1)[0];
        if (/\\|%2e|%2f|%5c|(?:^|\/)\.\.?($|\/)/i.test(pathOnly)) return false;
        try {
            const url = new URL(value, 'https://talenthub.invalid');
            return url.origin === 'https://talenthub.invalid'
                && (/^\/app\/learner\/(?:opportunity|ecosystem)\.php$/).test(url.pathname);
        } catch {
            return false;
        }
    }

    function normalizeText(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function normalizeTextList(value) {
        if (!Array.isArray(value)) return [];
        return value.map(normalizeText).filter(Boolean).slice(0, 6);
    }

    function normalizeReadyItems(items) {
        if (!Array.isArray(items) || items.length !== 3) return null;
        const ids = new Set();
        const normalized = [];
        for (let index = 0; index < items.length; index += 1) {
            const item = items[index] && typeof items[index] === 'object' ? items[index] : {};
            const catalogId = normalizeText(item.catalog_id);
            const score = Number(item.match_score);
            if (!catalogId || ids.has(catalogId) || !Number.isInteger(score) || score < 0 || score > 100) return null;
            ids.add(catalogId);
            normalized.push({
                catalog_id: catalogId,
                rank: Number.isInteger(Number(item.rank)) ? Number(item.rank) : index + 1,
                match_score: score,
                why_fit: normalizeText(item.why_fit),
                matched_skills: normalizeTextList(item.matched_skills),
                missing_skills: normalizeTextList(item.missing_skills),
                expected_outcomes: normalizeTextList(item.expected_outcomes),
                evidence: normalizeTextList(item.evidence),
                title: normalizeText(item.title) || `Dự án phù hợp #${index + 1}`,
                summary: normalizeText(item.summary),
                canonical_url: isSafeInternalOpportunityUrl(item.canonical_url) ? item.canonical_url : '',
            });
        }
        return normalized.sort((left, right) => left.rank - right.rank);
    }

    function createOpportunityMatchController({ api, view, createIdempotencyKey }) {
        if (!api || typeof api !== 'object') {
            throw new TypeError('Opportunity match controller requires an API client.');
        }
        if (!view || typeof view.render !== 'function') {
            throw new TypeError('Opportunity match controller requires a view.');
        }
        const keyFactory = typeof createIdempotencyKey === 'function' ? createIdempotencyKey : defaultIdempotencyKey;
        let inFlight = null;
        let idempotencyKey = '';

        function renderResponse(response) {
            const payload = response && typeof response === 'object' ? response : {};
            const state = mapOpportunityMatchState(payload.state);
            if (state === 'ready-model' || state === 'stale-model') {
                const items = normalizeReadyItems(payload.items);
                if (!items) {
                    view.render('source-error', { items: [] });
                    return;
                }
                view.render(state, { ...payload, items });
                return;
            }
            view.render(state, payload);
        }

        async function load() {
            view.render('loading', { items: [] });
            if (typeof api.get !== 'function') {
                view.render('source-error', { items: [] });
                return;
            }
            try {
                renderResponse(await api.get(ENDPOINT));
            } catch {
                view.render('source-error', { items: [] });
            }
        }

        function generate() {
            if (inFlight) return inFlight;
            if (typeof api.send !== 'function') {
                view.render('source-error', { items: [] });
                return Promise.resolve();
            }
            if (!idempotencyKey) idempotencyKey = String(keyFactory() || '');
            view.render('loading', { items: [] });
            inFlight = Promise.resolve()
                .then(() => api.send('POST', ENDPOINT, {}, { idempotencyKey }))
                .then(renderResponse)
                .catch(() => view.render('source-error', { items: [] }))
                .finally(() => { inFlight = null; });
            return inFlight;
        }

        return { load, generate };
    }

    function element(tagName, className, textValue) {
        const node = document.createElement(tagName);
        if (className) node.className = className;
        if (textValue !== undefined) node.textContent = String(textValue);
        return node;
    }

    function appendList(container, values, tone, emptyCopy) {
        const list = element('div', `learner-opportunity-ai-chips learner-opportunity-ai-chips--${tone}`);
        const safeValues = values.length ? values : [emptyCopy];
        safeValues.forEach((value) => list.appendChild(element('span', '', value)));
        container.appendChild(list);
    }

    function detailColumn(label, values, tone, emptyCopy) {
        const column = element('section', 'learner-opportunity-ai-card__detail');
        column.appendChild(element('h4', '', label));
        if (Array.isArray(values)) appendList(column, values, tone, emptyCopy);
        else column.appendChild(element('p', '', values || emptyCopy));
        return column;
    }

    function evidenceLabel(reference) {
        const type = String(reference).split(':', 1)[0];
        const labels = {
            skill: 'Hồ sơ kỹ năng',
            skills: 'Hồ sơ kỹ năng',
            assessment: 'Điểm đánh giá',
            evaluation: 'Đánh giá đã công bố',
            activity: 'Hoạt động đã tham gia',
            experience: 'Kinh nghiệm hoạt động',
            opportunity: 'Dự án TalentHub',
            catalog: 'Danh mục cơ hội',
        };
        return labels[type] || 'Dữ liệu hồ sơ';
    }

    function scoreBand(score) {
        if (score >= 90) return { className: 'is-excellent', label: 'Rất phù hợp' };
        if (score >= 80) return { className: 'is-high', label: 'Phù hợp cao' };
        return { className: 'is-potential', label: 'Có tiềm năng' };
    }

    function renderCard(item) {
        const card = element('article', 'learner-opportunity-ai-card');
        card.dataset.catalogId = item.catalog_id;

        const identity = element('div', 'learner-opportunity-ai-card__identity');
        identity.appendChild(element('span', 'learner-opportunity-ai-rank', `#${item.rank}`));
        identity.appendChild(element('small', '', item.summary || 'Dự án cơ hội trên TalentHub'));
        identity.appendChild(element('h3', '', item.title));
        card.appendChild(identity);

        const band = scoreBand(item.match_score);
        const scoreWrap = element('div', `learner-opportunity-ai-score ${band.className}`);
        scoreWrap.style.setProperty('--match-score', String(item.match_score));
        scoreWrap.setAttribute('role', 'img');
        scoreWrap.setAttribute('aria-label', `Mức độ phù hợp ${item.match_score} trên 100, ${band.label}`);
        const ring = element('span', 'learner-opportunity-ai-score__ring');
        ring.appendChild(element('strong', '', item.match_score));
        ring.appendChild(element('small', '', '/100'));
        scoreWrap.appendChild(ring);
        scoreWrap.appendChild(element('em', '', `${item.match_score}/100 · ${band.label}`));
        card.appendChild(scoreWrap);

        const details = element('div', 'learner-opportunity-ai-card__details');
        details.appendChild(detailColumn('Vì sao phù hợp', item.why_fit, 'neutral', 'AI chưa cung cấp diễn giải.'));
        details.appendChild(detailColumn('Kỹ năng phù hợp', item.matched_skills, 'matched', 'Chưa có kỹ năng trùng khớp.'));
        details.appendChild(detailColumn('Cần bổ sung', item.missing_skills, 'missing', 'Chưa ghi nhận khoảng trống kỹ năng.'));
        details.appendChild(detailColumn('Bạn sẽ đạt được', item.expected_outcomes, 'outcome', 'Kết quả sẽ được cập nhật theo dự án.'));
        const evidence = [...new Set(item.evidence.map(evidenceLabel))];
        details.appendChild(detailColumn('Nguồn phân tích', evidence, 'evidence', 'Dữ liệu hồ sơ đã cho phép.'));
        card.appendChild(details);

        const actions = element('div', 'learner-opportunity-ai-card__actions');
        const detailButton = element('button', 'learner-btn learner-btn--outline', 'Xem phân tích chi tiết');
        detailButton.type = 'button';
        detailButton.setAttribute('aria-expanded', 'false');
        detailButton.addEventListener('click', () => {
            const expanded = detailButton.getAttribute('aria-expanded') !== 'true';
            detailButton.setAttribute('aria-expanded', String(expanded));
            card.classList.toggle('is-analysis-expanded', expanded);
        });
        actions.appendChild(detailButton);
        if (item.canonical_url) {
            const link = element('a', 'learner-btn learner-btn--primary', 'Xem dự án');
            link.href = item.canonical_url;
            actions.appendChild(link);
        } else {
            const unavailable = element('button', 'learner-btn learner-btn--primary', 'Xem dự án');
            unavailable.type = 'button';
            unavailable.disabled = true;
            actions.appendChild(unavailable);
        }
        card.appendChild(actions);
        return card;
    }

    function createOpportunityMatchView(root) {
        if (!root || typeof root.querySelector !== 'function') throw new TypeError('Opportunity match root is required.');
        const status = root.querySelector('[data-opportunity-ai-status]');
        const panels = {
            'not-generated': root.querySelector('[data-opportunity-ai-not-generated]'),
            loading: root.querySelector('[data-opportunity-ai-loading]'),
            'consent-required': root.querySelector('[data-opportunity-ai-consent]'),
            'insufficient-data': root.querySelector('[data-opportunity-ai-insufficient]'),
            'catalog-insufficient': root.querySelector('[data-opportunity-ai-catalog-insufficient]'),
            'source-error': root.querySelector('[data-opportunity-ai-error]'),
            results: root.querySelector('[data-opportunity-ai-results]'),
        };
        const list = root.querySelector('[data-opportunity-ai-list]');

        function render(state, payload = {}) {
            Object.values(panels).forEach((panel) => { if (panel) panel.hidden = true; });
            root.setAttribute('aria-busy', String(state === 'loading'));
            if (status) {
                status.textContent = COPY[state] || COPY['source-error'];
                status.className = `learner-opportunity-ai__status is-${state}`;
            }
            root.dataset.state = state;
            if (state === 'ready-model' || state === 'stale-model') {
                if (list) {
                    list.replaceChildren();
                    payload.items.forEach((item) => list.appendChild(renderCard(item)));
                }
                if (panels.results) panels.results.hidden = false;
                return;
            }
            const panel = panels[state] || panels['source-error'];
            if (panel) panel.hidden = false;
        }

        return { render };
    }

    function defaultIdempotencyKey() {
        const uuid = global.crypto && typeof global.crypto.randomUUID === 'function' ? global.crypto.randomUUID() : '';
        if (uuid) return `opportunity-${uuid}`;
        return `opportunity-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 14)}`;
    }

    function mountOpportunityMatches() {
        if (typeof document === 'undefined') return null;
        const root = document.querySelector('[data-opportunity-matches]');
        const panel = document.querySelector('[data-ecosystem-panel="opportunities"]');
        if (!root || !panel) return null;
        const api = global.TalentHubLearnerClient;
        const view = createOpportunityMatchView(root);
        if (!api) {
            view.render('source-error');
            return null;
        }
        const controller = createOpportunityMatchController({ api, view, createIdempotencyKey: defaultIdempotencyKey });
        const triggers = Array.from(document.querySelectorAll('[data-opportunity-ai-trigger]'));
        const syncTriggerVisibility = () => {
            triggers.forEach((button) => { button.hidden = panel.hidden; });
        };
        triggers.forEach((button) => {
            button.addEventListener('click', () => {
                triggers.forEach((trigger) => { trigger.disabled = true; });
                controller.generate().finally(() => {
                    triggers.forEach((trigger) => { trigger.disabled = false; });
                });
            });
        });
        document.querySelectorAll('[data-ecosystem-tab]').forEach((button) => {
            button.addEventListener('click', () => global.setTimeout(syncTriggerVisibility, 0));
            button.addEventListener('keydown', () => global.setTimeout(syncTriggerVisibility, 0));
        });
        syncTriggerVisibility();
        controller.load();
        return controller;
    }

    const api = {
        createOpportunityMatchController,
        createOpportunityMatchView,
        mapOpportunityMatchState,
        isSafeInternalOpportunityUrl,
        mountOpportunityMatches,
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.TalentHubOpportunityMatches = api;
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountOpportunityMatches, { once: true });
        else mountOpportunityMatches();
    }
})(typeof window !== 'undefined' ? window : globalThis);
