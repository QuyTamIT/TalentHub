'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const sourcePath = path.join(root, 'assets/js/learner-checkin.js');
const pagePath = path.join(root, 'app/learner/checkin.php');

function createNode() {
  const listeners = {};
  return {
    dataset: {},
    hidden: false,
    disabled: false,
    value: '',
    textContent: '',
    children: [],
    readyState: 2,
    srcObject: null,
    listeners,
    addEventListener(type, listener) { listeners[type] = listener; },
    appendChild(child) { this.children.push(child); return child; },
    replaceChildren(...children) { this.children = children; },
    setAttribute(name, value) { this[name] = value; },
    async play() {},
  };
}

function loadClient({ mediaDevices, detector, send, history = [], videoPlay } = {}) {
  const source = fs.readFileSync(sourcePath, 'utf8');
  const nodes = {
    video: createNode(), placeholder: createNode(), start: createNode(), stop: createNode(),
    form: createNode(), token: createNode(), feedback: createNode(), apiState: createNode(),
    submit: createNode(), reset: createNode(), history: createNode(),
  };
  if (videoPlay) nodes.video.play = videoPlay;
  const selectors = {
    '[data-camera-video]': nodes.video,
    '[data-camera-placeholder]': nodes.placeholder,
    '[data-camera-start]': nodes.start,
    '[data-camera-stop]': nodes.stop,
    '[data-manual-form]': nodes.form,
    '[data-manual-token]': nodes.token,
    '[data-checkin-feedback]': nodes.feedback,
    '[data-api-state]': nodes.apiState,
    '[data-submit-checkin]': nodes.submit,
    '[data-reset-checkin]': nodes.reset,
    '[data-checkin-history]': nodes.history,
  };
  const boot = createNode();
  boot.textContent = JSON.stringify({ apiBase: '/app/learner/api/v1', csrfToken: 'csrf' });
  let frame = null;
  const apiCalls = { get: [], send: [] };
  const api = {
    async get(url) { apiCalls.get.push(url); return { items: history }; },
    async send(method, url, body, options) {
      apiCalls.send.push({ method, url, body, options });
      return send ? send(method, url, body, options) : { activity: { title: 'Demo' }, experience: { hours: '2.50' } };
    },
  };
  const sandbox = {
    document: {
      hidden: false,
      getElementById(id) { return id === 'learner-checkin-boot' ? boot : null; },
      querySelector(selector) { return selectors[selector] || null; },
      createElement() { return createNode(); },
      addEventListener() {},
    },
    navigator: { mediaDevices },
    BarcodeDetector: detector,
    TalentHubLearnerApi: { createLearnerApiClient() { return api; } },
    crypto: require('node:crypto').webcrypto,
    requestAnimationFrame(callback) { frame = callback; return 1; },
    cancelAnimationFrame() { frame = null; },
    addEventListener() {},
    module: { exports: {} },
    Uint8Array,
    Date,
    Math,
    JSON,
    Promise,
    setTimeout,
    clearTimeout,
  };
  sandbox.window = sandbox;
  sandbox.globalThis = sandbox;
  vm.runInNewContext(source, sandbox, { filename: sourcePath });
  return { contract: sandbox.module.exports, nodes, apiCalls, runFrame: async () => frame && frame() };
}

test('learner check-in client never derives request identifiers from raw tokens', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');
  assert.equal(source.includes('token.slice'), false, 'raw token substrings must not enter idempotency headers');
  assert.match(source, /crypto\.getRandomValues|randomUUID/, 'request keys must be token-independent and cryptographically random');
});

test('learner check-in history renders untrusted server strings without innerHTML', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');
  assert.equal(source.includes('.innerHTML'), false, 'server-returned titles and hours must use DOM APIs/textContent');
  assert.match(source, /textContent/, 'history renderer must use textContent');
});

test('learner check-in camera implements real decode loop and manual fallback', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');
  assert.match(source, /BarcodeDetector/, 'camera path must use a supported browser QR decoder');
  assert.match(source, /requestAnimationFrame|setInterval/, 'scanner must repeatedly decode frames while the stream is active');
  assert.match(source, /unsupported-decoder|Decoder unsupported|Không hỗ trợ bộ giải mã/u, 'unsupported decoder state must keep manual token fallback available');
});

