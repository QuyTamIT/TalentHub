'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const { buildRoadmapViewModel, roadmapProgressSnapshot, formatRoadmapMinutes } = require('../assets/js/learner-ai-roadmap.js');

test('roadmapProgressSnapshot detects 100% completion across all 3 phases', () => {
    const phases = [
        {
            position: 1,
            tasks: [
                { task_id: 't1', title: 'Task 1.1', status: 'completed' },
                { task_id: 't2', title: 'Task 1.2', status: 'completed' },
                { task_id: 't3', title: 'Task 1.3', status: 'completed' },
            ],
        },
        {
            position: 2,
            tasks: [
                { task_id: 't4', title: 'Task 2.1', status: 'completed' },
                { task_id: 't5', title: 'Task 2.2', status: 'completed' },
                { task_id: 't6', title: 'Task 2.3', status: 'completed' },
            ],
        },
        {
            position: 3,
            tasks: [
                { task_id: 't7', title: 'Task 3.1', status: 'completed' },
                { task_id: 't8', title: 'Task 3.2', status: 'completed' },
                { task_id: 't9', title: 'Task 3.3', status: 'completed' },
            ],
        },
    ];

    const snapshot = roadmapProgressSnapshot(phases);
    assert.equal(snapshot.completedTasks, 9);
    assert.equal(snapshot.totalTasks, 9);
    assert.equal(snapshot.overallPercent, 100);
    assert.equal(snapshot.phases[0].status, 'completed');
    assert.equal(snapshot.phases[1].status, 'completed');
    assert.equal(snapshot.phases[2].status, 'completed');
    assert.equal(snapshot.phases[0].actionLabel, 'Chuyển sang chặng 2');
    assert.equal(snapshot.phases[1].actionLabel, 'Chuyển sang chặng 3');
    assert.equal(snapshot.phases[2].actionLabel, 'Hoàn tất lộ trình');
});

test('roadmapProgressSnapshot handles partially completed roadmap correctly', () => {
    const phases = [
        {
            position: 1,
            tasks: [
                { task_id: 't1', status: 'completed' },
                { task_id: 't2', status: 'completed' },
            ],
        },
        {
            position: 2,
            tasks: [
                { task_id: 't3', status: 'in_progress' },
                { task_id: 't4', status: 'not_started' },
            ],
        },
        {
            position: 3,
            tasks: [
                { task_id: 't5', status: 'not_started' },
            ],
        },
    ];

    const snapshot = roadmapProgressSnapshot(phases);
    assert.equal(snapshot.completedTasks, 2);
    assert.equal(snapshot.totalTasks, 5);
    assert.equal(snapshot.overallPercent, 40);
    assert.equal(snapshot.phases[0].actionLabel, 'Chuyển sang chặng 2');
    assert.equal(snapshot.phases[1].actionLabel, 'Tiếp tục nhiệm vụ');
    assert.equal(snapshot.phases[2].actionLabel, 'Tiếp tục nhiệm vụ');
});

