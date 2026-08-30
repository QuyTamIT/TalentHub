'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const {
    createOpportunityMatchController,
    createOpportunityMatchView,
    mapOpportunityMatchState,
    isSafeInternalProjectUrl,
    classifySafeOpportunityUrl,
    humanizeOpportunityLabel,
    normalizeReadyItems,
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
                    { catalog_id: 'p1', rank: 1, match_score: 92, why_fit: 'Dự án tận dụng nền tảng Python đã có. Điểm đánh giá cho thấy tư duy phân tích phù hợp. Hồ sơ còn thiếu một sản phẩm IoT hoàn chỉnh. Cơ hội này giúp rèn năng lực triển khai thực tế.', fit_reasons: ['Có nền tảng Python'], gap_reasons: ['Chưa có sản phẩm IoT'], skills_to_develop: ['Thiết kế IoT'], matched_skills: ['data_analysis'], missing_skills: ['user_research'], expected_outcomes: ['problem_solving'], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p1' },
                    { catalog_id: 'p2', rank: 2, match_score: 84, why_fit: 'Cơ hội phù hợp với tư duy dữ liệu. Kinh nghiệm nhóm hỗ trợ việc triển khai. Hồ sơ chưa có dashboard hoàn chỉnh. Dự án giúp rèn cách trình bày insight.', fit_reasons: ['Có tư duy dữ liệu'], gap_reasons: ['Chưa có dashboard'], skills_to_develop: ['Trình bày insight'], matched_skills: [], missing_skills: [], expected_outcomes: [], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p2' },
                    { catalog_id: 'p3', rank: 3, match_score: 76, why_fit: 'Dự án phù hợp với thế mạnh sáng tạo. Hoạt động nhóm là một nền tảng hữu ích. Hồ sơ chưa có nghiên cứu người dùng. Cơ hội giúp rèn quy trình thiết kế.', fit_reasons: ['Có thế mạnh sáng tạo'], gap_reasons: ['Chưa có nghiên cứu người dùng'], skills_to_develop: ['Thiết kế sản phẩm'], matched_skills: [], missing_skills: [], expected_outcomes: [], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p3' },
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
    assert.deepEqual(states.at(-1).payload.items[0].fit_reasons, ['Có nền tảng Python']);
    assert.deepEqual(states.at(-1).payload.items[0].gap_reasons, ['Chưa có sản phẩm IoT']);
    assert.deepEqual(states.at(-1).payload.items[0].skills_to_develop, ['Thiết kế IoT']);
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

test('analyzed no-fit response still renders every Gemini-selected opportunity', async () => {
    const states = [];
    const controller = createOpportunityMatchController({
        api: {
            async get() {
                return {
                    state: 'no_fit_model',
                    analysis: {
                        headline: 'Các dự án hiện tại chưa đạt mức phù hợp cao',
                        explanation: 'Gemini đã phân tích dựa trên hồ sơ được cho phép. Hai dự án có một số điểm liên quan đến năng lực hiện tại. Hồ sơ vẫn thiếu minh chứng kỹ thuật quan trọng. Bạn có thể xem chi tiết từng dự án để biết phần cần rèn luyện.',
                    },
                    items: [{
                        catalog_id: 'p-low', rank: 1, match_score: 28,
                        why_fit: 'Dự án có liên quan đến khả năng làm việc nhóm hiện tại. Nền tảng phân tích hỗ trợ một phần yêu cầu. Hồ sơ chưa có sản phẩm kỹ thuật tương ứng. Cơ hội này phù hợp để tham khảo và xác định kỹ năng cần rèn luyện.',
                        fit_reasons: ['Có khả năng làm việc nhóm.'],
                        gap_reasons: ['Chưa có sản phẩm kỹ thuật tương ứng.'],
                        skills_to_develop: ['Xây dựng sản phẩm kỹ thuật'],
                        evidence: [], canonical_url: '/app/learner/opportunity.php?id=p-low',
                    }],
                };
            },
        },
        view: { render: (state, payload) => states.push({ state, payload }) },
        createIdempotencyKey: () => 'unused-key',
    });

    await controller.load();

    assert.equal(states.at(-1).state, 'no-fit-model');
    assert.equal(states.at(-1).payload.items.length, 1);
    assert.equal(states.at(-1).payload.items[0].catalog_id, 'p-low');
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

test('only the same-origin internal project detail route is accepted', () => {
    assert.equal(isSafeInternalProjectUrl('/app/learner/project.php?id=50000000-0000-4000-8000-000000000001'), true);
    assert.equal(isSafeInternalProjectUrl('/app/learner/opportunity.php?id=p1'), false);
    assert.equal(isSafeInternalProjectUrl('/app/learner/ecosystem.php?tab=opportunities'), false);
    assert.equal(isSafeInternalProjectUrl('https://evil.example/p1'), false);
    assert.equal(isSafeInternalProjectUrl('//evil.example/p1'), false);
    assert.equal(isSafeInternalProjectUrl('javascript:alert(1)'), false);
    assert.equal(isSafeInternalProjectUrl('/app/learner/../admin.php'), false);
});

test('project actions reject GitHub and every external URL', () => {
    assert.equal(classifySafeOpportunityUrl('https://github.com/talenthub-demo/ecosmart-ai'), null);
    assert.deepEqual(
        classifySafeOpportunityUrl('/app/learner/project.php?id=50000000-0000-4000-8000-000000000001'),
        { url: '/app/learner/project.php?id=50000000-0000-4000-8000-000000000001', external: false },
    );
    for (const unsafeUrl of [
        'http://github.com/talenthub-demo/ecosmart-ai',
        'https://user:password@github.com/talenthub-demo/ecosmart-ai',
        '//github.com/talenthub-demo/ecosmart-ai',
        'javascript:alert(1)',
        'data:text/html,unsafe',
    ]) {
        assert.equal(classifySafeOpportunityUrl(unsafeUrl), null);
    }
});

test('renderer disables a project action when a persisted GitHub URL is received', () => {
    const previousDocument = global.document;
    const makeNode = (tagName = '') => ({
        tagName: String(tagName).toUpperCase(),
        className: '',
        textContent: '',
        hidden: false,
        children: [],
        dataset: {},
        style: {},
        attributes: {},
        appendChild(child) { this.children.push(child); return child; },
        replaceChildren(...children) { this.children = children; },
        setAttribute(name, value) { this.attributes[name] = String(value); },
        getAttribute(name) { return this.attributes[name] || null; },
        querySelector() { return null; },
    });
    const list = makeNode('div');
    const results = makeNode('div');
    const root = makeNode('section');
    root.querySelector = (selector) => {
        if (selector === '[data-opportunity-ai-list]') return list;
        if (selector === '[data-opportunity-ai-results]') return results;
        return null;
    };
    global.document = { createElement: (tagName) => makeNode(tagName) };

    try {
        const items = normalizeReadyItems([{
            catalog_id: 'ecosmart-ai', rank: 1, match_score: 30,
            why_fit: 'Dự án liên quan đến tư duy dữ liệu của bạn. Kỹ năng thiết kế hỗ trợ trải nghiệm người dùng. Hồ sơ còn thiếu thực hành thị giác máy tính. Dự án giúp xác định kỹ năng cần rèn luyện.',
            fit_reasons: ['Có tư duy dữ liệu.'],
            gap_reasons: ['Thiếu thực hành thị giác máy tính.'],
            skills_to_develop: ['Thị giác máy tính'],
            canonical_url: 'https://github.com/talenthub-demo/ecosmart-ai',
        }]);
        const view = createOpportunityMatchView(root);
        view.render('ready-model', { items });

        const card = list.children[0];
        const cta = card.children.at(-1).children[0];
        assert.equal(cta.tagName, 'BUTTON');
        assert.equal(cta.disabled, true);
    } finally {
        global.document = previousDocument;
    }
});

test('normalizer requires substantial project analysis and retains detailed sections', () => {
    const base = {
        catalog_id: 'p1', rank: 1, match_score: 38,
        why_fit: 'Dự án liên quan đến nền tảng hiện có. Hồ sơ cho thấy một số năng lực phù hợp. Bạn vẫn thiếu minh chứng thực tế quan trọng. Cơ hội này giúp rèn kỹ năng qua công việc cụ thể.',
        fit_reasons: ['Có nền tảng liên quan.'],
        gap_reasons: ['Chưa có minh chứng thực tế.'],
        skills_to_develop: ['Phân tích dữ liệu'],
        evidence: [], canonical_url: '/app/learner/project.php?id=50000000-0000-4000-8000-000000000001',
    };
    const normalized = normalizeReadyItems([base]);
    assert.deepEqual(normalized[0].fit_reasons, base.fit_reasons);
    assert.deepEqual(normalized[0].gap_reasons, base.gap_reasons);
    assert.deepEqual(normalized[0].skills_to_develop, base.skills_to_develop);
    assert.equal(normalizeReadyItems([{ ...base, why_fit: 'Quá ngắn.' }]), null);
    assert.equal(normalizeReadyItems([{ ...base, fit_reasons: [] }]), null);
});

test('renderer uses safe DOM APIs, score progressbar and detailed Gemini sections', () => {
    assert.match(source, /document\.createElement/);
    assert.match(source, /textContent/);
    assert.doesNotMatch(source, /\.innerHTML\s*=/);
    assert.doesNotMatch(source, /structured_score|gemini_score/);
    assert.match(source, /role', 'progressbar'/);
    assert.match(source, /learner-opportunity-ai-score__bar/);
    for (const label of ['Phân tích của Gemini', 'Tại sao phù hợp', 'Tại sao chưa phù hợp', 'Kỹ năng sẽ được học hỏi và rèn luyện', 'Xem dự án']) {
        assert.match(source, new RegExp(label));
    }
});

test('no-fit analysis humanizes skill codes for Vietnamese learners', () => {
    assert.equal(humanizeOpportunityLabel('creative_design'), 'Thiết kế sáng tạo');
    assert.equal(humanizeOpportunityLabel('data_analysis'), 'Phân tích dữ liệu');
    assert.equal(humanizeOpportunityLabel('new_student_skill'), 'New student skill');
});

test('prose-only no-fit state removes prepared chips and visible score formula', () => {
    assert.match(ecosystemSource, /data-opportunity-ai-analysis-explanation/);
    assert.doesNotMatch(ecosystemSource, /data-opportunity-ai-metric-weighting/);
    assert.doesNotMatch(ecosystemSource, /Cách tính điểm/);
    assert.doesNotMatch(ecosystemSource, /data-opportunity-ai-analysis-strengths/);
    assert.doesNotMatch(learnerCss, /learner-opportunity-ai__analysis-chip/);
    assert.match(ecosystemSource, /learner\.css\?v=/);
});

test('no-fit renderer shows Gemini prose without generated metrics or prepared tags', () => {
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
                headline: 'Chưa có dự án được đề xuất ở thời điểm hiện tại',
                explanation: 'Hồ sơ hiện chưa có đủ bằng chứng để Gemini đề xuất một dự án cụ thể. Bạn nên bổ sung sản phẩm thực tế rồi thực hiện phân tích lại.',
            },
        });

        assert.equal(fields.get('[data-opportunity-ai-analysis-headline]').textContent, 'Chưa có dự án được đề xuất ở thời điểm hiện tại');
        assert.match(fields.get('[data-opportunity-ai-analysis-explanation]').textContent, /chưa có đủ bằng chứng/);
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
