const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const modulePath = path.join(__dirname, '..', 'assets', 'js', 'learner-assessment.js');

function createMockView() {
  return {
    states: [],
    renderedPayloads: [],
    render(state, payload) {
      this.states.push(state);
      this.renderedPayloads.push(payload);
    },
  };
}

function createDomNode(tagName = 'div', hidden = false) {
  const listeners = {};
  const children = [];
  return {
    tagName: String(tagName).toUpperCase(),
    hidden,
    disabled: false,
    textContent: '',
    className: '',
    dataset: {},
    children,
    listeners,
    classList: {
      add() {},
      remove() {},
      toggle() {},
    },
    style: { setProperty() {} },
    get firstChild() { return children[0] || null; },
    appendChild(child) { children.push(child); return child; },
    append(...items) { children.push(...items); },
    removeChild(child) {
      const index = children.indexOf(child);
      if (index >= 0) children.splice(index, 1);
      return child;
    },
    replaceChildren(...items) { children.splice(0, children.length, ...items); },
    setAttribute(name, value) { this[name] = String(value); },
    addEventListener(type, listener) { listeners[type] = listener; },
    dispatch(type, event = {}) { return listeners[type]?.(event); },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    closest() { return null; },
  };
}

function createCatalogHarness() {
  const nodes = {
    loading: createDomNode('div', false),
    empty: createDomNode('div', true),
    error: createDomNode('div', true),
    cards: createDomNode('div', false),
    catalogRetry: createDomNode('button'),
    historyWarning: createDomNode('div', true),
    historyRetry: createDomNode('button'),
    bandConfirmation: createDomNode('div', true),
    bandConfirm: createDomNode('button'),
    bandError: createDomNode('p', true),
    catalogMiddle: Object.assign(createDomNode('input'), { value: 'middle', checked: false }),
    catalogHigh: Object.assign(createDomNode('input'), { value: 'high', checked: false }),
    catalogCollege: Object.assign(createDomNode('input'), { value: 'college', checked: false }),
    talents: createDomNode('div'),
    career: createDomNode('div'),
    completedCount: createDomNode('strong'),
    latestDate: createDomNode('strong'),
    progressMeter: createDomNode('div'),
    progressBar: createDomNode('span'),
  };
  const rootSelectors = {
    '[data-catalog-loading]': nodes.loading,
    '[data-empty-catalog]': nodes.empty,
    '[data-catalog-error]': nodes.error,
    '[data-catalog-cards]': nodes.cards,
    '[data-catalog-retry]': nodes.catalogRetry,
    '[data-catalog-history-warning]': nodes.historyWarning,
    '[data-catalog-history-retry]': nodes.historyRetry,
    '[data-catalog-band-confirmation]': nodes.bandConfirmation,
    '[data-catalog-band-confirm]': nodes.bandConfirm,
    '[data-catalog-band-error]': nodes.bandError,
  };
  const documentSelectors = {
    '[data-discovery-talents]': nodes.talents,
    '[data-discovery-career]': nodes.career,
    '[data-discovery-completed-count]': nodes.completedCount,
    '[data-discovery-latest-date]': nodes.latestDate,
    '[data-discovery-progress]': nodes.progressMeter,
    '[data-discovery-progress-bar]': nodes.progressBar,
  };
  const doc = {
    createElement: (tag) => createDomNode(tag),
    createElementNS: (_namespace, tag) => createDomNode(tag),
    querySelector: (selector) => documentSelectors[selector] || null,
    querySelectorAll: () => [],
    getElementById: () => null,
  };
  const root = createDomNode('section');
  root.ownerDocument = doc;
  root.querySelector = (selector) => {
    if (selector === '[name="catalog_education_band"]:checked') {
      return [nodes.catalogMiddle, nodes.catalogHigh, nodes.catalogCollege].find((input) => input.checked) || null;
    }
    return rootSelectors[selector] || null;
  };
  return { root, nodes, doc };
}

