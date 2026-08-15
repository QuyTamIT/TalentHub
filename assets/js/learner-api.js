/**
 * Shared authenticated JSON API client for learner pages.
 */
(function initLearnerApi(global) {
    'use strict';

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

    function createLearnerApiClient({
        baseUrl = '/api/v1',
        csrfToken = '',
        fetchImpl = global.fetch,
        onUnauthorized = () => {},
    } = {}) {
        if (typeof fetchImpl !== 'function') throw new TypeError('fetchImpl must be a function');
        let csrf = csrfToken;

        async function request(method, path, body) {
            const headers = { Accept: 'application/json' };
            const options = { method, headers, credentials: 'same-origin' };
            if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-Token'] = csrf;
                options.body = JSON.stringify(body);
            }

            const response = await fetchImpl(`${baseUrl}${path}`, options);
            let payload;
            try {
                payload = await response.json();
            } catch {
                throw new LearnerApiError(response.status, 'INVALID_RESPONSE', 'Phản hồi máy chủ không hợp lệ.');
            }

            if (!response.ok) {
                const error = payload && payload.error ? payload.error : {};
                const normalized = new LearnerApiError(
                    response.status,
                    String(error.code || 'REQUEST_FAILED'),
                    String(error.message || 'Không thể hoàn tất yêu cầu.'),
                    error.details,
                    String(payload?.meta?.requestId || '')
                );
                if (response.status === 401) onUnauthorized(normalized);
                throw normalized;
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