test('buildRoadmapViewModel prepares complete summary data for 3 phases', () => {
    const payload = {
        phases: [
            {
                position: 1,
                title: 'Khám phá & Thiết lập',
                goal: 'Mục tiêu chặng 1',
                deliverable: 'Báo cáo chặng 1',
                tasks: [
                    { task_id: 't1', title: 'Nhiệm vụ 1.1 (Mốc 10 ngày)', estimated_minutes: 90, status: 'completed' },
                    { task_id: 't2', title: 'Nhiệm vụ 1.2', estimated_minutes: 60, status: 'completed' },
                ],
            },
            {
                position: 2,
                title: 'Thực hành Ứng dụng',
                goal: 'Mục tiêu chặng 2',
                deliverable: 'Sản phẩm demo',
                tasks: [
                    { task_id: 't3', title: 'Nhiệm vụ 2.1', estimated_minutes: 120, status: 'completed' },
                ],
            },
            {
                position: 3,
                title: 'Hoàn thiện & Đóng gói',
                goal: 'Mục tiêu chặng 3',
                deliverable: 'Portfolio hoàn chỉnh',
                tasks: [
                    { task_id: 't4', title: 'Nhiệm vụ 3.1', estimated_minutes: 60, status: 'completed' },
                ],
            },
        ],
    };

    const vm = buildRoadmapViewModel(payload);
    assert.equal(vm.phases.length, 3);
    assert.equal(vm.phases[0].title, 'Khám phá & Thiết lập');
    assert.equal(vm.phases[0].completedTaskCount, 2);
    assert.equal(vm.phases[0].displayTasks[0].presentation.title, 'Nhiệm vụ 1.1');
    assert.equal(vm.phases[0].displayTasks[0].presentation.milestoneLabel, 'Mốc ngày 10');
    assert.equal(vm.phases[0].displayTasks[0].presentation.durationLabel, '90 phút');

    const totalMinutes = vm.phases.flatMap(p => p.tasks).reduce((sum, t) => sum + t.estimated_minutes, 0);
    assert.equal(formatRoadmapMinutes(totalMinutes), '5 giờ 30 phút');
});

