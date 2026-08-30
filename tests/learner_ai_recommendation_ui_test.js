const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const modulePath = path.join(__dirname, '..', 'assets', 'js', 'learner-recommendations.js');
const pagePath = path.join(__dirname, '..', 'app', 'learner', 'ai-recommendations.php');

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

test('recommendation source failures preserve a safe retryable error code', async () => {
  const { createRecommendationController } = require(modulePath);
  const view = createView();
  const api = {
    async get() {
      const error = new Error('hidden internal detail');
      error.code = 'SERVICE_UNAVAILABLE';
      error.status = 503;
      error.requestId = 'request-123';
      throw error;
    },
    async send() { throw new Error('not needed'); },
  };
  await createRecommendationController({ api, view }).load();
  assert.equal(view.states.at(-1).state, 'source-error');
  assert.equal(view.states.at(-1).payload.error_code, 'SERVICE_UNAVAILABLE');
  assert.doesNotMatch(JSON.stringify(view.states.at(-1).payload), /hidden internal detail/);
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
    presentationState({ state: 'stale_model' }),
    presentationState({ state: 'fallback_rule' }),
  ], [
    'loading', 'consent-required', 'insufficient-data', 'source-error', 'ready-rule', 'ready-model', 'stale-model', 'fallback-rule',
  ]);
});

test('recommendation client renders untrusted response strings with textContent only', () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner recommendation client must exist');
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.equal(source.includes('.innerHTML'), false);
  assert.equal(source.includes('.textContent'), true);
  assert.equal(source.includes('dataset.aiReportUnsafe'), true);
});

test('recommendation client renders a registration link for career activities', () => {
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.equal(source.includes("register_activity"), true);
  assert.equal(source.includes("activity-detail.php?id="), true);
  assert.equal(source.includes("activity_source_id"), true);
});

test('recommendation click tracker sends a same-origin keepalive request without awaiting it', () => {
  const { createRecommendationClickTracker } = require(modulePath);
  const calls = [];
  const tracker = createRecommendationClickTracker({
    csrfToken: 'csrf-token-1',
    fetchImpl(endpoint, options) {
      calls.push({ endpoint, options });
      return Promise.reject(new Error('expired CSRF or offline'));
    },
  });

  const result = tracker.track({
    itemId: 'item-1',
    catalogId: 'catalog-1',
    actionType: 'open_catalog_item',
  });

  assert.equal(result, undefined, 'telemetry must not return a navigation-blocking promise');
  assert.equal(calls.length, 1);
  assert.equal(calls[0].endpoint, '/app/learner/api/v1/recommendation-click.php');
  assert.equal(calls[0].options.method, 'POST');
  assert.equal(calls[0].options.credentials, 'same-origin');
  assert.equal(calls[0].options.keepalive, true);
  assert.equal(calls[0].options.headers['X-CSRF-Token'], 'csrf-token-1');
  assert.deepEqual(JSON.parse(calls[0].options.body), {
    itemId: 'item-1', catalogId: 'catalog-1', actionType: 'open_catalog_item',
  });
});

test('recommendation click tracker ignores invalid input and synchronous transport failures', () => {
  const { createRecommendationClickTracker } = require(modulePath);
  let calls = 0;
  const tracker = createRecommendationClickTracker({
    fetchImpl() {
      calls += 1;
      throw new Error('transport unavailable');
    },
  });

  assert.doesNotThrow(() => tracker.track({ itemId: 'item-1', actionType: 'view_activity' }));
  assert.doesNotThrow(() => tracker.track({ itemId: '', actionType: 'view_activity' }));
  assert.equal(calls, 1, 'invalid payload must not be sent');
});

test('recommendation CTA click handling does not cancel browser navigation', () => {
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.match(source, /data-ai-recommendation-cta/);
  assert.match(source, /clickTracker\.track/);
  assert.doesNotMatch(source, /preventDefault\s*\(/);
});

test('AI page version-stamps every runtime JavaScript asset', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  assert.match(page, /\$assetVersion\s*=\s*static\s+function/);
  for (const asset of ['learner-api.js', 'learner.js', 'learner-ai-roadmap.js']) {
    assert.match(page, new RegExp(`${asset.replace('.', '\\.') }\\?v=<\\?= \\$assetVersion\\(`));
  }
});