function createRunnerHarness() {
  const nodes = {
    error: createDomNode('div', true),
    errorMessage: createDomNode('p'),
    retry: createDomNode('button'),
    bandModal: createDomNode('div', true),
    bandConfirm: createDomNode('button'),
    bandError: createDomNode('p', true),
    start: createDomNode('button'),
    submit: createDomNode('button'),
    middle: Object.assign(createDomNode('input'), { value: 'middle', checked: false }),
    high: Object.assign(createDomNode('input'), { value: 'high', checked: false }),
    college: Object.assign(createDomNode('input'), { value: 'college', checked: false }),
    introName: createDomNode('span'),
  };
  const rootSelectors = {
    '[data-assessment-error]': nodes.error,
    '[data-assessment-error-message]': nodes.errorMessage,
    '[data-assessment-retry]': nodes.retry,
    '[data-assessment-start]': nodes.start,
    '[data-assessment-intro-name]': nodes.introName,
  };
  const documentSelectors = {
    '[data-assessment-band-confirmation]': nodes.bandModal,
    '[data-confirm-band]': nodes.bandConfirm,
    '[data-assessment-band-error]': nodes.bandError,
    '[data-assessment-submit]': nodes.submit,
  };
  const doc = {
    createElement: (tag) => createDomNode(tag),
    querySelector: (selector) => {
      if (selector === '[name="education_band"]:checked') {
        return [nodes.middle, nodes.high, nodes.college].find((input) => input.checked) || null;
      }
      return documentSelectors[selector] || null;
    },
    querySelectorAll: () => [],
    getElementById: () => null,
  };
  const root = createDomNode('main');
  root.ownerDocument = doc;
  root.dataset.assessmentCode = 'holland';
  root.querySelector = (selector) => rootSelectors[selector] || null;
  return { root, nodes, doc };
}

test('learner-assessment module exists and exports createAssessmentController', () => {
  assert.equal(fs.existsSync(modulePath), true, 'learner-assessment.js must exist');
  const mod = require(modulePath);
  assert.equal(typeof mod.createAssessmentController, 'function', 'createAssessmentController must be exported');
});

test('authoritative scores and storage are never managed in the browser', () => {
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.equal(source.includes('scoreHolland'), false, 'scoreHolland must be removed');
  assert.equal(source.includes('localStorage'), false, 'localStorage must be removed');
});

test('untrusted text is rendered with textContent and not innerHTML', () => {
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.equal(source.includes('.innerHTML'), false, 'innerHTML must not be used in the assessment controller');
  assert.equal(source.includes('.textContent'), true, 'textContent must be used for safe text rendering');
});

test('presentation states include all required states', () => {
  const mod = require(modulePath);
  assert.equal(typeof mod.presentationState, 'function', 'presentationState helper should be available');

  assert.equal(mod.presentationState({ status: 'loading' }), 'loading');
  assert.equal(mod.presentationState({ status: 'ready' }), 'ready');
  assert.equal(mod.presentationState({ status: 'saving' }), 'saving');
  assert.equal(mod.presentationState({ status: 'save-error' }), 'save-error');
  assert.equal(mod.presentationState({ status: 'submitting' }), 'submitting');
  assert.equal(mod.presentationState({ status: 'validation-error' }), 'validation-error');
  assert.equal(mod.presentationState({ status: 'expired' }), 'expired');
  assert.equal(mod.presentationState({ status: 'source-error' }), 'source-error');
  assert.equal(mod.presentationState({ status: 'complete' }), 'complete');
});

test('controller loads catalog by education band', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const calls = [];
  const api = {
    async get(endpoint) {
      calls.push({ method: 'GET', endpoint });
      return {
        student_id: 'student-1',
        education_band: 'high',
        assessments: [
          { code: 'holland', name: 'Holland', status: 'published' },
        ],
      };
    },
    async send() {},
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-001',
  });

  const catalog = await controller.loadCatalog('high');
  assert.equal(calls.length, 1);
  assert.equal(calls[0].endpoint, '/assessments.php?band=high');
  assert.equal(catalog.education_band, 'high');
  assert.equal(view.states.at(-1), 'ready');
});

test('controller starts or resumes an attempt and loads questions', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const calls = [];
  const attemptData = {
    id: 'attempt-1111',
    student_id: 'student-1',
    assessment_code: 'holland',
    version_id: 'version-1',
    status: 'in_progress',
    answers: {},
  };
  const detailData = {
    assessment: { code: 'holland', name: 'Holland High' },
    questions: [
      { id: 'q-1', prompt: 'Question 1', options: [{ value: 1, label: '1' }] },
      { id: 'q-2', prompt: 'Question 2', options: [{ value: 1, label: '1' }] },
    ],
    history: [],
  };

  const api = {
    async get(endpoint) {
      calls.push({ method: 'GET', endpoint });
      return detailData;
    },
    async send(method, endpoint, body) {
      calls.push({ method, endpoint, body });
      return attemptData;
    },
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-002',
  });

  const attempt = await controller.startOrResume('holland', 'high');
  assert.equal(attempt.id, 'attempt-1111');
  assert.equal(calls[0].endpoint, '/assessment-attempts.php');
  assert.deepEqual(calls[0].body, { assessmentCode: 'holland', educationBand: 'high' });
  assert.equal(view.states.at(-1), 'ready');
});

