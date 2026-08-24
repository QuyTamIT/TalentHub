/**
 * Shared authenticated JSON API client for learner pages.
 */
(function initLearnerApi(global) {
    'use strict';

    const API_ROOT = '/api/v1';
    const LEARNER_API_ROOT = '/app/learner/api/v1';
    const ALLOWED_API_BASES = new Set([API_ROOT, LEARNER_API_ROOT]);
    const MUTATION_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

    class LearnerApiError extends Error {
        constructor(status, code, message, details = [], requestId = '', retryAfter = 0) {
            super(message);
            this.name = 'LearnerApiError';
            this.status = status;
            this.code = code;
            this.details = Array.isArray(details) ? details : [];
            this.requestId = requestId;
            this.retryAfter = Number.isInteger(retryAfter) && retryAfter > 0 ? retryAfter : 0;
        }
    }

    function createInvalidResponseError(status) {
        return new LearnerApiError(status, 'INVALID_RESPONSE', 'Phản hồi máy chủ không hợp lệ.');
    }

    function getResponseStatus(response) {
        return Number.isInteger(response?.status) ? response.status : 0;
    }

    function normalizeApiBase(baseUrl) {
        const requestedBase = String(baseUrl || '').trim().replace(/\/+$/, '');
        return ALLOWED_API_BASES.has(requestedBase) ? requestedBase : API_ROOT;
    }

    function createApiPathError() {
        return new LearnerApiError(0, 'INVALID_API_PATH', 'Đường dẫn API không hợp lệ.');
    }

    function createRequestId() {
        const uuid = global.crypto && typeof global.crypto.randomUUID === 'function'
            ? global.crypto.randomUUID()
            : '';
        if (/^[A-Za-z0-9_-]{16,64}$/.test(uuid)) return uuid;
        return `learner-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
    }

    function buildApiUrl(baseUrl, path) {
        if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) {
            throw createApiPathError();
        }

        const pathOnly = path.split(/[?#]/, 1)[0];
        if (/\\|%2e|%2f|%5c/i.test(pathOnly)) throw createApiPathError();

        let url;
        try {
            url = new URL(`${baseUrl}${path}`, 'https://talenthub.invalid');
        } catch {
            throw createApiPathError();
        }

        if (url.pathname !== baseUrl && !url.pathname.startsWith(`${baseUrl}/`)) {
            throw createApiPathError();
        }
        return `${url.pathname}${url.search}`;
    }

    function normalizeApiError(response, payload) {
        const payloadObject = payload && typeof payload === 'object' ? payload : {};
        const error = payloadObject.error && typeof payloadObject.error === 'object' ? payloadObject.error : {};
        const meta = payloadObject.meta && typeof payloadObject.meta === 'object' ? payloadObject.meta : {};
        const retryAfterValue = response?.headers && typeof response.headers.get === 'function'
            ? Number.parseInt(response.headers.get('Retry-After') || '', 10)
            : 0;
        return new LearnerApiError(
            getResponseStatus(response),
            typeof error.code === 'string' && error.code ? error.code : 'REQUEST_FAILED',
            typeof error.message === 'string' && error.message ? error.message : 'Không thể hoàn tất yêu cầu.',
            error.details,
            typeof meta.requestId === 'string' ? meta.requestId : '',
            Number.isInteger(retryAfterValue) && retryAfterValue > 0 ? retryAfterValue : 0,
        );
    }

    function createLearnerApiClient({
        baseUrl = '/api/v1',
        csrfToken = '',
        fetchImpl = global.fetch,
        onUnauthorized = () => {},
        timeoutMs = 10000,
        getRetryCount = 1,
        retryDelayMs = 150,
    } = {}) {
        if (typeof fetchImpl !== 'function') throw new TypeError('fetchImpl must be a function');
        const apiBase = normalizeApiBase(baseUrl);
        const unauthorizedHandler = typeof onUnauthorized === 'function' ? onUnauthorized : () => {};
        const defaultTimeoutMs = Math.max(0, Number(timeoutMs) || 0);
        const defaultGetRetryCount = Math.max(0, Math.min(2, Number.parseInt(getRetryCount, 10) || 0));
        const defaultRetryDelayMs = Math.max(0, Number(retryDelayMs) || 0);
        let csrf = String(csrfToken || '');

        function notifyUnauthorized(error) {
            if (error.status !== 401) return;
            try {
                unauthorizedHandler(error);
            } catch {
                // Preserve the normalized API error even if the caller's UI callback fails.
            }
        }

        function wait(delay) {
            if (delay <= 0) return Promise.resolve();
            return new Promise(resolve => global.setTimeout(resolve, delay));
        }

        function createRequestLifecycle(callerSignal, requestTimeoutMs) {
            const controller = new AbortController();
            let timedOut = false;
            const abortFromCaller = () => controller.abort();
            if (callerSignal?.aborted) controller.abort();
            else callerSignal?.addEventListener?.('abort', abortFromCaller, { once: true });
            const timer = requestTimeoutMs > 0 ? global.setTimeout(() => {
                timedOut = true;
                controller.abort();
            }, requestTimeoutMs) : null;
            return {
                signal: controller.signal,
                callerAborted: () => callerSignal?.aborted === true,
                timedOut: () => timedOut,
                cleanup: () => {
                    if (timer !== null) global.clearTimeout(timer);
                    callerSignal?.removeEventListener?.('abort', abortFromCaller);
                },
            };
        }

        async function requestOnce(method, path, body, requestOptions = {}) {
            const normalizedMethod = String(method).toUpperCase();
            const headers = { Accept: 'application/json' };
            headers['X-Request-ID'] = requestOptions.requestId;
            const options = { method: normalizedMethod, headers, credentials: 'same-origin' };
            if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
            if (MUTATION_METHODS.has(normalizedMethod)) headers['X-CSRF-Token'] = csrf;
            const idempotencyKey = requestOptions && typeof requestOptions.idempotencyKey === 'string'
                ? requestOptions.idempotencyKey.trim()
                : '';
            if (idempotencyKey !== '') headers['X-Idempotency-Key'] = idempotencyKey;

            const url = buildApiUrl(apiBase, path);
            const requestTimeoutMs = requestOptions && Object.prototype.hasOwnProperty.call(requestOptions, 'timeoutMs')
                ? Math.max(0, Number(requestOptions.timeoutMs) || 0)
                : defaultTimeoutMs;
            const lifecycle = createRequestLifecycle(requestOptions?.signal, requestTimeoutMs);
            options.signal = lifecycle.signal;
            try {
                let response;
                try {
                    response = await fetchImpl(url, options);
                } catch {
                    if (lifecycle.callerAborted()) {
                        throw new LearnerApiError(0, 'REQUEST_ABORTED', 'Yêu cầu đã được hủy.');
                    }
                    if (lifecycle.timedOut()) {
                        throw new LearnerApiError(0, 'REQUEST_TIMEOUT', 'Máy chủ phản hồi quá lâu. Vui lòng thử lại.');
                    }
                    throw new LearnerApiError(0, 'NETWORK_ERROR', 'Không thể kết nối đến máy chủ.');
                }

                let payload;
                try {
                    if (!response || typeof response.json !== 'function') throw new TypeError('Missing JSON response');
                    payload = await response.json();
                } catch {
                    if (lifecycle.callerAborted()) {
                        throw new LearnerApiError(0, 'REQUEST_ABORTED', 'Yêu cầu đã được hủy.');
                    }
                    if (lifecycle.timedOut()) {
                        throw new LearnerApiError(0, 'REQUEST_TIMEOUT', 'Máy chủ phản hồi quá lâu. Vui lòng thử lại.');
                    }
                    const malformed = createInvalidResponseError(getResponseStatus(response));
                    notifyUnauthorized(malformed);
                    throw malformed;
                }

                if (response.ok !== true) {
                    const normalized = normalizeApiError(response, payload);
                    notifyUnauthorized(normalized);
                    throw normalized;
                }

                if (!payload || typeof payload !== 'object' || !Object.prototype.hasOwnProperty.call(payload, 'data')) {
                    throw createInvalidResponseError(getResponseStatus(response));
                }

                return payload.data;
            } finally {
                lifecycle.cleanup();
            }
        }

        async function request(method, path, body, requestOptions = {}) {
            const normalizedMethod = String(method).toUpperCase();
            const logicalRequestOptions = { ...requestOptions, requestId: createRequestId() };
            const requestedRetryCount = Number.parseInt(requestOptions?.retryCount, 10);
            const retryCount = normalizedMethod === 'GET'
                ? (Number.isInteger(requestedRetryCount)
                    ? Math.max(0, Math.min(2, requestedRetryCount))
                    : defaultGetRetryCount)
                : 0;
            for (let attempt = 0; ; attempt += 1) {
                try {
                    return await requestOnce(normalizedMethod, path, body, logicalRequestOptions);
                } catch (error) {
                    const retryable = error instanceof LearnerApiError
                        && (error.code === 'NETWORK_ERROR'
                            || error.code === 'REQUEST_TIMEOUT'
                            || [502, 503, 504].includes(error.status));
                    if (!retryable || attempt >= retryCount || logicalRequestOptions.signal?.aborted) throw error;
                    await wait(defaultRetryDelayMs);
                }
            }
        }

        return {
            get: (path, requestOptions) => request('GET', path, undefined, requestOptions),
            send: (method, path, body, requestOptions) => request(String(method).toUpperCase(), path, body, requestOptions),
            setCsrfToken: (token) => { csrf = String(token || ''); },
        };
    }

    const api = { createLearnerApiClient, LearnerApiError };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.TalentHubLearnerApi = api;
})(typeof window !== 'undefined' ? window : globalThis);
