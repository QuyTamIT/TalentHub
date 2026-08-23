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

test('one safe request ID is preserved across retries of the same logical GET', async () => {
  const requestIds = [];
  const client = createLearnerApiClient({
    retryDelayMs: 0,
    fetchImpl: async (url, options) => {
      requestIds.push(options.headers['X-Request-ID']);
      if (requestIds.length === 1) throw new Error('temporary network failure');
      return { ok: true, status: 200, json: async () => ({ data: { ok: true } }) };
    },
  });

  await client.get('/students/me');
  assert.equal(requestIds.length, 2);
  assert.match(requestIds[0], /^[A-Za-z0-9_-]{16,64}$/);
  assert.equal(requestIds[1], requestIds[0]);
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

test('client ignores an external base URL and stays under the canonical API root', async () => {
  let requestUrl = '';
  const client = createLearnerApiClient({
    baseUrl: 'https://untrusted.example/api/v1',
    fetchImpl: async (url) => {
      requestUrl = url;
      return { ok: true, status: 200, json: async () => ({ data: {} }) };
    },
  });

  await client.get('/students/me?include=profile');
  assert.equal(requestUrl, '/api/v1/students/me?include=profile');
});

test('client accepts the learner-local API base without escaping it', async () => {
  let requestUrl = '';
  const client = createLearnerApiClient({
    baseUrl: '/app/learner/api/v1',
    fetchImpl: async (url) => {
      requestUrl = url;
      return { ok: true, status: 200, json: async () => ({ data: {} }) };
    },
  });

  await client.get('/recommendations.php');
  assert.equal(requestUrl, '/app/learner/api/v1/recommendations.php');
});

test('activity registration mutation stays under learner-local API and carries CSRF', async () => {
  let request;
  const client = createLearnerApiClient({
    baseUrl: '/app/learner/api/v1', csrfToken: 'csrf-phase-4',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, status: 201, json: async () => ({ data: { registration: { status: 'approved' } } }) };
    },
  });
  await client.send('POST', '/activity-registrations.php', { action: 'register', activityId: 'activity-1' });
  assert.equal(request.url, '/app/learner/api/v1/activity-registrations.php');
  assert.equal(request.options.headers['X-CSRF-Token'], 'csrf-phase-4');
});

test('client rejects traversal paths before making a request', async () => {
  let calls = 0;
  const client = createLearnerApiClient({
    fetchImpl: async () => {
      calls += 1;
      return { ok: true, status: 200, json: async () => ({ data: {} }) };
    },
  });

  await assert.rejects(client.get('/../../protected'), error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.code, 'INVALID_API_PATH');
    return true;
  });
  assert.equal(calls, 0);
});

test('read methods never send CSRF tokens even when called with a body', async () => {
  let request;
  const client = createLearnerApiClient({
    csrfToken: 'csrf-test',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, status: 200, json: async () => ({ data: {} }) };
    },
  });

  await client.send('GET', '/students/me', { ignored: true });
  assert.equal(request.options.headers['X-CSRF-Token'], undefined);
  assert.equal(request.options.body, JSON.stringify({ ignored: true }));
});

test('bodyless mutations include the current CSRF token', async () => {
  let request;
  const client = createLearnerApiClient({
    csrfToken: 'csrf-first',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, status: 200, json: async () => ({ data: {} }) };
    },
  });

  client.setCsrfToken('csrf-current');
  await client.send('DELETE', '/students/me/avatar');
  assert.equal(request.options.headers['X-CSRF-Token'], 'csrf-current');
  assert.equal(request.options.body, undefined);
});

test('recommendation generation sends an explicit idempotency key without exposing it in the body', async () => {
  let request;
  const client = createLearnerApiClient({
    baseUrl: '/app/learner/api/v1', csrfToken: 'csrf-current',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, status: 202, json: async () => ({ data: { state: 'pending' } }) };
    },
  });

  await client.send('POST', '/recommendations.php', undefined, { idempotencyKey: 'idempotency-key-0001' });
  assert.equal(request.options.headers['X-Idempotency-Key'], 'idempotency-key-0001');
  assert.equal(request.options.body, undefined);
});

test('403, 422, and 503 responses use the normalized error contract', async () => {
  for (const [status, code] of [[403, 'FORBIDDEN'], [422, 'VALIDATION_FAILED'], [503, 'SERVICE_UNAVAILABLE']]) {
    const client = createLearnerApiClient({
      fetchImpl: async () => ({
        ok: false,
        status,
        json: async () => ({ error: { code, details: [{ field: 'name' }] }, meta: { requestId: `req-${status}` } }),
      }),
    });

    await assert.rejects(client.get('/students/me'), error => {
      assert.ok(error instanceof LearnerApiError);
      assert.equal(error.status, status);
      assert.equal(error.code, code);
      assert.deepEqual(error.details, [{ field: 'name' }]);
      assert.equal(error.requestId, `req-${status}`);
      return true;
    });
  }
});