test('question-loading source failure remains a source error and can be retried', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  let questionLoads = 0;
  const controller = createAssessmentController({
    api: {
      async send() {
        return { id: 'attempt-source-failure', status: 'in_progress' };
      },
      async get() {
        questionLoads += 1;
        throw Object.assign(new Error('questions unavailable'), { code: 'SOURCE_FAILURE' });
      },
    },
    view,
  });

  const result = await controller.startOrResume('holland', 'high');
  assert.equal(result.status, 'source-error');
  assert.equal(view.states.at(-1), 'source-error');
  await controller.retry();
  assert.equal(questionLoads, 2, 'retry repeats the failed question-loading action');
  assert.equal(view.states.at(-1), 'source-error');
});

test('autosave coalesces repeated changes for one question', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const calls = [];
  let releasePatch;

  const api = {
    async get() { return {}; },
    send(method, endpoint, body) {
      calls.push({ method, endpoint, body });
      return new Promise((resolve) => { releasePatch = resolve; });
    },
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-003',
  });

  controller.setAttempt({
    id: 'attempt-1111',
    status: 'in_progress',
    answers: {},
  });

  const first = controller.saveAnswer('q-1', 4);
  const second = controller.saveAnswer('q-1', 5);

  assert.strictEqual(first, second);
  assert.equal(calls.length, 1);
  assert.deepEqual(calls[0].body, {
    attemptId: 'attempt-1111',
    questionId: 'q-1',
    answer: 4,
  });

  releasePatch({ id: 'attempt-1111', answers: { 'q-1': 4 } });
  await first;
  assert.equal(view.states.at(-1), 'ready');
});

test('autosave persists the latest answer when a question changes in flight', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const calls = [];
  const releases = [];
  const api = {
    async get() { return {}; },
    send(method, endpoint, body) {
      calls.push({ method, endpoint, body });
      return new Promise((resolve) => releases.push(resolve));
    },
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-latest',
  });
  controller.setAttempt({ id: 'attempt-latest', status: 'in_progress', answers: {} });

  const save = controller.saveAnswer('q-latest', 4);
  controller.saveAnswer('q-latest', 5);
  assert.equal(calls.length, 1);

  releases.shift()({ answers: { 'q-latest': 4 } });
  await save;
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(calls.length, 2);
  assert.equal(calls[1].body.answer, 5);

  releases.shift()({ answers: { 'q-latest': 5 } });
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(controller.getAttempt().answers['q-latest'], 5);
});

test('submit reuses one idempotency key while in flight', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const calls = [];
  let releaseSubmit;

  const api = {
    async get() { return {}; },
    send(method, endpoint, body, options) {
      calls.push({ method, endpoint, body, options });
      return new Promise((resolve) => { releaseSubmit = resolve; });
    },
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'assessment-submit-key-0001',
  });

  controller.setAttempt({
    id: 'attempt-2222',
    status: 'in_progress',
    answers: { 'q-1': 5, 'q-2': 4 },
  });

  const first = controller.submit();
  const second = controller.submit();

  assert.strictEqual(first, second);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].method, 'POST');
  assert.equal(calls[0].endpoint, '/assessment-submit.php');
  assert.equal(calls[0].options?.idempotencyKey, 'assessment-submit-key-0001');

  releaseSubmit({
    id: 'attempt-2222',
    status: 'submitted',
    result: { id: 'result-1', result_code: 'RIA', summary: 'Holland result' },
  });

  const result = await first;
  assert.equal(result.status, 'submitted');
  assert.equal(view.states.at(-1), 'complete');
});

test('controller maps expired attempt to expired state', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const api = {
    async get(endpoint) {
      return {
        id: 'attempt-3333',
        status: 'expired',
        expires_at: '2026-01-01T00:00:00Z',
      };
    },
    async send() {},
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-004',
  });

  await controller.loadAttempt('attempt-3333');
  assert.equal(view.states.at(-1), 'expired');
});

test('controller maps API source failure to source-error and allows retry', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  let attempts = 0;
  const api = {
    async get(endpoint) {
      attempts += 1;
      if (attempts === 1) {
        const error = new Error('Network error');
        error.status = 500;
        error.code = 'SOURCE_FAILURE';
        throw error;
      }
      return {
        id: 'attempt-4444',
        status: 'in_progress',
        questions: [{ id: 'q-1', prompt: 'Q1' }],
        answers: {},
      };
    },
    async send() {},
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-005',
  });

  await controller.loadAttempt('attempt-4444');
  assert.equal(view.states.at(-1), 'source-error');

  await controller.retry();
  assert.equal(attempts, 2);
  assert.equal(view.states.at(-1), 'ready');
});

