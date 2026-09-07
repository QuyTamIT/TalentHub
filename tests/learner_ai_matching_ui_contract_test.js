'use strict';

const assert = require('node:assert/strict');
const { test } = require('node:test');
const projects = require('../assets/js/learner-opportunity-matches.js');
const jobs = require('../assets/js/learner-job-matches.js');

function projectPayload(sentenceCount) {
    return {
        state: 'ready_model',
        items: [{
            catalog_id: 'project-1', rank: 1, match_score: 70,
            why_fit: Array.from({ length: sentenceCount }, (_, i) => `Hướng dẫn bước ${i + 1}.`).join(' '),
            fit_reasons: ['Có dữ liệu kỹ năng để đối chiếu.'],
            gap_reasons: ['Cần bổ sung bằng chứng thực hành.'],
            skills_to_develop: ['SQL'],
        }],
    };
}

test('project controller renders every backend-supported analysis length', async () => {
    for (const count of [3, 4, 5, 6, 7, 8]) {
        const payload = projectPayload(count);
        const rendered = [];
        const controller = projects.createOpportunityMatchController({
            api: { get: async () => payload },
            view: { render: (state, result) => rendered.push({ state, result }) },
        });
        await controller.load();
        assert.equal(rendered.at(-1).state, 'ready-model', `${count} sentences must render`);
        assert.equal(rendered.at(-1).result.items[0].why_fit, payload.items[0].why_fit);
    }
});

test('project UI still rejects analyses outside the backend sentence contract', () => {
    for (const count of [1, 2, 9]) {
        assert.equal(projects.normalizeReadyItems(projectPayload(count).items), null);
    }
});

test('partial project results preserve detailed five-to-seven sentence analyses', async () => {
    for (const count of [5, 6, 7]) {
        const payload = { ...projectPayload(count), state: 'partial_model' };
        const renders = [];
        const controller = projects.createOpportunityMatchController({
            api: { get: async () => payload },
            view: { render: (state, result) => renders.push({ state, result }) },
        });
        await controller.load();
        assert.equal(renders.at(-1).state, 'ready-model');
        assert.equal(renders.at(-1).result.items[0].why_fit, payload.items[0].why_fit);
    }
});

test('job UI retains detailed educator analyses without truncation', () => {
    const analysis = projectPayload(7).items[0].why_fit;
    const payload = jobs.normalizeJobMatchPayload({
        state: 'ready_model', enterprise_groups: [{
            enterprise_id: 'enterprise-1', enterprise_name: 'Đơn vị kiểm thử',
            positions: [{
                catalog_id: 'job-1', match_score: 70, title: 'Thực tập dữ liệu', analysis,
                url: '/app/learner/opportunity.php?type=internship&id=00000000-0000-4000-8000-000000000001',
            }],
        }],
    });
    assert.equal(payload.enterprise_groups[0].positions[0].analysis, analysis);
});

for (const [name, createController, ready] of [
    ['project', projects.createOpportunityMatchController, projectPayload(6)],
    ['job', jobs.createJobMatchController, { state: 'no_matching_jobs', enterprise_groups: [] }],
]) {
    test(`${name} polls pending POST with GET and renders completion`, async () => {
        const renders = []; let posts = 0; let gets = 0; let waits = 0;
        const controller = createController({
            api: {
                send: async () => { posts++; return { state: 'pending' }; },
                get: async () => { gets++; return gets === 1 ? { state: 'not_generated' } : ready; },
            },
            view: { render: (state) => renders.push(state) },
            createIdempotencyKey: () => 'stable-key',
            wait: async () => { waits++; },
        });
        await controller.generate();
        assert.equal(posts, 1);
        assert.equal(gets, 2);
        assert.equal(waits, 2);
        assert.equal(renders.at(-1), name === 'project' ? 'ready-model' : 'no-matches');
    });

    test(`${name} bounds pending polling, exits loading and allows another action`, async () => {
        const renders = []; let posts = 0; let gets = 0; let waits = 0;
        const controller = createController({
            api: {
                send: async () => { posts++; return { state: 'pending' }; },
                get: async () => { gets++; return { state: 'pending' }; },
            },
            view: { render: (state) => renders.push(state) },
            createIdempotencyKey: () => `key-${posts}`,
            wait: async () => { waits++; },
        });
        await controller.generate();
        assert.equal(gets, 3);
        assert.equal(waits, 3);
        assert.equal(renders.at(-1), 'pending');
        await controller.generate();
        assert.equal(posts, 2, 'in-flight lock must be released');
        assert.equal(gets, 6);
    });

    test(`${name} preserves the POST key on transport retry before polling`, async () => {
        const keys = []; let madeKeys = 0;
        const controller = createController({
            api: {
                send: async (_method, _url, _body, options) => {
                    keys.push(options.idempotencyKey);
                    if (keys.length === 1) throw Object.assign(new Error('timeout'), { code: 'REQUEST_TIMEOUT' });
                    return { state: 'pending' };
                },
                get: async () => ready,
            },
            view: { render: () => {} },
            createIdempotencyKey: () => `key-${++madeKeys}`,
            wait: async () => {},
        });
        await controller.generate();
        assert.deepEqual(keys, ['key-1', 'key-1']);
        assert.equal(madeKeys, 1);
    });

    test(`${name} read failures during polling do not misreport accepted generation as failed`, async () => {
        const renders = []; let gets = 0;
        const controller = createController({
            api: {
                send: async () => ({ state: 'pending' }),
                get: async () => { gets++; throw new Error('network'); },
            },
            view: { render: (state) => renders.push(state) },
            createIdempotencyKey: () => 'key', wait: async () => {},
        });
        await controller.generate();
        assert.equal(gets, 3);
        assert.equal(renders.at(-1), 'pending');
        assert.ok(!renders.includes('source-error'));
    });

    test(`${name} no completed run yet remains pending through bounded polling exhaustion`, async () => {
        const renders = []; let gets = 0;
        const controller = createController({
            api: {
                send: async () => ({ state: 'pending', accepted: true }),
                get: async () => { gets++; return { state: 'not_generated' }; },
            },
            view: { render: (state, payload) => renders.push({ state, payload }) },
            createIdempotencyKey: () => 'pending-key', wait: async () => {},
        });
        await controller.generate();
        assert.equal(gets, 3);
        assert.equal(renders.at(-1).state, 'pending');
        assert.equal(renders.at(-1).payload.accepted, true);
        assert.ok(!renders.some(({ state }) => state === 'not-generated' || state === 'source-error'));
    });
}

test('pending views show waiting copy while progress and error panels remain hidden', () => {
    for (const [createView, prefix, progressKey, errorKey] of [
        [projects.createOpportunityMatchView, 'opportunity', 'loading', 'error'],
        [jobs.createJobMatchView, 'job', 'progress', 'source-error'],
    ]) {
        const elements = new Map();
        const root = {
            dataset: {}, setAttribute() {},
            querySelector(selector) {
                if (!elements.has(selector)) elements.set(selector, { hidden: false, textContent: '', children: [] });
                return elements.get(selector);
            },
        };
        createView(root).render('pending', { state: 'pending' });
        assert.equal(root.dataset.state, 'pending');
        assert.equal(root.querySelector(`[data-${prefix}-ai-${progressKey}]`).hidden, true);
        assert.equal(root.querySelector(`[data-${prefix}-ai-${errorKey}]`).hidden, true);
        assert.match(root.querySelector(`[data-${prefix}-ai-status]`).textContent, /chưa có kết quả hoàn tất/);
    }
});
