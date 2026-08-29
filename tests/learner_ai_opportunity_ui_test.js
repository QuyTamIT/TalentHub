'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const {
    createOpportunityMatchController,
    mapOpportunityMatchState,
    isSafeInternalOpportunityUrl,
} = require('../assets/js/learner-opportunity-matches.js');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets/js/learner-opportunity-matches.js'), 'utf8');

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
                    { catalog_id: 'p1', rank: 1, match_score: 92, why_fit: 'Python và IoT', matched_skills: [], missing_skills: [], expected_outcomes: [], evidence: [], canonical_url: '/app/learner/opportunity.php?id=p1' },
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