test('controller maps validation errors on submit to validation-error', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const api = {
    async get() { return {}; },
    async send() {
      const error = new Error('Incomplete answers');
      error.status = 422;
      error.code = 'VALIDATION_FAILED';
      error.details = [{ field: 'attemptId', code: 'INCOMPLETE_ANSWERS' }];
      throw error;
    },
  };

  const controller = createAssessmentController({
    api,
    view,
    createIdempotencyKey: () => 'test-idemp-006',
  });

  controller.setAttempt({
    id: 'attempt-5555',
    status: 'in_progress',
    answers: {},
  });

  await controller.submit();
  assert.equal(view.states.at(-1), 'validation-error');
});

test('controller loads the latest server result without browser scoring', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const api = {
    async get(endpoint) {
      assert.equal(endpoint, '/assessments.php?code=disc');
      return {
        assessment: { code: 'disc', name: 'DISC' },
        questions: [],
        history: [
          {
            id: 'attempt-disc-1',
            status: 'submitted',
            result_code: 'DISC',
            summary: 'Server result',
            dimension_scores: { D: 80, I: 60, S: 40, C: 20 },
          },
        ],
      };
    },
    async send() {},
  };
  const controller = createAssessmentController({ api, view });
  const payload = await controller.loadResult('disc', '', 'attempt-disc-1');
  assert.equal(payload.result.result_code, 'DISC');
  assert.deepEqual(controller.getResult().dimension_scores, { D: 80, I: 60, S: 40, C: 20 });
  assert.equal(view.states.at(-1), 'complete');
});

test('history loading uses the dedicated read endpoint without changing the primary result state', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const calls = [];
  const response = {
    assessment_history: { source: 'assessment_engine', items: [{ id: 'attempt-1' }] },
    teacher_evaluations: { source: 'teacher_published_evaluation', items: [{ id: 'evaluation-1' }] },
  };
  const controller = createAssessmentController({
    api: {
      async get(endpoint) { calls.push(endpoint); return response; },
      async send() {},
    },
    view,
  });

  const payload = await controller.loadHistory();
  assert.deepEqual(calls, ['/assessments.php?view=history']);
  assert.equal(payload, response);
  assert.deepEqual(view.states, [], 'history loading must not overwrite the primary result presentation state');
});

test('history source failure rejects so its own error sections render instead of empty collections', async () => {
  const { createAssessmentController } = require(modulePath);
  const view = createMockView();
  const sourceFailure = Object.assign(new Error('history unavailable'), { code: 'SOURCE_FAILURE' });
  const controller = createAssessmentController({
    api: {
      async get() { throw sourceFailure; },
      async send() {},
    },
    view,
  });

  await assert.rejects(controller.loadHistory(), sourceFailure);
  assert.deepEqual(view.states, [], 'history failure must not hide or replace the primary assessment result');
});

test('retake lock preserves the latest stored result action', () => {
  const { mergeCatalogWithHistory } = require(modulePath);
  const merged = mergeCatalogWithHistory(
    { assessments: [{ code: 'disc', attempt_status: 'retake_locked', next_retake_at: '2026-11-09T00:00:00Z' }] },
    { assessment_history: { items: [{ id: 'attempt-1', assessment_type: 'disc', status: 'submitted', result_code: 'CDIS', dimension_scores: { D: 25, I: 25, S: 25, C: 100 } }] } },
  );
  assert.equal(merged.assessments[0].latest_result.result_code, 'CDIS');
  assert.equal(merged.assessments[0].can_view_result, true);
});

test('discovery summary uses stored assessment dimensions', () => {
  const { deriveDiscoverySummary } = require(modulePath);
  const summary = deriveDiscoverySummary({ assessment_history: { items: [
    { assessment_type: 'holland', dimension_scores: { R: 25, I: 25, A: 100, S: 25, E: 25, C: 50 } },
    { assessment_type: 'multiple_intelligence', dimension_scores: { LING: 25, LOGI: 100, SPAT: 25, BODY: 25, MUSIC: 25, INTER: 25, INTRA: 25, NAT: 25 } },
  ] } });
  assert.equal(summary.career.find(item => item.code === 'A').score, 100);
  assert.equal(summary.talents.find(item => item.code === 'LOGI').score, 100);
});