test('learner check-in page treats server GET history as authoritative in database mode', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  const source = fs.readFileSync(sourcePath, 'utf8');
  assert.equal(page.includes('$checkinHistory'), false, 'database page must not render legacy mock check-in history as source of truth');
  assert.match(source, /api\.get\('\/checkins\.php/, 'client must load own history from the Phase 5 GET endpoint');
  assert.match(source, /renderHistory/, 'client must replace history from server response after GET/POST');
  assert.match(page, /id="learner-checkin-boot"/, 'check-in page uses a unique boot node instead of shadowing the shared session boot node');
  assert.match(source, /getElementById\('learner-checkin-boot'\)/, 'check-in client reads its dedicated learner API base');
});

test('learner check-in client stops media tracks on every exit path', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');
  for (const token of ['beforeunload', 'visibilitychange', 'cleanupStream', 'submitToken']) {
    assert.match(source, new RegExp(token), 'media cleanup contract includes ' + token);
  }
});

test('learner check-in client does not retain the raw QR token in long-lived state', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');
  assert.equal(/state\.(?:lastToken|token)\s*=/.test(source), false, 'raw token must not be persisted in module state');
  assert.equal(source.includes('localStorage'), false, 'raw token must never be persisted in local storage');
  assert.equal(source.includes('sessionStorage'), false, 'raw token must never be persisted in session storage');
  assert.equal(source.includes('console.log'), false, 'raw token must never be logged');
});

test('supported camera decodes one token, submits it, refreshes history, and stops tracks', async () => {
  let stopped = 0;
  const stream = { getTracks: () => [{ stop() { stopped += 1; } }] };
  class Detector { async detect() { return [{ rawValue: 'camera-token' }]; } }
  const client = loadClient({ mediaDevices: { async getUserMedia() { return stream; } }, detector: Detector });
  await client.contract.startCamera();
  assert.equal(client.nodes.video.srcObject, stream);
  await client.runFrame();
  assert.equal(client.apiCalls.send.length, 1);
  assert.equal(client.apiCalls.send[0].body.token, 'camera-token');
  assert.deepEqual(Object.keys(client.apiCalls.send[0].body), ['token']);
  assert.ok(stopped >= 1, 'successful scan stops the media track');
  assert.ok(client.apiCalls.get.length >= 2, 'initial load and successful submit both refresh history');
});

test('permission denial and missing browser APIs keep manual fallback available', async () => {
  const denied = new Error('denied'); denied.name = 'NotAllowedError';
  class Detector { async detect() { return []; } }
  const permission = loadClient({ mediaDevices: { async getUserMedia() { throw denied; } }, detector: Detector });
  await permission.contract.startCamera();
  assert.match(permission.nodes.feedback.textContent, /Quyền camera bị từ chối/u);
  assert.equal(permission.nodes.form.hidden, false);

  const unsupported = loadClient({ mediaDevices: undefined, detector: undefined });
  await unsupported.contract.startCamera();
  assert.match(unsupported.nodes.feedback.textContent, /không hỗ trợ camera/u);
  unsupported.nodes.token.value = 'manual-token';
  unsupported.nodes.form.listeners.submit({ preventDefault() {} });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(unsupported.apiCalls.send[0].body.token, 'manual-token');
  assert.deepEqual(Object.keys(unsupported.apiCalls.send[0].body), ['token']);
});

test('double submit is blocked while pending and a failed request can be retried', async () => {
  let resolveFirst;
  let attempts = 0;
  const client = loadClient({
    send() {
      attempts += 1;
      if (attempts === 1) return new Promise(resolve => { resolveFirst = resolve; });
      if (attempts === 2) throw Object.assign(new Error('temporary'), { code: 'SERVICE_UNAVAILABLE' });
      return { activity: { title: 'Retry' }, experience: { hours: '1.00' } };
    },
  });
  const first = client.contract.submitToken('same-token');
  await client.contract.submitToken('same-token');
  assert.equal(client.apiCalls.send.length, 1, 'pending submit suppresses a second request');
  resolveFirst({ activity: { title: 'First' }, experience: { hours: '1.00' } });
  await first;
  await client.contract.submitToken('same-token');
  assert.equal(client.apiCalls.send.length, 2, 'request can be retried after completion');
  await client.contract.submitToken('same-token');
  assert.equal(client.apiCalls.send.length, 3, 'request can be retried after a server error');
});

test('stale getUserMedia resolution is stopped and cannot replace the active stream', async () => {
  const pending = [];
  const stopped = [0, 0];
  const streams = [0, 1].map(index => ({ getTracks: () => [{ stop() { stopped[index] += 1; } }] }));
  class Detector { async detect() { return []; } }
  const client = loadClient({
    mediaDevices: { getUserMedia() { return new Promise(resolve => pending.push(resolve)); } },
    detector: Detector,
  });
  const first = client.contract.startCamera();
  const second = client.contract.startCamera();
  pending[0](streams[0]);
  await first;
  assert.equal(stopped[0], 1, 'the stale first stream is stopped immediately');
  assert.notEqual(client.nodes.video.srcObject, streams[0]);
  pending[1](streams[1]);
  await second;
  assert.equal(client.nodes.video.srcObject, streams[1], 'only the newest camera operation becomes active');
  client.contract.cleanupStream();
  assert.equal(stopped[1], 1);
});

