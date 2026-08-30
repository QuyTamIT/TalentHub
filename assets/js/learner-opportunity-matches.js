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
        low_fit_model: 'low-fit-model',
        no_fit_model: 'no-fit-model',
        partial_model: 'ready-model',
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
        'low-fit-model': 'Gemini đã phân tích các cơ hội gần phù hợp và chỉ ra khoảng trống cần bổ sung.',
        'no-fit-model': 'Gemini chưa tìm thấy cơ hội đạt ngưỡng phù hợp với hồ sơ hiện tại.',
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

    function humanizeOpportunityLabel(value) {
        const normalized = normalizeText(value).toLowerCase();
        const labels = {
            creative_design: 'Thiết kế sáng tạo',
            communication: 'Giao tiếp',
            leadership: 'Lãnh đạo',
            teamwork: 'Làm việc nhóm',
            data_analysis: 'Phân tích dữ liệu',
            logical_thinking: 'Tư duy logic',
            problem_solving: 'Giải quyết vấn đề',
            critical_thinking: 'Tư duy phản biện',
            python: 'Python',
            sql: 'SQL',
            marketing: 'Marketing',
            user_research: 'Nghiên cứu người dùng',
        };
        if (labels[normalized]) return labels[normalized];
        const words = normalized.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
        return words ? words.charAt(0).toUpperCase() + words.slice(1) : '';
    }

    function hasThreeToFourSentences(value) {
        const matches = normalizeText(value).match(/[.!?]+(?=\s|$)/g) || [];
        return matches.length >= 3 && matches.length <= 4;
    }

    function normalizeReadyItems(items) {
        if (!Array.isArray(items) || items.length < 1 || items.length > 3) return null;
        const ids = new Set();
        const normalized = [];
        for (let index = 0; index < items.length; index += 1) {
            const item = items[index] && typeof items[index] === 'object' ? items[index] : {};
            const catalogId = normalizeText(item.catalog_id);
            const score = Number(item.match_score);
            if (!catalogId || ids.has(catalogId) || !Number.isInteger(score) || score < 0 || score > 100) return null;
            const rationale = normalizeText(item.why_fit) || normalizeText(item.why_not_fit_yet);
            const fitReasons = normalizeTextList(item.fit_reasons);
            const gapReasons = normalizeTextList(item.gap_reasons);
            const skillsToDevelop = normalizeTextList(item.skills_to_develop);
            if (!hasThreeToFourSentences(rationale) || fitReasons.length === 0 || gapReasons.length === 0 || skillsToDevelop.length === 0) return null;
            ids.add(catalogId);
            normalized.push({
                catalog_id: catalogId,
                rank: Number.isInteger(Number(item.rank)) ? Number(item.rank) : index + 1,
                match_score: score,
                why_fit: rationale,
                why_not_fit_yet: normalizeText(item.why_not_fit_yet),
                fit_reasons: fitReasons,
                gap_reasons: gapReasons,
                skills_to_develop: skillsToDevelop,
                analysis_kind: normalizeText(item.analysis_kind) || 'recommendation',
                missing_conditions: normalizeTextList(item.missing_conditions).map(humanizeOpportunityLabel),
                improvement_steps: normalizeTextList(item.improvement_steps),
                tier: normalizeText(item.tier) || (score >= 60 ? 'suitable' : (score >= 40 ? 'low_fit' : 'no_fit')),
                matched_skills: normalizeTextList(item.matched_skills).map(humanizeOpportunityLabel),
                missing_skills: normalizeTextList(item.missing_skills).map(humanizeOpportunityLabel),
                expected_outcomes: normalizeTextList(item.expected_outcomes).map(humanizeOpportunityLabel),
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
            const hasAnalyzedNoFitItems = state === 'no-fit-model' && Array.isArray(payload.items) && payload.items.length > 0;
            if (state === 'ready-model' || state === 'stale-model' || state === 'low-fit-model' || hasAnalyzedNoFitItems) {
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
                renderResponse(await api.get(ENDPOINT, { timeoutMs: 30000 }));
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
                .then(() => api.send('POST', ENDPOINT, {}, { idempotencyKey, timeoutMs: 60000 }))
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

    function analysisList(label, values, tone) {
        const section = element('section', `learner-opportunity-ai-card__analysis-list is-${tone}`);
        section.appendChild(element('h4', '', label));
        const list = element('ul', '');
        values.forEach((value) => list.appendChild(element('li', '', value)));
        section.appendChild(list);
        return section;
    }

    function scoreBand(score) {
        if (score >= 90) return { className: 'is-excellent', label: 'Rất phù hợp' };
        if (score >= 80) return { className: 'is-high', label: 'Phù hợp cao' };
        if (score >= 60) return { className: 'is-potential', label: 'Phù hợp' };
        if (score >= 40) return { className: 'is-low-fit', label: 'Phù hợp ít' };
        return { className: 'is-no-fit', label: 'Chưa phù hợp' };
    }

    function renderCard(item) {
        const card = element('article', 'learner-opportunity-ai-card');
        card.dataset.catalogId = item.catalog_id;

        const header = element('header', 'learner-opportunity-ai-card__header');
        const identity = element('div', 'learner-opportunity-ai-card__identity');
        identity.appendChild(element('span', 'learner-opportunity-ai-rank', `#${item.rank}`));
        identity.appendChild(element('small', '', item.summary || 'Dự án cơ hội trên TalentHub'));
        identity.appendChild(element('h3', '', item.title));
        header.appendChild(identity);

        const band = scoreBand(item.match_score);
        const scoreWrap = element('div', `learner-opportunity-ai-score ${band.className}`);
        const scoreValue = element('div', 'learner-opportunity-ai-score__value');
        scoreValue.appendChild(element('strong', '', item.match_score));
        scoreValue.appendChild(element('span', '', '/100'));
        scoreWrap.appendChild(scoreValue);
        scoreWrap.appendChild(element('em', '', band.label));
        const scoreTrack = element('div', 'learner-opportunity-ai-score__track');
        scoreTrack.setAttribute('role', 'progressbar');
        scoreTrack.setAttribute('aria-label', `Mức độ phù hợp ${item.match_score} trên 100, ${band.label}`);
        scoreTrack.setAttribute('aria-valuemin', '0');
        scoreTrack.setAttribute('aria-valuemax', '100');
        scoreTrack.setAttribute('aria-valuenow', String(item.match_score));
        const scoreBar = element('span', 'learner-opportunity-ai-score__bar');
        scoreBar.style.width = `${item.match_score}%`;
        scoreTrack.appendChild(scoreBar);
        scoreWrap.appendChild(scoreTrack);
        header.appendChild(scoreWrap);
        card.appendChild(header);

        const narrative = element('section', 'learner-opportunity-ai-card__narrative');
        narrative.appendChild(element('h4', '', 'Phân tích của Gemini'));
        narrative.appendChild(element('p', '', item.why_fit));
        card.appendChild(narrative);

        const analysisGrid = element('div', 'learner-opportunity-ai-card__analysis-grid');
        analysisGrid.appendChild(analysisList('Tại sao phù hợp', item.fit_reasons, 'fit'));
        analysisGrid.appendChild(analysisList('Tại sao chưa phù hợp', item.gap_reasons, 'gap'));
        analysisGrid.appendChild(analysisList('Kỹ năng sẽ được học hỏi và rèn luyện', item.skills_to_develop, 'skills'));
        card.appendChild(analysisGrid);

        const actions = element('div', 'learner-opportunity-ai-card__actions');
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
            'low-fit-model': root.querySelector('[data-opportunity-ai-low-fit]'),
            'no-fit-model': root.querySelector('[data-opportunity-ai-no-fit]'),
            'source-error': root.querySelector('[data-opportunity-ai-error]'),
            results: root.querySelector('[data-opportunity-ai-results]'),
        };
        const list = root.querySelector('[data-opportunity-ai-list]');
        const progressText = root.querySelector('[data-opportunity-ai-progress-text]');
        const progressPct = root.querySelector('[data-opportunity-ai-progress-pct]');
        const progressBar = root.querySelector('[data-opportunity-ai-progress-bar]');
        const progressStages = root.querySelector('[data-opportunity-ai-progress-stages]');
        let progressTimer = null;
        let progressStartTime = 0;

        function setProgressState(percentage, text, currentStage) {
            if (progressBar) {
                progressBar.style.width = `${percentage}%`;
                const track = progressBar.parentElement;
                if (track && track.getAttribute('role') === 'progressbar') {
                    track.setAttribute('aria-valuenow', String(percentage));
                }
            }
            if (progressPct) progressPct.textContent = `${percentage}%`;
            if (progressText) progressText.textContent = text;
            if (progressStages) {
                const stages = progressStages.querySelectorAll('[data-stage]');
                stages.forEach((stageEl) => {
                    const stageNum = Number(stageEl.getAttribute('data-stage'));
                    if (stageNum < currentStage) {
                        stageEl.className = 'is-done';
                    } else if (stageNum === currentStage) {
                        stageEl.className = 'is-active';
                    } else {
                        stageEl.className = '';
                    }
                });
            }
        }

        function startProgressAnimation() {
            stopProgressAnimation();
            progressStartTime = Date.now();
            setProgressState(15, 'Đang chuẩn bị dữ liệu hồ sơ và danh mục cơ hội...', 1);
            progressTimer = global.setInterval(() => {
                const elapsed = (Date.now() - progressStartTime) / 1000;
                if (elapsed < 3) {
                    const pct = Math.min(30, Math.round(15 + elapsed * 5));
                    setProgressState(pct, 'Đang tổng hợp hồ sơ năng lực và kỹ năng...', 1);
                } else if (elapsed < 7) {
                    const pct = Math.min(55, Math.round(30 + (elapsed - 3) * 6.25));
                    setProgressState(pct, 'Đang quét danh mục cơ hội việc làm & thực tập...', 2);
                } else if (elapsed < 16) {
                    const pct = Math.min(85, Math.round(55 + (elapsed - 7) * 3.33));
                    setProgressState(pct, 'Gemini AI đang phân tích độ phù hợp và tìm điểm mạnh...', 3);
                } else {
                    const pct = Math.min(95, Math.round(85 + (elapsed - 16) * 0.8));
                    setProgressState(pct, 'Đang hoàn thiện bảng xếp hạng và diễn giải Top 3...', 4);
                }
            }, 300);
        }

        function stopProgressAnimation() {
            if (progressTimer !== null) {
                global.clearInterval(progressTimer);
                progressTimer = null;
            }
        }

        function render(state, payload = {}) {
            Object.values(panels).forEach((panel) => { if (panel) panel.hidden = true; });
            root.setAttribute('aria-busy', String(state === 'loading'));
            if (status) {
                status.textContent = COPY[state] || COPY['source-error'];
                status.className = `learner-opportunity-ai__status is-${state}`;
            }
            root.dataset.state = state;
            if (state === 'loading') {
                startProgressAnimation();
            } else {
                stopProgressAnimation();
            }
            const hasAnalyzedNoFitItems = state === 'no-fit-model' && Array.isArray(payload.items) && payload.items.length > 0;
            if (state === 'ready-model' || state === 'stale-model' || state === 'low-fit-model' || hasAnalyzedNoFitItems) {
                if (list) {
                    list.replaceChildren();
                    payload.items.forEach((item) => list.appendChild(renderCard(item)));
                }
                if (state === 'low-fit-model' && panels['low-fit-model']) panels['low-fit-model'].hidden = false;
                if (hasAnalyzedNoFitItems && panels['no-fit-model']) {
                    const analysis = payload.analysis && typeof payload.analysis === 'object' ? payload.analysis : {};
                    const headline = panels['no-fit-model'].querySelector('[data-opportunity-ai-analysis-headline]');
                    const explanation = panels['no-fit-model'].querySelector('[data-opportunity-ai-analysis-explanation]');
                    if (headline) headline.textContent = normalizeText(analysis.headline);
                    if (explanation) explanation.textContent = normalizeText(analysis.explanation);
                    panels['no-fit-model'].hidden = false;
                }
                if (panels.results) panels.results.hidden = false;
                return;
            }
            if (state === 'no-fit-model') {
                const panel = panels['no-fit-model'];
                if (panel) {
                    const analysis = payload.analysis && typeof payload.analysis === 'object' ? payload.analysis : {};
                    const headline = panel.querySelector('[data-opportunity-ai-analysis-headline]');
                    const explanation = panel.querySelector('[data-opportunity-ai-analysis-explanation]');
                    if (headline) headline.textContent = normalizeText(analysis.headline);
                    if (explanation) explanation.textContent = normalizeText(analysis.explanation);
                    panel.hidden = false;
                }
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
        if (root.__talentHubOpportunityController) return root.__talentHubOpportunityController;
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
        root.__talentHubOpportunityController = controller;
        return controller;
    }

    const api = {
        createOpportunityMatchController,
        createOpportunityMatchView,
        mapOpportunityMatchState,
        isSafeInternalOpportunityUrl,
        humanizeOpportunityLabel,
        normalizeReadyItems,
        mountOpportunityMatches,
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.TalentHubOpportunityMatches = api;
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountOpportunityMatches, { once: true });
        else mountOpportunityMatches();
    }
})(typeof window !== 'undefined' ? window : globalThis);
