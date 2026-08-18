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
