(function initLearnerSkillGap(global) {
    'use strict';

    const ENDPOINT = '/ai-job-matches.php';
    const STATE_MAP = Object.freeze({
        not_generated: 'not-generated',
        consent_required: 'consent-required',
        insufficient_data: 'insufficient-data',
        benchmark_insufficient: 'insufficient-data',
        catalog_insufficient: 'catalog-insufficient',
        no_matching_jobs: 'no-matches',
        pending: 'loading',
        ready_model: 'ready-model',
        stale_model: 'stale-model',
        provider_unavailable: 'source-error',
        rate_limited: 'source-error',
        invalid_response: 'source-error',
    });

    function cleanText(value, fallback = '') {
        return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
    }

    function score(value) {
        const numeric = Number(value);
        return Number.isFinite(numeric) && numeric >= 0 && numeric <= 100 ? Math.round(numeric) : null;
    }

    function cleanCodes(value) {
        return Array.isArray(value)
            ? value.map((item) => cleanText(item)).filter(Boolean).slice(0, 8)
            : [];
    }

    function isSafeLearnerActivityUrl(value) {
        if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || /\\|%2e|%2f|%5c/i.test(value)) return false;
        try {
            const url = new URL(value, 'https://talenthub.invalid');
            const allowedPaths = new Set([
                '/app/learner/activity-detail.php',
                '/app/learner/project.php',
                '/app/learner/opportunity.php',
            ]);
            const id = url.searchParams.get('id') || '';
            return url.origin === 'https://talenthub.invalid'
                && allowedPaths.has(url.pathname)
                && /^[a-z0-9][a-z0-9_-]{0,127}$/i.test(id);
        } catch {
            return false;
        }
    }

    function normalizeSkill(raw, includeImpact) {
        const code = cleanText(raw?.code);
        const label = cleanText(raw?.label);
        const current = score(raw?.current_score);
        const target = score(raw?.target_score);
        const gap = score(raw?.gap_score);
        if (!code || !label || current === null || target === null || gap === null) return null;
        const item = {
            code,
            label,
            current_score: current,
            target_score: target,
            gap_score: gap,
            evidence_count: Array.isArray(raw?.evidence_refs) ? raw.evidence_refs.length : 0,
        };
        if (includeImpact) item.impact = cleanText(raw?.impact, 'Kỹ năng này chưa đạt benchmark của vị trí mục tiêu.');
        return item;
    }

    function normalizeSkillGapPayload(payload) {
        const gap = payload && typeof payload.skill_gap === 'object' ? payload.skill_gap : {};
        const targetRole = cleanText(gap?.role?.title);
        const matchScore = score(gap?.match_score);
        const readinessScore = score(gap?.skill_readiness_score);
        const skillsMet = (Array.isArray(gap?.skills_met) ? gap.skills_met : [])
            .map((item) => normalizeSkill(item, false)).filter(Boolean).slice(0, 12);
        const skillsMissing = (Array.isArray(gap?.skills_missing) ? gap.skills_missing : [])
            .map((item) => normalizeSkill(item, true)).filter(Boolean).slice(0, 12);
        const activities = (Array.isArray(gap?.recommended_activities) ? gap.recommended_activities : [])
            .slice(0, 3).map((raw) => ({
                catalog_id: cleanText(raw?.catalog_id),
                title: cleanText(raw?.title),
                item_type: cleanText(raw?.item_type),
                provider_name: cleanText(raw?.provider_name),
                url: isSafeLearnerActivityUrl(raw?.url) ? raw.url : '',
                reason: cleanText(raw?.reason),
                skill_codes: cleanCodes(raw?.skill_codes),
            })).filter((item) => item.catalog_id && item.title && item.reason);

        return {
            target_role: targetRole,
            match_score: matchScore,
            skill_readiness_score: readinessScore,
            is_near_match: payload?.state === 'no_matching_jobs',
            near_match_title: cleanText(payload?.near_match?.title),
            gap_state: cleanText(gap?.state),
            skills_met: skillsMet,
            skills_missing: skillsMissing,
            activities,
        };
    }

    function presentationState(payload) {
        const mapped = STATE_MAP[cleanText(payload?.state)] || 'source-error';
        if (mapped === 'no-matches') {
            const model = normalizeSkillGapPayload(payload);
            return model.target_role && model.match_score !== null && model.skill_readiness_score !== null ? 'ready-model' : 'no-matches';
        }
        if (mapped !== 'ready-model' && mapped !== 'stale-model') return mapped;
        const model = normalizeSkillGapPayload(payload);
        if (!model.target_role || model.match_score === null || model.skill_readiness_score === null) return 'not-generated';
        return mapped;
    }

    function createSkillGapController({ api, view }) {
        if (!api || typeof api.get !== 'function') throw new TypeError('Skill Gap requires an API client.');
        if (!view || typeof view.render !== 'function') throw new TypeError('Skill Gap requires a view.');
        return {
            async load() {
                view.render('loading', {});
                try {
                    const payload = await api.get(ENDPOINT);
                    const state = presentationState(payload);
                    const model = state === 'ready-model' || state === 'stale-model'
                        ? normalizeSkillGapPayload(payload)
                        : {};
                    view.render(state, model);
                    return model;
                } catch {
                    view.render('source-error', {});
                    return null;
                }
            },
        };
    }

    function createSkillGapView(root) {
        const doc = root.ownerDocument || global.document;
        const target = root.querySelector('[data-skill-gap-target]');
        const scores = root.querySelector('[data-skill-gap-scores]');
        const met = root.querySelector('[data-skill-gap-met]');
        const missing = root.querySelector('[data-skill-gap-missing]');
        const activities = root.querySelector('[data-skill-gap-activities]');
        const status = root.querySelector('[data-skill-gap-status]');
        const content = root.querySelector('[data-skill-gap-content]');

        function clear(node) {
            while (node && node.firstChild) node.removeChild(node.firstChild);
        }

        function element(tag, className, value) {
            const node = doc.createElement(tag);
            if (className) node.className = className;
            if (value !== undefined) node.textContent = String(value);
            return node;
        }

        function scoreCard(label, value, modifier) {
            const card = element('div', `learner-skill-gap__score ${modifier}`);
            const heading = element('div', 'learner-skill-gap__score-heading');
            heading.append(element('span', '', label), element('strong', '', `${value}/100`));
            const track = element('div', 'learner-skill-gap__score-track');
            track.setAttribute('role', 'progressbar');
            track.setAttribute('aria-valuemin', '0');
            track.setAttribute('aria-valuemax', '100');
            track.setAttribute('aria-valuenow', String(value));
            const fill = element('span');
            fill.style.width = `${value}%`;
            track.appendChild(fill);
            card.append(heading, track);
            return card;
        }

        function skillCard(skill, isMissing) {
            const card = element('article', `learner-skill-gap__skill ${isMissing ? 'is-missing' : 'is-met'}`);
            const heading = element('div', 'learner-skill-gap__skill-heading');
            const titleWrap = element('div', 'learner-skill-gap__skill-title-wrap');
            titleWrap.appendChild(element('h4', '', skill.label));

            const badge = element('span', `learner-skill-badge ${isMissing ? 'learner-skill-badge--missing' : 'learner-skill-badge--met'}`,
                isMissing ? `Thiếu ${skill.gap_score}đ` : '✓ Đạt chuẩn');
            heading.append(titleWrap, badge);
            card.appendChild(heading);

            // Comparative Visual Progress Bar
            const barContainer = element('div', 'learner-skill-gap__bar-container');
            const barTrack = element('div', 'learner-skill-gap__bar-track');

            const barCurrent = element('div', 'learner-skill-gap__bar-fill');
            const currentPct = Math.min(100, Math.max(0, skill.current_score));
            barCurrent.style.width = `${currentPct}%`;
            barTrack.appendChild(barCurrent);

            // Target marker line
            const targetPct = Math.min(100, Math.max(0, skill.target_score));
            const targetMarker = element('div', 'learner-skill-gap__target-marker');
            targetMarker.style.left = `${targetPct}%`;
            targetMarker.title = `Điểm chuẩn mục tiêu: ${skill.target_score}/100`;
            barTrack.appendChild(targetMarker);
            barContainer.appendChild(barTrack);
            card.appendChild(barContainer);

            const metrics = element('div', 'learner-skill-gap__skill-metrics');
            metrics.append(
                element('span', 'learner-skill-metric-item', `Hiện tại: ${skill.current_score}`),
                element('span', 'learner-skill-metric-target', `Chuẩn vị trí: ${skill.target_score}`)
            );
            card.appendChild(metrics);

            if (isMissing) {
                const impactEl = element('p', 'learner-skill-gap__impact');
                impactEl.textContent = skill.impact;
                card.appendChild(impactEl);
            } else {
                const evidenceEl = element('p', 'learner-skill-gap__evidence');
                evidenceEl.textContent = `${skill.evidence_count} bằng chứng năng lực đã xác thực`;
                card.appendChild(evidenceEl);
            }
            return card;
        }

        function activityCard(activity) {
            const card = element('article', 'learner-skill-gap__activity');
            const header = element('div', 'learner-skill-gap__activity-header');
            header.append(
                element('span', 'learner-eyebrow', activity.item_type || 'Hoạt động đề xuất'),
                activity.provider_name ? element('span', 'learner-skill-gap__provider', activity.provider_name) : element('span')
            );
            card.appendChild(header);

            const body = element('div', 'learner-skill-gap__activity-body');
            body.appendChild(element('h4', '', activity.title));
            body.appendChild(element('p', '', activity.reason));
            card.appendChild(body);

            const footer = element('div', 'learner-skill-gap__activity-footer');
            if (activity.skill_codes.length) {
                const skillsWrap = element('div', 'learner-skill-gap__activity-skills');
                activity.skill_codes.forEach(code => {
                    skillsWrap.appendChild(element('span', 'learner-skill-tag', code));
                });
                footer.appendChild(skillsWrap);
            }
            if (activity.url) {
                const link = element('a', 'learner-btn learner-btn--outline learner-btn--sm', 'Xem chi tiết');
                link.href = activity.url;
                footer.appendChild(link);
            }
            card.appendChild(footer);
            return card;
        }

        function renderReady(model, stale) {
            target.textContent = model.target_role;
            clear(scores);
            scores.append(scoreCard('Mức phù hợp tổng thể', model.match_score, 'is-match'), scoreCard('Mức sẵn sàng kỹ năng', model.skill_readiness_score, 'is-readiness'));
            clear(met);
            clear(missing);
            clear(activities);
            if (model.skills_met.length) model.skills_met.forEach((skill) => met.appendChild(skillCard(skill, false)));
            else met.appendChild(element('p', 'learner-skill-gap__empty', 'Chưa có kỹ năng đạt benchmark được xác nhận.'));
            if (model.skills_missing.length) model.skills_missing.forEach((skill) => missing.appendChild(skillCard(skill, true)));
            else missing.appendChild(element('p', 'learner-skill-gap__empty', 'Bạn đã đạt toàn bộ benchmark kỹ năng của vị trí này.'));
            if (model.activities.length) model.activities.forEach((activity) => activities.appendChild(activityCard(activity)));
            else activities.appendChild(element('p', 'learner-skill-gap__empty', model.skills_missing.length ? 'Chưa có hoạt động đang mở khớp chính xác với kỹ năng còn thiếu.' : 'Hiện chưa cần hoạt động bù khoảng cách kỹ năng.'));
            status.textContent = model.is_near_match
                ? `Chưa có vị trí đạt 40 điểm. Đây là Skill Gap của ${model.near_match_title || 'vị trí gần ngưỡng nhất'}.`
                : stale ? 'Đang hiển thị phân tích gần nhất.' : 'Phân tích từ kết quả Job Matching mới nhất.';
            content.hidden = false;
        }

        const stateMessages = {
            loading: 'Đang tải kết quả Job Matching gần nhất...',
            'not-generated': 'Hãy dùng “AI gợi ý việc làm phù hợp” tại Doanh nghiệp & cơ hội để tạo phân tích Skill Gap.',
            'consent-required': 'Cần cấp quyền sử dụng dữ liệu năng lực trước khi phân tích Skill Gap.',
            'insufficient-data': 'Chưa đủ dữ liệu kỹ năng hoặc benchmark để phân tích Skill Gap.',
            'catalog-insufficient': 'Chưa có vị trí đang mở để xác định benchmark nghề mục tiêu.',
            'no-matches': 'Chưa có vị trí đạt ngưỡng phù hợp để lập Skill Gap.',
            'source-error': 'Chưa thể tải Skill Gap. Dữ liệu đã lưu không bị ảnh hưởng.',
        };

        return {
            render(state, model = {}) {
                root.dataset.state = state;
                content.hidden = true;
                if (state === 'ready-model' || state === 'stale-model') {
                    renderReady(model, state === 'stale-model');
                    return;
                }
                status.textContent = stateMessages[state] || stateMessages['source-error'];
            },
        };
    }

    function mountSkillGap() {
        const root = global.document?.querySelector('[data-skill-gap]');
        if (!root || root.__skillGapController) return root?.__skillGapController || null;
        const view = createSkillGapView(root);
        if (!global.TalentHubLearnerClient) {
            view.render('source-error', {});
            return null;
        }
        const controller = createSkillGapController({ api: global.TalentHubLearnerClient, view });
        root.__skillGapController = controller;
        controller.load();
        return controller;
    }

    const exported = { normalizeSkillGapPayload, isSafeLearnerActivityUrl, createSkillGapController, createSkillGapView, mountSkillGap };
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    global.TalentHubSkillGap = exported;
    if (global.document) {
        if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', mountSkillGap, { once: true });
        else mountSkillGap();
    }
})(typeof window !== 'undefined' ? window : globalThis);
