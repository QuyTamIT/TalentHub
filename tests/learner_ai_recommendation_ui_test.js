const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const modulePath = path.join(__dirname, '..', 'assets', 'js', 'learner-recommendations.js');

function createView() {
  return {
    states: [],
    evidence: [],
    feedbackFocusCount: 0,
    render(state, payload) { this.states.push({ state, payload }); },
    toggleEvidence(itemId) { this.evidence.push(itemId); },
    focusFeedback() { this.feedbackFocusCount += 1; },
  };
}

test('recommendation controller maps API states and retries a source failure', async () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const { createRecommendationController } = require(modulePath);
  const view = createView();
  let reads = 0;
  const api = {
    async get() {
      reads += 1;
      return reads === 1
        ? { state: 'source_unavailable', items: [] }
        : { state: 'ready_rule', engine_type: 'rule', items: [{ item_id: 'item-1', evidence: [] }] };
    },
    async send() { throw new Error('not needed'); },
  };
  const controller = createRecommendationController({ api, view, createIdempotencyKey: () => 'idempotency-key-0001' });

  await controller.load();
  assert.equal(view.states.at(-1).state, 'source-error');
  await controller.retry();
  assert.equal(reads, 2);
  assert.equal(view.states.at(-1).state, 'ready-rule');
});

test('recommendation controller reuses one idempotency key for an in-flight generation', async () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const { createRecommendationController } = require(modulePath);
  const view = createView();
  const calls = [];
  let release;
  const api = {
    async get() { return { state: 'not_generated', items: [] }; },
    send(method, endpoint, body, options) {
      calls.push({ method, endpoint, body, options });
      return new Promise((resolve) => { release = resolve; });
    },
  };
  const controller = createRecommendationController({ api, view, createIdempotencyKey: () => 'idempotency-key-0002' });

  const first = controller.generate();
  const second = controller.generate();
  assert.strictEqual(first, second);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].method, 'POST');
  assert.equal(calls[0].endpoint, '/recommendations.php');
  assert.equal(calls[0].options.idempotencyKey, 'idempotency-key-0002');
  release({ state: 'ready_rule', engine_type: 'rule', items: [] });
  await first;
  assert.equal(view.states.at(-1).state, 'ready-rule');
});

test('recommendation controller expands evidence and moves focus after feedback is saved', async () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const { createRecommendationController } = require(modulePath);
  const view = createView();
  const calls = [];
  const api = {
    async get() { return { state: 'ready_model', engine_type: 'model', items: [{ item_id: 'item-1', evidence: [] }] }; },
    async send(method, endpoint, body) {
      calls.push({ method, endpoint, body });
      return { feedback_id: 'feedback-1' };
    },
  };
  const controller = createRecommendationController({ api, view, createIdempotencyKey: () => 'idempotency-key-0003' });

  await controller.load();
  controller.expandEvidence('item-1');
  await controller.submitFeedback({ itemId: 'item-1', verdict: 'helpful', reasonCode: 'relevant' });

  assert.deepEqual(view.evidence, ['item-1']);
  assert.deepEqual(calls[0], {
    method: 'POST', endpoint: '/recommendation-feedback.php',
    body: { itemId: 'item-1', verdict: 'helpful', reasonCode: 'relevant' },
  });
  assert.equal(view.states.at(-1).state, 'feedback-saved');
  assert.equal(view.feedbackFocusCount, 1);
});

test('recommendation controller grants only missing consent scopes before generating', async () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const { createRecommendationController } = require(modulePath);
  const view = createView();
  const calls = [];
  const api = {
    async get() {
      return { state: 'consent_required', missing_consent_scopes: ['skills', 'assessment'], items: [] };
    },
    async send(method, endpoint, body, options) {
      calls.push({ method, endpoint, body, options });
      if (endpoint === '/recommendations.php') return { state: 'ready_rule', engine_type: 'rule', items: [] };
      return { id: `consent-${body.scope}` };
    },
  };
  const controller = createRecommendationController({ api, view, createIdempotencyKey: () => 'idempotency-key-0004' });

  await controller.load();
  await controller.grantMissingConsent(['assessment', 'skills', 'unknown', 'skills']);

  assert.deepEqual(calls, [
    { method: 'POST', endpoint: '/ai-consent.php', body: { scope: 'assessment', action: 'granted' }, options: undefined },
    { method: 'POST', endpoint: '/ai-consent.php', body: { scope: 'skills', action: 'granted' }, options: undefined },
    { method: 'POST', endpoint: '/recommendations.php', body: undefined, options: { idempotencyKey: 'idempotency-key-0004' } },
  ]);
  assert.equal(view.states.at(-1).state, 'ready-rule');
});

test('presentation state exposes every stable recommendation response state', () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const { presentationState } = require(modulePath);
  assert.deepEqual([
    presentationState({ state: 'pending' }),
    presentationState({ state: 'consent_required' }),
    presentationState({ state: 'insufficient_data' }),
    presentationState({ state: 'source_unavailable' }),
    presentationState({ state: 'ready_rule' }),
    presentationState({ state: 'ready_model' }),
    presentationState({ state: 'fallback_rule' }),
  ], [
    'loading', 'consent-required', 'insufficient-data', 'source-error', 'ready-rule', 'ready-model', 'fallback-rule',
  ]);
});

test('recommendation client renders untrusted response strings with textContent only', () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.equal(source.includes('.innerHTML'), false);
  assert.equal(source.includes('.textContent'), true);
  assert.equal(source.includes('dataset.aiReportUnsafe'), true);
});