test('discovery progress counts distinct submitted assessments and keeps the latest database timestamp', () => {
  const { deriveDiscoveryProgress } = require(modulePath);
  assert.equal(typeof deriveDiscoveryProgress, 'function');
  const progress = deriveDiscoveryProgress({ assessment_history: { items: [
    { assessment_type: 'holland', status: 'submitted', submitted_at: '2026-08-20T09:00:00Z' },
    { assessment_type: 'holland', status: 'submitted', submitted_at: '2026-08-21T09:00:00Z' },
    { assessment_type: 'mbti', status: 'submitted', submitted_at: '2026-08-22T11:30:00Z' },
    { assessment_type: 'disc', status: 'in_progress', submitted_at: '2026-08-23T11:30:00Z' },
  ] } });

  assert.deepEqual(progress, {
    completed: 2,
    total: 4,
    percent: 50,
    latestSubmittedAt: '2026-08-22T11:30:00Z',
  });
});

test('offset-less MySQL assessment timestamps are normalized to the application timezone', () => {
  const { normalizeDiscoveryDate } = require(modulePath);
  assert.equal(typeof normalizeDiscoveryDate, 'function');
  assert.equal(
    normalizeDiscoveryDate('2026-08-24 23:30:00').toISOString(),
    '2026-08-24T16:30:00.000Z',
  );
  assert.equal(
    normalizeDiscoveryDate('2026-08-24T23:30:00+07:00').toISOString(),
    '2026-08-24T16:30:00.000Z',
  );
});

test('catalog renders database result badge, completion status, and an accessible talent radar', async () => {
  const { bootCatalog } = require(modulePath);
  const { root, nodes } = createCatalogHarness();
  await bootCatalog(root, {
    async get(endpoint) {
      if (endpoint === '/assessments.php') {
        return {
          education_band: 'college',
          assessments: [
            { code: 'holland', name: 'Holland', status: 'published' },
            { code: 'multiple_intelligence', name: 'Đa trí thông minh', status: 'published' },
          ],
        };
      }
      return { assessment_history: { items: [
        {
          id: 'attempt-holland',
          assessment_type: 'holland',
          status: 'submitted',
          result_code: 'ACR',
          submitted_at: '2026-08-21T09:00:00Z',
          dimension_scores: { R: 40, I: 35, A: 80, S: 60, E: 55, C: 45 },
        },
        {
          id: 'attempt-mi',
          assessment_type: 'multiple_intelligence',
          status: 'submitted',
          result_code: 'LOGI · LING · SPAT',
          submitted_at: '2026-08-22T09:00:00Z',
          dimension_scores: { LING: 75, LOGI: 90, SPAT: 80, BODY: 55, MUSIC: 40, INTER: 65, INTRA: 70, NAT: 50 },
        },
      ] } };
    },
  });

  const resultBadge = nodes.cards.children[0].children.find((child) => child.className === 'learner-assessment-card__result');
  assert.equal(resultBadge?.textContent, 'ACR');
  assert.equal(nodes.completedCount.textContent, '2/4 bài đánh giá');
  assert.equal(nodes.latestDate.textContent, '22/08/2026');
  assert.equal(nodes.progressMeter['aria-valuenow'], '50');
  assert.equal(nodes.talents.children[0]?.tagName, 'SVG');
  assert.equal(nodes.talents.children[0]?.role, 'img');
  assert.match(nodes.talents.children[0]?.['aria-label'] || '', /Logic — Toán học 90/);
});

test('assessment state styles explicitly hide hidden catalog content', () => {
  const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'css', 'learner.css'), 'utf8');
  assert.equal(source.includes('.learner-assessment-state[hidden],\n.learner-empty-catalog[hidden] {\n    display: none !important;\n}'), true);
});

test('discover page exposes distinct catalog and history errors with retry controls', () => {
  const source = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'discover.php'), 'utf8');
  for (const marker of [
    'data-catalog-error',
    'data-catalog-retry',
    'data-catalog-history-warning',
    'data-catalog-history-retry',
  ]) {
    assert.match(source, new RegExp(marker), `discover page contains ${marker}`);
  }
});

test('discover page exposes an accessible education-band confirmation state', () => {
  const source = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'discover.php'), 'utf8');
  for (const marker of [
    'data-catalog-band-confirmation',
    'data-catalog-band-confirm',
    'data-catalog-band-error',
    'name="catalog_education_band"',
    'value="middle"',
    'value="high"',
    'value="college"',
    'aria-labelledby="catalog-band-title"',
    'role="radiogroup"',
    'class="learner-visually-hidden"',
  ]) {
    assert.match(source, new RegExp(marker), `discover band chooser contains ${marker}`);
  }
});

