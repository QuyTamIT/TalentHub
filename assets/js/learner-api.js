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
        constructor(status, code, message, details = [], requestId = '') {
            super(message);
            this.name = 'LearnerApiError';
            this.status = status;
            this.code = code;
            this.details = Array.isArray(details) ? details : [];
            this.requestId = requestId;
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
        return new LearnerApiError(
            getResponseStatus(response),
            typeof error.code === 'string' && error.code ? error.code : 'REQUEST_FAILED',
            typeof error.message === 'string' && error.message ? error.message : 'Không thể hoàn tất yêu cầu.',
            error.details,
            typeof meta.requestId === 'string' ? meta.requestId : ''
        );
    }

    function createLearnerApiClient({
        baseUrl = '/api/v1',
        csrfToken = '',
        fetchImpl = global.fetch,
        onUnauthorized = () => {},
    } = {}) {
        if (typeof fetchImpl !== 'function') throw new TypeError('fetchImpl must be a function');
        const apiBase = normalizeApiBase(baseUrl);
        const unauthorizedHandler = typeof onUnauthorized === 'function' ? onUnauthorized : () => {};
        let csrf = String(csrfToken || '');

        function notifyUnauthorized(error) {
            if (error.status !== 401) return;
            try {
                unauthorizedHandler(error);
            } catch {
                // Preserve the normalized API error even if the caller's UI callback fails.
            }
        }

        async function request(method, path, body) {
            const normalizedMethod = String(method).toUpperCase();
            const headers = { Accept: 'application/json' };
            const options = { method: normalizedMethod, headers, credentials: 'same-origin' };
            if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
            if (MUTATION_METHODS.has(normalizedMethod)) headers['X-CSRF-Token'] = csrf;

            const url = buildApiUrl(apiBase, path);
            let response;
            try {
                response = await fetchImpl(url, options);
            } catch {
                throw new LearnerApiError(0, 'NETWORK_ERROR', 'Không thể kết nối đến máy chủ.');
            }

            let payload;
            try {
                if (!response || typeof response.json !== 'function') throw new TypeError('Missing JSON response');
                payload = await response.json();
            } catch {
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
        }

        return {
            get: (path) => request('GET', path),
            send: (method, path, body) => request(String(method).toUpperCase(), path, body),
            setCsrfToken: (token) => { csrf = String(token || ''); },
        };
    }

    const api = { createLearnerApiClient, LearnerApiError };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.TalentHubLearnerApi = api;
})(typeof window !== 'undefined' ? window : globalThis);
