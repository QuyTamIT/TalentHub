const test = require('node:test');
const assert = require('node:assert/strict');
const { createLearnerApiClient, LearnerApiError } = require('../assets/js/learner-api.js');

test('GET returns response data and sends same-origin credentials', async () => {
  const calls = [];
  const client = createLearnerApiClient({
    baseUrl: '/api/v1',
    csrfToken: 'csrf-test',
    fetchImpl: async (url, options) => {
      calls.push({ url, options });
      return { ok: true, status: 200, json: async () => ({ data: { id: 'student-1' }, meta: { requestId: 'req-1' } }) };
    },
  });
  assert.deepEqual(await client.get('/students/me'), { id: 'student-1' });
  assert.equal(calls[0].options.credentials, 'same-origin');
  assert.equal(calls[0].options.headers.Accept, 'application/json');
});

test('PATCH sends JSON and CSRF token', async () => {
  let request;
  const client = createLearnerApiClient({
    baseUrl: '/api/v1', csrfToken: 'csrf-test',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, status: 200, json: async () => ({ data: { updated: true }, meta: { requestId: 'req-2' } }) };
    },
  });
  await client.send('PATCH', '/students/me', { fullName: 'Nguyễn Văn A' });
  assert.equal(request.options.headers['Content-Type'], 'application/json');
  assert.equal(request.options.headers['X-CSRF-Token'], 'csrf-test');
  assert.equal(request.options.body, JSON.stringify({ fullName: 'Nguyễn Văn A' }));
});

test('API errors preserve safe contract and notify on 401', async () => {
  let unauthorized = 0;
  const client = createLearnerApiClient({
    baseUrl: '/api/v1', csrfToken: 'csrf-test', onUnauthorized: () => { unauthorized += 1; },
    fetchImpl: async () => ({
      ok: false, status: 401,
      json: async () => ({ error: { code: 'SESSION_EXPIRED', message: 'Phiên đăng nhập đã hết hạn.' }, meta: { requestId: 'req-3' } }),
    }),
  });
  await assert.rejects(client.get('/students/me'), error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.status, 401);
    assert.equal(error.code, 'SESSION_EXPIRED');
    assert.equal(error.requestId, 'req-3');
    return true;
  });
  assert.equal(unauthorized, 1);
});