test('assessment history action preserves an explicitly confirmed education band', () => {
  const source = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'assessment.php'), 'utf8');
  assert.match(source, /\$historyResultUrl/);
  assert.match(source, /href="<\?= learner_escape\(\$historyResultUrl\); \?>"/);
});

test('catalog band-required success shows chooser and confirmation reloads the catalog with the exact band', async () => {
  const { bootCatalog } = require(modulePath);
  const { root, nodes } = createCatalogHarness();
  const calls = [];
  await bootCatalog(root, {
    async get(endpoint) {
      calls.push(endpoint);
      if (endpoint === '/assessments.php') {
        return {
          code: 'EDUCATION_BAND_REQUIRED',
          requires_education_band: true,
          education_band: null,
          assessments: [],
        };
      }
      if (endpoint === '/assessments.php?band=college') {
        return {
          requires_education_band: false,
          education_band: 'college',
          assessments: [{ code: 'holland', name: 'Holland College', status: 'published', attempt_status: 'submitted' }],
        };
      }
      return {
        assessment_history: {
          items: [{
            id: 'attempt-college',
            assessment_type: 'holland',
            status: 'submitted',
            result_code: 'ACR',
            submitted_at: '2026-08-24T00:00:00Z',
          }],
        },
      };
    },
  });

  assert.equal(nodes.bandConfirmation.hidden, false, 'band-required is a dedicated state');
  assert.equal(nodes.empty.hidden, true, 'band-required is not a valid empty catalog');
  assert.equal(nodes.error.hidden, true, 'band-required is not a source error');
  assert.equal(nodes.cards.hidden, true);

  nodes.catalogCollege.checked = true;
  await nodes.bandConfirm.dispatch('click');
  assert.ok(calls.includes('/assessments.php?band=college'), 'confirmation reloads with explicit college band');
  assert.equal(nodes.bandConfirmation.hidden, true);
  assert.equal(nodes.cards.hidden, false);
  assert.equal(nodes.cards.children.length, 1);
  const links = nodes.cards.children[0].children.filter((child) => child.tagName === 'A');
  assert.match(links[0].href, /[?&]band=college(?:&|$)/, 'assessment navigation retains the confirmed band');
  assert.match(links[1].href, /[?&]band=college(?:&|$)/, 'stored result navigation retains the confirmed band');
});

test('catalog source failure renders source error instead of valid empty state', async () => {
  const { bootCatalog } = require(modulePath);
  const { root, nodes } = createCatalogHarness();
  const sourceFailure = Object.assign(new Error('catalog unavailable'), { code: 'SOURCE_FAILURE' });
  await bootCatalog(root, {
    async get(endpoint) {
      assert.equal(endpoint, '/assessments.php');
      throw sourceFailure;
    },
  });

  assert.equal(nodes.loading.hidden, true);
  assert.equal(nodes.error.hidden, false, 'catalog failure exposes its dedicated error');
  assert.equal(nodes.empty.hidden, true, 'catalog failure is not a valid empty catalog');
  assert.equal(nodes.cards.hidden, true);
});

test('runner carries the catalog-confirmed education band into detail loading', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  const calls = [];
  const originalLocation = global.location;
  global.location = { href: '', search: '?code=holland&band=college' };
  try {
    await bootRunner(root, {
      async get(endpoint) {
        calls.push(endpoint);
        return {
          assessment: { code: 'holland', name: 'Holland College', education_band: 'college' },
          questions: [],
          history: [],
        };
      },
      async send() {},
    }, doc);
    assert.equal(calls[0], '/assessments.php?code=holland&band=college');
    assert.equal(nodes.bandModal.hidden, true);
  } finally {
    global.location = originalLocation;
  }
});

test('history-only failure preserves a usable catalog and shows its own warning', async () => {
  const { bootCatalog } = require(modulePath);
  const { root, nodes } = createCatalogHarness();
  await bootCatalog(root, {
    async get(endpoint) {
      if (endpoint === '/assessments.php') {
        return { assessments: [{ code: 'holland', name: 'Holland', status: 'published' }] };
      }
      throw Object.assign(new Error('history unavailable'), { code: 'SOURCE_FAILURE' });
    },
  });

  assert.equal(nodes.cards.hidden, false);
  assert.equal(nodes.cards.children.length, 1, 'catalog card remains rendered');
  assert.equal(nodes.empty.hidden, true);
  assert.equal(nodes.error.hidden, true);
  assert.equal(nodes.historyWarning.hidden, false, 'history failure has a separate warning');
});

