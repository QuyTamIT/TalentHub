(function (global) {
    'use strict';

    function createController({ api, onResult }) {
        let saved = { state: 'not_generated', items: [] }, busy = false;
        async function run(generate) {
            if (busy) return;
            busy = true;
            onResult({ ...saved, state: 'loading', progress: 0 });
            let progress = 0;
            const timer = setInterval(() => {
                progress = Math.min(90, progress + 25);
                onResult({ ...saved, state: 'loading', progress });
            }, 500);
            try {
                const result = generate ? await api.send('POST', '/activity-matches.php', {}) : await api.get('/activity-matches.php');
                if (!result || !Array.isArray(result.items) || typeof result.state !== 'string') throw Error('Invalid payload');
                saved = { ...result, items: result.items, progress: 100 };
            } catch (error) {
                const denied = error.status === 401 || error.status === 403;
                saved = denied ? { state: 'error', items: [], error: true } : { ...saved, state: saved.items.length ? 'stale_model' : 'error', error: true };
                saved.errorCode = error.code || (denied ? 'AUTH_REQUIRED' : 'SERVICE_UNAVAILABLE');
            } finally {
                clearInterval(timer);
                busy = false;
                onResult(saved);
            }
        }
        return {
            generate: () => run(true),
            revalidate: async () => {
                if (busy) return;
                await run(false);
                if (!saved.error && ['stale_model', 'not_generated'].includes(saved.state)) await run(true);
            },
        };
    }

    function mount(root, api) {
        const doc = root.ownerDocument;
        const button = root.querySelector('[data-generate]');
        const status = root.querySelector('[data-status]');
        const statusWrap = root.querySelector('[data-status-wrap]');
        const progress = root.querySelector('progress');
        const triggerLabel = root.querySelector('[data-trigger-label]');
        const emptyInsight = root.querySelector('[data-empty-insight]');
        const banner = doc.getElementById('activity-match-banner');
        const bannerText = banner?.querySelector('[data-match-banner-text]');
        const bannerCount = banner?.querySelector('[data-match-count]');
        const resetBtn = banner?.querySelector('[data-match-reset]');
        const discovery = doc.querySelector('[data-activity-discovery-page]');
        const messages = {
            not_generated: 'Chưa có gợi ý. Nhấn nút để lọc hoạt động theo kỹ năng cần phát triển.',
            insufficient_data: 'Hồ sơ chưa có điểm kỹ năng. Hãy bổ sung hồ sơ hoặc hoàn thành đánh giá.',
            consent_required: 'Cần đồng ý sử dụng dữ liệu kỹ năng, đánh giá, nhận xét và hoạt động trong cài đặt AI.',
            no_matches: 'Chưa có hoạt động sát nhu cầu phát triển hiện tại. Bạn vẫn có thể khám phá danh sách bên dưới.',
            completed: 'Đã lọc và xếp hạng theo kỹ năng cần phát triển. AI giải thích độ phù hợp trên từng thẻ.',
            stale_model: 'Dữ liệu đã thay đổi hoặc chưa thể xác minh lại. Hãy làm mới trước khi quyết định.',
            error: 'Dịch vụ gợi ý tạm thời không khả dụng. Vui lòng thử lại sau.',
        };

        function exitMatchMode() {
            if (banner) banner.hidden = true;
            discovery?.dispatchEvent(new CustomEvent('activity-match-exit'));
        }

        function enterMatchMode(items) {
            if (banner) {
                banner.hidden = false;
                if (bannerCount) bannerCount.textContent = `${items.length} hoạt động phù hợp`;
                if (bannerText) bannerText.textContent = `Đang hiển thị ${items.length} hoạt động phù hợp theo kỹ năng cần phát triển (AI giải thích độ phù hợp).`;
            }
            discovery?.dispatchEvent(new CustomEvent('activity-match-enter', { detail: { items } }));
            const catalog = doc.getElementById('activity-catalog');
            if (catalog) catalog.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function renderEmptyInsight(payload) {
            if (!emptyInsight) return;
            emptyInsight.replaceChildren();
            const hasEmptyAnalysis = payload.state === 'no_matches' && typeof payload.analysis === 'string' && payload.analysis.trim() !== '';
            if (!hasEmptyAnalysis) {
                emptyInsight.hidden = true;
                return;
            }
            emptyInsight.hidden = false;
            const card = doc.createElement('article');
            card.className = 'learner-activity-matches__insight';
            const title = doc.createElement('h3');
            title.textContent = payload.no_match_reason === 'no_documented_needs'
                ? 'Bạn có thể khám phá thêm theo sở thích'
                : 'Chưa sát nhu cầu hiện tại, vẫn có thể khám phá';
            const analysis = doc.createElement('p');
            analysis.className = 'learner-activity-matches__analysis';
            const analysisLabel = doc.createElement('strong');
            analysisLabel.textContent = 'AI giải thích: ';
            analysis.append(analysisLabel, doc.createTextNode(payload.analysis));
            const invitation = doc.createElement('p');
            invitation.className = 'learner-activity-matches__invitation';
            invitation.textContent = 'Bạn vẫn có thể cân nhắc tham gia theo sở thích để mở rộng trải nghiệm và kết nối.';
            card.append(title, analysis, invitation);
            emptyInsight.append(card);
        }

        function render(payload) {
            const loading = payload.state === 'loading';
            root.setAttribute('aria-busy', String(loading));
            button.disabled = loading;
            doc.querySelectorAll('[data-match-kpi]').forEach((kpi) => {
                kpi.disabled = loading;
                kpi.setAttribute('aria-busy', String(loading));
            });
            if (triggerLabel) triggerLabel.textContent = loading ? 'Đang tìm hoạt động phù hợp...' : 'Gợi ý hoạt động phù hợp';
            if (statusWrap) statusWrap.hidden = false;
            if (progress) {
                progress.hidden = !loading;
                progress.value = payload.progress || 0;
            }
            const steps = ['Quét hồ sơ', 'Lọc hoạt động', 'Đối chiếu kỹ năng', 'Xếp hạng'];
            if (status) {
                status.textContent = loading
                    ? `${steps[Math.min(3, Math.floor((payload.progress || 0) / 25))]} — ${payload.progress || 0}%`
                    : (messages[payload.state] || messages.error);
                if (payload.state === 'no_matches' && payload.no_match_reason === 'no_activities') {
                    status.textContent = 'Hiện chưa có hoạt động đang mở, còn hạn và còn chỗ tại trường để đối chiếu với kỹ năng của bạn.';
                }
                if (payload.errorCode === 'AUTH_REQUIRED' || payload.errorCode === 'AUTHENTICATION_REQUIRED') {
                    status.textContent = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại để nhận gợi ý.';
                }
                if (payload.errorCode === 'ACTIVITY_MATCH_STORAGE_UNAVAILABLE') {
                    status.textContent = 'Gợi ý hoạt động chưa sẵn sàng vì hệ thống lưu kết quả chưa được thiết lập.';
                }
                if (payload.error && payload.state === 'stale_model') {
                    status.textContent += ' Chưa thể làm mới; danh sách dưới đây có thể là kết quả cũ.';
                }
            }

            renderEmptyInsight(payload);

            if (loading) return;

            if (payload.state === 'completed' && Array.isArray(payload.items) && payload.items.length > 0) {
                const kpiCount = doc.querySelector('[data-match-kpi-count]');
                if (kpiCount) kpiCount.textContent = String(payload.items.length);
                enterMatchMode(payload.items);
                return;
            }

            exitMatchMode();
        }

        const controller = createController({ api, onResult: render });
        let activated = false;
        function startMatch() {
            activated = true;
            button.setAttribute('aria-expanded', 'true');
            if (statusWrap) {
                statusWrap.hidden = false;
                statusWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return controller.generate();
        }
        button.addEventListener('click', startMatch);
        doc.querySelectorAll('[data-match-kpi]').forEach((kpi) => {
            kpi.addEventListener('click', startMatch);
        });
        resetBtn?.addEventListener('click', () => {
            exitMatchMode();
            if (statusWrap) statusWrap.hidden = true;
            if (status) status.textContent = '';
            if (emptyInsight) {
                emptyInsight.hidden = true;
                emptyInsight.replaceChildren();
            }
        });

        const view = doc.defaultView;
        if (view) {
            let lastCheck = 0;
            const refresh = () => {
                if (!activated || doc.hidden || Date.now() - lastCheck < 60000) return;
                lastCheck = Date.now();
                controller.revalidate();
            };
            view.addEventListener('focus', refresh);
            const timer = view.setInterval(refresh, 60000);
            view.addEventListener('pagehide', () => { view.clearInterval(timer); view.removeEventListener('focus', refresh); }, { once: true });
        }
        return controller;
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { createController, mount };
    if (global.document) {
        const root = global.document.querySelector('[data-activity-matches]');
        if (root && global.TalentHubLearnerApi) {
            mount(root, global.TalentHubLearnerApi.createLearnerApiClient({ baseUrl: '/app/learner/api/v1', timeoutMs: 180000 }));
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
