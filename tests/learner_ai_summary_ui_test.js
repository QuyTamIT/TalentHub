const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const modulePath = path.join(root, 'assets', 'js', 'learner-ai-summary.js');

function memoryStorage() {
  const values = new Map();
  return {
    getItem(key) { return values.has(key) ? values.get(key) : null; },
    setItem(key, value) { values.set(key, String(value)); },
    removeItem(key) { values.delete(key); },
  };
}

function recordingView() {
  const events = [];
  return {
    events,
    open() { events.push(['open']); },
    close() { events.push(['close']); },
    render(state, payload) { events.push([state, payload]); },
  };
}

test('auto analysis is activated only by the fixed query value', () => {
  const { shouldAutoAnalyze } = require(modulePath);
  assert.equal(shouldAutoAnalyze('?onboarding=completed&ai=analyze'), true);
  assert.equal(shouldAutoAnalyze('?ai=delete'), false);
  assert.equal(shouldAutoAnalyze('https://evil.example/?ai=analyze'), false);
});

test('summary API client receives the session CSRF token for generation', () => {
  const { createSummaryApiClient } = require(modulePath);
  assert.equal(typeof createSummaryApiClient, 'function');
  const client = { get() {}, send() {} };
  let options = null;
  const factory = (received) => {
    options = received;
    return client;
  };
  const suppliedDocument = {
    getElementById(id) {
      assert.equal(id, 'learner-session-boot');
      return { textContent: JSON.stringify({ csrfToken: 'csrf-browser-e2e-123' }) };
    },
  };

  assert.equal(createSummaryApiClient(factory, suppliedDocument), client);
  assert.deepEqual(options, {
    baseUrl: '/app/learner/api/v1',
    csrfToken: 'csrf-browser-e2e-123',
    timeoutMs: 45000,
  });
});

test('not-generated roadmap causes one in-flight POST with a stable idempotency key', async () => {
  const { createAiSummaryController } = require(modulePath);
  const calls = [];
  const view = recordingView();
  let release;
  const pending = new Promise((resolve) => { release = resolve; });
  const api = {
    async get(endpoint) { calls.push(['GET', endpoint]); return { state: 'not_generated' }; },
    async send(method, endpoint, body, options) {
      calls.push([method, endpoint, body, options]);
      await pending;
      return { state: 'ready_model', executive_summary: 'Bạn có tiềm năng xây dựng sản phẩm công nghệ.' };
    },
  };
  const controller = createAiSummaryController({ api, view, storage: memoryStorage(), createIdempotencyKey: () => 'roadmap-summary-key-0001' });
  const first = controller.run();
  const second = controller.run();
  release();
  const [one, two] = await Promise.all([first, second]);
  assert.equal(one, two);
  assert.equal(calls.filter((call) => call[0] === 'POST').length, 1);
  assert.deepEqual(calls[1], ['POST', '/ai-roadmap.php', { action: 'generate' }, { idempotencyKey: 'roadmap-summary-key-0001' }]);
  assert.equal(view.events.some(([state]) => state === 'ready_model'), true);
});

test('existing model roadmap is summarized without regenerating', async () => {
  const { createAiSummaryController } = require(modulePath);
  let posts = 0;
  const view = recordingView();
  const controller = createAiSummaryController({
    api: {
      async get() { return { state: 'ready_model', executive_summary: 'Tóm tắt đã lưu.' }; },
      async send() { posts += 1; },
    },
    view,
    storage: memoryStorage(),
  });
  await controller.run();
  assert.equal(posts, 0);
  assert.equal(view.events.at(-1)[0], 'ready_model');
});

test('fallback is labelled explicitly and retry is available after failure', async () => {
  const { createAiSummaryController } = require(modulePath);
  const fallbackView = recordingView();
  await createAiSummaryController({
    api: { async get() { return { state: 'fallback_rule', executive_summary: 'Gợi ý dự phòng.' }; }, async send() {} },
    view: fallbackView,
    storage: memoryStorage(),
  }).run();
  assert.equal(fallbackView.events.at(-1)[0], 'fallback_rule');

  let attempts = 0;
  const retryView = recordingView();
  const retryController = createAiSummaryController({
    api: {
      async get() { return { state: 'not_generated' }; },
      async send() { attempts += 1; if (attempts === 1) throw new Error('offline'); return { state: 'ready_model', executive_summary: 'Đã phục hồi.' }; },
    },
    view: retryView,
    storage: memoryStorage(),
    createIdempotencyKey: () => 'roadmap-summary-key-0002',
  });
  await retryController.run();
  assert.equal(retryView.events.at(-1)[0], 'error');
  await retryController.retry();
  assert.equal(attempts, 2);
  assert.equal(retryView.events.at(-1)[0], 'ready_model');
  retryController.defer();
  assert.equal(retryView.events.at(-1)[0], 'close');
});

test('stable engine failure rotates the idempotency key before retry', async () => {
  const { createAiSummaryController } = require(modulePath);
  const keys = [];
  let sequence = 0;
  const controller = createAiSummaryController({
    api: {
      async get() { return { state: 'not_generated' }; },
      async send(_method, _endpoint, _body, options) {
        keys.push(options.idempotencyKey);
        return keys.length === 1
          ? { state: 'engine_failure' }
          : { state: 'ready_model', executive_summary: 'Đã tạo lại.' };
      },
    },
    view: recordingView(),
    storage: memoryStorage(),
    createIdempotencyKey: () => `roadmap-summary-key-${String(++sequence).padStart(4, '0')}`,
  });
  await controller.run();
  await controller.retry();
  assert.deepEqual(keys, ['roadmap-summary-key-0001', 'roadmap-summary-key-0002']);
});

test('discover page contains an accessible AI summary modal and safe renderer', () => {
  const page = fs.readFileSync(path.join(root, 'app', 'learner', 'discover.php'), 'utf8');
  const source = fs.readFileSync(modulePath, 'utf8');
  for (const marker of [
    'data-ai-summary-modal', 'role="dialog"', 'aria-modal="true"',
    'data-ai-summary-live', 'aria-live="polite"', 'data-ai-summary-retry',
    'data-ai-summary-defer', 'ai-recommendations.php', 'learner-ai-summary.js',
  ]) assert.match(page, new RegExp(marker));
  assert.match(source, /textContent/);
  assert.doesNotMatch(source, /innerHTML/);
  assert.match(source, /LearnerUI\.openModal/);
});
