/** Roadmap-first learner AI page. */
(function initLearnerAiRoadmap(global) {
    'use strict';

    const READY_STATES = new Set(['ready-model', 'stale-model', 'fallback-rule']);

    function presentationState(payload) {
        const state = typeof payload?.state === 'string' ? payload.state : '';
        if (state === 'not_generated') return 'not-generated';
        if (state === 'pending') return 'pending';
        if (state === 'consent_required') return 'consent-required';
        if (state === 'insufficient_data') return 'insufficient-data';
        if (state === 'ready_model') return 'ready-model';
        if (state === 'stale_model') return 'stale-model';
        if (state === 'ready_rule') return 'fallback-rule';
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

    function normalizeTalentScore(value) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) return 0;
        const percentage = numeric > 0 && numeric <= 1 ? numeric * 100 : numeric;
        return Math.max(0, Math.min(100, Math.round(percentage)));
    }

    function buildRoadmapViewModel(payload) {
        const records = (value, limit = 8) => (Array.isArray(value) ? value.filter((item) => item && typeof item === 'object').slice(0, limit) : []);
        const rawPhases = Array.isArray(payload?.phases) ? payload.phases : [];
        const phases = rawPhases
            .filter((phase) => phase && typeof phase === 'object')
            .sort((left, right) => integer(left.position) - integer(right.position))
            .slice(0, 3)
            .map((phase) => ({
                ...phase,
                rangeLabel: `${integer(phase.start_day) === 0 ? 1 : integer(phase.start_day)}–${integer(phase.end_day)} ngày`,
                tasks: Array.isArray(phase.tasks) ? phase.tasks.filter((task) => task && typeof task === 'object') : [],
                progress: {
                    completed_tasks: integer(phase?.progress?.completed_tasks),
                    total_tasks: integer(phase?.progress?.total_tasks),
                },
            }));
        const currentPhaseIndex = phases.findIndex((phase) => {
            const total = phase.progress.total_tasks || phase.tasks.length;
            const complete = phase.progress.completed_tasks;
            return total === 0 ? phase.tasks.some((task) => task.status !== 'completed') : complete < total;
        });
        const phaseStateIndex = currentPhaseIndex === -1 ? phases.length : currentPhaseIndex;
        for (const [index, phase] of phases.entries()) {
            phase.status = index < phaseStateIndex ? 'completed' : index === currentPhaseIndex ? 'current' : 'upcoming';
            phase.displayTasks = phase.tasks.slice(0, 2);
        }
        const tasks = phases.flatMap((phase) => phase.tasks.map((task) => ({ ...task, phaseTitle: text(phase.title, 'Giai đoạn') })));
        const nextActions = tasks.filter((task) => task.status !== 'completed').slice(0, 3);
        const activities = tasks.filter((task) => task?.action?.type === 'register_activity');
        const evidence = payload?.evidence_summary && typeof payload.evidence_summary === 'object' ? payload.evidence_summary : {};
        const evidenceTotal = ['assessment_count', 'skill_count', 'activity_count', 'evaluation_count']
            .reduce((total, key) => total + integer(evidence[key]), 0);
        const completedTasks = integer(payload?.progress?.completed_tasks);
        const totalTasks = integer(payload?.progress?.total_tasks);
        return {
            ...payload,
            phases,
            nextActions,
            activities,
            evidenceTotal,
            confidenceLabel: confidenceLabel(payload?.confidence_band),
            talentMap: records(payload?.talent_map).map((item) => ({ ...item, score: normalizeTalentScore(item?.score) })),
            strengths: records(payload?.strengths),
            improvements: records(payload?.improvements),
            potentialPaths: records(payload?.potential_paths),
            trendSignals: records(payload?.trend_signals),
            growthHypotheses: records(payload?.growth_hypotheses),
            currentPhaseIndex,
            overallPercent: totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0,
        };
    }

    function defaultIdempotencyKey() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') return `roadmap-page-${global.crypto.randomUUID()}`;
        return `roadmap-page-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
    }

    function createRoadmapApiClient(factory, suppliedDocument = null) {
        const targetDocument = suppliedDocument || global.document;
        let csrfToken = '';
        try { csrfToken = JSON.parse(targetDocument?.getElementById('learner-session-boot')?.textContent || '{}').csrfToken || ''; }
        catch { csrfToken = ''; }
        return factory({ baseUrl: '/app/learner/api/v1', csrfToken, timeoutMs: 45000 });
    }

    function createRoadmapController({
        api, view, createIdempotencyKey = defaultIdempotencyKey,
        schedule = (callback, delay) => global.setTimeout(callback, delay),
        cancelSchedule = (handle) => global.clearTimeout(handle),
        pendingDelays = [1000, 2000, 4000, 8000, 15000],
    }) {
        if (!api || typeof api.get !== 'function' || typeof api.send !== 'function') throw new TypeError('A roadmap API client is required.');
        if (!view || typeof view.render !== 'function') throw new TypeError('A roadmap view is required.');
        let generation = null;
        let currentRoadmapId = '';
        let pendingAttempt = 0;
        let pendingHandle = null;

        function stopPolling(reset = true) {
            if (pendingHandle !== null) cancelSchedule(pendingHandle);
            pendingHandle = null;
            if (reset) pendingAttempt = 0;
        }

        function schedulePendingPoll() {
            if (pendingHandle !== null || pendingAttempt >= pendingDelays.length) return;
            const delay = pendingDelays[pendingAttempt];
            pendingAttempt += 1;
            pendingHandle = schedule(async () => {
                pendingHandle = null;
                await load(false);
            }, delay);
        }

        function render(payload) {
            const state = presentationState(payload);
            if (READY_STATES.has(state)) currentRoadmapId = text(payload?.roadmap_id);
            view.render(state, READY_STATES.has(state) ? buildRoadmapViewModel(payload) : payload);
            if (state === 'pending') schedulePendingPoll();
            else stopPolling();
            return payload;
        }

        async function load(showLoading = true) {
            if (showLoading) view.render('loading', {});
            try { return render(await api.get('/ai-roadmap.php')); }
            catch (error) { return render({ state: 'source_unavailable', message: error?.message }); }
        }

        async function loadVersion(version) {
            const safeVersion = Number.parseInt(version, 10);
            if (!Number.isInteger(safeVersion) || safeVersion < 1) throw new TypeError('Roadmap version is invalid.');
            view.render('loading', {});
            try { return render(await api.get(`/ai-roadmap.php?version=${safeVersion}`)); }
            catch (error) { return render({ state: 'source_unavailable', message: error?.message }); }
        }

        async function updateTask(taskId, currentStatus = 'not_started') {
            const id = text(taskId);
            if (!id) throw new TypeError('Roadmap task id is required.');
            const previous = text(currentStatus, 'not_started');
            view.updateTask?.(id, 'completed');
            try {
                const response = await api.send('POST', '/ai-roadmap-task.php', { taskId: id, status: 'completed' }, { idempotencyKey: createIdempotencyKey() });
                view.feedback?.('task-saved');
                return response;
            } catch (error) {
                view.updateTask?.(id, previous);
                view.feedback?.('task-error');
                throw error;
            }
        }

        async function submitFeedback(roadmapId, verdict) {
            const id = text(roadmapId || currentRoadmapId);
            const safeVerdict = verdict === 'helpful' ? 'helpful' : verdict === 'not_helpful' ? 'not_helpful' : '';
            if (!id || !safeVerdict) throw new TypeError('Roadmap feedback is invalid.');
            view.feedback?.('feedback-saving');
            try {
                const response = await api.send('POST', '/recommendation-feedback.php', {
                    roadmapId: id,
                    verdict: safeVerdict,
                    reasonCode: safeVerdict === 'helpful' ? 'useful_direction' : 'not_relevant',
                }, { idempotencyKey: createIdempotencyKey() });
                view.feedback?.('feedback-saved');
                return response;
            } catch (error) {
                view.feedback?.('feedback-error');
                throw error;
            }
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

        return { load, loadVersion, generate, retry: load, updateTask, submitFeedback, dispose: stopPolling };
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
            fallbackCopy: root.querySelector('[data-roadmap-fallback-copy]'),
            freshness: root.querySelector('[data-roadmap-freshness]'),
            summaryLabel: root.querySelector('[data-roadmap-summary-label]'),
            summary: root.querySelector('[data-roadmap-summary-text]'),
            evidenceTotal: root.querySelector('[data-roadmap-evidence-total]'),
            confidence: root.querySelector('[data-roadmap-confidence]'),
            directionLabel: root.querySelector('[data-roadmap-direction-label]'),
            directionRationale: root.querySelector('[data-roadmap-direction-rationale]'),
            alternatives: root.querySelector('[data-roadmap-direction-alternatives]'),
            insights: root.querySelector('[data-roadmap-insights]'),
            talentMap: root.querySelector('[data-roadmap-talent-map]'),
            strengths: root.querySelector('[data-roadmap-strengths]'),
            improvements: root.querySelector('[data-roadmap-improvements]'),
            trends: root.querySelector('[data-roadmap-trends]'),
            potentialPaths: root.querySelector('[data-roadmap-potential-paths]'),
            growthHypotheses: root.querySelector('[data-roadmap-growth-hypotheses]'),
            phases: root.querySelector('[data-roadmap-phases]'),
            overallProgress: root.querySelector('[data-roadmap-overall-progress]'),
            progressBar: root.querySelector('[data-roadmap-progress-bar]'),
            nextActions: root.querySelector('[data-roadmap-next-actions]'),
            activities: root.querySelector('[data-roadmap-activities]'),
            evidence: root.querySelector('[data-roadmap-evidence-content]'),
            engine: root.querySelector('[data-roadmap-engine-content]'),
            version: root.querySelector('[data-roadmap-version-select]'),
            changed: root.querySelector('[data-roadmap-version-changes]'),
            analysisToggle: root.querySelector('[data-roadmap-analysis-toggle]'),
            analysisDetails: root.querySelector('[data-roadmap-analysis-details]'),
            feedbackStatus: root.querySelector('[data-roadmap-feedback-status]'),
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

        function svgElement(tag, attributes = {}) {
            const node = doc.createElementNS('http://www.w3.org/2000/svg', tag);
            for (const [name, value] of Object.entries(attributes)) node.setAttribute(name, value);
            return node;
        }
        function statusCopy(state) {
            return {
                loading: 'Đang tải lộ trình AI.', 'not-generated': 'Chưa có lộ trình AI.', pending: 'AI đang tạo lộ trình.',
                'consent-required': 'Cần quyền dữ liệu để tạo lộ trình.', 'insufficient-data': 'Chưa đủ dữ liệu để tạo lộ trình.',
                'source-error': 'Chưa thể tải lộ trình.', 'ready-model': 'Lộ trình từ AI đã sẵn sàng.',
                'stale-model': 'Đang hiển thị lộ trình AI gần nhất trong khi hệ thống cập nhật.',
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
            set(nodes.summaryLabel, state === 'fallback-rule' ? 'Gợi ý dự phòng theo quy tắc' : (state === 'stale-model' ? 'Bản AI gần nhất' : 'Tóm tắt từ AI'));
            set(nodes.summary, text(model.executive_summary, 'Chưa có nội dung tóm tắt.'));
            set(nodes.evidenceTotal, `${model.evidenceTotal} nguồn dữ liệu đã cho phép`);
            set(nodes.confidence, model.confidenceLabel);
            set(nodes.directionLabel, text(model?.primary_direction?.label, 'Chưa xác định'));
            set(nodes.directionRationale, text(model?.primary_direction?.rationale, 'Hướng này cần được kiểm chứng qua trải nghiệm thực tế.'));
            set(nodes.freshness, state === 'stale-model' ? `Bản gần nhất: ${displayDate(model.generated_at)} · Đang thử cập nhật lại` : `Cập nhật: ${displayDate(model.generated_at)}`);
            renderAlternatives(model.alternative_directions);
            renderInsights(model.insights);
            renderCapabilityAnalysis(model);
            renderPhases(model.phases);
            renderNextActions(model.nextActions);
            renderActivities(model.activities);
            renderEvidence(model.evidence_summary);
            renderEngine(model.engine, state);
            if (state === 'fallback-rule') {
                set(nodes.fallbackCopy, model?.engine?.fallback_reason === 'rule_only'
                    ? 'AI chưa được bật cho tài khoản này; nội dung đang hiển thị là lộ trình theo quy tắc.'
                    : 'AI tạm thời chưa phản hồi; nội dung này không được gắn nhãn là kết quả từ mô hình.');
            }
            renderHistory(model.version_history, model.version, model.changed_sections_from_previous);
            const complete = integer(model?.progress?.completed_tasks);
            const total = integer(model?.progress?.total_tasks);
            set(nodes.overallProgress, `${complete}/${total} nội dung đã hoàn thành`);
            nodes.overallProgress?.setAttribute('role', 'progressbar');
            nodes.overallProgress?.setAttribute('aria-valuemin', '0');
            nodes.overallProgress?.setAttribute('aria-valuemax', '100');
            nodes.overallProgress?.setAttribute('aria-valuenow', String(model.overallPercent));
            if (typeof nodes.overallProgress?.style?.setProperty === 'function') {
                nodes.overallProgress.style.setProperty('--roadmap-progress', `${model.overallPercent}%`);
            }
            if (nodes.progressBar?.style) nodes.progressBar.style.width = `${model.overallPercent}%`;
            hide(nodes.analysisDetails, true);
            nodes.analysisToggle?.setAttribute('aria-expanded', 'false');
        }

        function evidenceTitle(record) {
            const references = Array.isArray(record?.evidence_ref_ids) ? record.evidence_ref_ids.filter((value) => typeof value === 'string') : [];
            return references.length > 0 ? `Nguồn bằng chứng: ${references.join(', ')}` : 'Chưa có nguồn bằng chứng hiển thị.';
        }

        function renderCapabilityAnalysis(model) {
            clear(nodes.talentMap);
            if (model.talentMap.length >= 3) nodes.talentMap?.appendChild(renderTalentRadar(model.talentMap));
            else nodes.talentMap?.appendChild(element('p', 'learner-roadmap-empty', 'Chưa đủ dữ liệu để vẽ bản đồ năng khiếu.'));
            renderTextRecords(nodes.strengths, model.strengths, 'Chưa có điểm mạnh đủ bằng chứng.');
            renderTextRecords(nodes.improvements, model.improvements, 'Chưa có điểm cần cải thiện đủ bằng chứng.');
            renderTextRecords(nodes.trends, model.trendSignals, 'Chưa có xu hướng đủ bằng chứng.', 'label');
            renderTextRecords(nodes.potentialPaths, model.potentialPaths, 'Chưa có hướng phát triển đủ bằng chứng.', 'label');
            renderTextRecords(nodes.growthHypotheses, model.growthHypotheses, 'Chưa có giả thuyết phát triển đủ bằng chứng.');
        }

        function renderTalentRadar(items) {
            const safeItems = items.slice(0, 8);
            const center = { x: 210, y: 145 };
            const radius = 92;
            const point = (index, distance) => {
                const angle = -Math.PI / 2 + (Math.PI * 2 * index) / safeItems.length;
                return { x: center.x + Math.cos(angle) * distance, y: center.y + Math.sin(angle) * distance };
            };
            const polygonPoints = (distance) => safeItems.map((_item, index) => {
                const position = point(index, distance);
                return `${position.x.toFixed(2)},${position.y.toFixed(2)}`;
            }).join(' ');
            const label = safeItems.map((item) => `${text(item?.field, 'Lĩnh vực')} ${item.score}%`).join(', ');
            const svg = svgElement('svg', {
                class: 'learner-roadmap-radar', viewBox: '0 0 420 300', role: 'img',
                'aria-label': `Bản đồ năng khiếu: ${label}`,
            });
            svg.appendChild(svgElement('title', {}, 'Bản đồ năng khiếu'));
            for (const scale of [1, 0.75, 0.5, 0.25]) {
                svg.appendChild(svgElement('polygon', { class: 'learner-roadmap-radar__grid', points: polygonPoints(radius * scale) }));
            }
            for (let index = 0; index < safeItems.length; index += 1) {
                const axis = point(index, radius);
                svg.appendChild(svgElement('line', {
                    class: 'learner-roadmap-radar__axis', x1: center.x, y1: center.y, x2: axis.x, y2: axis.y,
                }));
            }
            svg.appendChild(svgElement('polygon', {
                class: 'learner-roadmap-radar__data', points: safeItems.map((item, index) => {
                    const position = point(index, radius * (Number(item.score) / 100));
                    return `${position.x.toFixed(2)},${position.y.toFixed(2)}`;
                }).join(' '),
            }));
            for (let index = 0; index < safeItems.length; index += 1) {
                const item = safeItems[index];
                const position = point(index, radius * (Number(item.score) / 100));
                svg.appendChild(svgElement('circle', {
                    class: 'learner-roadmap-radar__point', cx: position.x, cy: position.y, r: 5,
                    'aria-label': `${text(item?.field, 'Lĩnh vực')}: ${item.score}%`,
                }));
                const labelPosition = point(index, radius + 25);
                const labelNode = svgElement('text', {
                    class: 'learner-roadmap-radar__label', x: labelPosition.x, y: labelPosition.y, 'text-anchor': 'middle',
                });
                labelNode.textContent = `${text(item?.field, 'Lĩnh vực')} ${item.score}%`;
                svg.appendChild(labelNode);
            }
            return svg;
        }

        function renderTextRecords(node, items, emptyCopy, field = 'text') {
            clear(node);
            if (!Array.isArray(items) || items.length === 0) {
                node?.appendChild(element('p', 'learner-roadmap-empty', emptyCopy));
                return;
            }
            for (const item of items) {
                const record = element('article', 'learner-roadmap-capability__record', text(item?.[field], 'Nhận định cần kiểm chứng.'));
                record.setAttribute('title', evidenceTitle(item));
                node?.appendChild(record);
            }
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
                details.className = `learner-roadmap-phase is-${text(phase.status, 'upcoming')}`;
                details.open = phase.status === 'current';
                const summary = element('summary', 'learner-roadmap-phase__summary');
                const number = element('span', 'learner-roadmap-phase__number', integer(phase.position));
                const heading = element('span');
                heading.append(element('strong', '', text(phase.title, 'Giai đoạn')), element('small', '', phase.rangeLabel));
                const progress = element('span', 'learner-roadmap-phase__progress', `${phase.progress.completed_tasks}/${phase.progress.total_tasks}`);
                summary.append(number, heading, progress);
                const currentBadge = phase.status === 'current' ? element('span', 'learner-roadmap-phase__current', 'Bạn đang ở đây') : null;
                const body = element('div', 'learner-roadmap-phase__body');
                body.append(element('p', 'learner-roadmap-phase__goal', text(phase.goal, 'Tiếp tục phát triển năng lực theo hướng đã chọn.')));
                body.append(renderTasks(phase.displayTasks));
                details.append(summary);
                if (currentBadge) details.append(currentBadge);
                details.append(body);
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
            for (const task of tasks.slice(0, 1)) {
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
                const registrationPath = text(task?.action?.registration_path);
                if (/^\/app\/learner\/activity-detail\.php\?id=[0-9a-f-]{36}$/i.test(registrationPath)) {
                    const link = element('a', 'learner-btn learner-btn--outline', 'Xem và đăng ký');
                    link.href = registrationPath;
                    article.appendChild(link);
                } else {
                    article.appendChild(element('span', 'learner-roadmap-activity__unavailable', 'Hoạt động hiện không còn nhận đăng ký'));
                }
                nodes.activities?.appendChild(article);
            }
        }

        function renderHistory(history, selectedVersion, changedSections) {
            clear(nodes.version);
            const versions = Array.isArray(history) ? history : [];
            for (const entry of versions) {
                const option = element('option', '', `Phiên bản ${integer(entry?.version)} · ${displayDate(entry?.generated_at)}`);
                option.value = String(integer(entry?.version));
                option.selected = integer(entry?.version) === integer(selectedVersion);
                nodes.version?.appendChild(option);
            }
            const labels = { executive_summary: 'tóm tắt', primary_direction: 'hướng ưu tiên', alternative_directions: 'hướng bổ sung', insights: 'nhận định', analysis_origin: 'nguồn phân tích', roadmap_plan: 'kế hoạch 90 ngày' };
            const changes = (Array.isArray(changedSections) ? changedSections : []).map((key) => labels[key]).filter(Boolean);
            set(nodes.changed, changes.length > 0 ? `Dữ liệu mới đã làm thay đổi: ${changes.join(', ')}.` : 'Đây là phiên bản đầu tiên hoặc không có thay đổi nội dung chính.');
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

        function updateTask(taskId, status) {
            const controls = Array.from(root.querySelectorAll?.('[data-roadmap-task-id]') || []);
            const control = controls.find((item) => item.dataset.roadmapTaskId === taskId);
            if (!control) return;
            control.dataset.roadmapTaskStatus = status;
            control.textContent = status === 'completed' ? '✓' : '';
            control.closest?.('.learner-roadmap-task')?.classList?.toggle('is-completed', status === 'completed');
            const all = Array.from(root.querySelectorAll?.('[data-roadmap-task-id]') || []);
            const completed = all.filter((item) => item.dataset.roadmapTaskStatus === 'completed').length;
            set(nodes.overallProgress, `${completed}/${all.length} nhiệm vụ hoàn thành`);
            for (const phase of Array.from(root.querySelectorAll?.('.learner-roadmap-phase') || [])) {
                const phaseTasks = Array.from(phase.querySelectorAll?.('[data-roadmap-task-id]') || []);
                const phaseCompleted = phaseTasks.filter((item) => item.dataset.roadmapTaskStatus === 'completed').length;
                set(phase.querySelector?.('.learner-roadmap-phase__progress'), `${phaseCompleted}/${phaseTasks.length}`);
            }
        }

        function feedback(state) {
            set(nodes.feedbackStatus, {
                'task-saved': 'Đã lưu tiến độ.', 'task-error': 'Chưa thể lưu tiến độ; thay đổi đã được hoàn tác.',
                'feedback-saving': 'Đang lưu phản hồi...', 'feedback-saved': 'Cảm ơn bạn. Phản hồi sẽ giúp lần phân tích sau phù hợp hơn.',
                'feedback-error': 'Chưa thể lưu phản hồi. Vui lòng thử lại.',
            }[state] || '');
        }

        function toggleAnalysis() {
            if (!nodes.analysisDetails || !nodes.analysisToggle) return;
            const expanded = nodes.analysisToggle.getAttribute('aria-expanded') === 'true';
            hide(nodes.analysisDetails, expanded);
            nodes.analysisToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        }

        return { render, updateTask, feedback, toggleAnalysis };
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
        const view = createDomView(root);
        const controller = createRoadmapController({ api: createRoadmapApiClient(factory, global.document), view });
        root.addEventListener('click', (event) => {
            const target = event.target instanceof global.Element ? event.target.closest('button') : null;
            if (!target || !root.contains(target)) return;
            if (target.matches('[data-roadmap-generate]')) controller.generate(target.dataset.roadmapGenerate);
            else if (target.matches('[data-roadmap-retry]')) controller.retry();
            else if (target.matches('[data-roadmap-continue]')) root.querySelector('.learner-roadmap-task:not(.is-completed) .learner-roadmap-task__control')?.focus();
            else if (target.matches('[data-roadmap-analysis-toggle]')) view.toggleAnalysis();
            else if (target.matches('[data-roadmap-task-id]') && target.dataset.roadmapTaskStatus !== 'completed') controller.updateTask(target.dataset.roadmapTaskId, target.dataset.roadmapTaskStatus).catch(() => {});
            else if (target.matches('[data-roadmap-feedback-value]')) controller.submitFeedback('', target.dataset.roadmapFeedbackValue).catch(() => {});
        });
        root.querySelector('[data-roadmap-version-select]')?.addEventListener('change', (event) => controller.loadVersion(event.target.value));
        controller.load();
    }

    const exported = { presentationState, buildRoadmapViewModel, createRoadmapApiClient, createRoadmapController, createDomView, confidenceLabel, normalizeTalentScore };
    global.TalentHubLearnerAiRoadmap = exported;
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    if (global.document && typeof global.document.addEventListener === 'function') {
        if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', boot, { once: true });
        else boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