test('AI page mounts the live recommendation catalog beside the roadmap', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  assert.match(page, /data-ai-page/);
  assert.match(page, /data-ai-source-error/);
  assert.match(page, /data-ai-results/);
  assert.match(page, /learner-recommendations\.js/);
});

test('recommendations group current item types without inventing a roadmap', () => {
  const { recommendationSection } = require(modulePath);
  assert.equal(recommendationSection('strength'), 'strength');
  assert.equal(recommendationSection('activity'), 'activity');
  assert.equal(recommendationSection('roadmap'), 'other');

  const source = fs.readFileSync(modulePath, 'utf8');
  assert.match(source, /Điểm mạnh nổi bật/);
  assert.match(source, /Hoạt động phù hợp/);
  assert.match(source, /Gợi ý khác/);
  assert.doesNotMatch(source, /Lộ trình 3 tháng/);
});

test('recommendation client stays regression-covered beside the Roadmap-first experience', () => {
  const source = fs.readFileSync(modulePath, 'utf8');
  const page = fs.readFileSync(pagePath, 'utf8');

  assert.doesNotMatch(source, /Rule baseline/);
  assert.match(source, /document\.createElement\('details'\)/);
  assert.match(source, /payload\?\.provider/);
  assert.match(source, /payload\?\.model_version/);
  assert.match(page, /LỘ TRÌNH PHÁT TRIỂN 90 NGÀY/);
  assert.match(page, /learner-recommendations\.js/);
  assert.match(page, /learner-ai-roadmap\.js/);
});

test('feedback confirmation preserves the engine label from the current payload', () => {
  const { engineLabel } = require(modulePath);

  assert.equal(engineLabel('feedback-saved', { engine_type: 'model' }), 'Gợi ý từ mô hình AI');
  assert.equal(engineLabel('feedback-saved', { state: 'fallback_rule' }), 'Gợi ý dự phòng theo quy tắc');
});

test('current hero payload renders two activities and one strength with no empty group', () => {
  const { createDomView } = require(modulePath);
  class FakeElement {
    constructor() { this.children = []; this.dataset = {}; this.attributes = {}; this.hidden = false; }
    get firstChild() { return this.children[0] || null; }
    append(...children) { this.children.push(...children); }
    appendChild(child) { this.children.push(child); return child; }
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); }
    setAttribute(name, value) { this.attributes[name] = value; }
    focus() {}
  }
  const list = new FakeElement();
  const root = { querySelector: (selector) => selector === '[data-ai-result-list]' ? list : null };
  const originalDocument = global.document;
  global.document = { createElement: () => new FakeElement() };

  try {
    createDomView(root).render('ready-rule', {
      state: 'ready_rule',
      items: [
        {
          item_id: 'activity-1', item_type: 'activity', title: 'Hoạt động 1',
          action: { type: 'register_activity', activity_source_id: '123e4567-e89b-12d3-a456-426614174000' },
        },
        { item_id: 'strength-1', item_type: 'strength', title: 'Điểm mạnh 1' },
        { item_id: 'activity-2', item_type: 'activity', title: 'Hoạt động 2' },
      ],
    });
  } finally {
    global.document = originalDocument;
  }

  assert.deepEqual(list.children.map((section) => section.children[0].children[0].textContent), [
    'Điểm mạnh nổi bật', 'Hoạt động phù hợp',
  ]);
  assert.deepEqual(list.children.map((section) => section.children[1].children.length), [1, 2]);
  const activityCard = list.children[1].children[1].children[0];
  const actionLink = activityCard.children.find((child) => child.dataset?.aiRecommendationCta === 'true');
  assert.equal(actionLink.dataset.aiRecommendationItem, 'activity-1');
  assert.equal(actionLink.dataset.aiRecommendationAction, 'register_activity');
});