test('createDomView handles full completion flow, modal confirmation, and celebration card', () => {
    const { createDomView } = require('../assets/js/learner-ai-roadmap.js');

    // Create a mock DOM node environment
    function createMockElement(tag, className = '') {
        const el = {
            tagName: tag.toUpperCase(),
            className,
            classList: {
                classes: new Set(className ? className.split(/\s+/).filter(Boolean) : []),
                add(...names) { names.forEach(n => this.classes.add(n)); el.className = Array.from(this.classes).join(' '); },
                remove(...names) { names.forEach(n => this.classes.delete(n)); el.className = Array.from(this.classes).join(' '); },
                toggle(name, force) {
                    if (force !== undefined) {
                        if (force) this.classes.add(name); else this.classes.delete(name);
                    } else {
                        if (this.classes.has(name)) this.classes.delete(name); else this.classes.add(name);
                    }
                    el.className = Array.from(this.classes).join(' ');
                },
                contains(name) { return this.classes.has(name); },
            },
            children: [],
            childNodes: [],
            firstChild: null,
            _textContent: undefined,
            get textContent() {
                if (this._textContent !== undefined) return this._textContent;
                if (this.children.length === 0) return '';
                return this.children.map(c => c.textContent).join(' ');
            },
            set textContent(val) {
                this._textContent = String(val);
                this.children = [];
                this.childNodes = [];
                this.firstChild = null;
            },
            dataset: {},
            attributes: {},
            hidden: false,
            disabled: false,
            style: {},
            setAttribute(k, v) { this.attributes[k] = String(v); },
            getAttribute(k) { return this.attributes[k] || null; },
            removeAttribute(k) { delete this.attributes[k]; },
            appendChild(child) {
                this.children.push(child);
                this.childNodes.push(child);
                child.parentNode = this;
                this.firstChild = this.children[0];
                return child;
            },
            append(...nodes) {
                for (const node of nodes) {
                    if (typeof node === 'string') {
                        this.appendChild(createMockElement('span', '', node));
                    } else if (node) {
                        this.appendChild(node);
                    }
                }
            },
            removeChild(child) {
                const idx = this.children.indexOf(child);
                if (idx !== -1) {
                    this.children.splice(idx, 1);
                    this.childNodes.splice(idx, 1);
                    child.parentNode = null;
                }
                this.firstChild = this.children[0] || null;
                return child;
            },
            querySelector(selector) {
                return this.querySelectorAll(selector)[0] || null;
            },
            querySelectorAll(selector) {
                const results = [];
                function match(node) {
                    if (selector.startsWith('.') && node.classList?.contains(selector.slice(1))) results.push(node);
                    else if (selector.startsWith('#') && node.id === selector.slice(1)) results.push(node);
                    else if (selector.startsWith('[') && selector.endsWith(']')) {
                        const attr = selector.slice(1, -1);
                        if (attr.includes('=')) {
                            const [k, v] = attr.split('=').map(s => s.replace(/["']/g, '').trim());
                            if (k.startsWith('data-')) {
                                const dataKey = k.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                                if (node.dataset?.[dataKey] === v || node.attributes?.[k] === v) results.push(node);
                            } else if (node.attributes?.[k] === v) results.push(node);
                        } else {
                            if (attr.startsWith('data-')) {
                                const dataKey = attr.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                                if (dataKey in (node.dataset || {}) || attr in (node.attributes || {})) results.push(node);
                            } else if (attr in (node.attributes || {})) results.push(node);
                        }
                    } else if (node.tagName === selector.toUpperCase()) results.push(node);
                    for (const child of node.children) match(child);
                }
                for (const child of this.children) match(child);
                return results;
            },
            closest(selector) {
                let curr = this;
                while (curr) {
                    if (selector.startsWith('.') && curr.classList?.contains(selector.slice(1))) return curr;
                    curr = curr.parentNode;
                }
                return null;
            },
            focus() {},
            scrollIntoView() {},
        };
        if (className) el.classList.classes = new Set(className.split(/\s+/).filter(Boolean));
        return el;
    }

    let mockDoc;
    const celebrationEl = createMockElement('section', 'learner-roadmap-celebration');
    celebrationEl.dataset.roadmapCelebration = '';
    celebrationEl.hidden = true;
    const phasesEl = createMockElement('div', 'learner-roadmap-phases');
    phasesEl.dataset.roadmapPhases = '';
    const planControlsEl = createMockElement('div', 'learner-roadmap-plan__controls');

    const completeModalEl = createMockElement('div', 'learner-roadmap-complete-modal');
    completeModalEl.dataset.roadmapCompleteModal = '';
    completeModalEl.hidden = true;
    const confirmBtn = createMockElement('button');
    confirmBtn.dataset.roadmapCompleteConfirm = '';
    completeModalEl.appendChild(confirmBtn);

    mockDoc = {
        createElement(tag) {
            const node = createMockElement(tag);
            node.ownerDocument = mockDoc;
            return node;
        },
        querySelector(selector) {
            if (selector.includes('data-roadmap-complete-modal')) return completeModalEl;
            return mockRoot.querySelector(selector);
        },
    };

    const mockRoot = createMockElement('main', 'learner-roadmap');
    mockRoot.ownerDocument = mockDoc;
    mockRoot.appendChild(celebrationEl);
    mockRoot.appendChild(phasesEl);
    mockRoot.appendChild(planControlsEl);

    const storage = new Map();
    const mockGlobal = {
        document: mockDoc,
        localStorage: {
            getItem: (k) => storage.get(k) || null,
            setItem: (k, v) => storage.set(k, String(v)),
            removeItem: (k) => storage.delete(k),
        },
        Element: function() {},
    };

    const view = createDomView(mockRoot, {
        document: mockDoc,
        global: mockGlobal,
        schedule: (fn) => fn(),
        cancelSchedule: () => {},
    });

    const rawModel = {
        id: 'road-101',
        progress: { completed_tasks: 3, total_tasks: 3 },
        overallPercent: 100,
        phases: [
            {
                position: 1,
                title: 'Chặng 1',
                rangeLabel: 'Ngày 1-30',
                deliverable: 'Báo cáo 1',
                progress: { completed_tasks: 1, total_tasks: 1 },
                tasks: [{ task_id: 't1', title: 'Task 1', estimated_minutes: 60, status: 'completed' }],
            },
            {
                position: 2,
                title: 'Chặng 2',
                rangeLabel: 'Ngày 31-60',
                deliverable: 'Báo cáo 2',
                progress: { completed_tasks: 1, total_tasks: 1 },
                tasks: [{ task_id: 't2', title: 'Task 2', estimated_minutes: 60, status: 'completed' }],
            },
            {
                position: 3,
                title: 'Chặng 3',
                rangeLabel: 'Ngày 61-90',
                deliverable: 'Báo cáo 3',
                progress: { completed_tasks: 1, total_tasks: 1 },
                tasks: [{ task_id: 't3', title: 'Task 3', estimated_minutes: 60, status: 'completed' }],
            },
        ],
    };
    const model = buildRoadmapViewModel(rawModel);

    // 1. Initial render
    view.render('ready-model', model);
    assert.equal(celebrationEl.hidden, true, 'Celebration should be hidden initially before confirmation');

    // 2. User clicks continue on last phase -> opens modal
    view.continuePhase(3);
    assert.equal(completeModalEl.hidden, false, 'Complete modal should open');

    // 3. User confirms completion
    view.confirmCompleteRoadmap();
    assert.equal(completeModalEl.hidden, true, 'Modal should close after confirmation');
    assert.equal(view.isRoadmapCompletedConfirmed(), true, 'Roadmap completion should be confirmed');
    assert.equal(celebrationEl.hidden, false, 'Celebration card should now be visible');
    assert.equal(phasesEl.hidden, true, 'Old roadmap phases must be hidden when celebration is visible');
    assert.equal(planControlsEl.hidden, true, 'Roadmap plan controls must be hidden when celebration is visible');

    // Check celebration card contents
    const celebrationText = celebrationEl.textContent;
    assert.match(celebrationText, /Chúc mừng bạn đã hoàn thành toàn bộ lộ trình/);
    assert.match(celebrationText, /100%/);
    assert.match(celebrationText, /3\/3/);
    assert.match(celebrationText, /Thời lượng dự kiến/);
    assert.doesNotMatch(celebrationText, /đã nghiệm thu|Thời lượng tích lũy|Giai đoạn đạt chuẩn/);
    assert.match(celebrationText, /Khi dữ liệu không thay đổi, hệ thống có thể sử dụng lại lộ trình hiện tại/);

    // Verify refresh button exists inside celebration
    const refreshBtn = celebrationEl.querySelector('[data-roadmap-generate]');
    assert.ok(refreshBtn, 'CTA button with data-roadmap-generate must exist');
    assert.equal(refreshBtn.dataset.roadmapGenerate, 'refresh');

    // 4. If a task becomes incomplete, celebration should hide and old phases should reappear
    view.updateTask('t1', 'in_progress');
    assert.equal(view.isRoadmapCompletedConfirmed(), false, 'Confirmed state should reset when task is uncompleted');
    assert.equal(celebrationEl.hidden, true, 'Celebration card should be hidden when task is incomplete');
    assert.equal(phasesEl.hidden, false, 'Old roadmap phases must be restored when celebration is hidden');
    assert.equal(planControlsEl.hidden, false, 'Roadmap plan controls must be restored when celebration is hidden');
});

test('celebration state persists when user navigates away and returns with backend roadmap_id payload', () => {
    const { createDomView, buildRoadmapViewModel } = require('../assets/js/learner-ai-roadmap.js');

    function createMockElement(tag, className = '') {
        const el = {
            tagName: tag.toUpperCase(),
            className,
            classList: {
                classes: new Set(className ? className.split(/\s+/).filter(Boolean) : []),
                add(...names) { names.forEach(n => this.classes.add(n)); el.className = Array.from(this.classes).join(' '); },
                remove(...names) { names.forEach(n => this.classes.delete(n)); el.className = Array.from(this.classes).join(' '); },
                toggle(name, force) {
                    if (force !== undefined) {
                        if (force) this.classes.add(name); else this.classes.delete(name);
                    } else {
                        if (this.classes.has(name)) this.classes.delete(name); else this.classes.add(name);
                    }
                    el.className = Array.from(this.classes).join(' ');
                },
                contains(name) { return this.classes.has(name); },
            },
            children: [],
            childNodes: [],
            firstChild: null,
            _textContent: undefined,
            get textContent() {
                if (this._textContent !== undefined) return this._textContent;
                if (this.children.length === 0) return '';
                return this.children.map(c => c.textContent).join(' ');
            },
            set textContent(val) {
                this._textContent = String(val);
                this.children = [];
                this.childNodes = [];
                this.firstChild = null;
            },
            dataset: {},
            attributes: {},
            hidden: false,
            disabled: false,
            style: {},
            setAttribute(k, v) { this.attributes[k] = String(v); },
            getAttribute(k) { return this.attributes[k] || null; },
            removeAttribute(k) { delete this.attributes[k]; },
            appendChild(child) {
                this.children.push(child);
                this.childNodes.push(child);
                child.parentNode = this;
                this.firstChild = this.children[0];
                return child;
            },
            append(...nodes) {
                for (const node of nodes) {
                    if (typeof node === 'string') {
                        this.appendChild(createMockElement('span', '', node));
                    } else if (node) {
                        this.appendChild(node);
                    }
                }
            },
            removeChild(child) {
                const idx = this.children.indexOf(child);
                if (idx !== -1) {
                    this.children.splice(idx, 1);
                    this.childNodes.splice(idx, 1);
                    child.parentNode = null;
                }
                this.firstChild = this.children[0] || null;
                return child;
            },
            querySelector(selector) { return this.querySelectorAll(selector)[0] || null; },
            querySelectorAll(selector) {
                const results = [];
                function match(node) {
                    if (selector.startsWith('.') && node.classList?.contains(selector.slice(1))) results.push(node);
                    else if (selector.startsWith('#') && node.id === selector.slice(1)) results.push(node);
                    else if (selector.startsWith('[') && selector.endsWith(']')) {
                        const attr = selector.slice(1, -1);
                        if (attr.includes('=')) {
                            const [k, v] = attr.split('=').map(s => s.replace(/["']/g, '').trim());
                            if (k.startsWith('data-')) {
                                const dataKey = k.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                                if (node.dataset?.[dataKey] === v || node.attributes?.[k] === v) results.push(node);
                            } else if (node.attributes?.[k] === v) results.push(node);
                        } else {
                            if (attr.startsWith('data-')) {
                                const dataKey = attr.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                                if (dataKey in (node.dataset || {}) || attr in (node.attributes || {})) results.push(node);
                            } else if (attr in (node.attributes || {})) results.push(node);
                        }
                    } else if (node.tagName === selector.toUpperCase()) results.push(node);
                    for (const child of node.children) match(child);
                }
                for (const child of this.children) match(child);
                return results;
            },
            focus() {},
            scrollIntoView() {},
        };
        if (className) el.classList.classes = new Set(className.split(/\s+/).filter(Boolean));
        return el;
    }

    const storage = new Map();
    const mockGlobal = {
        localStorage: {
            getItem: (k) => storage.get(k) || null,
            setItem: (k, v) => storage.set(k, String(v)),
            removeItem: (k) => storage.delete(k),
        },
        Element: function() {},
    };

    const celebrationEl = createMockElement('section', 'learner-roadmap-celebration');
    celebrationEl.dataset.roadmapCelebration = '';
    celebrationEl.hidden = true;
    const phasesEl = createMockElement('div', 'learner-roadmap-phases');
    phasesEl.dataset.roadmapPhases = '';
    const completeModalEl = createMockElement('div', 'learner-roadmap-complete-modal');
    completeModalEl.dataset.roadmapCompleteModal = '';
    completeModalEl.hidden = true;

    const mockDoc = {
        createElement(tag) {
            const node = createMockElement(tag);
            node.ownerDocument = mockDoc;
            return node;
        },
        querySelector(selector) {
            if (selector.includes('data-roadmap-complete-modal')) return completeModalEl;
            return mockRoot.querySelector(selector);
        },
    };

    const mockRoot = createMockElement('main', 'learner-roadmap');
    mockRoot.ownerDocument = mockDoc;
    mockRoot.appendChild(celebrationEl);
    mockRoot.appendChild(phasesEl);

    // Payload exactly matching PHP API which uses roadmap_id (not id)
    const backendPayload = {
        roadmap_id: 'rdm-998877-backend',
        version: 1,
        progress: { completed_tasks: 3, total_tasks: 3 },
        phases: [
            {
                position: 1,
                title: 'Chặng 1',
                rangeLabel: 'Ngày 1-30',
                progress: { completed_tasks: 1, total_tasks: 1 },
                tasks: [{ task_id: 't1', title: 'Task 1', status: 'completed' }],
            },
            {
                position: 2,
                title: 'Chặng 2',
                rangeLabel: 'Ngày 31-60',
                progress: { completed_tasks: 1, total_tasks: 1 },
                tasks: [{ task_id: 't2', title: 'Task 2', status: 'completed' }],
            },
            {
                position: 3,
                title: 'Chặng 3',
                rangeLabel: 'Ngày 61-90',
                progress: { completed_tasks: 1, total_tasks: 1 },
                tasks: [{ task_id: 't3', title: 'Task 3', status: 'completed' }],
            },
        ],
    };

    const view1 = createDomView(mockRoot, { document: mockDoc, global: mockGlobal });
    const model1 = buildRoadmapViewModel(backendPayload);
    assert.equal(model1.roadmap_id, 'rdm-998877-backend');
    assert.equal(model1.id, 'rdm-998877-backend');

    view1.render('ready-model', model1);
    assert.equal(celebrationEl.hidden, true);

    // Confirm completion
    view1.confirmCompleteRoadmap();
    assert.equal(view1.isRoadmapCompletedConfirmed(), true);
    assert.equal(celebrationEl.hidden, false);
    assert.equal(phasesEl.hidden, true);

    // Verify key in storage
    assert.equal(storage.get('th_roadmap_completed_rdm-998877-backend'), 'confirmed');

    // Simulate navigating to another module and returning (fresh DOM view, fresh model from API)
    const celebrationEl2 = createMockElement('section', 'learner-roadmap-celebration');
    celebrationEl2.dataset.roadmapCelebration = '';
    celebrationEl2.hidden = true;
    const phasesEl2 = createMockElement('div', 'learner-roadmap-phases');
    phasesEl2.dataset.roadmapPhases = '';

    const mockRoot2 = createMockElement('main', 'learner-roadmap');
    mockRoot2.ownerDocument = mockDoc;
    mockRoot2.appendChild(celebrationEl2);
    mockRoot2.appendChild(phasesEl2);

    const view2 = createDomView(mockRoot2, { document: mockDoc, global: mockGlobal });
    const model2 = buildRoadmapViewModel(backendPayload);

    // Initial render when returning to the module
    view2.render('ready-model', model2);

    // Celebration should immediately be visible and phases hidden
    assert.equal(view2.isRoadmapCompletedConfirmed(), true, 'Completed confirmation must persist across module navigation');
    assert.equal(celebrationEl2.hidden, false, 'Celebration banner must remain visible upon return');
    assert.equal(phasesEl2.hidden, true, 'Roadmap phases must remain hidden upon return');
});

test('beginProcessing scrolls smoothly to processing element for user visibility', () => {
    const { createDomView } = require('../assets/js/learner-ai-roadmap.js');

    function createMockElement(tag, className = '') {
        const el = {
            tagName: tag.toUpperCase(),
            className,
            classList: {
                classes: new Set(className ? className.split(/\s+/).filter(Boolean) : []),
                add(...names) { names.forEach(n => this.classes.add(n)); el.className = Array.from(this.classes).join(' '); },
                remove(...names) { names.forEach(n => this.classes.delete(n)); el.className = Array.from(this.classes).join(' '); },
                toggle(name, force) {
                    if (force !== undefined) {
                        if (force) this.classes.add(name); else this.classes.delete(name);
                    } else {
                        if (this.classes.has(name)) this.classes.delete(name); else this.classes.add(name);
                    }
                    el.className = Array.from(this.classes).join(' ');
                },
                contains(name) { return this.classes.has(name); },
            },
            children: [],
            childNodes: [],
            firstChild: null,
            _textContent: undefined,
            get textContent() {
                if (this._textContent !== undefined) return this._textContent;
                if (this.children.length === 0) return '';
                return this.children.map(c => c.textContent).join(' ');
            },
            set textContent(val) {
                this._textContent = String(val);
                this.children = [];
                this.childNodes = [];
                this.firstChild = null;
            },
            dataset: {},
            attributes: {},
            hidden: false,
            disabled: false,
            style: {},
            setAttribute(k, v) { this.attributes[k] = String(v); },
            getAttribute(k) { return this.attributes[k] || null; },
            removeAttribute(k) { delete this.attributes[k]; },
            appendChild(child) {
                this.children.push(child);
                this.childNodes.push(child);
                child.parentNode = this;
                this.firstChild = this.children[0];
                return child;
            },
            removeChild(child) {
                const idx = this.children.indexOf(child);
                if (idx !== -1) {
                    this.children.splice(idx, 1);
                    this.childNodes.splice(idx, 1);
                    child.parentNode = null;
                }
                this.firstChild = this.children[0] || null;
                return child;
            },
            querySelector(selector) { return this.querySelectorAll(selector)[0] || null; },
            querySelectorAll(selector) {
                const results = [];
                function match(node) {
                    if (selector.startsWith('.') && node.classList?.contains(selector.slice(1))) results.push(node);
                    else if (selector.startsWith('#') && node.id === selector.slice(1)) results.push(node);
                    else if (selector.startsWith('[') && selector.endsWith(']')) {
                        const attr = selector.slice(1, -1);
                        if (attr.includes('=')) {
                            const [k, v] = attr.split('=').map(s => s.replace(/["']/g, '').trim());
                            if (k.startsWith('data-')) {
                                const dataKey = k.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                                if (node.dataset?.[dataKey] === v || node.attributes?.[k] === v) results.push(node);
                            } else if (node.attributes?.[k] === v) results.push(node);
                        } else {
                            if (attr.startsWith('data-')) {
                                const dataKey = attr.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                                if (dataKey in (node.dataset || {}) || attr in (node.attributes || {})) results.push(node);
                            } else if (attr in (node.attributes || {})) results.push(node);
                        }
                    } else if (node.tagName === selector.toUpperCase()) results.push(node);
                    for (const child of node.children) match(child);
                }
                for (const child of this.children) match(child);
                return results;
            },
            focus() {},
            scrollIntoView() {},
        };
        if (className) el.classList.classes = new Set(className.split(/\s+/).filter(Boolean));
        return el;
    }

    let scrolledToProcessing = false;
    const processingEl = createMockElement('section', 'learner-roadmap-processing');
    processingEl.dataset.roadmapProcessing = '';
    processingEl.hidden = true;
    processingEl.scrollIntoView = () => {
        scrolledToProcessing = true;
    };

    const mockDoc = {
        createElement(tag) {
            const node = createMockElement(tag);
            node.ownerDocument = mockDoc;
            return node;
        },
        querySelector(selector) {
            return mockRoot.querySelector(selector);
        },
    };

    const mockRoot = createMockElement('main', 'learner-roadmap');
    mockRoot.ownerDocument = mockDoc;
    mockRoot.appendChild(processingEl);

    const view = createDomView(mockRoot, {
        document: mockDoc,
        global: { localStorage: { getItem: () => null, setItem: () => {}, removeItem: () => {} } },
    });

    view.render('processing', { preserveReady: true });
    assert.equal(processingEl.hidden, false, 'Processing element must be unhidden');
    assert.equal(scrolledToProcessing, true, 'Must scroll into view when processing starts');
    view.render('source-unavailable', {});
});
