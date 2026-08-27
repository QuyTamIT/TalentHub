/**
 * Evidence-backed learner recommendation UI. The page receives only the
 * response-mapper contract and never renders raw snapshots or provider responses.
 */
(function initLearnerRecommendations(global) {
    'use strict';

    const READY_STATES = new Set(['ready-rule', 'ready-model', 'stale-model', 'fallback-rule']);

    function presentationState(payload) {
        const state = typeof payload?.state === 'string' ? payload.state : '';
        if (state === 'consent_required') return 'consent-required';
        if (state === 'insufficient_data' || state === 'not_generated') return 'insufficient-data';
        if (state === 'ready_rule') return 'ready-rule';
        if (state === 'ready_model') return 'ready-model';
        if (state === 'stale_model') return 'stale-model';
        if (state === 'fallback_rule') return 'fallback-rule';
        if (state === 'pending') return 'loading';
        return 'source-error';
    }

    function recommendationSection(itemType) {
        if (itemType === 'strength') return 'strength';
        if (itemType === 'activity') return 'activity';
        return 'other';
    }

    function createRecommendationController({ api, view, createIdempotencyKey = defaultIdempotencyKey }) {
        if (!api || typeof api.get !== 'function' || typeof api.send !== 'function') {
            throw new TypeError('A learner recommendation API client is required.');
        }
        if (!view || typeof view.render !== 'function') {
            throw new TypeError('A learner recommendation view is required.');
        }

        let generation = null;
        let currentPayload = { state: 'not_generated', items: [] };

        function renderPayload(payload) {
            currentPayload = payload && typeof payload === 'object' ? payload : { state: 'source_unavailable', items: [] };
            view.render(presentationState(currentPayload), currentPayload);
            return currentPayload;
        }

        function renderSourceError() {
            return renderPayload({ state: 'source_unavailable', items: [] });
        }

        async function load() {
            view.render('loading', currentPayload);
            try {
                return renderPayload(await api.get('/recommendations.php'));
            } catch {
                return renderSourceError();
            }
        }

        function generate() {
            if (generation !== null) return generation;

            const idempotencyKey = createIdempotencyKey();
            view.render('loading', currentPayload);
            generation = Promise.resolve(api.send('POST', '/recommendations.php', undefined, { idempotencyKey }))
                .then(renderPayload)
                .catch(renderSourceError)
                .finally(() => {
                    generation = null;
                });
            return generation;
        }

        function retry() {
            return load();
        }

        function expandEvidence(itemId) {
            if (typeof itemId === 'string' && itemId !== '' && typeof view.toggleEvidence === 'function') {
                view.toggleEvidence(itemId);
            }
        }

        function submitFeedback(feedback) {
            const allowed = ['itemId', 'verdict', 'reasonCode', 'safeComment'];
            const body = {};
            for (const field of allowed) {
                if (Object.prototype.hasOwnProperty.call(feedback || {}, field)) body[field] = feedback[field];
            }
            return Promise.resolve(api.send('POST', '/recommendation-feedback.php', body))
                .then((response) => {
                    view.render('feedback-saved', currentPayload);
                    if (typeof view.focusFeedback === 'function') view.focusFeedback();
                    return response;
                });
        }

        function grantMissingConsent(scopes) {
            const missing = Array.isArray(currentPayload.missing_consent_scopes)
                ? new Set(currentPayload.missing_consent_scopes)
                : new Set();
            const allowed = new Set(['assessment', 'skills', 'activity', 'evaluation']);
            const requested = Array.isArray(scopes) ? scopes : [];
            const approved = [...new Set(requested.filter((scope) => typeof scope === 'string' && allowed.has(scope) && missing.has(scope)))];
            if (approved.length === 0) return Promise.resolve(currentPayload);
            return Promise.all(approved.map((scope) => api.send('POST', '/ai-consent.php', { scope, action: 'granted' })))
                .then(() => generate());
        }

        return { load, generate, retry, expandEvidence, submitFeedback, grantMissingConsent };
    }

    function defaultIdempotencyKey() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return `rec-${global.crypto.randomUUID()}`;
        }
        return `rec-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
    }

    function createDomView(root) {
        const nodes = {
            status: root.querySelector('[data-ai-state-status]'),
            loading: root.querySelector('[data-ai-loading]'),
            consent: root.querySelector('[data-ai-consent]'),
            consentCopy: root.querySelector('[data-ai-consent-copy]'),
            consentActions: root.querySelector('[data-ai-consent-actions]'),
            insufficient: root.querySelector('[data-ai-insufficient]'),
            insufficientCopy: root.querySelector('[data-ai-insufficient-copy]'),
            sourceError: root.querySelector('[data-ai-source-error]'),
            results: root.querySelector('[data-ai-results]'),
            list: root.querySelector('[data-ai-result-list]'),
            engineLabel: root.querySelector('[data-ai-engine-label]'),
            engineDetails: root.querySelector('[data-ai-engine-details]'),
            generatedAt: root.querySelector('[data-ai-generated-at]'),
            feedbackStatus: root.querySelector('[data-ai-feedback-status]'),
        };
        const evidenceByItem = new Map();

        function setHidden(node, hidden) {
            if (node) node.hidden = hidden;
        }

        function statusText(state) {
            return {
                loading: 'Đang tải gợi ý năng lực.',
                'consent-required': 'Cần sự đồng ý để tạo gợi ý.',
                'insufficient-data': 'Chưa đủ dữ liệu để tạo gợi ý.',
                'source-error': 'Chưa thể lấy dữ liệu gợi ý.',
                'ready-rule': 'Gợi ý theo quy tắc đã sẵn sàng.',
                'ready-model': 'Gợi ý từ mô hình đã sẵn sàng.',
                'stale-model': 'Đang hiển thị gợi ý AI gần nhất trong khi hệ thống cập nhật.',
                'fallback-rule': 'Đang hiển thị gợi ý dự phòng theo quy tắc.',
                'feedback-saved': 'Đã lưu phản hồi của bạn.',
            }[state] || 'Trạng thái gợi ý đã thay đổi.';
        }

        function render(state, payload) {
            const showResults = READY_STATES.has(state) || state === 'feedback-saved';
            setHidden(nodes.loading, state !== 'loading');
            setHidden(nodes.consent, state !== 'consent-required');
            setHidden(nodes.insufficient, state !== 'insufficient-data');
            setHidden(nodes.sourceError, state !== 'source-error');
            setHidden(nodes.results, !showResults);
            if (nodes.status) nodes.status.textContent = statusText(state);
            if (state === 'feedback-saved' && nodes.feedbackStatus) nodes.feedbackStatus.textContent = statusText(state);
            if (state === 'insufficient-data' && nodes.insufficientCopy && payload?.state === 'not_generated') {
                nodes.insufficientCopy.textContent = 'Chưa có gợi ý. Chọn “Tạo gợi ý” để phân tích các dữ liệu bạn đã cho phép.';
            }
            if (state === 'consent-required') renderConsent(payload);
            if (showResults) renderResults(payload, state);
        }

        function renderConsent(payload) {
            const allowed = new Set(['assessment', 'skills', 'activity', 'evaluation']);
            const scopes = Array.isArray(payload?.missing_consent_scopes)
                ? [...new Set(payload.missing_consent_scopes.filter((scope) => allowed.has(scope)))]
                : [];
            if (nodes.consentCopy) {
                nodes.consentCopy.textContent = scopes.length > 0
                    ? 'Bạn có thể đồng ý dùng các nhóm dữ liệu cần thiết để cá nhân hóa gợi ý. Bạn luôn có thể rút lại sự đồng ý sau này.'
                    : 'Chưa thể xác định nhóm dữ liệu cần đồng ý. Hãy thử lại sau.';
            }
            if (!nodes.consentActions) return;
            while (nodes.consentActions.firstChild) nodes.consentActions.removeChild(nodes.consentActions.firstChild);
            if (scopes.length === 0) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'learner-btn learner-btn--primary';
            button.textContent = 'Đồng ý và tạo gợi ý';
            button.dataset.aiGrantScopes = scopes.join(',');
            nodes.consentActions.appendChild(button);
        }

        function renderResults(payload, state) {
            if (!nodes.list) return;
            while (nodes.list.firstChild) nodes.list.removeChild(nodes.list.firstChild);
            evidenceByItem.clear();
            if (nodes.engineLabel) nodes.engineLabel.textContent = engineLabel(state, payload);
            renderEngineDetails(payload);
            if (nodes.generatedAt) {
                const generatedAt = displayDate(payload?.generated_at);
                nodes.generatedAt.textContent = generatedAt === 'Ngày nguồn không xác định' ? '' : `Tạo ngày ${generatedAt}`;
            }
            const items = Array.isArray(payload?.items)
                ? payload.items.filter((item) => item && typeof item === 'object')
                : [];
            const groups = [
                ['strength', 'Điểm mạnh nổi bật'],
                ['activity', 'Hoạt động phù hợp'],
                ['other', 'Gợi ý khác'],
            ];
            for (const [sectionName, heading] of groups) {
                const sectionItems = items.filter((item) => recommendationSection(item.item_type) === sectionName);
                if (sectionItems.length === 0) continue;
                nodes.list.appendChild(renderGroup(sectionName, heading, sectionItems));
            }
        }

        function renderGroup(sectionName, heading, items) {
            const section = document.createElement('section');
            section.className = `learner-ai-result-group learner-ai-result-group--${sectionName}`;
            const title = document.createElement('h3');
            title.className = 'learner-ai-result-group__title';
            title.textContent = heading;
            const cards = document.createElement('div');
            cards.className = 'learner-ai-result-group__cards';
            for (const item of items) {
                cards.appendChild(renderItem(item));
            }
            section.append(title, cards);
            return section;
        }

        function renderEngineDetails(payload) {
            if (!nodes.engineDetails) return;
            while (nodes.engineDetails.firstChild) nodes.engineDetails.removeChild(nodes.engineDetails.firstChild);
            const metadata = [
                ['Nhà cung cấp', payload?.provider],
                ['Phiên bản mô hình', payload?.model_version],
                ['Phiên bản quy tắc', payload?.rule_version],
                ['Phiên bản hướng dẫn', payload?.prompt_version],
                ['Lý do dùng phương án dự phòng', payload?.fallback_reason],
            ].filter((entry) => typeof entry[1] === 'string' && entry[1].trim() !== '');
            if (metadata.length === 0) return;

            const details = document.createElement('details');
            details.className = 'learner-ai-engine-details';
            const summary = document.createElement('summary');
            summary.textContent = 'Thông tin kỹ thuật';
            const list = document.createElement('dl');
            for (const [label, value] of metadata) {
                const term = document.createElement('dt');
                term.textContent = label;
                const description = document.createElement('dd');
                description.textContent = value;
                list.append(term, description);
            }
            details.append(summary, list);
            nodes.engineDetails.appendChild(details);
        }

        function renderItem(item) {
            const article = document.createElement('article');
            article.className = 'learner-card learner-ai-result';
            const title = document.createElement('h4');
            title.textContent = text(item.title, 'Gợi ý phát triển');
            const summary = document.createElement('p');
            summary.className = 'learner-ai-result__summary';
            summary.textContent = text(item.summary, 'Gợi ý được xây dựng từ dữ liệu bạn đã cho phép.');
            article.append(title, summary);

            const type = text(item.item_type, 'development');
            const typeLabel = document.createElement('small');
            typeLabel.className = 'learner-ai-result__type';
            typeLabel.textContent = ({ activity: 'Hoạt động', strength: 'Điểm mạnh', improvement: 'Cần cải thiện', development: 'Phát triển', roadmap: 'Lộ trình' })[type] || 'Gợi ý';
            article.appendChild(typeLabel);

            const action = item.action && typeof item.action === 'object' ? item.action : null;
            let hasActionLink = false;
            if (action?.type === 'register_activity'
                && typeof action.activity_source_id === 'string'
                && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(action.activity_source_id)) {
                const link = document.createElement('a');
                link.className = 'learner-btn learner-btn--primary';
                link.href = `activity-detail.php?id=${encodeURIComponent(action.activity_source_id)}`;
                link.textContent = 'Xem hoạt động và đăng ký';
                article.appendChild(link);
                hasActionLink = true;
            }
            if (!hasActionLink) {
                const evidence = Array.isArray(item.evidence) ? item.evidence : [];
                const catalog = evidence.find((entry) => ['catalog', 'opportunity'].includes(entry?.source_type)
                    && entry?.safe_value && typeof entry.safe_value === 'object');
                const url = typeof catalog?.safe_value?.url === 'string' ? catalog.safe_value.url.trim() : '';
                if (/^\/(?!\/)[A-Za-z0-9._~!$&'()*+,;=:@%/?#-]+$/.test(url)) {
                    const link = document.createElement('a');
                    link.className = 'learner-btn learner-btn--primary';
                    link.href = url;
                    link.textContent = 'Xem chi tiết';
                    article.appendChild(link);
                }
            }

            const itemId = text(item.item_id, '');
            const evidence = Array.isArray(item.evidence) ? item.evidence : [];
            if (itemId !== '' && evidence.length > 0) {
                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'learner-btn learner-btn--text';
                toggle.textContent = 'Xem dữ liệu nguồn';
                toggle.dataset.aiEvidenceToggle = itemId;
                toggle.setAttribute('aria-expanded', 'false');
                const evidenceList = document.createElement('ul');
                evidenceList.className = 'learner-ai-evidence';
                evidenceList.hidden = true;
                for (const record of evidence) evidenceList.appendChild(renderEvidence(record));
                evidenceByItem.set(itemId, { toggle, evidenceList });
                article.append(toggle, evidenceList);
            }

            if (itemId !== '') {
                const feedback = document.createElement('div');
                feedback.className = 'learner-ai-feedback-actions';
                feedback.append(
                    feedbackButton(itemId, 'helpful', 'relevant', 'Hữu ích'),
                    feedbackButton(itemId, 'not_helpful', 'not_relevant', 'Chưa phù hợp'),
                    feedbackButton(itemId, 'not_helpful', 'unsafe_output', 'Báo cáo nội dung không an toàn', true),
                );
                article.appendChild(feedback);
            }
            return article;
        }

        function renderEvidence(record) {
            const row = document.createElement('li');
            const date = displayDate(record?.observedAt ?? record?.observed_at);
            row.textContent = `${sourceLabel(record?.sourceType ?? record?.source_type)} · ${date}`;
            return row;
        }

        function toggleEvidence(itemId) {
            const entry = evidenceByItem.get(itemId);
            if (!entry) return;
            entry.evidenceList.hidden = !entry.evidenceList.hidden;
            entry.toggle.setAttribute('aria-expanded', String(!entry.evidenceList.hidden));
        }

        function focusFeedback() {
            if (!nodes.feedbackStatus) return;
            try {
                nodes.feedbackStatus.focus({ preventScroll: true });
            } catch {
                nodes.feedbackStatus.focus();
            }
        }

        return { render, toggleEvidence, focusFeedback };
    }

    function feedbackButton(itemId, verdict, reasonCode, label, reportUnsafe = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'learner-btn learner-btn--text';
        button.textContent = label;
        button.dataset.aiFeedbackItem = itemId;
        button.dataset.aiFeedbackVerdict = verdict;
        button.dataset.aiFeedbackReason = reasonCode;
        if (reportUnsafe) button.dataset.aiReportUnsafe = 'true';
        return button;
    }

    function engineLabel(state, payload = {}) {
        const effectiveState = state !== 'feedback-saved'
            ? state
            : (typeof payload?.state === 'string'
                ? presentationState(payload)
                : (payload?.engine_type === 'model' ? 'ready-model' : 'ready-rule'));
        if (effectiveState === 'ready-model') return 'Gợi ý từ mô hình AI';
        if (effectiveState === 'stale-model') return 'Gợi ý AI gần nhất';
        if (effectiveState === 'fallback-rule') return 'Gợi ý dự phòng theo quy tắc';
        return 'Gợi ý theo quy tắc';
    }

    function sourceLabel(sourceType) {
        return {
            skill: 'Kỹ năng đã xác minh',
            assessment: 'Kết quả đánh giá',
            activity_experience: 'Hoạt động đã xác nhận',
            evaluation: 'Đánh giá giáo viên đã công bố',
        }[String(sourceType || '')] || 'Dữ liệu đã cho phép';
    }

    function displayDate(value) {
        if (typeof value !== 'string' || value === '') return 'Ngày nguồn không xác định';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Ngày nguồn không xác định';
        return new Intl.DateTimeFormat('vi-VN', { timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
    }

    function text(value, fallback) {
        return typeof value === 'string' && value.trim() !== '' ? value : fallback;
    }

    function boot() {
        if (typeof document === 'undefined') return;
        const root = document.querySelector('[data-ai-page]');
        if (!root || !global.TalentHubLearnerApi) return;
        const bootNode = document.getElementById('learner-session-boot');
        let csrfToken = '';
        try {
            csrfToken = JSON.parse(bootNode?.textContent || '{}').csrfToken || '';
        } catch {
            csrfToken = '';
        }
        let api;
        try {
            api = global.TalentHubLearnerApi.createLearnerApiClient({
                baseUrl: '/app/learner/api/v1',
                csrfToken,
            });
        } catch {
            return;
        }
        const controller = createRecommendationController({ api, view: createDomView(root) });
        root.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('button') : null;
            if (!target || !root.contains(target)) return;
            if (target.matches('[data-ai-generate]')) {
                controller.generate();
            } else if (target.matches('[data-ai-retry]')) {
                controller.retry();
            } else if (target.dataset.aiGrantScopes) {
                controller.grantMissingConsent(target.dataset.aiGrantScopes.split(','));
            } else if (target.dataset.aiEvidenceToggle) {
                controller.expandEvidence(target.dataset.aiEvidenceToggle);
            } else if (target.dataset.aiFeedbackItem) {
                controller.submitFeedback({
                    itemId: target.dataset.aiFeedbackItem,
                    verdict: target.dataset.aiFeedbackVerdict,
                    reasonCode: target.dataset.aiFeedbackReason,
                });
            }
        });
        controller.load();
    }

    const api = { createRecommendationController, createDomView, presentationState, recommendationSection, engineLabel };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.TalentHubLearnerRecommendations = api;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
        else boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
