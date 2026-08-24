/** Roadmap-first learner AI page. */
(function initLearnerAiRoadmap(global) {
    'use strict';

    const READY_STATES = new Set(['ready-model', 'fallback-rule']);

    function presentationState(payload) {
        const state = typeof payload?.state === 'string' ? payload.state : '';
        if (state === 'not_generated') return 'not-generated';
        if (state === 'pending') return 'pending';
        if (state === 'consent_required') return 'consent-required';
        if (state === 'insufficient_data') return 'insufficient-data';
        if (state === 'ready_model') return 'ready-model';
        if (state === 'fallback_rule') return 'fallback-rule';
        return 'source-error';
    }

    function text(value, fallback = '') {
        return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
    }

    function integer(value) {
        return Number.isInteger(value) && value >= 0 ? value : 0;
    }

    function confidenceLabel(value) {
        return { high: 'Độ tin cậy cao', medium: 'Độ tin cậy trung bình', low: 'Độ tin cậy cần kiểm chứng' }[value]
            || 'Độ tin cậy chưa xác định';
    }

    function buildRoadmapViewModel(payload) {
        const rawPhases = Array.isArray(payload?.phases) ? payload.phases : [];
        const phases = rawPhases
            .filter((phase) => phase && typeof phase === 'object')
            .sort((left, right) => integer(left.position) - integer(right.position))
            .slice(0, 3)
            .map((phase) => ({
                ...phase,
                rangeLabel: `${integer(phase.start_day)}–${integer(phase.end_day)} ngày`,
                tasks: Array.isArray(phase.tasks) ? phase.tasks.filter((task) => task && typeof task === 'object') : [],
                progress: {
                    completed_tasks: integer(phase?.progress?.completed_tasks),
                    total_tasks: integer(phase?.progress?.total_tasks),
                },
            }));
        const tasks = phases.flatMap((phase) => phase.tasks.map((task) => ({ ...task, phaseTitle: text(phase.title, 'Giai đoạn') })));
        const nextActions = tasks.filter((task) => task.status !== 'completed').slice(0, 3);
        const activities = tasks.filter((task) => task?.action?.type === 'register_activity');
        const evidence = payload?.evidence_summary && typeof payload.evidence_summary === 'object' ? payload.evidence_summary : {};
        const evidenceTotal = ['assessment_count', 'skill_count', 'activity_count', 'evaluation_count']
            .reduce((total, key) => total + integer(evidence[key]), 0);
        return {
            ...payload,
            phases,
            nextActions,
            activities,
            evidenceTotal,
            confidenceLabel: confidenceLabel(payload?.confidence_band),
        };
    }

    function defaultIdempotencyKey() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') return `roadmap-page-${global.crypto.randomUUID()}`;
        return `roadmap-page-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
    }

    function createRoadmapController({ api, view, createIdempotencyKey = defaultIdempotencyKey }) {
        if (!api || typeof api.get !== 'function' || typeof api.send !== 'function') throw new TypeError('A roadmap API client is required.');
        if (!view || typeof view.render !== 'function') throw new TypeError('A roadmap view is required.');
        let generation = null;

        function render(payload) {
            const state = presentationState(payload);
            view.render(state, READY_STATES.has(state) ? buildRoadmapViewModel(payload) : payload);
            return payload;
        }

        async function load() {
            view.render('loading', {});
            try { return render(await api.get('/ai-roadmap.php')); }
            catch (error) { return render({ state: 'source_unavailable', message: error?.message }); }
        }

        function generate(action = 'generate') {
            if (generation !== null) return generation;
            const safeAction = action === 'refresh' ? 'refresh' : 'generate';
            view.render('loading', {});
            generation = Promise.resolve(api.send(
                'POST', '/ai-roadmap.php', { action: safeAction }, { idempotencyKey: createIdempotencyKey() },
            )).then(render)
                .catch((error) => render({ state: 'source_unavailable', message: error?.message }))
                .finally(() => { generation = null; });
            return generation;
        }

        return { load, generate, retry: load };
    }

    function createDomView(root) {
        const doc = root.ownerDocument || global.document;
        const nodes = {
            status: root.querySelector('[data-roadmap-status]'),
            loading: root.querySelector('[data-roadmap-loading]'),
            notGenerated: root.querySelector('[data-roadmap-not-generated]'),
            consent: root.querySelector('[data-roadmap-consent]'),
            insufficient: root.querySelector('[data-roadmap-insufficient]'),
            pending: root.querySelector('[data-roadmap-pending]'),
            error: root.querySelector('[data-roadmap-error]'),
            ready: root.querySelector('[data-roadmap-ready]'),
            fallback: root.querySelector('[data-roadmap-fallback]'),
            freshness: root.querySelector('[data-roadmap-freshness]'),
            summaryLabel: root.querySelector('[data-roadmap-summary-label]'),
            summary: root.querySelector('[data-roadmap-summary-text]'),
            evidenceTotal: root.querySelector('[data-roadmap-evidence-total]'),
            confidence: root.querySelector('[data-roadmap-confidence]'),
            directionLabel: root.querySelector('[data-roadmap-direction-label]'),
            directionRationale: root.querySelector('[data-roadmap-direction-rationale]'),
            alternatives: root.querySelector('[data-roadmap-direction-alternatives]'),
            insights: root.querySelector('[data-roadmap-insights]'),
            phases: root.querySelector('[data-roadmap-phases]'),
            overallProgress: root.querySelector('[data-roadmap-overall-progress]'),
            nextActions: root.querySelector('[data-roadmap-next-actions]'),
            activities: root.querySelector('[data-roadmap-activities]'),
            evidence: root.querySelector('[data-roadmap-evidence-content]'),
            engine: root.querySelector('[data-roadmap-engine-content]'),
        };

        function hide(node, value) { if (node) node.hidden = value; }
        function set(node, value) { if (node) node.textContent = String(value ?? ''); }
        function clear(node) { if (node) while (node.firstChild) node.removeChild(node.firstChild); }
        function element(tag, className, value) {
            const node = doc.createElement(tag);
            if (className) node.className = className;
            if (value !== undefined) node.textContent = String(value);
            return node;
        }
        function statusCopy(state) {
            return {
                loading: 'Đang tải lộ trình AI.', 'not-generated': 'Chưa có lộ trình AI.', pending: 'AI đang tạo lộ trình.',
                'consent-required': 'Cần quyền dữ liệu để tạo lộ trình.', 'insufficient-data': 'Chưa đủ dữ liệu để tạo lộ trình.',
                'source-error': 'Chưa thể tải lộ trình.', 'ready-model': 'Lộ trình từ AI đã sẵn sàng.',
                'fallback-rule': 'Gợi ý dự phòng theo quy tắc đã sẵn sàng.',
            }[state] || 'Trạng thái lộ trình đã thay đổi.';
        }

        function render(state, payload) {
            hide(nodes.loading, state !== 'loading');
            hide(nodes.notGenerated, state !== 'not-generated');
            hide(nodes.consent, state !== 'consent-required');
            hide(nodes.insufficient, state !== 'insufficient-data');
            hide(nodes.pending, state !== 'pending');
            hide(nodes.error, state !== 'source-error');
            hide(nodes.ready, !READY_STATES.has(state));
            hide(nodes.fallback, state !== 'fallback-rule');
            set(nodes.status, statusCopy(state));
            if (READY_STATES.has(state)) renderReady(payload, state);
        }

        function renderReady(model, state) {
            set(nodes.summaryLabel, state === 'ready-model' ? 'Tóm tắt từ AI' : 'Gợi ý dự phòng theo quy tắc');
            set(nodes.summary, text(model.executive_summary, 'Chưa có nội dung tóm tắt.'));
            set(nodes.evidenceTotal, `${model.evidenceTotal} nguồn dữ liệu đã cho phép`);
            set(nodes.confidence, model.confidenceLabel);
            set(nodes.directionLabel, text(model?.primary_direction?.label, 'Chưa xác định'));
            set(nodes.directionRationale, text(model?.primary_direction?.rationale, 'Hướng này cần được kiểm chứng qua trải nghiệm thực tế.'));
            set(nodes.freshness, `Cập nhật: ${displayDate(model.generated_at)}`);
            renderAlternatives(model.alternative_directions);
            renderInsights(model.insights);
            renderPhases(model.phases);
            renderNextActions(model.nextActions);
            renderActivities(model.activities);
            renderEvidence(model.evidence_summary);
            renderEngine(model.engine, state);
            const complete = integer(model?.progress?.completed_tasks);
            const total = integer(model?.progress?.total_tasks);
            set(nodes.overallProgress, `${complete}/${total} nhiệm vụ hoàn thành`);
        }

        function renderAlternatives(items) {
            clear(nodes.alternatives);
            for (const direction of (Array.isArray(items) ? items.slice(0, 2) : [])) {
                const chip = element('span', 'learner-roadmap-direction__chip');
                const label = element('strong', '', text(direction?.label, 'Hướng bổ sung'));
                const rationale = element('small', '', text(direction?.rationale, 'Cần kiểm chứng thêm.'));
                chip.append(label, rationale);
                nodes.alternatives?.appendChild(chip);
            }
        }

        function renderInsights(items) {
            clear(nodes.insights);
            const icons = { strength: '↗', improvement: '!', potential: '◎' };
            for (const insight of (Array.isArray(items) ? items.slice(0, 3) : [])) {
                const article = element('article', `learner-card learner-roadmap-insight learner-roadmap-insight--${text(insight?.category, 'potential')}`);
                const icon = element('span', 'learner-roadmap-insight__icon', icons[insight?.category] || '◎');
                icon.setAttribute('aria-hidden', 'true');
                const copy = element('div');
                copy.append(element('h3', '', text(insight?.title, 'Nhận định')), element('p', '', text(insight?.summary, 'Cần thêm trải nghiệm để kiểm chứng.')));
                article.append(icon, copy);
                nodes.insights?.appendChild(article);
            }
        }

        function renderPhases(phases) {
            clear(nodes.phases);
            for (const phase of phases) {
                const details = doc.createElement('details');
                details.className = 'learner-roadmap-phase';
                if (integer(phase.position) === 1) details.open = true;
                const summary = element('summary', 'learner-roadmap-phase__summary');
                const number = element('span', 'learner-roadmap-phase__number', integer(phase.position));
                const heading = element('span');
                heading.append(element('small', '', phase.rangeLabel), element('strong', '', text(phase.title, 'Giai đoạn')));
                const progress = element('span', 'learner-roadmap-phase__progress', `${phase.progress.completed_tasks}/${phase.progress.total_tasks}`);
                summary.append(number, heading, progress);
                const body = element('div', 'learner-roadmap-phase__body');
                body.append(renderPhaseFacts(phase), renderTasks(phase.tasks));
                details.append(summary, body);
                nodes.phases?.appendChild(details);
            }
        }

        function renderPhaseFacts(phase) {
            const list = element('dl', 'learner-roadmap-phase__facts');
            for (const [label, value] of [
                ['Mục tiêu', phase.goal], ['Kỹ năng trọng tâm', phase.skill_focus], ['Sản phẩm/Đầu ra', phase.deliverable],
                ['Nỗ lực', phase.effort_label], ['Đo lường', phase.metric_label],
            ]) {
                list.append(element('dt', '', label), element('dd', '', text(value, 'Chưa xác định')));
            }
            return list;
        }

        function renderTasks(tasks) {
            const list = element('ol', 'learner-roadmap-task-list');
            for (const task of tasks) {
                const item = element('li', `learner-roadmap-task is-${text(task?.status, 'not_started')}`);
                const control = element('button', 'learner-roadmap-task__control');
                control.type = 'button';
                control.dataset.roadmapTaskId = text(task?.task_id);
                control.dataset.roadmapTaskStatus = text(task?.status, 'not_started');
                control.setAttribute('aria-label', `${task?.status === 'completed' ? 'Đã hoàn thành' : 'Đánh dấu hoàn thành'}: ${text(task?.title, 'Nhiệm vụ')}`);
                control.textContent = task?.status === 'completed' ? '✓' : '';
                const copy = element('span');
                copy.append(element('strong', '', text(task?.title, 'Nhiệm vụ')), element('small', '', `${integer(task?.estimated_minutes)} phút · ${text(task?.description, 'Đầu việc thực hành')}`));
                item.append(control, copy);
                list.appendChild(item);
            }
            return list;
        }

        function renderNextActions(tasks) {
            clear(nodes.nextActions);
            if (tasks.length === 0) {
                nodes.nextActions?.appendChild(element('p', 'learner-roadmap-empty', 'Bạn đã hoàn thành toàn bộ nhiệm vụ hiện tại.'));
                return;
            }
            for (const task of tasks) {
                const article = element('article', 'learner-roadmap-next__item');
                article.append(element('strong', '', text(task.title, 'Nhiệm vụ tiếp theo')), element('span', '', text(task.phaseTitle, 'Giai đoạn')), element('small', '', `Khoảng ${integer(task.estimated_minutes)} phút`));
                nodes.nextActions?.appendChild(article);
            }
        }

        function renderActivities(tasks) {
            clear(nodes.activities);
            if (tasks.length === 0) {
                nodes.activities?.appendChild(element('p', 'learner-roadmap-empty', 'Chưa có hoạt động hệ thống phù hợp trong lộ trình hiện tại.'));
                return;
            }
            for (const task of tasks.slice(0, 3)) {
                const article = element('article', 'learner-roadmap-activity');
                article.append(element('span', 'learner-roadmap-activity__badge', 'Hoạt động'), element('strong', '', text(task.title, 'Hoạt động phù hợp')), element('p', '', text(task.description, 'Hoạt động giúp thực hành kỹ năng trong lộ trình.')));
                nodes.activities?.appendChild(article);
            }
        }

        function renderEvidence(summary) {
            clear(nodes.evidence);
            const labels = [
                ['assessment_count', 'Kết quả đánh giá'], ['skill_count', 'Kỹ năng đã xác minh'],
                ['activity_count', 'Hoạt động đã xác nhận'], ['evaluation_count', 'Đánh giá đã công bố'],
            ];
            const list = element('ul', 'learner-roadmap-evidence-list');
            for (const [key, label] of labels) list.appendChild(element('li', '', `${label}: ${integer(summary?.[key])}`));
            nodes.evidence?.appendChild(list);
        }

        function renderEngine(engine, state) {
            clear(nodes.engine);
            const allowed = state === 'ready-model'
                ? [['Nhà cung cấp', engine?.provider], ['Mô hình', engine?.model_version], ['Phiên bản hướng dẫn', engine?.prompt_version]]
                : [['Phiên bản quy tắc', engine?.rule_version], ['Lý do dự phòng', engine?.fallback_reason]];
            for (const [label, value] of allowed) {
                if (!text(value)) continue;
                nodes.engine?.append(element('dt', '', label), element('dd', '', text(value)));
            }
        }

        return { render };
    }

    function displayDate(value) {
        if (typeof value !== 'string' || value === '') return 'chưa xác định';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'chưa xác định';
        return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
    }

    function boot() {
        const root = global.document?.querySelector('[data-ai-roadmap-page]');
        const factory = global.TalentHubLearnerApi?.createLearnerApiClient;
        if (!root || typeof factory !== 'function') return;
        let csrfToken = '';
        try { csrfToken = JSON.parse(global.document.getElementById('learner-session-boot')?.textContent || '{}').csrfToken || ''; }
        catch { csrfToken = ''; }
        const controller = createRoadmapController({ api: factory({ baseUrl: '/app/learner/api/v1', csrfToken }), view: createDomView(root) });
        root.addEventListener('click', (event) => {
            const target = event.target instanceof global.Element ? event.target.closest('button') : null;
            if (!target || !root.contains(target)) return;
            if (target.matches('[data-roadmap-generate]')) controller.generate(target.dataset.roadmapGenerate);
            else if (target.matches('[data-roadmap-retry]')) controller.retry();
            else if (target.matches('[data-roadmap-continue]')) root.querySelector('.learner-roadmap-task:not(.is-completed) .learner-roadmap-task__control')?.focus();
        });
        controller.load();
    }

    const exported = { presentationState, buildRoadmapViewModel, createRoadmapController, createDomView, confidenceLabel };
    global.TalentHubLearnerAiRoadmap = exported;
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    if (global.document && typeof global.document.addEventListener === 'function') {
        if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', boot, { once: true });
        else boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