test('enterprise opportunity card binds canonical title and CTA to its catalog id', () => {
  const { createDomView } = require(modulePath);
  class FakeElement {
    constructor() { this.children = []; this.dataset = {}; this.attributes = {}; this.hidden = false; }
    get firstChild() { return this.children[0] || null; }
    append(...children) { this.children.push(...children); }
    appendChild(child) { this.children.push(child); return child; }
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); }
    setAttribute(name, value) { this.attributes[name] = value; }
    focus() {}
  }
  const list = new FakeElement();
  const root = { querySelector: (selector) => selector === '[data-ai-result-list]' ? list : null };
  const originalDocument = global.document;
  global.document = { createElement: () => new FakeElement() };

  try {
    createDomView(root).render('ready-model', {
      state: 'ready_model',
      items: [{
        item_id: 'recommendation-1', item_type: 'activity', title: 'Tên AI tự đặt', catalog_id: 'internship-b',
        action: { type: 'register_activity', activity_source_id: '123e4567-e89b-12d3-a456-426614174000' },
        evidence: [
          { source_type: 'catalog', source_id: 'project-a', safe_value: { title: 'Sai nguồn', item_type: 'project', url: '/wrong' } },
          { source_type: 'opportunity', source_id: 'internship-b', safe_value: { title: 'Kỹ sư AI thực tập', opportunity_type: 'internship', url: '/app/learner/ecosystem.php?tab=opportunities&focus=internship-b#opportunity-internship-b' } },
        ],
      }],
    });
  } finally {
    global.document = originalDocument;
  }

  const card = list.children[0].children[1].children[0];
  assert.equal(card.children[0].textContent, 'Kỹ sư AI thực tập');
  const actionLink = card.children.find((child) => child.dataset?.aiRecommendationCta === 'true');
  assert.equal(actionLink.href, '/app/learner/ecosystem.php?tab=opportunities&focus=internship-b#opportunity-internship-b');
  assert.equal(actionLink.textContent, 'Xem trong Hệ sinh thái');
  assert.equal(actionLink.dataset.aiRecommendationCatalog, 'internship-b');
  assert.equal(actionLink.dataset.aiRecommendationAction, 'view_opportunity');
});

test('project recommendation card renders external project url and Khám phá dự án CTA', () => {
  const { createDomView } = require(modulePath);
  class FakeElement {
    constructor() { this.children = []; this.dataset = {}; this.attributes = {}; this.hidden = false; }
    get firstChild() { return this.children[0] || null; }
    append(...children) { this.children.push(...children); }
    appendChild(child) { this.children.push(child); return child; }
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); }
    setAttribute(name, value) { this.attributes[name] = value; }
    focus() {}
  }
  const list = new FakeElement();
  const root = { querySelector: (selector) => selector === '[data-ai-result-list]' ? list : null };
  const originalDocument = global.document;
  global.document = { createElement: () => new FakeElement() };

  try {
    createDomView(root).render('ready-model', {
      state: 'ready_model',
      items: [{
        item_id: 'rec-project-1', item_type: 'activity', title: 'EcoSmart AI', catalog_id: '50000000-0000-4000-8000-000000000001',
        action: { type: 'open_catalog_item', catalog_id: '50000000-0000-4000-8000-000000000001' },
        evidence: [
          {
            source_type: 'catalog',
            source_id: '50000000-0000-4000-8000-000000000001',
            safe_value: {
              title: 'Ứng dụng AI phân loại rác & Tái chế thông minh trong học đường (EcoSmart AI)',
              item_type: 'project',
              url: 'https://github.com/talenthub-demo/ecosmart-ai',
            },
          },
        ],
      }],
    });
  } finally {
    global.document = originalDocument;
  }

  const card = list.children[0].children[1].children[0];
  assert.equal(card.children[0].textContent, 'Ứng dụng AI phân loại rác & Tái chế thông minh trong học đường (EcoSmart AI)');
  const actionLink = card.children.find((child) => child.dataset?.aiRecommendationCta === 'true');
  assert.equal(actionLink.href, 'https://github.com/talenthub-demo/ecosmart-ai');
  assert.equal(actionLink.textContent, 'Khám phá dự án');
  assert.equal(actionLink.target, '_blank');
  assert.equal(actionLink.dataset.aiRecommendationCatalog, '50000000-0000-4000-8000-000000000001');
  assert.equal(actionLink.dataset.aiRecommendationAction, 'open_catalog_item');
});
