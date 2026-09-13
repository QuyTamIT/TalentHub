(function (global) {
    'use strict';
    function createController({ api, render }) {
        let saved = { state: 'not_generated', items: [] }, busy = false;
        async function run(generate) {
            if (busy) return;
            busy = true;
            render({ ...saved, state: 'loading', progress: 0 });
            // Indicative animation only: the synchronous endpoint does not stream progress.
            let progress = 0;
            const timer = setInterval(() => {
                progress = Math.min(90, progress + 25);
                render({ ...saved, state: 'loading', progress });
            }, 500);
            try {
                const result = generate ? await api.send('POST', '/activity-matches.php', {}) : await api.get('/activity-matches.php');
                if (!result || !Array.isArray(result.items) || typeof result.state !== 'string') throw Error('Invalid payload');
                saved = { ...result, items: result.items.slice(0, 3), progress: 100 };
            } catch (error) {
                const denied = error.status === 401 || error.status === 403;
                saved = denied ? { state: 'error', items: [], error: true } : { ...saved, state: saved.items.length ? 'stale_model' : 'error', error: true };
                saved.errorCode = error.code || (denied ? 'AUTH_REQUIRED' : 'SERVICE_UNAVAILABLE');
            } finally {
                clearInterval(timer);
                busy = false;
                render(saved);
            }
        }
        return { load: () => run(false), generate: () => run(true), revalidate: async () => {
            if (busy) return;
            await run(false);
            if (!saved.error && ['stale_model', 'not_generated'].includes(saved.state)) await run(true);
        } };
    }

    function mount(root, api) {
        const doc = root.ownerDocument;
        const button = root.querySelector('[data-generate]');
        const cards = root.querySelector('[data-cards]');
        const status = root.querySelector('[data-status]');
        const progress = root.querySelector('progress');
        const toggle = root.querySelector('[data-toggle]');
        const body = root.querySelector('[data-body]');
        const panel = root.querySelector('[data-panel]');
        const triggerLabel = root.querySelector('[data-trigger-label]');
        const messages = {
            not_generated: 'Chưa phân tích. Hãy yêu cầu gợi ý dựa trên kỹ năng của bạn.',
            insufficient_data: 'Hồ sơ chưa có điểm kỹ năng. Hãy bổ sung hồ sơ hoặc hoàn thành đánh giá.',
            consent_required: 'Cần đồng ý sử dụng dữ liệu kỹ năng, đánh giá, nhận xét và hoạt động trong cài đặt AI.',
            no_matches: 'Chưa có hoạt động sát nhu cầu phát triển hiện tại. Bạn vẫn có thể khám phá danh sách hoạt động theo sở thích.',
            completed: 'AI phân tích dựa trên dữ liệu hiện tại. Điểm đối chiếu kỹ năng 0–100, không phải xác suất thành công.',
            stale_model: 'Dữ liệu đã thay đổi hoặc chưa thể xác minh lại. Hãy làm mới trước khi quyết định.',
            error: 'Dịch vụ gợi ý tạm thời không khả dụng. Đây là lỗi xử lý, không có nghĩa là không có hoạt động phù hợp. Vui lòng thử lại sau.',
        };
        function render(payload) {
            const loading = payload.state === 'loading';
            root.setAttribute('aria-busy', String(loading));
            button.disabled = loading;
            if (triggerLabel) triggerLabel.textContent = loading ? 'Đang phân tích...' : 'Phân tích lại hoạt động phù hợp';
            progress.hidden = !loading;
            progress.value = payload.progress || 0;
            const steps = ['Quét hồ sơ', 'Lọc hoạt động', 'Đối chiếu kỹ năng', 'Xếp hạng'];
            status.textContent = loading ? `${steps[Math.min(3, Math.floor(progress.value / 25))]} — ${progress.value}% (tiến trình minh họa)` : (messages[payload.state] || messages.error);
            const hasEmptyAnalysis = payload.state === 'no_matches' && typeof payload.analysis === 'string' && payload.analysis.trim() !== '';
            if (hasEmptyAnalysis) status.textContent = 'Đã đối chiếu kỹ năng của bạn với các hoạt động đang mở tại trường.';
            if (payload.state === 'no_matches' && payload.no_match_reason === 'no_activities') status.textContent = 'Hiện chưa có hoạt động đang mở, còn hạn và còn chỗ tại trường để đối chiếu với kỹ năng của bạn. Hãy quay lại khi có hoạt động mới.';
            if (payload.errorCode === 'AUTH_REQUIRED' || payload.errorCode === 'AUTHENTICATION_REQUIRED') status.textContent = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại để phân tích.';
            if (payload.errorCode === 'ACTIVITY_MATCH_STORAGE_UNAVAILABLE') status.textContent = 'Gợi ý hoạt động chưa sẵn sàng vì hệ thống lưu kết quả chưa được thiết lập. Vui lòng liên hệ quản trị viên. Đây không phải kết luận không có hoạt động phù hợp.';
            if (payload.error && payload.state === 'stale_model') status.textContent += ' Chưa thể làm mới; các thẻ dưới đây là kết quả cũ.';
            cards.replaceChildren();
            if (hasEmptyAnalysis) {
                const card = doc.createElement('article');
                card.className = 'learner-activity-matches__insight';
                const title = doc.createElement('h3');
                title.textContent = payload.no_match_reason === 'no_documented_needs'
                    ? 'Bạn có thể khám phá thêm theo sở thích'
                    : 'Chưa sát nhu cầu hiện tại, vẫn có thể khám phá';
                const analysis = doc.createElement('p');
                analysis.className = 'learner-activity-matches__analysis';
                analysis.textContent = payload.analysis;
                const invitation = doc.createElement('p');
                invitation.className = 'learner-activity-matches__invitation';
                invitation.textContent = 'Bạn vẫn có thể cân nhắc tham gia theo sở thích để mở rộng trải nghiệm và kết nối. Hãy xem nội dung cùng điều kiện tham gia trước khi đăng ký.';
                const browse = doc.createElement('a');
                browse.href = '#activity-catalog';
                browse.textContent = 'Khám phá các hoạt động bên dưới';
                card.append(title, analysis, invitation, browse);
                cards.append(card);
            }
            for (const item of payload.items) {
                const card = doc.createElement('article');
                const title = doc.createElement('h3'); title.textContent = String(item.title || 'Hoạt động');
                const score = doc.createElement('p'); score.textContent = `Điểm gợi ý: ${Math.max(0, Math.min(100, Number(item.score) || 0))}/100`;
                score.className = 'learner-activity-matches__score';
                const reason = doc.createElement('p'); reason.textContent = String(item.why_fit || '');
                const skills = doc.createElement('p'); skills.textContent = `Kỹ năng có thể rèn luyện: ${(Array.isArray(item.skills_to_develop) ? item.skills_to_develop : []).join(', ') || 'Củng cố kỹ năng hiện có'}`;
                const link = doc.createElement('a'); link.textContent = 'Xem chi tiết / Đăng ký';
                link.href = `activity-detail.php?id=${encodeURIComponent(String(item.activity_id))}`;
                card.append(title, score, reason, skills, link); cards.append(card);
            }
        }
        toggle.addEventListener('click', () => {
            body.hidden = !body.hidden;
            toggle.setAttribute('aria-expanded', String(!body.hidden));
            toggle.textContent = body.hidden ? 'Mở rộng' : 'Thu gọn';
        });
        const controller = createController({ api, render });
        panel.hidden = true;
        let activated = false;
        button.addEventListener('click', () => {
            activated = true;
            panel.hidden = false;
            body.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.textContent = 'Thu gọn';
            return controller.generate();
        });
        // Only revalidate an analysis the user has requested, while this page is visible.
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
        if (root && global.TalentHubLearnerApi) mount(root, global.TalentHubLearnerApi.createLearnerApiClient({ baseUrl: '/app/learner/api/v1', timeoutMs: 180000 }));
    }
})(typeof window !== 'undefined' ? window : globalThis);
