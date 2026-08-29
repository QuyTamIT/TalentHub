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

    function integerOrNull(value, minimum = 0, maximum = 100) {
        if (value === null || value === undefined || value === '') return null;
        const parsed = Number(value);
        return Number.isInteger(parsed) && parsed >= minimum && parsed <= maximum ? parsed : null;
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

    function isLikelyVietnamese(value) {
        const text = normalizeText(value);
        return /[ăâđêôơưáàảãạắằẳẵặấầẩẫậéèẻẽẹếềểễệíìỉĩịóòỏõọốồổỗộớờởỡợúùủũụứừửữựýỳỷỹỵ]/i.test(text)
            || /\b(bạn|cơ hội|kỹ năng|phù hợp|hồ sơ|hiện tại|điểm|dự án)\b/i.test(text);
    }

    function renderAnalysisValues(target, values, fallback, { humanize = false } = {}) {
        if (!target) return;
        const normalized = normalizeTextList(values)
            .map((value) => humanize ? humanizeOpportunityLabel(value) : value)
            .filter(Boolean);
        const visible = normalized.length ? normalized : [fallback];
        target.replaceChildren();
        visible.forEach((value) => target.appendChild(element('span', 'learner-opportunity-ai__analysis-chip', value)));
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
            ids.add(catalogId);
            normalized.push({
                catalog_id: catalogId,
                rank: Number.isInteger(Number(item.rank)) ? Number(item.rank) : index + 1,
                match_score: score,
                why_fit: normalizeText(item.why_fit),
                why_not_fit_yet: normalizeText(item.why_not_fit_yet),
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
            if (state === 'ready-model' || state === 'stale-model' || state === 'low-fit-model') {
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
        if (score >= 60) return { className: 'is-potential', label: 'Phù hợp' };
        if (score >= 40) return { className: 'is-low-fit', label: 'Phù hợp ít' };
        return { className: 'is-no-fit', label: 'Chưa phù hợp' };
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
        const whyLabel = item.tier === 'low_fit' ? 'Vì sao chưa phù hợp' : 'Vì sao phù hợp';
        details.appendChild(detailColumn(whyLabel, item.tier === 'low_fit' ? item.why_not_fit_yet : item.why_fit, 'neutral', 'AI chưa cung cấp diễn giải.'));
        details.appendChild(detailColumn('Kỹ năng phù hợp', item.matched_skills, 'matched', 'Chưa có kỹ năng trùng khớp.'));
        details.appendChild(detailColumn('Cần bổ sung', item.missing_skills, 'missing', 'Chưa ghi nhận khoảng trống kỹ năng.'));
        if (item.tier === 'low_fit') {
            details.appendChild(detailColumn('Điều kiện còn thiếu', item.missing_conditions, 'missing', 'Không có điều kiện bổ sung được nêu.'));
            details.appendChild(detailColumn('Bước cải thiện', item.improvement_steps, 'outcome', 'Hãy cập nhật hồ sơ và thử lại.'));
        }
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
            if (state === 'ready-model' || state === 'stale-model' || state === 'low-fit-model') {
                if (list) {
                    list.replaceChildren();
                    payload.items.forEach((item) => list.appendChild(renderCard(item)));
                }
                if (state === 'low-fit-model' && panels['low-fit-model']) panels['low-fit-model'].hidden = false;
                if (panels.results) panels.results.hidden = false;
                return;
            }
            if (state === 'no-fit-model') {
                const panel = panels['no-fit-model'];
                if (panel) {
                    const analysis = payload.analysis && typeof payload.analysis === 'object' ? payload.analysis : {};
                    const headline = panel.querySelector('[data-opportunity-ai-analysis-headline]');
                    const explanation = panel.querySelector('[data-opportunity-ai-analysis-explanation]');
                    const meta = analysis.analysis_meta && typeof analysis.analysis_meta === 'object' ? analysis.analysis_meta : {};
                    const evaluatedCount = integerOrNull(meta.evaluated_count, 0, 10000) ?? 0;
                    const bestScore = integerOrNull(meta.best_score);
                    const threshold = integerOrNull(meta.match_threshold) ?? 60;
                    const dataWeight = integerOrNull(meta.data_weight) ?? 70;
                    const aiWeight = integerOrNull(meta.ai_weight) ?? 30;
                    const fallbackExplanation = evaluatedCount > 0 && bestScore !== null
                        ? `Gemini đã đối chiếu ${evaluatedCount} cơ hội. Điểm cấu trúc cao nhất là ${bestScore}/100, còn dưới ngưỡng đề xuất ${threshold}/100.`
                        : 'Gemini đã đối chiếu hồ sơ nhưng các cơ hội hiện tại chưa đạt ngưỡng đề xuất.';
                    if (headline) headline.textContent = isLikelyVietnamese(analysis.headline) ? normalizeText(analysis.headline) : 'Chưa có cơ hội đạt ngưỡng phù hợp';
                    if (explanation) explanation.textContent = isLikelyVietnamese(analysis.explanation) ? normalizeText(analysis.explanation) : fallbackExplanation;
                    const metricValues = {
                        evaluated: `${evaluatedCount} cơ hội`,
                        'best-score': bestScore === null ? '—/100' : `${bestScore}/100`,
                        threshold: `${threshold}/100`,
                        weighting: `${dataWeight}% dữ liệu · ${aiWeight}% Gemini`,
                    };
                    Object.entries(metricValues).forEach(([name, value]) => {
                        const target = panel.querySelector(`[data-opportunity-ai-metric-${name}]`);
                        if (target) target.textContent = value;
                    });
                    renderAnalysisValues(panel.querySelector('[data-opportunity-ai-analysis-strengths]'), analysis.learner_strengths, 'Chưa ghi nhận điểm mạnh nổi bật', { humanize: true });
                    renderAnalysisValues(panel.querySelector('[data-opportunity-ai-analysis-demands]'), analysis.catalog_demands, 'Danh mục hiện tại chưa khai báo kỹ năng yêu cầu', { humanize: true });
                    const gaps = normalizeTextList(analysis.main_gaps).filter(isLikelyVietnamese);
                    const nextSteps = normalizeTextList(analysis.next_steps).filter(isLikelyVietnamese);
                    renderAnalysisValues(panel.querySelector('[data-opportunity-ai-analysis-gaps]'), gaps, 'Mức độ tương đồng giữa hồ sơ và các cơ hội hiện tại còn thấp.');
                    renderAnalysisValues(panel.querySelector('[data-opportunity-ai-analysis-next-steps]'), nextSteps, 'Tiếp tục bổ sung kỹ năng và bằng chứng dự án đã được xác minh trong hồ sơ năng lực.');
                    const sources = [...new Set(normalizeTextList(analysis.evidence_ref_ids).map(evidenceLabel))];
                    renderAnalysisValues(panel.querySelector('[data-opportunity-ai-analysis-sources]'), sources, 'Hồ sơ kỹ năng và danh mục cơ hội');
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
        mountOpportunityMatches,
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.TalentHubOpportunityMatches = api;
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountOpportunityMatches, { once: true });
        else mountOpportunityMatches();
    }
})(typeof window !== 'undefined' ? window : globalThis);