test('stale getUserMedia rejection cannot tear down a newer active stream', async () => {
  const pending = [];
  let stopped = 0;
  const active = { getTracks: () => [{ stop() { stopped += 1; } }] };
  class Detector { async detect() { return []; } }
  const client = loadClient({
    mediaDevices: { getUserMedia() { return new Promise((resolve, reject) => pending.push({ resolve, reject })); } },
    detector: Detector,
  });
  const first = client.contract.startCamera();
  const second = client.contract.startCamera();
  pending[1].resolve(active);
  await second;
  assert.equal(client.nodes.video.srcObject, active);
  pending[0].reject(new Error('stale camera failure'));
  await first;
  assert.equal(client.nodes.video.srcObject, active, 'stale rejection leaves the newer stream attached');
  assert.equal(stopped, 0, 'stale rejection does not stop the newer stream');
  client.contract.cleanupStream();
  assert.equal(stopped, 1);
});

test('decoder failure stops the active camera and does not schedule another frame', async () => {
  let stopped = 0;
  class Detector { async detect() { throw new Error('decoder failed'); } }
  const client = loadClient({
    mediaDevices: { async getUserMedia() { return { getTracks: () => [{ stop() { stopped += 1; } }] }; } },
    detector: Detector,
  });
  await client.contract.startCamera();
  await client.runFrame();
  assert.equal(stopped, 1, 'decoder failure releases the media track');
  assert.equal(client.nodes.video.srcObject, null, 'decoder failure clears the video element');
  assert.equal(client.nodes.apiState.textContent, 'Decoder unsupported');
});

test('stopping while decode is pending prevents a late QR result from submitting', async () => {
  let resolveDetect;
  class Detector { detect() { return new Promise(resolve => { resolveDetect = resolve; }); } }
  const client = loadClient({
    mediaDevices: { async getUserMedia() { return { getTracks: () => [{ stop() {} }] }; } },
    detector: Detector,
  });
  await client.contract.startCamera();
  const frame = client.runFrame();
  client.contract.cleanupStream();
  resolveDetect([{ rawValue: 'late-token' }]);
  await frame;
  assert.equal(client.apiCalls.send.length, 0, 'late decoder result is ignored after explicit stop');
});

test('stale decoder rejection cannot tear down a restarted camera', async () => {
  let rejectFirstDetect;
  let detectCalls = 0;
  let oldStopped = 0;
  let newStopped = 0;
  const streams = [
    { getTracks: () => [{ stop() { oldStopped += 1; } }] },
    { getTracks: () => [{ stop() { newStopped += 1; } }] },
  ];
  class Detector {
    detect() {
      detectCalls += 1;
      if (detectCalls === 1) return new Promise((resolve, reject) => { rejectFirstDetect = reject; });
      return Promise.resolve([]);
    }
  }
  let cameraCall = 0;
  const client = loadClient({
    mediaDevices: { async getUserMedia() { return streams[cameraCall++]; } },
    detector: Detector,
  });
  await client.contract.startCamera();
  const oldFrame = client.runFrame();
  client.contract.cleanupStream();
  await client.contract.startCamera();
  assert.equal(client.nodes.video.srcObject, streams[1]);
  rejectFirstDetect(new Error('old detector stopped'));
  await oldFrame;
  assert.equal(client.nodes.video.srcObject, streams[1], 'stale decoder rejection leaves restarted camera active');
  assert.equal(newStopped, 0, 'stale decoder rejection does not stop the new stream');
  assert.equal(client.nodes.apiState.textContent, 'Camera active');
  assert.equal(oldStopped, 1);
  client.contract.cleanupStream();
  assert.equal(newStopped, 1);
});

test('cleanup while video play is pending prevents scanner reactivation', async () => {
  let resolvePlay;
  let stopped = 0;
  class Detector { async detect() { return [{ rawValue: 'must-not-submit' }]; } }
  const client = loadClient({
    mediaDevices: { async getUserMedia() { return { getTracks: () => [{ stop() { stopped += 1; } }] }; } },
    detector: Detector,
    videoPlay: () => new Promise(resolve => { resolvePlay = resolve; }),
  });
  const starting = client.contract.startCamera();
  await new Promise(resolve => setImmediate(resolve));
  client.contract.cleanupStream();
  resolvePlay();
  await starting;
  await client.runFrame();
  assert.equal(client.nodes.video.srcObject, null);
  assert.equal(client.apiCalls.send.length, 0, 'scanner remains inactive after cleanup during video.play');
  assert.ok(stopped >= 1, 'pending stream is stopped');
});