test('a non-JSON 401 still notifies unauthorized handling with a safe error', async () => {
  let unauthorized = 0;
  const client = createLearnerApiClient({
    onUnauthorized: () => { unauthorized += 1; },
    fetchImpl: async () => ({
      ok: false,
      status: 401,
      json: async () => { throw new Error('HTML error page'); },
    }),
  });

  await assert.rejects(client.get('/students/me'), error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.status, 401);
    assert.equal(error.code, 'INVALID_RESPONSE');
    return true;
  });
  assert.equal(unauthorized, 1);
});

test('malformed successful envelopes produce safe client errors', async () => {
  for (const payload of [null, {}]) {
    const client = createLearnerApiClient({
      fetchImpl: async () => ({ ok: true, status: 200, json: async () => payload }),
    });

    await assert.rejects(client.get('/students/me'), error => {
      assert.ok(error instanceof LearnerApiError);
      assert.equal(error.status, 200);
      assert.equal(error.code, 'INVALID_RESPONSE');
      return true;
    });
  }
});

test('caller abort cancels the request and returns a normalized abort error', async () => {
  const caller = new AbortController();
  const client = createLearnerApiClient({
    fetchImpl: async (url, options) => new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => {
        const error = new Error('aborted');
        error.name = 'AbortError';
        reject(error);
      }, { once: true });
    }),
  });

  const request = client.get('/students/me', { signal: caller.signal });
  caller.abort();
  await assert.rejects(request, error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.code, 'REQUEST_ABORTED');
    return true;
  });
});

test('request timeout aborts a hanging request with a recoverable timeout error', async () => {
  const client = createLearnerApiClient({
    timeoutMs: 5,
    getRetryCount: 0,
    fetchImpl: async (url, options) => new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => {
        const error = new Error('aborted');
        error.name = 'AbortError';
        reject(error);
      }, { once: true });
    }),
  });

  await assert.rejects(client.get('/students/me'), error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.code, 'REQUEST_TIMEOUT');
    return true;
  });
});

test('request timeout remains active while the response body is being decoded', async () => {
  const client = createLearnerApiClient({
    timeoutMs: 5,
    getRetryCount: 0,
    fetchImpl: async (url, options) => ({
      ok: true,
      status: 200,
      json: async () => new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => {
          const error = new Error('aborted');
          error.name = 'AbortError';
          reject(error);
        }, { once: true });
      }),
    }),
  });

  const guarded = Promise.race([
    client.get('/students/me'),
    new Promise((resolve, reject) => setTimeout(() => reject(new Error('BODY_TIMEOUT_NOT_ENFORCED')), 40)),
  ]);
  await assert.rejects(guarded, error => {
    assert.equal(error.code, 'REQUEST_TIMEOUT');
    return true;
  });
});

test('GET retries one temporary network failure but mutations are never retried', async () => {
  let getCalls = 0;
  const getClient = createLearnerApiClient({
    retryDelayMs: 0,
    fetchImpl: async () => {
      getCalls += 1;
      if (getCalls === 1) throw new Error('network down');
      return { ok: true, status: 200, json: async () => ({ data: { recovered: true } }) };
    },
  });
  assert.deepEqual(await getClient.get('/students/me'), { recovered: true });
  assert.equal(getCalls, 2);

  let mutationCalls = 0;
  const mutationClient = createLearnerApiClient({
    retryDelayMs: 0,
    fetchImpl: async () => {
      mutationCalls += 1;
      throw new Error('network down');
    },
  });
  await assert.rejects(mutationClient.send('PATCH', '/students/me', { fullName: 'A' }), error => {
    assert.equal(error.code, 'NETWORK_ERROR');
    return true;
  });
  assert.equal(mutationCalls, 1);
});

test('429 responses expose a safe numeric retry-after value without auto retry', async () => {
  let calls = 0;
  const client = createLearnerApiClient({
    fetchImpl: async () => {
      calls += 1;
      return {
        ok: false,
        status: 429,
        headers: { get: name => name.toLowerCase() === 'retry-after' ? '30' : null },
        json: async () => ({ error: { code: 'RATE_LIMIT_EXCEEDED', message: 'Vui lòng thử lại sau.' } }),
      };
    },
  });

  await assert.rejects(client.get('/students/me'), error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.status, 429);
    assert.equal(error.retryAfter, 30);
    return true;
  });
  assert.equal(calls, 1);
});

test('a caller can explicitly disable GET retry for freshness-sensitive reads', async () => {
  let calls = 0;
  const client = createLearnerApiClient({
    retryDelayMs: 0,
    fetchImpl: async () => {
      calls += 1;
      throw new Error('network down');
    },
  });
  await assert.rejects(client.get('/students/me', { retryCount: 0 }), error => {
    assert.equal(error.code, 'NETWORK_ERROR');
    return true;
  });
  assert.equal(calls, 1);
});
