const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const modulePath = path.join(__dirname, '..', 'assets', 'js', 'learner-ai-roadmap.js');

function recorder() {
  return {
    events: [],
    render(state, payload) { this.events.push(['render', state, payload]); },
    updateTask(id, status) { this.events.push(['task', id, status]); },
    feedback(state) { this.events.push(['feedback', state]); },
  };
}

test('task progress is optimistic, persisted, and rolled back after failure', async () => {
  const { createRoadmapController } = require(modulePath);
  const view = recorder();
  const calls = [];
  const api = {
    async get() { return { state: 'not_generated' }; },
    async send(method, endpoint, body, options) {
      calls.push([method, endpoint, body, options]);
      if (body.taskId === 'task-fail') throw new Error('offline');
      return { state: 'task_updated', task_id: body.taskId, status: body.status };
    },
  };
  const controller = createRoadmapController({ api, view, createIdempotencyKey: () => 'interaction-key' });
  await controller.updateTask('task-ok', 'not_started');
  assert.deepEqual(view.events.slice(-2), [['task', 'task-ok', 'completed'], ['feedback', 'task-saved']]);
  await assert.rejects(controller.updateTask('task-fail', 'in_progress'), /offline/);
  assert.deepEqual(view.events.slice(-3), [
    ['task', 'task-fail', 'completed'], ['task', 'task-fail', 'in_progress'], ['feedback', 'task-error'],
  ]);
  assert.equal(calls[0][1], '/ai-roadmap-task.php');
});

test('feedback uses roadmap id and an allowlisted reason code only', async () => {
  const { createRoadmapController } = require(modulePath);
  const view = recorder();
  const calls = [];
  const api = { get: async () => ({}), send: async (...args) => { calls.push(args); return { state: 'feedback_saved' }; } };
  const controller = createRoadmapController({ api, view, createIdempotencyKey: () => 'feedback-key' });
  await controller.submitFeedback('roadmap-1', 'not_helpful');
  assert.deepEqual(calls[0], ['POST', '/recommendation-feedback.php', {
    roadmapId: 'roadmap-1', verdict: 'not_helpful', reasonCode: 'not_relevant',
  }, { idempotencyKey: 'feedback-key' }]);
  assert.deepEqual(view.events.at(-1), ['feedback', 'feedback-saved']);
});

test('historical version loading stays on the owner-scoped roadmap API', async () => {
  const { createRoadmapController } = require(modulePath);
  const view = recorder();
  const calls = [];
  const api = { get: async (endpoint) => { calls.push(endpoint); return { state: 'not_generated' }; }, send: async () => ({}) };
  const controller = createRoadmapController({ api, view });
  await controller.loadVersion(3);
  assert.deepEqual(calls, ['/ai-roadmap.php?version=3']);
});

test('activity cards link only when the server supplies a validated registration path', () => {
  const source = require('node:fs').readFileSync(modulePath, 'utf8');
  assert.match(source, /registration_path/);
  assert.doesNotMatch(source, /activity-detail\.php\?id=\$\{/);
});
