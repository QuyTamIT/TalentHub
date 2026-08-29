'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const {
    createOpportunityMatchController,
    createOpportunityMatchView,
    mapOpportunityMatchState,
    isSafeInternalOpportunityUrl,
    humanizeOpportunityLabel,
    mountOpportunityMatches,
} = require('../assets/js/learner-opportunity-matches.js');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets/js/learner-opportunity-matches.js'), 'utf8');
const ecosystemSource = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'ecosystem.php'), 'utf8');
const learnerCss = fs.readFileSync(path.join(__dirname, '..', 'assets', 'css', 'learner.css'), 'utf8');

test('generation renders three distinct database-backed matches', async () => {
    const states = [];
    const api = {
        async get() { return { state: 'not_generated', items: [] }; },
        async send(method, endpoint, body, options) {
            assert.equal(method, 'POST');
            assert.equal(endpoint, '/opportunity-matches.php');
            assert.deepEqual(body, {});
            assert.equal(options.idempotencyKey, 'opportunity-match-key-0001');
            return {
                state: 'ready_model',
                items: [
                    { catalog_id: 'p1', rank: 1, match_score: 92, why_fit: 'Python và IoT', matched_skills: ['data_analysis'], missing_skills: ['user_research'], expected_outcomes: ['problem_solving'], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p1' },
                    { catalog_id: 'p2', rank: 2, match_score: 84, why_fit: 'Phân tích dữ liệu', matched_skills: [], missing_skills: [], expected_outcomes: [], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p2' },
                    { catalog_id: 'p3', rank: 3, match_score: 76, why_fit: 'Sáng tạo và hợp tác', matched_skills: [], missing_skills: [], expected_outcomes: [], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p3' },
                ],
            };
        },
    };
    const controller = createOpportunityMatchController({
        api,
        view: { render: (state, payload) => states.push({ state, payload }) },
        createIdempotencyKey: () => 'opportunity-match-key-0001',
    });

    await controller.generate();

    assert.equal(states.at(-1).state, 'ready-model');
    assert.deepEqual(states.at(-1).payload.items.map((item) => item.catalog_id), ['p1', 'p2', 'p3']);
    assert.deepEqual(states.at(-1).payload.items[0].matched_skills, ['Phân tích dữ liệu']);
    assert.deepEqual(states.at(-1).payload.items[0].missing_skills, ['Nghiên cứu người dùng']);
    assert.deepEqual(states.at(-1).payload.items[0].expected_outcomes, ['Giải quyết vấn đề']);
});

test('generation deduplicates an in-flight request and reuses its idempotency key', async () => {
    let release;
    let sends = 0;
    const pending = new Promise((resolve) => { release = resolve; });
    const api = {
        async get() { return { state: 'not_generated', items: [] }; },
        async send(method, endpoint, body, options) {
            sends += 1;
            assert.equal(options.idempotencyKey, 'stable-opportunity-key');
            await pending;
            return { state: 'provider_unavailable', items: [] };
        },
    };
    const states = [];
    const controller = createOpportunityMatchController({
        api,
        view: { render: (state) => states.push(state) },
        createIdempotencyKey: () => 'stable-opportunity-key',
    });

    const first = controller.generate();
    const second = controller.generate();
    assert.equal(first, second);
    release();
    await first;

    assert.equal(sends, 1);
    assert.deepEqual(states, ['loading', 'source-error']);
});

test('controller maps every approved API state and rejects malformed ready payloads', async () => {
    const expected = {
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
    };
    for (const [apiState, viewState] of Object.entries(expected)) {
        assert.equal(mapOpportunityMatchState(apiState), viewState);
    }

    const states = [];
    const controller = createOpportunityMatchController({
        api: { async get() { return { state: 'ready_model', items: [{ catalog_id: 'duplicate' }, { catalog_id: 'duplicate' }] }; } },
        view: { render: (state) => states.push(state) },
        createIdempotencyKey: () => 'unused-key-0000001',
    });
    await controller.load();
    assert.equal(states.at(-1), 'source-error');
});

test('only same-origin internal opportunity links are accepted', () => {
    assert.equal(isSafeInternalOpportunityUrl('/app/learner/opportunity.php?id=p1'), true);
    assert.equal(isSafeInternalOpportunityUrl('/app/learner/ecosystem.php?tab=opportunities#opportunity-p1'), true);
    assert.equal(isSafeInternalOpportunityUrl('https://evil.example/p1'), false);
    assert.equal(isSafeInternalOpportunityUrl('//evil.example/p1'), false);
    assert.equal(isSafeInternalOpportunityUrl('javascript:alert(1)'), false);
    assert.equal(isSafeInternalOpportunityUrl('/app/learner/../admin.php'), false);
});

test('renderer uses safe DOM APIs and never exposes component scores', () => {
    assert.match(source, /document\.createElement/);
    assert.match(source, /textContent/);
    assert.doesNotMatch(source, /\.innerHTML\s*=/);
    assert.doesNotMatch(source, /structured_score|gemini_score/);
    for (const label of ['Vì sao phù hợp', 'Kỹ năng phù hợp', 'Cần bổ sung', 'Bạn sẽ đạt được', 'Nguồn phân tích', 'Xem phân tích chi tiết', 'Xem dự án']) {
        assert.match(source, new RegExp(label));
    }
});

test('no-fit analysis humanizes skill codes for Vietnamese learners', () => {
    assert.equal(humanizeOpportunityLabel('creative_design'), 'Thiết kế sáng tạo');
    assert.equal(humanizeOpportunityLabel('data_analysis'), 'Phân tích dữ liệu');
    assert.equal(humanizeOpportunityLabel('new_student_skill'), 'New student skill');
});

test('no-fit analysis renders visual metrics, structured insights and evidence sources', () => {
    for (const marker of [
        'data-opportunity-ai-metric-evaluated',
        'data-opportunity-ai-metric-best-score',
        'data-opportunity-ai-metric-threshold',
        'data-opportunity-ai-metric-weighting',
        'data-opportunity-ai-analysis-strengths',
        'data-opportunity-ai-analysis-gaps',
        'data-opportunity-ai-analysis-next-steps',
        'data-opportunity-ai-analysis-sources',
    ]) {
        assert.match(ecosystemSource, new RegExp(marker));
    }
    assert.match(source, /analysis_meta/);
    assert.match(source, /humanizeOpportunityLabel/);
    assert.match(learnerCss, /learner-opportunity-ai__analysis-metrics/);
    assert.match(learnerCss, /learner-opportunity-ai__analysis-chip/);
});

test('no-fit renderer converts a legacy English Gemini result into a Vietnamese visual analysis', () => {
    const previousDocument = global.document;
    const makeNode = () => ({
        className: '',
        textContent: '',
        hidden: false,
        children: [],
        dataset: {},
        appendChild(child) { this.children.push(child); return child; },
        replaceChildren(...children) { this.children = children; },
        setAttribute() {},
        querySelector() { return null; },
    });
    const fields = new Map([
        ['[data-opportunity-ai-analysis-headline]', makeNode()],
        ['[data-opportunity-ai-analysis-explanation]', makeNode()],
        ['[data-opportunity-ai-metric-evaluated]', makeNode()],
        ['[data-opportunity-ai-metric-best-score]', makeNode()],
        ['[data-opportunity-ai-metric-threshold]', makeNode()],
        ['[data-opportunity-ai-metric-weighting]', makeNode()],
        ['[data-opportunity-ai-analysis-strengths]', makeNode()],
        ['[data-opportunity-ai-analysis-demands]', makeNode()],
        ['[data-opportunity-ai-analysis-gaps]', makeNode()],
        ['[data-opportunity-ai-analysis-next-steps]', makeNode()],
        ['[data-opportunity-ai-analysis-sources]', makeNode()],
    ]);
    const panel = makeNode();
    panel.querySelector = (selector) => fields.get(selector) || null;
    const root = makeNode();
    root.querySelector = (selector) => selector === '[data-opportunity-ai-no-fit]' ? panel : null;
    global.document = {
        createElement() { return makeNode(); },
    };

    try {
        const view = createOpportunityMatchView(root);
        view.render('no-fit-model', {
            analysis: {
                headline: 'No opportunities currently reach the threshold for suitable match',
                explanation: 'Candidate scores did not reach the current threshold.',
                learner_strengths: ['creative_design', 'data_analysis'],
                catalog_demands: ['python'],
                main_gaps: ['Current catalog items lack alignment with demonstrated strengths.'],
                next_steps: ['Review future catalog additions.'],
                evidence_ref_ids: ['skill:creative-design', 'opportunity:project-1'],
                analysis_meta: {
                    evaluated_count: 21,
                    best_score: 38,
                    match_threshold: 60,
                    data_weight: 70,
                    ai_weight: 30,
                },
            },
        });

        assert.equal(fields.get('[data-opportunity-ai-analysis-headline]').textContent, 'Chưa có cơ hội đạt ngưỡng phù hợp');
        assert.match(fields.get('[data-opportunity-ai-analysis-explanation]').textContent, /Gemini đã đối chiếu 21 cơ hội/);
        assert.equal(fields.get('[data-opportunity-ai-metric-best-score]').textContent, '38/100');
        assert.equal(fields.get('[data-opportunity-ai-metric-weighting]').textContent, '70% dữ liệu · 30% Gemini');
        assert.deepEqual(fields.get('[data-opportunity-ai-analysis-strengths]').children.map((child) => child.textContent), ['Thiết kế sáng tạo', 'Phân tích dữ liệu']);
        assert.deepEqual(fields.get('[data-opportunity-ai-analysis-gaps]').children.map((child) => child.textContent), ['Mức độ tương đồng giữa hồ sơ và các cơ hội hiện tại còn thấp.']);
        assert.deepEqual(fields.get('[data-opportunity-ai-analysis-sources]').children.map((child) => child.textContent), ['Hồ sơ kỹ năng', 'Dự án TalentHub']);

        view.render('no-fit-model', { analysis: { analysis_meta: { evaluated_count: 0, best_score: null } } });
        assert.equal(fields.get('[data-opportunity-ai-metric-best-score]').textContent, '—/100');
    } finally {
        global.document = previousDocument;
    }
});

test('mounting twice reuses one controller and registers one trigger listener', async () => {
    const previousDocument = global.document;
    const previousClient = global.TalentHubLearnerClient;
    let triggerListeners = 0;
    const root = {
        dataset: {},
        querySelector() { return null; },
        setAttribute() {},
    };
    const panel = { hidden: false };
    const trigger = {
        hidden: false,
        disabled: false,
        addEventListener(type) { if (type === 'click') triggerListeners += 1; },
    };
    global.document = {
        querySelector(selector) {
            if (selector === '[data-opportunity-matches]') return root;
            if (selector === '[data-ecosystem-panel="opportunities"]') return panel;
            return null;
        },
        querySelectorAll(selector) {
            if (selector === '[data-opportunity-ai-trigger]') return [trigger];
            return [];
        },
    };
    global.TalentHubLearnerClient = {
        async get() { return { state: 'not_generated', items: [] }; },
        async send() { return { state: 'provider_unavailable', items: [] }; },
    };
    try {
        const first = mountOpportunityMatches();
        const second = mountOpportunityMatches();
        await Promise.resolve();
        assert.equal(second, first);
        assert.equal(triggerListeners, 1);
    } finally {
        global.document = previousDocument;
        global.TalentHubLearnerClient = previousClient;
    }
});
