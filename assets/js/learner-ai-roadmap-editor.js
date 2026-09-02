/** Editable three-phase learner roadmap with an AI refinement preview. */
(function initRoadmapEditor(global) {
    'use strict';

    const PHASE_FIELDS = ['title', 'goal', 'skill_focus', 'deliverable', 'effort_label', 'metric_label'];
    const LIMITS = { title: 120, goal: 700, skill_focus: 500, deliverable: 500, effort_label: 500, metric_label: 500 };
    const MIN_TASKS = 1;
    const MAX_TASKS = 10;

    function clone(value) {
        return typeof global.structuredClone === 'function'
            ? global.structuredClone(value)
            : JSON.parse(JSON.stringify(value));
    }

    function createRoadmapEditorDraft(model) {
        const phases = (Array.isArray(model?.phases) ? model.phases : []).slice().sort((a, b) => a.position - b.position).slice(0, 3).map((phase) => ({
            phase_id: phase.phase_id, position: phase.position, start_day: phase.start_day, end_day: phase.end_day, code: phase.code,
            title: String(phase.title || ''), goal: String(phase.goal || ''), skill_focus: String(phase.skill_focus || ''),
            deliverable: String(phase.deliverable || ''), effort_label: String(phase.effort_label || ''), metric_label: String(phase.metric_label || ''),
            tasks: (Array.isArray(phase.tasks) ? phase.tasks : []).map((task, index) => {
                const rawTitle = String(task.title || '');
                const match = rawTitle.match(/\s*\(Mốc\s+(\d+)\s+ngày\)\s*$/iu);
                return {
                    task_id: task.task_id, position: index + 1,
                    title: match ? rawTitle.slice(0, match.index).trim() : rawTitle,
                    description: String(task.description || ''),
                    milestone_day: match ? Number.parseInt(match[1], 10) : Number(phase.end_day),
                    estimated_minutes: Number(task.estimated_minutes) || 60,
                };
            }),
        }));
        return { phases };
    }

    function initialEditorState() {
        return { open: false, model: null, original: null, draft: null, refined: null, previewId: null, activePhase: 1, step: 'edit', previewSource: 'ai_refined', errors: [], dirty: false, pending: null };
    }

    function phaseAt(draft, position) { return draft?.phases?.find((phase) => phase.position === Number(position)); }

    function createDraftTask(phase, taskId) {
        return {
            task_id: taskId,
            position: phase.tasks.length + 1,
            title: '',
            description: '',
            milestone_day: Number(phase.end_day),
            estimated_minutes: 30,
        };
    }

    function createTaskId() {
        if (typeof global.crypto?.randomUUID === 'function') return global.crypto.randomUUID();
        if (typeof global.crypto?.getRandomValues !== 'function') throw new Error('Không thể tạo mã nhiệm vụ an toàn.');
        const bytes = global.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    function roadmapEditorReducer(state, action) {
        if (action?.type === 'open') {
            const original = createRoadmapEditorDraft(action.model);
            return { ...initialEditorState(), open: true, model: clone(action.model), original: clone(original), draft: clone(original) };
        }
        if (!state.open) return state;
        if (action?.type === 'set-phase') return { ...state, activePhase: Math.max(1, Math.min(3, Number(action.phase) || 1)) };
        if (action?.type === 'edit-field') {
            const draft = clone(state.draft); const phase = phaseAt(draft, action.phase);
            if (!phase || !PHASE_FIELDS.includes(action.field)) return state;
            phase[action.field] = String(action.value ?? '');
            return { ...state, draft, dirty: true, errors: [] };
        }
        if (action?.type === 'edit-task') {
            const draft = clone(state.draft); const phase = phaseAt(draft, action.phase);
            const task = phase?.tasks?.find((item) => item.task_id === action.taskId);
            if (!task || !['title','description','milestone_day','estimated_minutes'].includes(action.field)) return state;
            task[action.field] = ['milestone_day','estimated_minutes'].includes(action.field) ? Number(action.value) : String(action.value ?? '');
            return { ...state, draft, dirty: true, errors: [] };
        }
        if (action?.type === 'reset-field') {
            const draft = clone(state.draft); const phase = phaseAt(draft, action.phase); const original = phaseAt(state.original, action.phase);
            if (!phase || !original) return state;
            if (action.taskId) {
                const task = phase.tasks.find((item) => item.task_id === action.taskId);
                const originalTask = original.tasks.find((item) => item.task_id === action.taskId);
                if (!task || !originalTask || !['title','description','milestone_day','estimated_minutes'].includes(action.field)) return state;
                task[action.field] = originalTask[action.field];
            } else {
                if (!PHASE_FIELDS.includes(action.field)) return state;
                phase[action.field] = original[action.field];
            }
            return { ...state, draft, dirty: JSON.stringify(draft) !== JSON.stringify(state.original) };
        }
        if (action?.type === 'reset-task') {
            const draft = clone(state.draft); const phase = phaseAt(draft, action.phase); const original = phaseAt(state.original, action.phase);
            const index = phase?.tasks?.findIndex((item) => item.task_id === action.taskId) ?? -1;
            const originalTask = original?.tasks?.find((item) => item.task_id === action.taskId);
            if (index < 0 || !originalTask) return state;
            const position = phase.tasks[index].position;
            phase.tasks[index] = { ...clone(originalTask), position };
            return { ...state, draft, dirty: JSON.stringify(draft) !== JSON.stringify(state.original), errors: [] };
        }
        if (action?.type === 'reset-phase') {
            const draft = clone(state.draft); const index = draft.phases.findIndex((phase) => phase.position === Number(action.phase));
            if (index < 0) return state;
            draft.phases[index] = clone(state.original.phases[index]);
            return { ...state, draft, dirty: JSON.stringify(draft) !== JSON.stringify(state.original), errors: [] };
        }
        if (action?.type === 'delete-task') {
            const draft = clone(state.draft); const phase = phaseAt(draft, action.phase);
            if (!phase || phase.tasks.length <= MIN_TASKS) return state;
            phase.tasks = phase.tasks.filter((task) => task.task_id !== action.taskId).map((task, index) => ({ ...task, position: index + 1 }));
            return { ...state, draft, dirty: true, errors: [] };
        }
        if (action?.type === 'add-task') {
            const draft = clone(state.draft); const phase = phaseAt(draft, action.phase);
            const taskId = typeof action.taskId === 'string' ? action.taskId.trim() : '';
            if (!phase || taskId === '' || phase.tasks.length >= MAX_TASKS || phase.tasks.some((task) => task.task_id === taskId)) return state;
            phase.tasks.push(createDraftTask(phase, taskId));
            return { ...state, draft, dirty: true, errors: [] };
        }
        if (action?.type === 'pending') return { ...state, pending: action.value };
        if (action?.type === 'validation') return { ...state, errors: action.errors || [], activePhase: action.first?.phase || state.activePhase };
        if (action?.type === 'refine-success') return { ...state, refined: clone(action.payload.ai_draft), previewId: action.payload.preview_id, step: 'preview', previewSource: 'ai_refined', pending: null };
        if (action?.type === 'select-preview' && ['learner_draft','ai_refined'].includes(action.source)) return { ...state, previewSource: action.source };
        if (action?.type === 'back-to-edit') return { ...state, step: 'edit', pending: null };
        if (action?.type === 'close') return initialEditorState();
        return state;
    }

    function validateRoadmapEditorDraft(draft) {
        const errors = [];
        if (!Array.isArray(draft?.phases) || draft.phases.length !== 3) errors.push({ phase: 1, field: 'phases', message: 'Roadmap phải có đúng 3 chặng.' });
        for (const phase of draft?.phases || []) {
            for (const field of PHASE_FIELDS) {
                const value = typeof phase[field] === 'string' ? phase[field].trim() : '';
                if (!value || value.length > LIMITS[field]) errors.push({ phase: phase.position, field, message: 'Nội dung bắt buộc hoặc quá dài.' });
            }
            if (!Array.isArray(phase.tasks) || phase.tasks.length < MIN_TASKS || phase.tasks.length > MAX_TASKS) errors.push({ phase: phase.position, field: 'tasks', message: 'Mỗi tháng cần từ 1 đến 10 nhiệm vụ.' });
            for (const task of phase.tasks || []) {
                if (!String(task.title || '').trim() || String(task.title).length > 220) errors.push({ phase: phase.position, taskId: task.task_id, field: 'title', message: 'Tiêu đề nhiệm vụ chưa hợp lệ.' });
                if (!String(task.description || '').trim() || String(task.description).length > 900) errors.push({ phase: phase.position, taskId: task.task_id, field: 'description', message: 'Mô tả nhiệm vụ chưa hợp lệ.' });
                const minimum = phase.start_day === 0 ? 1 : phase.start_day;
                if (!Number.isInteger(task.milestone_day) || task.milestone_day < minimum || task.milestone_day > phase.end_day) errors.push({ phase: phase.position, taskId: task.task_id, field: 'milestone_day', message: 'Mốc ngày phải nằm trong chặng.' });
                if (!Number.isInteger(task.estimated_minutes) || task.estimated_minutes < 5 || task.estimated_minutes > 1440) errors.push({ phase: phase.position, taskId: task.task_id, field: 'estimated_minutes', message: 'Thời lượng từ 5 đến 1440 phút.' });
            }
        }
        return { valid: errors.length === 0, errors, first: errors[0] || null };
    }

    function refineRequest(model, draft) { return { roadmapId: model.roadmap_id, baseVersion: model.version, draft }; }
    function applyRequest(model, draft, source, refinementId) { return { roadmapId: model.roadmap_id, baseVersion: model.version, source, draft, refinementId }; }

    function make(tag, className, text) {
        const node = global.document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function autoSizeTextarea(control) {
        if (!control?.style) return;
        control.style.height = 'auto';
        control.style.height = `${control.scrollHeight}px`;
    }

    function formatMinutes(minutes) {
        const safeMinutes = Math.max(0, Number(minutes) || 0);
        if (safeMinutes < 60) return `${safeMinutes} phút`;
        const hours = Math.floor(safeMinutes / 60);
        const remainder = safeMinutes % 60;
        return remainder > 0 ? `${hours} giờ ${remainder} phút` : `${hours} giờ`;
    }

    function createRoadmapEditor(root, options = {}) {
        if (!root) throw new TypeError('Roadmap editor root is required.');
        const api = options.api;
        let state = initialEditorState(); let returnFocus = null; let pendingPromise = null;
        const body = root.querySelector('[data-roadmap-editor-body]');
        const status = root.querySelector('[data-roadmap-editor-status]');
        const title = root.querySelector('[data-roadmap-editor-title]');
        let fieldSequence = 0;

        function dispatch(action) { state = roadmapEditorReducer(state, action); render(); return state; }
        function fieldError(fieldName, taskId = null) {
            return state.errors.find((error) => error.phase === state.activePhase
                && error.field === fieldName
                && (taskId ? error.taskId === taskId : !error.taskId));
        }
        function field(parent, label, value, attrs = {}) {
            const wrap = make('div', attrs.wrapperClass || 'learner-roadmap-editor__inline-field');
            const input = attrs.multiline ? make('textarea') : make('input'); input.value = value; input.dataset.editorField = attrs.field;
            input.setAttribute('aria-label', label);
            input.id = `roadmap-editor-field-${++fieldSequence}`;
            input.className = attrs.inputClass || '';
            if (attrs.multiline) input.rows = 1;
            if (attrs.taskId) input.dataset.taskId = attrs.taskId;
            if (attrs.type) input.type = attrs.type;
            if (attrs.min !== undefined) input.min = String(attrs.min);
            if (attrs.max !== undefined) input.max = String(attrs.max);
            const error = fieldError(attrs.field, attrs.taskId || null);
            if (error) {
                const errorId = `${input.id}-error`;
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', errorId);
                wrap.classList.add('has-error');
                wrap.dataset.editorInvalid = '';
                wrap.dataset.errorId = errorId;
            }
            if (attrs.visibleLabel !== false) {
                const labelNode = make('label', attrs.labelClass || 'learner-roadmap-editor__field-label', label);
                labelNode.htmlFor = input.id;
                wrap.append(labelNode);
            }
            const control = make('div', 'learner-roadmap-editor__inline-control');
            control.append(input);
            if (attrs.resettable !== false) {
                const reset = make('button','learner-roadmap-editor__field-reset','↺'); reset.type = 'button'; reset.dataset.editorResetField = attrs.field;
                reset.setAttribute('aria-label', `Khôi phục ${label.toLowerCase()}`);
                reset.title = `Khôi phục ${label.toLowerCase()}`;
                if (attrs.taskId) reset.dataset.taskId = attrs.taskId;
                control.append(reset);
            }
            wrap.append(control);
            if (error) {
                const message = make('p', 'learner-roadmap-editor__field-error', error.message);
                message.id = wrap.dataset.errorId;
                wrap.append(message);
            }
            parent.append(wrap);
            return wrap;
        }
        function metaControl(parent, label, value, attrs = {}) {
            const wrap = make('label', 'learner-roadmap-editor__meta-control');
            wrap.append(make('span', '', label));
            const input = make('input'); input.type = 'number'; input.value = value; input.dataset.editorField = attrs.field; input.dataset.taskId = attrs.taskId;
            input.min = String(attrs.min); input.max = String(attrs.max); input.setAttribute('aria-label', attrs.ariaLabel || label);
            const error = fieldError(attrs.field, attrs.taskId);
            if (error) {
                const errorId = `roadmap-editor-field-${++fieldSequence}-error`;
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', errorId);
                const message = make('span', 'learner-roadmap-editor__field-error', error.message);
                message.id = errorId;
                wrap.append(input, message);
            } else {
                wrap.append(input);
            }
            if (attrs.suffix) wrap.append(make('span', '', attrs.suffix));
            parent.append(wrap);
        }
        function renderEditor() {
            body.replaceChildren(); const phase = phaseAt(state.draft, state.activePhase); if (!phase) return;
            const documentPanel = make('section', 'learner-roadmap-editor__document');
            const intro = make('div', 'learner-roadmap-editor__phase-intro');
            const heading = make('div', 'learner-roadmap-editor__phase-heading');
            const headingLine = make('div', 'learner-roadmap-editor__phase-title-line'); headingLine.append(make('span', '', `Tháng ${phase.position}:`));
            field(headingLine, 'Tên tháng', phase.title, { field: 'title', multiline: true, visibleLabel: false, wrapperClass: 'learner-roadmap-editor__phase-title', inputClass: 'learner-roadmap-editor__phase-title-input' });
            const editingState = make('p', 'learner-roadmap-editor__editing-state'); editingState.append(make('span', '', ''), global.document.createTextNode('Nội dung đang được chỉnh sửa trực tiếp'));
            heading.append(headingLine, editingState);
            const reset = make('button', 'learner-btn learner-btn--text learner-roadmap-editor__reset-phase', '↺  Khôi phục tháng'); reset.type = 'button'; reset.dataset.editorResetPhase = String(phase.position);
            intro.append(heading, reset); documentPanel.append(intro);

            const facts = make('div', 'learner-roadmap-editor__facts');
            field(facts, 'Mục tiêu', phase.goal, { field: 'goal', multiline: true, wrapperClass: 'learner-roadmap-editor__fact-row' });
            field(facts, 'Kỹ năng trọng tâm', phase.skill_focus, { field: 'skill_focus', multiline: true, wrapperClass: 'learner-roadmap-editor__fact-row' });
            field(facts, 'Sản phẩm đầu ra', phase.deliverable, { field: 'deliverable', multiline: true, wrapperClass: 'learner-roadmap-editor__fact-row' });
            field(facts, 'Nỗ lực dự kiến', phase.effort_label, { field: 'effort_label', wrapperClass: 'learner-roadmap-editor__fact-row' });
            field(facts, 'Tiêu chí hoàn thành', phase.metric_label, { field: 'metric_label', multiline: true, wrapperClass: 'learner-roadmap-editor__fact-row' });
            documentPanel.append(facts);

            const totalMinutes = phase.tasks.reduce((sum, task) => sum + (Number(task.estimated_minutes) || 0), 0);
            const taskHeading = make('div', 'learner-roadmap-editor__task-heading');
            const taskCount = make('span', 'learner-roadmap-editor__task-count', `${phase.tasks.length}/${MAX_TASKS} nhiệm vụ · ${formatMinutes(totalMinutes)}`);
            taskCount.dataset.editorField = 'tasks'; taskCount.tabIndex = -1;
            const taskCountError = fieldError('tasks');
            if (taskCountError) { taskCount.setAttribute('aria-invalid', 'true'); taskCount.setAttribute('aria-label', taskCountError.message); }
            taskHeading.append(make('h3', '', 'Kế hoạch hành động'), taskCount);
            documentPanel.append(taskHeading);
            const taskList = make('div', 'learner-roadmap-editor__task-list');
            const originalPhase = phaseAt(state.original, phase.position);
            for (const task of phase.tasks) {
                const row = make('section', 'learner-roadmap-editor__task-row'); row.dataset.editorTaskCard = task.task_id;
                row.append(make('strong', 'learner-roadmap-editor__task-number', `${task.position}.`));
                const copy = make('div', 'learner-roadmap-editor__task-copy');
                const resettable = Boolean(originalPhase?.tasks?.some((originalTask) => originalTask.task_id === task.task_id));
                field(copy, `Tiêu đề nhiệm vụ ${task.position}`, task.title, { field: 'title', taskId: task.task_id, multiline: true, visibleLabel: false, resettable, wrapperClass: 'learner-roadmap-editor__task-title', inputClass: 'learner-roadmap-editor__task-title-input' });
                field(copy, `Mô tả nhiệm vụ ${task.position}`, task.description, { field: 'description', taskId: task.task_id, multiline: true, visibleLabel: false, resettable, wrapperClass: 'learner-roadmap-editor__task-description', inputClass: 'learner-roadmap-editor__task-description-input' });
                row.append(copy);
                const meta = make('div', 'learner-roadmap-editor__task-actions');
                metaControl(meta, 'Mốc ngày', task.milestone_day, { field: 'milestone_day', taskId: task.task_id, min: phase.start_day === 0 ? 1 : phase.start_day, max: phase.end_day, ariaLabel: `Mốc ngày nhiệm vụ ${task.position}` });
                metaControl(meta, '', task.estimated_minutes, { field: 'estimated_minutes', taskId: task.task_id, min: 5, max: 1440, suffix: 'phút', ariaLabel: `Thời lượng nhiệm vụ ${task.position}` });
                if (resettable) {
                    const restore = make('button', 'learner-roadmap-editor__task-restore', 'Khôi phục'); restore.type = 'button'; restore.dataset.editorResetTask = task.task_id; restore.setAttribute('aria-label', `Khôi phục nhiệm vụ ${task.position}`);
                    meta.append(restore);
                }
                const remove = make('button', 'learner-roadmap-editor__delete', '⌫'); remove.type = 'button'; remove.dataset.editorDeleteTask = task.task_id; remove.setAttribute('aria-disabled', phase.tasks.length <= MIN_TASKS ? 'true' : 'false'); remove.setAttribute('aria-label', phase.tasks.length <= MIN_TASKS ? 'Mỗi tháng cần ít nhất 1 nhiệm vụ' : `Xóa nhiệm vụ ${task.position}`); remove.title = phase.tasks.length <= MIN_TASKS ? 'Mỗi tháng cần ít nhất 1 nhiệm vụ' : `Xóa nhiệm vụ ${task.position}`;
                meta.append(remove); row.append(meta); taskList.append(row);
            }
            documentPanel.append(taskList);
            if (phase.tasks.length < MAX_TASKS) {
                const add = make('button', 'learner-roadmap-editor__add-task', '＋ Thêm nhiệm vụ'); add.type = 'button'; add.dataset.editorAddTask = '';
                documentPanel.append(add);
            } else {
                const limit = make('p', 'learner-roadmap-editor__task-limit', 'Đã đạt tối đa 10 nhiệm vụ trong tháng này.'); limit.dataset.editorTaskLimit = '';
                documentPanel.append(limit);
            }
            body.append(documentPanel);
            body.querySelectorAll('textarea').forEach(autoSizeTextarea);
        }
        function previewCard(draft, source) {
            const wrap = make('div', 'learner-roadmap-editor__preview'); wrap.dataset.previewSource = source;
            for (const phase of draft.phases) {
                const card = make('section', 'learner-roadmap-editor__preview-phase'); card.append(make('span', '', `Ngày ${phase.start_day === 0 ? 1 : phase.start_day}–${phase.end_day}`), make('h3', '', phase.title), make('p', '', phase.goal));
                const list = make('ol'); for (const task of phase.tasks) { const item = make('li'); item.append(make('strong', '', task.title), make('p', '', task.description), make('small', '', `Mốc ngày ${task.milestone_day} · ${task.estimated_minutes} phút`)); list.append(item); } card.append(list); wrap.append(card);
            } return wrap;
        }
        function renderPreview() { body.replaceChildren(previewCard(state.previewSource === 'ai_refined' ? state.refined : state.draft, state.previewSource)); }
        function render() {
            root.hidden = !state.open; root.dataset.step = state.step; root.setAttribute('aria-busy', state.pending ? 'true' : 'false');
            if (!state.open) return;
            if (title) title.textContent = state.step === 'edit' ? 'Chỉnh sửa lộ trình 90 ngày' : 'Xem trước lộ trình';
            root.querySelectorAll('[data-editor-phase]').forEach((button) => { const phaseNumber = Number(button.dataset.editorPhase); const active = phaseNumber === state.activePhase; const hasError = state.errors.some((error) => error.phase === phaseNumber); button.setAttribute('aria-selected', active ? 'true' : 'false'); button.classList.toggle('is-active', active); button.classList.toggle('has-error', hasError); });
            root.querySelectorAll('[data-editor-step]').forEach((node) => { node.hidden = node.dataset.editorStep !== state.step; });
            root.querySelectorAll('[data-preview-source]').forEach((button) => { button.classList.toggle('is-active', button.dataset.previewSource === state.previewSource); });
            root.querySelectorAll('[data-editor-refine],[data-editor-apply],[data-editor-save]').forEach((button) => { button.disabled = Boolean(state.pending); });
            if (status) status.textContent = state.pending === 'refine' ? 'AI đang tinh chỉnh nội dung...' : state.pending === 'apply' ? 'Đang áp dụng roadmap...' : state.errors[0]?.message || '';
            if (body) state.step === 'edit' ? renderEditor() : renderPreview();
        }
        async function saveLearnerDraft() {
            if (pendingPromise) return pendingPromise; const validation = validateRoadmapEditorDraft(state.draft);
            if (!validation.valid) { dispatch({ type: 'validation', ...validation }); const first = validation.first; const scope = first.taskId ? `[data-editor-task-card="${first.taskId}"] ` : ''; body.querySelector(`${scope}[data-editor-field="${first.field}"]`)?.focus(); return null; }
            dispatch({ type: 'pending', value: 'apply' });
            pendingPromise = api.send('POST','/ai-roadmap-apply.php',applyRequest(state.model,state.draft,'learner_draft',null),{ idempotencyKey: options.createIdempotencyKey?.() }).then((payload) => { options.onApplied?.(payload); close(true); return payload; }).catch((error) => { dispatch({ type: 'pending', value: null }); if (status) status.textContent = error?.message || 'Chưa thể áp dụng roadmap.'; throw error; }).finally(() => { pendingPromise = null; });
            return pendingPromise;
        }
        async function refine() {
            if (pendingPromise) return pendingPromise; const validation = validateRoadmapEditorDraft(state.draft);
            if (!validation.valid) { dispatch({ type: 'validation', ...validation }); const first = validation.first; const scope = first.taskId ? `[data-editor-task-card="${first.taskId}"] ` : ''; body.querySelector(`${scope}[data-editor-field="${first.field}"]`)?.focus(); return null; }
            dispatch({ type: 'pending', value: 'refine' });
            pendingPromise = api.send('POST','/ai-roadmap-refine.php',refineRequest(state.model,state.draft),{ idempotencyKey: options.createIdempotencyKey?.() }).then((payload) => { dispatch({ type: 'refine-success', payload }); return payload; }).catch((error) => { dispatch({ type: 'pending', value: null }); if (status) status.textContent = error?.message || 'Chưa thể tinh chỉnh roadmap.'; throw error; }).finally(() => { pendingPromise = null; });
            return pendingPromise;
        }
        async function apply() {
            if (pendingPromise) return pendingPromise; const selected = state.previewSource === 'ai_refined' ? state.refined : state.draft;
            dispatch({ type: 'pending', value: 'apply' });
            pendingPromise = api.send('POST','/ai-roadmap-apply.php',applyRequest(state.model,selected,state.previewSource,state.previewId),{ idempotencyKey: options.createIdempotencyKey?.() }).then((payload) => { options.onApplied?.(payload); close(true); return payload; }).catch((error) => { dispatch({ type: 'pending', value: null }); if (status) status.textContent = error?.message || 'Chưa thể áp dụng roadmap.'; throw error; }).finally(() => { pendingPromise = null; });
            return pendingPromise;
        }
        function open(model) { returnFocus = global.document.activeElement; state = roadmapEditorReducer(state,{ type: 'open', model }); global.document.body.classList.add('has-roadmap-editor'); render(); root.querySelector('[data-editor-phase="1"]')?.focus(); }
        function close(force = false) { if (!force && state.dirty && !global.confirm('Bạn có muốn đóng và bỏ các thay đổi chưa áp dụng?')) return false; state = roadmapEditorReducer(state,{ type: 'close' }); global.document.body.classList.remove('has-roadmap-editor'); render(); returnFocus?.focus?.(); return true; }
        function focusAfterTaskDelete(deletedTaskId, priorTasks) {
            const deletedIndex = priorTasks.findIndex((task) => task.task_id === deletedTaskId);
            const phase = phaseAt(state.draft, state.activePhase);
            const nextTask = phase?.tasks?.[Math.min(Math.max(0, deletedIndex), Math.max(0, phase.tasks.length - 1))];
            if (nextTask) body.querySelector(`[data-editor-task-card="${nextTask.task_id}"] [data-editor-field="title"]`)?.focus();
            else body.querySelector('[data-editor-add-task]')?.focus();
        }
        const onClick = (event) => { const closeTarget = event.target.closest?.('[data-editor-close]'); if (closeTarget) { close(); return; } const button = event.target.closest?.('button'); if (!button) return;
            if (button.matches('[data-editor-phase]')) dispatch({ type:'set-phase', phase:button.dataset.editorPhase });
            else if (button.matches('[data-editor-reset-phase]')) dispatch({ type:'reset-phase', phase:button.dataset.editorResetPhase }); else if (button.matches('[data-editor-delete-task]')) { const priorTasks = [...(phaseAt(state.draft, state.activePhase)?.tasks || [])]; if (button.getAttribute('aria-disabled') === 'true') { if (status) status.textContent = 'Mỗi tháng cần ít nhất 1 nhiệm vụ.'; button.focus(); } else { const deletedTaskId = button.dataset.editorDeleteTask; dispatch({ type:'delete-task', phase:state.activePhase, taskId:deletedTaskId }); focusAfterTaskDelete(deletedTaskId, priorTasks); } }
            else if (button.matches('[data-editor-add-task]')) { const taskId = options.createTaskId?.() || createTaskId(); dispatch({ type:'add-task', phase:state.activePhase, taskId }); body.querySelector(`[data-editor-task-card="${taskId}"] [data-editor-field="title"]`)?.focus(); }
            else if (button.matches('[data-editor-reset-task]')) dispatch({ type:'reset-task', phase:state.activePhase, taskId:button.dataset.editorResetTask });
            else if (button.matches('[data-editor-reset-field]')) dispatch({ type:'reset-field', phase:state.activePhase, taskId:button.dataset.taskId || null, field:button.dataset.editorResetField });
            else if (button.matches('[data-editor-save]')) saveLearnerDraft().catch(() => {});
            else if (button.matches('[data-editor-refine]')) refine().catch(() => {}); else if (button.matches('[data-editor-back]')) dispatch({ type:'back-to-edit' });
            else if (button.matches('[data-preview-source]')) dispatch({ type:'select-preview', source:button.dataset.previewSource }); else if (button.matches('[data-editor-apply]')) apply().catch(() => {});
        };
        const onInput = (event) => { const input = event.target; const fieldName = input?.dataset?.editorField; if (!fieldName) return; state = roadmapEditorReducer(state, input.dataset.taskId ? { type:'edit-task',phase:state.activePhase,taskId:input.dataset.taskId,field:fieldName,value:input.value } : { type:'edit-field',phase:state.activePhase,field:fieldName,value:input.value }); if (input.tagName === 'TEXTAREA') autoSizeTextarea(input); if (status) status.textContent = ''; };
        const onResize = () => { if (state.open && body) body.querySelectorAll('textarea').forEach(autoSizeTextarea); };
        const onKey = (event) => {
            if (!state.open) return;
            if (event.key === 'Escape') { close(); return; }
            if (event.key !== 'Tab') return;
            const focusable = [...root.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [href]')].filter((node) => !node.closest('[hidden]'));
            if (focusable.length === 0) return;
            const first = focusable[0]; const last = focusable[focusable.length - 1];
            if (event.shiftKey && global.document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && global.document.activeElement === last) { event.preventDefault(); first.focus(); }
        };
        root.addEventListener('click',onClick); root.addEventListener('input',onInput); global.document.addEventListener('keydown',onKey);
        if (typeof global.addEventListener === 'function') global.addEventListener('resize', onResize);
        return { open, close, saveLearnerDraft, refine, apply, dispose() { root.removeEventListener('click',onClick); root.removeEventListener('input',onInput); global.document.removeEventListener('keydown',onKey); if (typeof global.removeEventListener === 'function') global.removeEventListener('resize', onResize); }, getState:()=>clone(state) };
    }

    const exported = { MIN_TASKS, MAX_TASKS, createRoadmapEditorDraft, createDraftTask, validateRoadmapEditorDraft, initialEditorState, roadmapEditorReducer, refineRequest, applyRequest, autoSizeTextarea, createRoadmapEditor };
    global.TalentHubLearnerRoadmapEditor = exported;
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
})(typeof window !== 'undefined' ? window : globalThis);
