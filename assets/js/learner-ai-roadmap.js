/** Roadmap-first learner AI page. */
(function initLearnerAiRoadmap(global) {
    'use strict';

    const READY_STATES = new Set(['ready-model', 'stale-model', 'ready-rule']);
    const PROCESSING_STEPS = [
        'Chuẩn bị dữ liệu năng lực',
        'Gemini đang phân tích',
        'Xây dựng lộ trình 90 ngày',
        'Kiểm tra và hoàn thiện',
    ];
    const TALENT_AXES = [
        { field: 'Tư duy Logic & Hệ thống', keywords: ['logic', 'he thong', 'phan tich', 'tu duy'] },
        { field: 'Kỹ năng Thực hành & Thao tác', keywords: ['thuc hanh', 'thao tac', 'ky thuat', 'ung dung'] },
        { field: 'Tổ chức & Điều phối', keywords: ['to chuc', 'dieu phoi', 'quan ly', 'quy trinh'] },
    ];

    function processingProgressAt(elapsedMs) {
        const elapsedSeconds = Math.max(0, Math.floor((Number(elapsedMs) || 0) / 1000));
        const activeIndex = elapsedSeconds < 5 ? 0 : elapsedSeconds < 25 ? 1 : elapsedSeconds < 55 ? 2 : 3;
        const ranges = [
            [0, 5, 8, 18],
            [5, 25, 18, 45],
            [25, 55, 45, 80],
            [55, 90, 80, 94],
        ];
        const [fromSecond, toSecond, fromPercent, toPercent] = ranges[activeIndex];
        const ratio = Math.max(0, Math.min(1, (elapsedSeconds - fromSecond) / (toSecond - fromSecond)));
        const percent = Math.min(94, Math.round(fromPercent + ((toPercent - fromPercent) * ratio)));
        return {
            elapsedSeconds,
            activeIndex,
            percent,
            steps: PROCESSING_STEPS.map((label, index) => ({
                label,
                status: index < activeIndex ? 'completed' : index === activeIndex ? 'active' : 'upcoming',
            })),
        };
    }

    function createProcessingTracker({
        onUpdate,
        now = () => Date.now(),
        schedule = (callback, delay) => global.setTimeout(callback, delay),
        cancelSchedule = (handle) => global.clearTimeout(handle),
        intervalMs = 1000,
    }) {
        if (typeof onUpdate !== 'function') throw new TypeError('A processing progress listener is required.');
        let startedAt = 0;
        let handle = null;
        let running = false;
        const emit = () => onUpdate(processingProgressAt(now() - startedAt));
        const tick = () => {
            handle = null;
            if (!running) return;
            emit();
            queue();
        };
        const queue = () => { if (running) handle = schedule(tick, intervalMs); };
        const stop = () => {
            running = false;
            if (handle !== null) cancelSchedule(handle);
            handle = null;
        };
        const terminal = (status) => {
            const snapshot = processingProgressAt(now() - startedAt);
            stop();
            onUpdate({ ...snapshot, status, percent: status === 'success' ? 100 : snapshot.percent });
        };
        return {
            start() {
                stop();
                startedAt = now();
                running = true;
                emit();
                queue();
            },
            succeed() { terminal('success'); },
            fail() { terminal('error'); },
            stop,
        };
    }

    function presentationState(payload) {
        const state = typeof payload?.state === 'string' ? payload.state : '';
        if (state === 'not_generated') return 'not-generated';
        if (state === 'pending') return 'pending';
        if (state === 'consent_required') return 'consent-required';
        if (state === 'data_insufficient') return 'insufficient-data';
        if (state === 'provider_unavailable') return 'source-error';
        if (state === 'ready_model') return 'ready-model';
        if (state === 'ready_rule' || state === 'fallback_rule') return 'ready-rule';
        if (state === 'roadmap_customized') return 'ready-model';
        if (state === 'stale_model') return 'stale-model';
        return 'source-error';
    }

    function isRecoverableGenerationState(payload) {
        return ['provider_unavailable', 'invalid_response', 'source_unavailable']
            .includes(String(payload?.state || ''));
    }

    function isRetryableTransportError(error) {
        return ['NETWORK_ERROR', 'REQUEST_TIMEOUT'].includes(String(error?.code || ''))
            || [502, 503, 504].includes(Number(error?.status));
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

    function searchableTalentField(value) {
        return text(value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
    }

    function completeTalentMap(value) {
        const result = TALENT_AXES.map((axis) => ({
            field: axis.field,
            score: 0,
            hasEvidence: false,
            evidence_ref_ids: [],
        }));
        const records = Array.isArray(value)
            ? value.filter((item) => item && typeof item === 'object').slice(0, 8)
            : [];
        for (const record of records) {
            const field = searchableTalentField(record?.field);
            const axisIndex = TALENT_AXES.findIndex((axis) => axis.keywords.some((keyword) => field.includes(keyword)));
            if (axisIndex < 0 || typeof record?.score !== 'number' || !Number.isFinite(record.score)) continue;
            const score = normalizeTalentScore(record.score);
            if (result[axisIndex].hasEvidence && result[axisIndex].score >= score) continue;
            result[axisIndex] = {
                field: TALENT_AXES[axisIndex].field,
                score,
                hasEvidence: true,
                evidence_ref_ids: Array.isArray(record?.evidence_ref_ids)
                    ? record.evidence_ref_ids.filter((item) => typeof item === 'string')
                    : [],
            };
        }
        return result;
    }

    function formatRoadmapMinutes(value) {
        const minutes = integer(value);
        if (minutes < 60) return `${minutes} phút`;
        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;
        return remainder > 0 ? `${hours} giờ ${remainder} phút` : `${hours} giờ`;
    }

    function roadmapTaskPresentation(task) {
        const rawTitle = text(task?.title, 'Nhiệm vụ');
        const match = rawTitle.match(/\s*\(Mốc\s+(\d+)\s+ngày\)\s*$/iu);
        return {
            title: match ? rawTitle.slice(0, match.index).trim() : rawTitle,
            milestoneLabel: match ? `Mốc ngày ${match[1]}` : '',
            durationLabel: `${integer(task?.estimated_minutes)} phút`,
        };
    }

    function roadmapTaskMilestone(task) {
        const rawTitle = text(task?.title);
        const match = rawTitle.match(/\s*\(Mốc\s+(\d+)\s+ngày\)\s*$/iu);
        return match ? integer(Number.parseInt(match[1], 10)) : Number.MAX_SAFE_INTEGER;
    }

    function preparePhaseTasks(phase) {
        const presented = (Array.isArray(phase?.tasks) ? phase.tasks : []).map((task, index) => ({
            ...task,
            position: integer(task?.position) || index + 1,
            presentation: roadmapTaskPresentation(task),
        }));
        const active = presented.filter((task) => task.status !== 'completed').sort((left, right) => (
            roadmapTaskMilestone(left) - roadmapTaskMilestone(right) || integer(left.position) - integer(right.position)
        ));
        const completed = presented.filter((task) => task.status === 'completed');
        phase.displayTasks = [...active, ...completed];
        phase.remainingTaskCount = active.length;
        phase.completedTaskCount = completed.length;
        const workloadMinutes = presented.reduce((sum, task) => sum + integer(task.estimated_minutes), 0);
        phase.workloadLabel = `${active.length} việc còn lại · ${completed.length} đã hoàn thành · ${formatRoadmapMinutes(workloadMinutes)}`;
        return phase;
    }

    function roadmapProgressSnapshot(phases) {
        const safePhases = (Array.isArray(phases) ? phases : [])
            .filter((phase) => phase && typeof phase === 'object')
            .sort((left, right) => integer(left.position) - integer(right.position));
        const phaseProgress = safePhases.map((phase) => {
            const tasks = Array.isArray(phase.tasks) ? phase.tasks : [];
            const completedTasks = tasks.filter((task) => task?.status === 'completed').length;
            const totalTasks = tasks.length;
            return {
                position: integer(phase.position),
                completedTasks,
                totalTasks,
                percent: totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0,
            };
        });
        const currentIndex = phaseProgress.findIndex((phase) => phase.completedTasks < phase.totalTasks);
        const stateIndex = currentIndex === -1 ? phaseProgress.length : currentIndex;
        const completedTasks = phaseProgress.reduce((sum, phase) => sum + phase.completedTasks, 0);
        const totalTasks = phaseProgress.reduce((sum, phase) => sum + phase.totalTasks, 0);
        return {
            completedTasks,
            totalTasks,
            overallPercent: totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0,
            phases: phaseProgress.map((phase, index) => ({
                ...phase,
                status: index < stateIndex ? 'completed' : index === currentIndex ? 'current' : 'upcoming',
                actionLabel: phase.totalTasks > 0 && phase.completedTasks === phase.totalTasks
                    ? (index < phaseProgress.length - 1 ? `Chuyển sang chặng ${phaseProgress[index + 1].position}` : 'Hoàn tất lộ trình')
                    : 'Tiếp tục nhiệm vụ',
            })),
        };
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
                rangeLabel: `Ngày ${integer(phase.start_day) === 0 ? 1 : integer(phase.start_day)}–${integer(phase.end_day)}`,
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
            preparePhaseTasks(phase);
        }
        const progressSnapshot = roadmapProgressSnapshot(phases);
        for (const [index, phase] of phases.entries()) {
            const progress = progressSnapshot.phases[index];
            phase.status = progress.status;
            phase.actionLabel = progress.actionLabel;
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
            talentMap: completeTalentMap(payload?.talent_map),
            strengths: records(payload?.strengths),
            improvements: records(payload?.improvements),
            potentialPaths: records(payload?.potential_paths),
            trendSignals: records(payload?.trend_signals),
            growthHypotheses: records(payload?.growth_hypotheses),
            currentPhaseIndex,
            overallPercent: totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0,
        };
    }

    function initialExpandedPhasePositions(phases) {
        const safePhases = Array.isArray(phases) ? phases : [];
        const current = safePhases.find((phase) => phase?.status === 'current');
        if (current) return [integer(current.position)];
        const incomplete = safePhases.find((phase) => phase?.status !== 'completed');
        if (incomplete) return [integer(incomplete.position)];
        const last = safePhases.at(-1);
        return last ? [integer(last.position)] : [];
    }

    function nextExpandedPhasePositions(currentPositions, action, phasePositions) {
        const validPositions = (Array.isArray(phasePositions) ? phasePositions : [])
            .map((position) => integer(position))
            .filter((position) => position > 0);
        const expanded = new Set((Array.isArray(currentPositions) ? currentPositions : [])
            .map((position) => integer(position))
            .filter((position) => validPositions.includes(position)));
        if (action?.type === 'expand-all') return [...validPositions];
        if (action?.type === 'collapse-all') return [];
        const position = integer(action?.position);
        if (action?.type !== 'toggle' || !validPositions.includes(position)) return validPositions.filter((item) => expanded.has(item));
        if (expanded.has(position)) expanded.delete(position);
        else expanded.add(position);
        return validPositions.filter((item) => expanded.has(item));
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
        let lastReadyPayload = null;
        let pendingAttempt = 0;
        let pendingHandle = null;
        let lastGenerationAction = 'refresh';
        const pendingTaskRequests = new Map();
        const pendingCompletionSchedules = new Map();

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
            let state = presentationState(payload);
            if (READY_STATES.has(state)) lastReadyPayload = payload;
            // Giữ bản roadmap gần nhất khi lần cập nhật mới lỗi (Gemini timeout,
            // rate limit hoặc tạm thời không khả dụng). Người học vẫn cần xem
            // được lộ trình cũ thay vì bị đưa về màn hình trống.
            if (state === 'source-error' && lastReadyPayload !== null) {
                payload = {
                    ...lastReadyPayload,
                    state: 'stale_model',
                    freshness_status: 'stale',
                    last_refresh_error: text(payload?.availability_reason, 'refresh_failed'),
                    refresh_state: 'fallback_not_applied',
                };
                state = 'stale-model';
            }
            if (READY_STATES.has(state)) currentRoadmapId = text(payload?.roadmap_id);
            view.render(state, READY_STATES.has(state) ? buildRoadmapViewModel(payload) : payload);
            if (state === 'pending') schedulePendingPoll();
            else stopPolling();
            return payload;
        }

        async function load(showLoading = true) {
            if (showLoading) view.render('loading', { mode: 'initial-load' });
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

        function updateTask(taskId, currentStatus = 'not_started') {
            const id = text(taskId);
            if (!id) throw new TypeError('Roadmap task id is required.');
            if (pendingTaskRequests.has(id)) return pendingTaskRequests.get(id);
            if (pendingCompletionSchedules.has(id)) {
                cancelSchedule(pendingCompletionSchedules.get(id));
                pendingCompletionSchedules.delete(id);
            }
            const previous = text(currentStatus, 'not_started');
            const next = previous === 'completed' ? 'in_progress' : 'completed';
            const delaysCompletion = next === 'completed' && typeof view.previewTaskCompletion === 'function';
            const request = (async () => {
                if (delaysCompletion) view.previewTaskCompletion(id);
                else view.updateTask?.(id, next);
                view.setTaskPending?.(id, true);
                let completionDelay = Promise.resolve();
                if (delaysCompletion) {
                    completionDelay = new Promise((resolve) => {
                        const handle = schedule(() => {
                            pendingCompletionSchedules.delete(id);
                            resolve();
                        }, Math.max(0, Number(view.taskCompletionDelay?.()) || 0));
                        pendingCompletionSchedules.set(id, handle);
                    });
                }
                try {
                    const [response] = await Promise.all([
                        api.send('POST', '/ai-roadmap-task.php', { taskId: id, status: next }, { idempotencyKey: createIdempotencyKey() }),
                        completionDelay,
                    ]);
                    if (delaysCompletion) {
                        view.updateTask?.(id, next);
                        view.feedback?.('task-saved-undo', { taskId: id });
                    } else {
                        view.feedback?.('task-saved');
                    }
                    return response;
                } catch (error) {
                    if (pendingCompletionSchedules.has(id)) {
                        cancelSchedule(pendingCompletionSchedules.get(id));
                        pendingCompletionSchedules.delete(id);
                    }
                    view.updateTask?.(id, previous);
                    view.feedback?.('task-error');
                    throw error;
                } finally {
                    view.setTaskPending?.(id, false);
                    pendingTaskRequests.delete(id);
                }
            })();
            pendingTaskRequests.set(id, request);
            return request;
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
            lastGenerationAction = safeAction;
            const preserveReady = lastReadyPayload !== null;
            view.render('processing', {
                mode: preserveReady ? 'refresh-generation' : 'first-generation',
                preserveReady,
            });
            const request = async () => {
                let lastError = null;
                let requestKey = createIdempotencyKey();
                for (let attempt = 1; attempt <= 2; attempt += 1) {
                    try {
                        const response = await api.send(
                            'POST', '/ai-roadmap.php', { action: safeAction }, {
                                idempotencyKey: requestKey,
                                // Gemini có thể dùng hai lần thử, mỗi lần tối đa 30 giây.
                                // Timeout phía trình duyệt phải dài hơn toàn bộ vòng đời backend.
                                timeoutMs: 90000,
                            },
                        );
                        if (!isRecoverableGenerationState(response) || attempt === 2) return response;
                        requestKey = createIdempotencyKey();
                    } catch (error) {
                        lastError = error;
                        if (attempt === 2 || !isRetryableTransportError(error)) throw error;
                    }
                }
                throw lastError || new Error('Roadmap analysis did not complete.');
            };
            generation = Promise.resolve().then(request).then(render)
                .catch((error) => render({ state: 'source_unavailable', message: error?.message }))
                .finally(() => { generation = null; });
            return generation;
        }

        function retry() {
            return generate(lastGenerationAction);
        }

        function dispose() {
            stopPolling();
            for (const handle of pendingCompletionSchedules.values()) cancelSchedule(handle);
            pendingCompletionSchedules.clear();
            view.dispose?.();
        }

        return { load, loadVersion, generate, retry, updateTask, submitFeedback, render, dispose };
    }

    function createDomView(root, options = {}) {
        const doc = root.ownerDocument || global.document;
        const nodes = {
            status: root.querySelector('[data-roadmap-status]'),
            processing: root.querySelector('[data-roadmap-processing]'),
            processingTitle: root.querySelector('[data-roadmap-processing-title]'),
            processingCopy: root.querySelector('[data-roadmap-processing-copy]'),
            processingPercent: root.querySelector('[data-roadmap-processing-percent]'),
            processingElapsed: root.querySelector('[data-roadmap-processing-elapsed]'),
            processingBar: root.querySelector('[data-roadmap-processing-bar]'),
            processingSteps: root.querySelector('[data-roadmap-processing-steps]'),
            processingNote: root.querySelector('[data-roadmap-processing-note]'),
            processingRetry: root.querySelector('[data-roadmap-processing-retry]'),
            loading: root.querySelector('[data-roadmap-loading]'),
            notGenerated: root.querySelector('[data-roadmap-not-generated]'),
            consent: root.querySelector('[data-roadmap-consent]'),
            insufficient: root.querySelector('[data-roadmap-insufficient]'),
            pending: root.querySelector('[data-roadmap-pending]'),
            error: root.querySelector('[data-roadmap-error]'),
            ready: root.querySelector('[data-roadmap-ready]'),
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
            zeroTalentMap: root.querySelector('[data-roadmap-zero-talent-map]'),
            strengths: root.querySelector('[data-roadmap-strengths]'),
            improvements: root.querySelector('[data-roadmap-improvements]'),
            trends: root.querySelector('[data-roadmap-trends]'),
            potentialPaths: root.querySelector('[data-roadmap-potential-paths]'),
            growthHypotheses: root.querySelector('[data-roadmap-growth-hypotheses]'),
            phases: root.querySelector('[data-roadmap-phases]'),
            overallProgress: root.querySelector('[data-roadmap-overall-progress]'),
            progressBar: root.querySelector('[data-roadmap-progress-bar]'),
            activities: root.querySelector('[data-roadmap-activities]'),
            activitiesCopy: root.querySelector('[data-roadmap-activities-copy]'),
            evidence: root.querySelector('[data-roadmap-evidence-content]'),
            engine: root.querySelector('[data-roadmap-engine-content]'),
            version: root.querySelector('[data-roadmap-version-select]'),
            changed: root.querySelector('[data-roadmap-version-changes]'),
            analysisToggle: root.querySelector('[data-roadmap-analysis-toggle]'),
            analysisDetails: root.querySelector('[data-roadmap-analysis-details]'),
            feedbackStatus: root.querySelector('[data-roadmap-feedback-status]'),
        };
        const schedule = typeof options.schedule === 'function'
            ? options.schedule
            : (callback, delay) => global.setTimeout(callback, delay);
        const cancelSchedule = typeof options.cancelSchedule === 'function'
            ? options.cancelSchedule
            : (handle) => global.clearTimeout(handle);
        const reduceMotion = typeof options.reduceMotion === 'boolean'
            ? options.reduceMotion
            : Boolean(global.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches);
        let processingActive = false;
        let processingPreserveReady = false;
        let successHideHandle = null;
        let renderedPhasePositions = [];
        let expandedPhasePositions = [];
        let renderedModel = null;

        function hide(node, value) { if (node) node.hidden = value; }
        function set(node, value) { if (node) node.textContent = String(value ?? ''); }
        function clear(node) { if (node) while (node.firstChild) node.removeChild(node.firstChild); }
        function element(tag, className, value) {
            const node = doc.createElement(tag);
            if (className) node.className = className;
            if (value !== undefined) node.textContent = String(value);
            return node;
        }

        function setGenerateDisabled(disabled) {
            for (const button of Array.from(root.querySelectorAll?.('[data-roadmap-generate]') || [])) {
                button.disabled = disabled;
            }
        }

        function renderProcessingSnapshot(snapshot) {
            set(nodes.processingPercent, `${integer(snapshot?.percent)}%`);
            set(nodes.processingElapsed, `${integer(snapshot?.elapsedSeconds)} giây`);
            if (nodes.processingBar?.style) nodes.processingBar.style.width = `${integer(snapshot?.percent)}%`;
            const stepNodes = Array.from(nodes.processingSteps?.querySelectorAll?.('[data-processing-step]') || []);
            for (const [index, step] of (Array.isArray(snapshot?.steps) ? snapshot.steps : []).entries()) {
                stepNodes[index]?.classList?.toggle('is-active', step.status === 'active' && snapshot?.status !== 'success');
                stepNodes[index]?.classList?.toggle('is-completed', step.status === 'completed' || snapshot?.status === 'success');
            }
            if (snapshot?.status === 'success') {
                set(nodes.processingTitle, 'Lộ trình mới đã sẵn sàng');
                set(nodes.processingCopy, 'TalentHub đã hoàn thiện và kiểm tra lộ trình 90 ngày mới.');
                set(nodes.processingNote, 'Nội dung mới đang được hiển thị bên dưới.');
                return;
            }
            if (snapshot?.status === 'error') {
                set(nodes.processingTitle, 'Chưa thể cập nhật lộ trình');
                set(nodes.processingCopy, 'Lần cập nhật này chưa hoàn tất. Lộ trình hiện tại của bạn không bị ảnh hưởng.');
                set(nodes.processingNote, 'Bạn vẫn có thể tiếp tục xem và theo dõi bản hiện tại.');
                return;
            }
            const activeCopy = [
                'TalentHub đang tổng hợp dữ liệu đã được bạn cho phép.',
                'Gemini đang phân tích điểm mạnh và hướng phát triển phù hợp.',
                'AI đang xây dựng ba giai đoạn trong lộ trình 90 ngày.',
                'TalentHub đang kiểm tra cấu trúc, đầu ra và cách đo lường.',
            ];
            set(nodes.processingCopy, activeCopy[integer(snapshot?.activeIndex)] || activeCopy[0]);
        }

        const processingTracker = createProcessingTracker({
            onUpdate: renderProcessingSnapshot,
            now: options.now,
            schedule: options.schedule,
            cancelSchedule: options.cancelSchedule,
        });

        function clearSuccessHide() {
            if (successHideHandle !== null) cancelSchedule(successHideHandle);
            successHideHandle = null;
        }

        function beginProcessing(payload) {
            clearSuccessHide();
            processingActive = true;
            processingPreserveReady = payload?.preserveReady === true;
            hide(nodes.processing, false);
            hide(nodes.ready, !processingPreserveReady);
            hide(nodes.processingRetry, true);
            nodes.processing?.classList?.toggle('is-error', false);
            nodes.processing?.classList?.toggle('is-success', false);
            set(nodes.processingTitle, processingPreserveReady ? 'AI đang cập nhật lộ trình của bạn' : 'AI đang tạo lộ trình của bạn');
            set(nodes.processingNote, processingPreserveReady
                ? 'Bạn có thể tiếp tục xem lộ trình hiện tại trong lúc chờ.'
                : 'Bạn có thể để trang mở; TalentHub sẽ hiển thị kết quả ngay khi hoàn tất.');
            setGenerateDisabled(true);
            processingTracker.start();
        }

        function completeProcessing(success) {
            if (!processingActive) return false;
            processingActive = false;
            setGenerateDisabled(false);
            if (success) {
                processingTracker.succeed();
                nodes.processing?.classList?.toggle('is-success', true);
                nodes.processing?.classList?.toggle('is-error', false);
                hide(nodes.processingRetry, true);
                successHideHandle = schedule(() => {
                    successHideHandle = null;
                    hide(nodes.processing, true);
                }, 1500);
                return true;
            }
            processingTracker.fail();
            nodes.processing?.classList?.toggle('is-success', false);
            nodes.processing?.classList?.toggle('is-error', true);
            hide(nodes.processing, false);
            hide(nodes.processingRetry, false);
            return true;
        }

        function statusCopy(state) {
            return {
                loading: 'Đang tải lộ trình AI.', 'not-generated': 'Chưa có lộ trình AI.', pending: 'AI đang tạo lộ trình.',
                processing: 'AI đang xử lý và xây dựng lộ trình 90 ngày.',
                'consent-required': 'Cần quyền dữ liệu để tạo lộ trình.', 'insufficient-data': 'Chưa đủ dữ liệu để tạo lộ trình.',
                'source-error': 'Chưa thể tải lộ trình.', 'ready-model': 'Lộ trình từ AI đã sẵn sàng.',
                'ready-rule': 'Lộ trình dự phòng theo quy tắc đã sẵn sàng.',
                'stale-model': 'Đang hiển thị lộ trình AI gần nhất trong khi hệ thống cập nhật.',
            }[state] || 'Trạng thái lộ trình đã thay đổi.';
        }

        function render(state, payload) {
            if (state === 'processing') {
                hide(nodes.loading, true);
                hide(nodes.notGenerated, true);
                hide(nodes.consent, true);
                hide(nodes.insufficient, true);
                hide(nodes.pending, true);
                hide(nodes.error, true);
                set(nodes.status, statusCopy(state));
                beginProcessing(payload);
                return;
            }

            // Backend may acknowledge generation before the refreshed roadmap is
            // ready. Keep the progress panel (and any previous roadmap) visible
            // while the controller polls instead of falling back to the old
            // generic pending card.
            if (state === 'pending' && processingActive) {
                hide(nodes.loading, true);
                hide(nodes.notGenerated, true);
                hide(nodes.consent, true);
                hide(nodes.insufficient, true);
                hide(nodes.pending, true);
                hide(nodes.error, true);
                hide(nodes.ready, !processingPreserveReady);
                set(nodes.status, statusCopy('processing'));
                return;
            }

            const failedRefresh = state === 'stale-model'
                && payload?.refresh_state === 'fallback_not_applied';
            hide(nodes.loading, state !== 'loading');
            hide(nodes.notGenerated, state !== 'not-generated');
            hide(nodes.consent, state !== 'consent-required');
            hide(nodes.insufficient, state !== 'insufficient-data');
            hide(nodes.pending, state !== 'pending');
            hide(nodes.error, state !== 'source-error');
            hide(nodes.ready, !READY_STATES.has(state));
            set(nodes.status, statusCopy(state));
            if (READY_STATES.has(state)) {
                if (processingActive) completeProcessing(!failedRefresh);
                else {
                    processingTracker.stop();
                    clearSuccessHide();
                    hide(nodes.processing, true);
                    setGenerateDisabled(false);
                }
                renderReady(payload, state);
                return;
            }

            if (state === 'insufficient-data') {
                renderTalentMap(nodes.zeroTalentMap, completeTalentMap([]));
            }

            processingTracker.stop();
            processingActive = false;
            processingPreserveReady = false;
            clearSuccessHide();
            hide(nodes.processing, true);
            setGenerateDisabled(false);
        }

        function renderReady(model, state) {
            renderedModel = model;
            set(nodes.summaryLabel, state === 'ready-rule'
                ? 'Lộ trình định hướng theo quy tắc'
                : state === 'stale-model' ? 'Bản AI gần nhất' : 'Tóm tắt từ AI');
            set(nodes.summary, text(model.executive_summary, 'Chưa có nội dung tóm tắt.'));
            set(nodes.evidenceTotal, `${model.evidenceTotal} nguồn dữ liệu đã cho phép`);
            set(nodes.confidence, model.confidenceLabel);
            set(nodes.directionLabel, text(model?.primary_direction?.label, 'Chưa xác định'));
            set(nodes.directionRationale, text(model?.primary_direction?.rationale, 'Hướng này cần được kiểm chứng qua trải nghiệm thực tế.'));
            set(nodes.freshness, state === 'stale-model' ? `Bản gần nhất: ${displayDate(model.generated_at)} · Đang thử cập nhật lại` : `Cập nhật ngày ${displayDate(model.generated_at)}`);
            renderAlternatives(model.alternative_directions);
            renderInsights(model.insights);
            renderCapabilityAnalysis(model);
            renderPhases(model.phases);
            renderActivities(model.activities, model);
            renderEvidence(model.evidence_summary);
            renderEngine(model.engine, state);
            renderHistory(model.version_history, model.version, model.changed_sections_from_previous);
            const complete = integer(model?.progress?.completed_tasks);
            const total = integer(model?.progress?.total_tasks);
            set(nodes.overallProgress, `${complete}/${total} nhiệm vụ hoàn thành`);
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
            renderTalentMap(nodes.talentMap, model.talentMap);
            renderTextRecords(nodes.strengths, model.strengths, 'Chưa có điểm mạnh đủ bằng chứng.');
            renderTextRecords(nodes.improvements, model.improvements, 'Chưa có điểm cần cải thiện đủ bằng chứng.');
            renderTextRecords(nodes.trends, model.trendSignals, 'Chưa có xu hướng đủ bằng chứng.', 'label');
            renderTextRecords(nodes.potentialPaths, model.potentialPaths, 'Chưa có hướng phát triển đủ bằng chứng.', 'label');
            renderTextRecords(nodes.growthHypotheses, model.growthHypotheses, 'Chưa có giả thuyết phát triển đủ bằng chứng.');
        }

        function renderTalentMap(node, items) {
            const safeItems = Array.isArray(items) && items.length > 0 ? items : completeTalentMap([]);
            clear(node);
            node?.appendChild(renderTalentBars(safeItems));
            if (safeItems.some((item) => item.hasEvidence === false)) {
                node?.appendChild(element(
                    'p',
                    'learner-roadmap-radar-note',
                    '0% là dữ liệu chưa được xác định, không phải đánh giá năng lực thấp.',
                ));
            }
        }

        function renderTalentBars(items) {
            const safeItems = items.slice(0, 8);
            const chart = element('div', 'learner-talent-bars');
            const ariaSummary = safeItems.map((item) => {
                const value = item.hasEvidence === false ? 'chưa có dữ liệu' : `${integer(item.score)}%`;
                return `${text(item?.field, 'Lĩnh vực')} ${value}`;
            }).join(', ');
            chart.setAttribute('role', 'img');
            chart.setAttribute('aria-label', `Bản đồ năng khiếu: ${ariaSummary}`);

            for (const item of safeItems) {
                const isUnmeasured = item.hasEvidence === false;
                const score = Math.max(0, Math.min(100, integer(item.score)));
                const row = element('div', `learner-talent-bar${isUnmeasured ? ' is-unmeasured' : ''}`);
                const heading = element('div', 'learner-talent-bar__heading');
                heading.append(
                    element('strong', '', text(item?.field, 'Lĩnh vực')),
                    element('span', '', isUnmeasured ? 'Chưa có dữ liệu' : `${score}%`),
                );
                const track = element('div', 'learner-talent-bar__track');
                const fill = element('span', 'learner-talent-bar__fill');
                fill.style.width = `${isUnmeasured ? 0 : score}%`;
                track.appendChild(fill);
                row.append(heading, track);
                chart.appendChild(row);
            }
            return chart;
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

        function renderPhases(phases, preferredExpandedPositions = null) {
            clear(nodes.phases);
            const safePhases = Array.isArray(phases) ? phases : [];
            renderedPhasePositions = safePhases.map((phase) => integer(phase.position)).filter((position) => position > 0);
            const preferred = (Array.isArray(preferredExpandedPositions) ? preferredExpandedPositions : [])
                .map((position) => integer(position))
                .filter((position) => renderedPhasePositions.includes(position));
            expandedPhasePositions = preferred.length > 0 ? preferred : initialExpandedPhasePositions(safePhases);
            const summaries = element('div', 'learner-roadmap-phase-summaries');
            const panels = element('div', 'learner-roadmap-phase-panels');
            for (const phase of safePhases) {
                const position = integer(phase.position);
                const status = text(phase.status, 'upcoming');
                const panelId = `roadmap-phase-panel-${position}`;
                const summaryId = `roadmap-phase-summary-${position}`;
                const isExpanded = expandedPhasePositions.includes(position);
                const summary = element('button', `learner-roadmap-phase-summary is-${status}${isExpanded ? ' is-expanded' : ''}`);
                summary.type = 'button';
                summary.id = summaryId;
                summary.dataset.roadmapPhaseToggle = String(position);
                summary.setAttribute('aria-controls', panelId);
                summary.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                summary.setAttribute('aria-label', `${isExpanded ? 'Thu gọn' : 'Mở rộng'} ${text(phase.title, `giai đoạn ${position}`)}`);
                const number = element('span', 'learner-roadmap-phase__number', status === 'completed' ? '✓' : position);
                const heading = element('div', 'learner-roadmap-phase__heading-wrap');
                const titleRow = element('div', 'learner-roadmap-phase__title-row');
                titleRow.append(
                    element('strong', '', text(phase.title, 'Giai đoạn')),
                    element('span', 'learner-phase-range-badge', text(phase.rangeLabel, 'Chưa xác định'))
                );
                const phaseProgressPct = phase.progress.total_tasks > 0
                    ? Math.round((phase.progress.completed_tasks / phase.progress.total_tasks) * 100)
                    : 0;
                const progressRow = element('div', 'learner-roadmap-phase__progress-row');
                const progressTrack = renderPhaseProgressTrack(phaseProgressPct);
                const progressText = element('span', 'learner-roadmap-phase__progress', `${phase.progress.completed_tasks}/${phase.progress.total_tasks} nhiệm vụ`);
                progressRow.append(progressTrack, progressText);
                heading.append(
                    titleRow,
                    element('span', `learner-roadmap-phase__status is-${status}`, status === 'current' ? 'Đang thực hiện' : status === 'completed' ? 'Đã hoàn thành' : 'Chưa bắt đầu'),
                    element('p', 'learner-roadmap-phase__summary-goal', text(phase.goal, 'Tiếp tục phát triển năng lực theo hướng đã chọn.')),
                    progressRow
                );
                summary.append(number, heading, element('span', 'learner-roadmap-phase__chevron', '⌄'));
                summaries.appendChild(summary);

                const panel = element('section', `learner-roadmap-phase-panel is-${status}`);
                panel.id = panelId;
                panel.dataset.roadmapPhasePanel = String(position);
                panel.setAttribute('aria-labelledby', summaryId);
                panel.hidden = !isExpanded;
                const overview = element('div', 'learner-roadmap-phase-panel__overview');
                const goalBox = element('div', 'learner-roadmap-phase__goal-card');
                goalBox.append(
                    element('span', 'learner-phase-context', `Chặng ${position} · ${phase.rangeLabel}`),
                    element('h3', 'learner-phase-goal-label', 'Mục tiêu của chặng'),
                    element('p', 'learner-roadmap-phase__goal', text(phase.goal, 'Tiếp tục phát triển năng lực theo hướng đã chọn.')),
                );
                overview.append(goalBox, renderPhaseFacts(phase));
                const actions = element('div', 'learner-roadmap-phase-panel__actions');
                const actionHeading = element('div', 'learner-roadmap-phase-panel__heading');
                actionHeading.append(
                    element('span', 'learner-roadmap-phase-panel__heading-icon', '◎'),
                    element('h3', '', 'Nhiệm vụ cần thực hiện'),
                    element('span', 'learner-roadmap-phase-panel__task-meta', text(phase.workloadLabel)),
                );
                actions.append(actionHeading, renderTasks(phase.displayTasks));
                const panelBody = element('div', 'learner-roadmap-phase-panel__body');
                panelBody.append(overview, actions);
                const footer = element('div', 'learner-roadmap-phase-panel__footer');
                const footerProgress = element('div', 'learner-roadmap-phase-panel__footer-progress');
                footerProgress.append(
                    element('span', '', `Tiến độ giai đoạn ${position}`),
                    renderPhaseProgressTrack(phaseProgressPct),
                    element('strong', 'learner-roadmap-phase-panel__progress', `${phase.progress.completed_tasks}/${phase.progress.total_tasks}`),
                );
                const continueButton = element('button', 'learner-btn learner-btn--primary', text(phase.actionLabel, 'Tiếp tục nhiệm vụ'));
                continueButton.type = 'button';
                continueButton.dataset.roadmapContinue = '';
                continueButton.dataset.roadmapContinuePhase = String(position);
                footer.append(footerProgress, continueButton);
                panel.append(panelBody, footer);
                panels.appendChild(panel);
            }
            nodes.phases?.append(summaries, panels);
        }

        function renderPhaseProgressTrack(percent) {
            const progressTrack = element('div', 'learner-phase-mini-track');
            const progressFill = element('span');
            progressFill.style.width = `${integer(percent)}%`;
            progressTrack.appendChild(progressFill);
            return progressTrack;
        }

        function renderPhaseFacts(phase) {
            const factsWrap = element('div', 'learner-roadmap-phase__facts');
            const facts = [
                { label: 'Kỹ năng trọng tâm', val: phase.skill_focus, icon: '⌘' },
                { label: 'Sản phẩm đầu ra', val: phase.deliverable, icon: '◇' },
                { label: 'Nỗ lực dự kiến', val: phase.effort_label, icon: '☆' },
                { label: 'Tiêu chí hoàn thành', val: phase.metric_label, icon: '▣' },
            ];
            for (const item of facts) {
                if (!item.val) continue;
                const pill = element('div', 'learner-phase-fact-pill');
                pill.append(
                    element('span', 'learner-phase-fact-icon', item.icon),
                    element('span', 'learner-phase-fact-label', item.label),
                    element('strong', 'learner-phase-fact-value', text(item.val, 'Chưa xác định'))
                );
                factsWrap.appendChild(pill);
            }
            return factsWrap;
        }

        function renderTaskList(tasks, modifier) {
            const list = element('ol', `learner-roadmap-task-list learner-roadmap-task-list--${modifier}`);
            for (const task of tasks) {
                const item = element('li', `learner-roadmap-task is-${text(task?.status, 'not_started')}`);
                const control = element('button', 'learner-roadmap-task__control');
                control.type = 'button';
                control.dataset.roadmapTaskId = text(task?.task_id);
                control.dataset.roadmapTaskStatus = text(task?.status, 'not_started');
                control.setAttribute('aria-label', `${task?.status === 'completed' ? 'Bỏ đánh dấu hoàn thành' : 'Đánh dấu hoàn thành'}: ${text(task?.title, 'Nhiệm vụ')}`);
                control.setAttribute('aria-pressed', task?.status === 'completed' ? 'true' : 'false');
                control.title = task?.status === 'completed' ? 'Bỏ hoàn thành' : 'Đánh dấu hoàn thành';
                control.textContent = task?.status === 'completed' ? '✓' : '';

                const presentation = task?.presentation || roadmapTaskPresentation(task);
                const content = element('div', 'learner-roadmap-task__content');
                const titleNode = element('strong', '', text(presentation.title, 'Nhiệm vụ'));
                const timeBadge = element('span', 'learner-task-time-badge', text(presentation.durationLabel, '0 phút'));
                const descNode = element('small', 'learner-task-desc', text(task?.description, 'Đầu việc thực hành'));

                content.appendChild(titleNode);
                if (text(presentation.milestoneLabel)) {
                    content.appendChild(element('span', 'learner-task-milestone-badge', presentation.milestoneLabel));
                }
                content.append(timeBadge, descNode);
                item.append(control, content);
                list.appendChild(item);
            }
            return list;
        }

        function renderTasks(tasks) {
            const wrap = element('div', 'learner-roadmap-tasks');
            const safeTasks = Array.isArray(tasks) ? tasks : [];
            const activeTasks = safeTasks.filter((task) => task?.status !== 'completed');
            const completedTasks = safeTasks.filter((task) => task?.status === 'completed');
            wrap.appendChild(renderTaskList(activeTasks, 'active'));
            if (completedTasks.length > 0) {
                const completedGroup = element('details', 'learner-roadmap-completed');
                completedGroup.open = activeTasks.length === 0;
                completedGroup.append(
                    element('summary', 'learner-roadmap-completed__summary', `Đã hoàn thành (${completedTasks.length})`),
                    renderTaskList(completedTasks, 'completed'),
                );
                wrap.appendChild(completedGroup);
            }
            return wrap;
        }

        function renderActivities(tasks, model) {
            clear(nodes.activities);
            hide(nodes.activitiesCopy, true);
            if (tasks.length === 0) {
                const next = Array.isArray(model?.nextActions) ? model.nextActions[0] : null;
                if (nodes.activitiesCopy) {
                    nodes.activitiesCopy.textContent = next
                        ? ('Lộ trình hiện chưa có hoạt động hệ thống liên kết. Bạn có thể bắt đầu bằng nhiệm vụ: ' + text(next.title, 'nhiệm vụ tiếp theo') + '.')
                        : 'Lộ trình hiện chưa có hoạt động hệ thống liên kết. Hãy theo dõi các nhiệm vụ trong lộ trình để tiếp tục phát triển.';
                    hide(nodes.activitiesCopy, false);
                }
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
            const hasChanges = Array.isArray(changedSections) && changedSections.length > 0;
            set(nodes.changed, hasChanges ? 'Lộ trình đã được cập nhật theo dữ liệu mới của bạn.' : 'Đây là phiên bản đầu tiên hoặc không có thay đổi nội dung chính.');
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
            if (!renderedModel || !Array.isArray(renderedModel.phases)) return;
            const focusedControl = doc.activeElement?.dataset?.roadmapTaskId === taskId;
            let changed = false;
            for (const phase of renderedModel.phases) {
                for (const task of (Array.isArray(phase.tasks) ? phase.tasks : [])) {
                    if (text(task?.task_id) !== taskId) continue;
                    task.status = status;
                    changed = true;
                }
                for (const task of (Array.isArray(phase.displayTasks) ? phase.displayTasks : [])) {
                    if (text(task?.task_id) === taskId) task.status = status;
                }
            }
            if (!changed) return;
            const snapshot = roadmapProgressSnapshot(renderedModel.phases);
            renderedModel.progress = { completed_tasks: snapshot.completedTasks, total_tasks: snapshot.totalTasks };
            renderedModel.overallPercent = snapshot.overallPercent;
            for (const [index, phase] of renderedModel.phases.entries()) {
                const progress = snapshot.phases[index];
                phase.progress = { completed_tasks: progress.completedTasks, total_tasks: progress.totalTasks };
                phase.status = progress.status;
                phase.actionLabel = progress.actionLabel;
                preparePhaseTasks(phase);
            }
            const preservedExpansion = [...expandedPhasePositions];
            renderPhases(renderedModel.phases, preservedExpansion);
            if (focusedControl) {
                const nextControl = Array.from(root.querySelectorAll?.('[data-roadmap-task-id]') || []).find((item) => item.dataset.roadmapTaskId === taskId);
                const completedGroup = nextControl?.closest?.('.learner-roadmap-completed');
                if (completedGroup) completedGroup.open = true;
                nextControl?.focus?.();
            }
            const nextActions = renderedModel.phases.flatMap((phase) => phase.tasks.map((task) => ({
                ...task,
                phaseTitle: text(phase.title, 'Giai đoạn'),
            }))).filter((task) => task.status !== 'completed').slice(0, 3);
            renderedModel.nextActions = nextActions;
            set(nodes.overallProgress, `${snapshot.completedTasks}/${snapshot.totalTasks} nhiệm vụ hoàn thành`);
            nodes.overallProgress?.setAttribute('aria-valuenow', String(snapshot.overallPercent));
            if (typeof nodes.overallProgress?.style?.setProperty === 'function') {
                nodes.overallProgress.style.setProperty('--roadmap-progress', `${snapshot.overallPercent}%`);
            }
            if (nodes.progressBar?.style) nodes.progressBar.style.width = `${snapshot.overallPercent}%`;
        }

        function setTaskPending(taskId, pending) {
            const controls = Array.from(root.querySelectorAll?.('[data-roadmap-task-id]') || []);
            const control = controls.find((item) => item.dataset.roadmapTaskId === taskId);
            if (!control) return;
            control.setAttribute('aria-disabled', pending ? 'true' : 'false');
            control.setAttribute('aria-busy', pending ? 'true' : 'false');
            const row = control.closest?.('.learner-roadmap-task');
            row?.classList?.toggle('is-task-pending', pending);
            const panel = control.closest?.('[data-roadmap-phase-panel]');
            const phaseAction = panel?.querySelector?.('[data-roadmap-continue]');
            if (phaseAction) phaseAction.disabled = pending;
        }

        function previewTaskCompletion(taskId) {
            const control = Array.from(root.querySelectorAll?.('[data-roadmap-task-id]') || []).find((item) => item.dataset.roadmapTaskId === taskId);
            if (!control) return;
            control.dataset.roadmapTaskStatus = 'completed';
            control.setAttribute('aria-pressed', 'true');
            control.setAttribute('aria-label', control.getAttribute('aria-label')?.replace(/^Đánh dấu hoàn thành:/, 'Bỏ đánh dấu hoàn thành:') || 'Bỏ đánh dấu hoàn thành');
            control.title = 'Bỏ hoàn thành';
            control.textContent = '✓';
            const row = control.closest?.('.learner-roadmap-task');
            row?.classList?.remove('is-not_started', 'is-in_progress');
            row?.classList?.add('is-completed', 'is-completion-preview');
        }

        function applyPhaseExpansion(nextPositions) {
            expandedPhasePositions = nextPositions;
            for (const button of Array.from(root.querySelectorAll?.('[data-roadmap-phase-toggle]') || [])) {
                const position = integer(Number.parseInt(button.dataset.roadmapPhaseToggle, 10));
                const expanded = expandedPhasePositions.includes(position);
                button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                button.setAttribute('aria-label', `${expanded ? 'Thu gọn' : 'Mở rộng'} ${text(button.querySelector?.('strong')?.textContent, `giai đoạn ${position}`)}`);
                button.classList?.toggle('is-expanded', expanded);
            }
            for (const panel of Array.from(root.querySelectorAll?.('[data-roadmap-phase-panel]') || [])) {
                const position = integer(Number.parseInt(panel.dataset.roadmapPhasePanel, 10));
                hide(panel, !expandedPhasePositions.includes(position));
            }
        }

        function togglePhase(position) {
            applyPhaseExpansion(nextExpandedPhasePositions(
                expandedPhasePositions,
                { type: 'toggle', position: Number.parseInt(position, 10) },
                renderedPhasePositions,
            ));
        }

        function setAllPhasesExpanded(expanded) {
            applyPhaseExpansion(nextExpandedPhasePositions(
                expandedPhasePositions,
                { type: expanded ? 'expand-all' : 'collapse-all' },
                renderedPhasePositions,
            ));
        }

        function continuePhase(position) {
            const safePosition = integer(Number.parseInt(position, 10));
            const panel = root.querySelector?.(`[data-roadmap-phase-panel="${safePosition}"]`);
            const incomplete = panel?.querySelector?.('.learner-roadmap-task:not(.is-completed) .learner-roadmap-task__control');
            if (incomplete) {
                incomplete.focus?.();
                incomplete.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
                return;
            }
            const phaseIndex = renderedPhasePositions.indexOf(safePosition);
            const nextPosition = renderedPhasePositions[phaseIndex + 1];
            if (nextPosition) {
                applyPhaseExpansion([nextPosition]);
                root.querySelector?.(`[data-roadmap-phase-toggle="${nextPosition}"]`)?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
                return;
            }
            feedback('roadmap-completed');
        }

        function feedback(state, details = {}) {
            if (state === 'task-saved-undo' && nodes.feedbackStatus) {
                clear(nodes.feedbackStatus);
                nodes.feedbackStatus.append(element('span', '', 'Đã hoàn thành và chuyển xuống nhóm bên dưới. '));
                const undo = element('button', 'learner-roadmap-task-undo', 'Hoàn tác');
                undo.type = 'button'; undo.dataset.roadmapUndoTask = text(details.taskId);
                undo.setAttribute('aria-label', 'Hoàn tác đánh dấu hoàn thành nhiệm vụ');
                nodes.feedbackStatus.append(undo);
                return;
            }
            set(nodes.feedbackStatus, {
                'task-saved': 'Đã lưu tiến độ.', 'task-error': 'Chưa thể lưu tiến độ. Vui lòng thử lại.',
                'roadmap-completed': 'Bạn đã hoàn tất lộ trình 90 ngày.',
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

        function dispose() {
            processingTracker.stop();
            clearSuccessHide();
        }

        return { render, updateTask, previewTaskCompletion, taskCompletionDelay: () => reduceMotion ? 0 : 500, setTaskPending, feedback, toggleAnalysis, togglePhase, setAllPhasesExpanded, continuePhase, dispose };
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
        const baseView = createDomView(root);
        const api = createRoadmapApiClient(factory, global.document);
        const editButton = root.querySelector('[data-roadmap-edit]');
        let editableModel = null;
        const view = Object.create(baseView);
        view.render = (state, data) => {
            baseView.render(state, data);
            const history = Array.isArray(data?.version_history) ? data.version_history : [];
            const newestVersion = history.reduce((maximum, entry) => Math.max(maximum, Number(entry?.version) || 0), Number(data?.version) || 0);
            const editable = READY_STATES.has(state) && data?.analysis_origin === 'model' && data?.status === 'active' && Number(data?.version) === newestVersion;
            editableModel = editable ? data : null;
            if (editButton) editButton.hidden = !editable;
        };
        const controller = createRoadmapController({ api, view });
        const editorRoot = global.document.querySelector('[data-roadmap-editor]');
        const editorFactory = global.TalentHubLearnerRoadmapEditor?.createRoadmapEditor;
        const editor = editorRoot && typeof editorFactory === 'function' ? editorFactory(editorRoot, {
            api,
            createIdempotencyKey: defaultIdempotencyKey,
            onApplied: (payload) => controller.render(payload),
        }) : null;
        root.addEventListener('click', (event) => {
            const target = event.target instanceof global.Element ? event.target.closest('button') : null;
            if (!target || !root.contains(target)) return;
            if (target.matches('[data-roadmap-generate]')) controller.generate(target.dataset.roadmapGenerate);
            else if (target.matches('[data-roadmap-retry]')) controller.retry();
            else if (target.matches('[data-roadmap-expand-all]')) view.setAllPhasesExpanded(true);
            else if (target.matches('[data-roadmap-collapse-all]')) view.setAllPhasesExpanded(false);
            else if (target.matches('[data-roadmap-phase-toggle]')) view.togglePhase(target.dataset.roadmapPhaseToggle);
            else if (target.matches('[data-roadmap-continue]')) view.continuePhase(target.dataset.roadmapContinuePhase);
            else if (target.matches('[data-roadmap-analysis-toggle]')) view.toggleAnalysis();
            else if (target.matches('[data-roadmap-edit]') && editableModel && editor) editor.open(editableModel);
            else if (target.matches('[data-roadmap-task-id]')) controller.updateTask(target.dataset.roadmapTaskId, target.dataset.roadmapTaskStatus).catch(() => {});
            else if (target.matches('[data-roadmap-undo-task]')) controller.updateTask(target.dataset.roadmapUndoTask, 'completed').catch(() => {});
            else if (target.matches('[data-roadmap-feedback-value]')) controller.submitFeedback('', target.dataset.roadmapFeedbackValue).catch(() => {});
        });
        root.querySelector('[data-roadmap-version-select]')?.addEventListener('change', (event) => controller.loadVersion(event.target.value));
        controller.load();
    }

    const exported = {
        PROCESSING_STEPS, TALENT_AXES, processingProgressAt, createProcessingTracker,
        presentationState, buildRoadmapViewModel, createRoadmapApiClient, createRoadmapController,
        createDomView, confidenceLabel, normalizeTalentScore, completeTalentMap,
        initialExpandedPhasePositions, nextExpandedPhasePositions,
        formatRoadmapMinutes, roadmapTaskPresentation, roadmapProgressSnapshot, preparePhaseTasks,
    };
    global.TalentHubLearnerAiRoadmap = exported;
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    if (global.document && typeof global.document.addEventListener === 'function') {
        if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', boot, { once: true });
        else boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