test('catalog retry clears source error and renders the recovered catalog', async () => {
  const { bootCatalog } = require(modulePath);
  const { root, nodes } = createCatalogHarness();
  let catalogAttempts = 0;
  await bootCatalog(root, {
    async get(endpoint) {
      if (endpoint === '/assessments.php') {
        catalogAttempts += 1;
        if (catalogAttempts === 1) throw new Error('temporary catalog failure');
        return { assessments: [{ code: 'disc', name: 'DISC', status: 'published' }] };
      }
      return { assessment_history: { items: [] } };
    },
  });

  assert.equal(nodes.error.hidden, false);
  await nodes.catalogRetry.dispatch('click');
  assert.equal(catalogAttempts, 2);
  assert.equal(nodes.error.hidden, true);
  assert.equal(nodes.cards.hidden, false);
  assert.equal(nodes.cards.children.length, 1);
});

test('runner source error keeps band modal closed and retry renders recovered detail', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  let attempts = 0;
  const api = {
    async get() {
      attempts += 1;
      if (attempts === 1) throw Object.assign(new Error('detail unavailable'), { code: 'SOURCE_FAILURE' });
      return {
        assessment: { name: 'Holland đã duyệt', question_count: 30, duration_minutes: 12 },
        education_band: 'high',
        questions: [],
      };
    },
    async send() {},
  };

  await bootRunner(root, api, doc);
  assert.equal(nodes.error.hidden, false);
  assert.equal(nodes.bandModal.hidden, true, 'source failure never opens education-band confirmation');

  await nodes.retry.dispatch('click');
  assert.equal(attempts, 2);
  assert.equal(nodes.error.hidden, true);
  assert.equal(nodes.bandModal.hidden, true);
  assert.equal(nodes.introName.textContent, 'Holland đã duyệt');
});

test('visible runner retry repeats question loading after start succeeds and owned-attempt GET fails', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  let starts = 0;
  let attemptLoads = 0;
  const api = {
    async get(endpoint) {
      if (endpoint.startsWith('/assessments.php?code=')) {
        return { assessment: { code: 'holland', name: 'Holland High', education_band: 'high' }, questions: [], history: [] };
      }
      attemptLoads += 1;
      if (attemptLoads === 1) throw Object.assign(new Error('questions unavailable'), { code: 'SOURCE_FAILURE' });
      return { id: 'attempt-retry', status: 'in_progress', questions: [{ id: 'q-1', prompt: 'Q1', options: [] }], answers: {} };
    },
    async send() {
      starts += 1;
      return { id: 'attempt-retry', status: 'in_progress' };
    },
  };

  await bootRunner(root, api, doc);
  await nodes.start.dispatch('click');
  assert.equal(nodes.error.hidden, false);
  await nodes.retry.dispatch('click');
  assert.equal(starts, 2, 'visible retry resumes the start/question-loading action');
  assert.equal(attemptLoads, 2);
  assert.equal(nodes.error.hidden, true);
});

test('runner band-required success lets a classless learner choose college and POSTs college exactly', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  const sends = [];
  await bootRunner(root, {
    async get() {
      return {
        code: 'EDUCATION_BAND_REQUIRED',
        requires_education_band: true,
        education_band: null,
        assessment: null,
        questions: [],
        history: [],
      };
    },
    async send(method, endpoint, body) {
      sends.push({ method, endpoint, body });
      return { id: 'attempt-college', status: 'in_progress', questions: [], answers: {} };
    },
  }, doc);

  assert.equal(nodes.bandModal.hidden, false);
  assert.equal(nodes.error.hidden, true, 'band-required is distinct from network failure');
  nodes.college.checked = true;
  await nodes.bandConfirm.dispatch('click');
  assert.deepEqual(sends[0], {
    method: 'POST',
    endpoint: '/assessment-attempts.php',
    body: { assessmentCode: 'holland', educationBand: 'college' },
  });
});

test('required education-band chooser delegates focus handling to the shared modal API', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  const originalLearnerUi = global.LearnerUI;
  const calls = [];
  global.LearnerUI = {
    openModal(modal) {
      calls.push(modal);
      modal.hidden = false;
    },
    closeModal(modal) {
      modal.hidden = true;
    },
  };
  try {
    await bootRunner(root, {
      async get() {
        return { code: 'EDUCATION_BAND_REQUIRED', requires_education_band: true, assessment: null, questions: [], history: [] };
      },
      async send() {},
    }, doc);
    assert.deepEqual(calls, [nodes.bandModal]);
  } finally {
    global.LearnerUI = originalLearnerUi;
  }
});

