/**
 * Post-assessment AI summary controller.
 * Reads a persisted roadmap first and creates one idempotent analysis only when needed.
 */
(function initLearnerAiSummary(global) {
    'use strict';

    const STORAGE_KEY = 'talenthub.ai.summary.idempotency.v1';
    const READY_STATES = new Set(['ready_model', 'stale_model']);

    function shouldAutoAnalyze(search) {
        if (typeof search !== 'string' || !search.startsWith('?')) return false;
        return new URLSearchParams(search).get('ai') === 'analyze';
    }

    function defaultIdempotencyKey() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return `roadmap-summary-${global.crypto.randomUUID()}`;
        }
        return `roadmap-summary-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
    }

    function createSummaryApiClient(factory, suppliedDocument = null) {
        const targetDocument = suppliedDocument || global.document;
        let csrfToken = '';
        try {
            csrfToken = JSON.parse(targetDocument?.getElementById('learner-session-boot')?.textContent || '{}').csrfToken || '';
        } catch {
            csrfToken = '';
        }
        return factory({ baseUrl: '/app/learner/api/v1', csrfToken, timeoutMs: 45000 });
    }

    function createAiSummaryController({ api, view, storage, createIdempotencyKey = defaultIdempotencyKey,
        wait = (ms) => new Promise(resolve => global.setTimeout(resolve, ms)), maxPolls = 30 }) {
        if (!api || typeof api.get !== 'function' || typeof api.send !== 'function') {
            throw new TypeError('A learner roadmap API client is required.');
        }
        if (!view || typeof view.open !== 'function' || typeof view.render !== 'function' || typeof view.close !== 'function') {
            throw new TypeError('An AI summary view is required.');
        }
        const sessionStorage = storage && typeof storage.getItem === 'function' ? storage : null;
        let inFlight = null;

        function idempotencyKey() {
            const existing = sessionStorage?.getItem(STORAGE_KEY);
            if (typeof existing === 'string' && /^[A-Za-z0-9_-]{16,100}$/.test(existing)) return existing;
            const created = createIdempotencyKey();
            sessionStorage?.setItem(STORAGE_KEY, created);
            return created;
        }

        function renderResult(result) {
            const state = typeof result?.state === 'string' ? result.state : 'error';
            view.render(state, result || {});
            if (state !== 'pending') sessionStorage?.removeItem(STORAGE_KEY);
            return result;
        }

        function run() {
            if (inFlight !== null) return inFlight;
            view.open();
            view.render('loading', {});
            inFlight = Promise.resolve()
                .then(() => api.get('/ai-roadmap.php'))
                .then((current) => {
                    if (current?.state === 'ready_model' || current?.state === 'pending') return current;
                    if (!['not_generated', 'ready_rule', 'fallback_rule', 'stale_model'].includes(current?.state)) return current;
                    return api.send(
                        'POST',
                        '/ai-roadmap.php',
                        { action: current?.state === 'not_generated' ? 'generate' : 'refresh' },
                        { idempotencyKey: idempotencyKey() },
                    );
                })
                .then(async (result) => {
                    for (let poll = 0; result?.state === 'pending' && poll < maxPolls; poll++) {
                        view.render('pending', result);
                        await wait(2000);
                        result = await api.get('/ai-roadmap.php');
                    }
                    // A failed run disappears from the pending query. Release
                    // its key and let the learner explicitly start a fresh run.
                    if (result?.state === 'not_generated') result = {state:'provider_unavailable'};
                    return renderResult(result);
                })
                .catch((error) => {
                    const result = { state: 'error', message: error?.message || 'Không thể kết nối dịch vụ AI.' };
                    view.render('error', result);
                    return result;
                })
                .finally(() => { inFlight = null; });
            return inFlight;
        }

        return {
            run,
            retry: run,
            defer() { view.close(); },
        };
    }

    function createDomAiSummaryView(modal) {
        const nodes = {
            eyebrow: modal.querySelector('[data-ai-summary-eyebrow]'),
            title: modal.querySelector('[data-ai-summary-title]'),
            live: modal.querySelector('[data-ai-summary-live]'),
            spinner: modal.querySelector('[data-ai-summary-spinner]'),
            message: modal.querySelector('[data-ai-summary-message]'),
            summary: modal.querySelector('[data-ai-summary-text]'),
            retry: modal.querySelector('[data-ai-summary-retry]'),
            detail: modal.querySelector('[data-ai-summary-detail]'),
        };

        function setText(node, value) {
            if (node) node.textContent = String(value ?? '');
        }

        return {
            open() {
                if (global.LearnerUI && typeof global.LearnerUI.openModal === 'function') {
                    global.LearnerUI.openModal(modal);
                } else {
                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');
                }
            },
            close() {
                if (global.LearnerUI && typeof global.LearnerUI.closeModal === 'function') {
                    global.LearnerUI.closeModal(modal);
                } else {
                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                }
            },
            render(state, payload) {
                const isLoading = state === 'loading' || state === 'pending';
                if (nodes.spinner) nodes.spinner.hidden = !isLoading;
                if (nodes.retry) nodes.retry.hidden = !['error', 'engine_failure', 'source_unavailable', 'provider_unavailable', 'ready_rule', 'fallback_rule', 'stale_model', 'ai_unavailable', 'consent_required', 'pending'].includes(state);
                if (nodes.detail) nodes.detail.hidden = !READY_STATES.has(state);

                if (state === 'ready_model') {
                    setText(nodes.eyebrow, 'Tóm tắt từ AI');
                    setText(nodes.title, 'Lộ trình phát triển của bạn đã sẵn sàng');
                    setText(nodes.message, 'AI đã tổng hợp dữ liệu đánh giá và tạo lộ trình 90 ngày.');
                    setText(nodes.summary, payload?.executive_summary);
                } else if (state === 'stale_model') {
                    setText(nodes.eyebrow, 'Bản phân tích AI gần nhất');
                    setText(nodes.title, 'AI đang cập nhật theo dữ liệu mới');
                    setText(nodes.message, payload?.next_retry_at ? `Hệ thống sẽ thử lại lúc ${payload.next_retry_at}.` : 'Bạn vẫn có thể xem kết quả gần nhất và thử cập nhật lại.');
                    setText(nodes.summary, payload?.executive_summary);
                } else if (state === 'ready_rule' || state === 'fallback_rule') {
                    setText(nodes.eyebrow, 'Cần phân tích AI');
                    setText(nodes.title, 'Chưa có lộ trình từ AI');
                    setText(nodes.message, 'Nhấn thử lại để AI phân tích kết quả đánh giá và tạo lộ trình của bạn.');
                    setText(nodes.summary, '');
                } else if (state === 'loading') {
                    setText(nodes.eyebrow, 'AI đang phân tích');
                    setText(nodes.title, 'Đang tạo lộ trình dành riêng cho bạn');
                    setText(nodes.message, 'Quá trình này có thể mất khoảng 30 giây. Vui lòng giữ trang đang mở.');
                    setText(nodes.summary, '');
                } else if (state === 'pending') {
                    setText(nodes.eyebrow, 'Đang xử lý');
                    setText(nodes.title, 'Phân tích của bạn đang được hoàn tất');
                    setText(nodes.message, 'Kết quả sẽ tự cập nhật tại đây khi AI hoàn tất.');
                    setText(nodes.summary, '');
                } else if (state === 'consent_required') {
                    setText(nodes.eyebrow, 'Cần thử lại phân tích');
                    setText(nodes.title, 'Chưa thể bắt đầu phân tích AI');
                    setText(nodes.message, 'AI được bật sẵn cho tài khoản học viên. Vui lòng thử lại để cập nhật phiên phân tích.');
                    setText(nodes.summary, '');
                } else if (state === 'insufficient_data' || state === 'data_insufficient') {
                    setText(nodes.eyebrow, 'Chưa đủ dữ liệu');
                    setText(nodes.title, 'Cần hoàn thành đủ bộ đánh giá');
                    setText(nodes.message, 'TalentHub chưa tìm thấy đủ bốn kết quả hợp lệ để tạo lộ trình.');
                    setText(nodes.summary, '');
                } else {
                    setText(nodes.eyebrow, 'Chưa thể phân tích');
                    setText(nodes.title, 'Dịch vụ AI đang tạm gián đoạn');
                    setText(nodes.message, payload?.message || 'Bạn có thể thử lại mà không cần làm lại bài đánh giá.');
                    setText(nodes.summary, '');
                }
                if (nodes.live) nodes.live.dataset.state = state;
            },
        };
    }

    function boot() {
        if (!shouldAutoAnalyze(global.location?.search || '')) return;
        const modal = global.document?.querySelector('[data-ai-summary-modal]');
        const apiFactory = global.TalentHubLearnerApi?.createLearnerApiClient;
        if (!modal || typeof apiFactory !== 'function') return;
        const controller = createAiSummaryController({
            api: createSummaryApiClient(apiFactory, global.document),
            view: createDomAiSummaryView(modal),
            storage: global.sessionStorage,
        });
        modal.querySelector('[data-ai-summary-retry]')?.addEventListener('click', () => controller.retry());
        modal.querySelectorAll('[data-ai-summary-defer]').forEach((button) => {
            button.addEventListener('click', () => controller.defer());
        });
        controller.run();
    }

    const exported = { shouldAutoAnalyze, createSummaryApiClient, createAiSummaryController, createDomAiSummaryView, boot };
    global.TalentHubLearnerAiSummary = exported;
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    if (global.document && typeof global.document.addEventListener === 'function') {
        global.document.addEventListener('DOMContentLoaded', boot);
    }
})(typeof window !== 'undefined' ? window : globalThis);