test('classless runner preserves the confirmed college band in result navigation', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  const originalLocation = global.location;
  global.location = { href: '', search: '' };
  try {
    await bootRunner(root, {
      async get() {
        return {
          code: 'EDUCATION_BAND_REQUIRED',
          requires_education_band: true,
          education_band: null,
          assessment: null,
          questions: [],
          history: [],
        };
      },
      async send(method, endpoint) {
        if (endpoint === '/assessment-attempts.php') {
          return { id: 'attempt-college', status: 'in_progress', questions: [], answers: {} };
        }
        return { id: 'attempt-college', status: 'submitted', result: { code: 'ACR' } };
      },
    }, doc);

    nodes.college.checked = true;
    await nodes.bandConfirm.dispatch('click');
    await nodes.submit.dispatch('click');
    assert.match(global.location.href, /[?&]attempt=attempt-college(?:&|$)/);
    assert.match(global.location.href, /[?&]band=college(?:&|$)/);
  } finally {
    global.location = originalLocation;
  }
});

test('result page reloads a classless attempt with its confirmed education band', async () => {
  const { bootResult } = require(modulePath);
  assert.equal(typeof bootResult, 'function');
  const calls = [];
  const root = createDomNode('main');
  root.dataset.assessmentCode = 'holland';
  const originalLocation = global.location;
  global.location = { href: '', search: '?code=holland&attempt=attempt-college&band=college' };
  try {
    await bootResult(root, {
      async get(endpoint) {
        calls.push(endpoint);
        if (endpoint.includes('view=history')) {
          return { assessment_history: { items: [] }, teacher_evaluations: { items: [] } };
        }
        return { assessment: { code: 'holland', education_band: 'college' }, history: [] };
      },
      async send() {},
    });
    assert.equal(calls[0], '/assessments.php?code=holland&band=college');
  } finally {
    global.location = originalLocation;
  }
});

test('runner uses a known grade 8 detail band and never silently defaults to high', async () => {
  const { bootRunner } = require(modulePath);
  const { root, nodes, doc } = createRunnerHarness();
  const sends = [];
  await bootRunner(root, {
    async get() {
      return {
        assessment: { code: 'holland', name: 'Holland Middle', education_band: 'middle' },
        questions: [],
        history: [],
      };
    },
    async send(method, endpoint, body) {
      sends.push({ method, endpoint, body });
      return { id: 'attempt-middle', status: 'in_progress', questions: [], answers: {} };
    },
  }, doc);

  assert.equal(nodes.bandModal.hidden, true);
  await nodes.start.dispatch('click');
  assert.equal(sends[0].body.educationBand, 'middle');
  assert.notEqual(sends[0].body.educationBand, 'high');
});

test('assessment submit routing accepts only server-provided local learner URLs', () => {
  const { safeOnboardingDestination } = require(modulePath);
  assert.equal(typeof safeOnboardingDestination, 'function');
  assert.equal(safeOnboardingDestination('/app/learner/assessment.php?code=disc'), '/app/learner/assessment.php?code=disc');
  assert.equal(safeOnboardingDestination('https://evil.example/steal'), null);
});

test('runner follows a valid onboarding next_url and ignores an external one', async () => {
  const { bootRunner } = require(modulePath);
  const originalLocation = global.location;

  async function submitWith(nextUrl) {
    const { root, nodes, doc } = createRunnerHarness();
    global.location = { href: '', search: '' };
    await bootRunner(root, {
      async get() {
        return { assessment: { code: 'holland', education_band: 'high' }, questions: [], history: [] };
      },
      async send(_method, endpoint) {
        if (endpoint === '/assessment-attempts.php') {
          return { id: 'attempt-routing', status: 'in_progress', questions: [], answers: {} };
        }
        return { id: 'attempt-routing', status: 'submitted', result_id: 'result-routing', next_url: nextUrl };
      },
    }, doc);
    await nodes.start.dispatch('click');
    await nodes.submit.dispatch('click');
    return global.location.href;
  }

  try {
    assert.equal(await submitWith('/app/learner/assessment.php?code=mbti'), '/app/learner/assessment.php?code=mbti');
    const fallback = await submitWith('https://evil.example/steal');
    assert.match(fallback, /^(?:\/app\/learner\/)?assessment-result\.php\?/);
    assert.doesNotMatch(fallback, /evil\.example/);
  } finally {
    global.location = originalLocation;
  }
});
