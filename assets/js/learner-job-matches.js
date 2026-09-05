(function initLearnerJobMatches(global) {
    'use strict';

    const ENDPOINT = '/ai-job-matches.php';
    const STAGES = ['Quét hồ sơ', 'Lọc vị trí', 'Gemini phân tích', 'Xếp hạng kết quả'];
    const STATE_MAP = Object.freeze({
        not_generated: 'not-generated', consent_required: 'consent-required',
        insufficient_data: 'insufficient-data', benchmark_insufficient: 'insufficient-data',
        catalog_insufficient: 'catalog-insufficient', no_matching_jobs: 'no-matches',
        ready_model: 'ready-model', stale_model: 'stale-model', pending: 'loading',
        provider_unavailable: 'source-error', rate_limited: 'source-error', invalid_response: 'source-error',
    });

    function text(value, fallback = '') { return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback; }
    function integerScore(value) { const score = Number(value); return Number.isInteger(score) && score >= 0 && score <= 100 ? score : null; }
    function boundedCount(value) { const count = Number(value); return Number.isInteger(count) && count >= 0 && count <= 20 ? count : null; }
    function mapJobMatchState(value) { return STATE_MAP[String(value || '')] || 'source-error'; }
    function isRecoverableGenerationState(payload) {
        return ['provider_unavailable', 'invalid_response'].includes(String(payload?.state || ''));
    }
    function isRetryableTransportError(error) {
        return ['NETWORK_ERROR', 'REQUEST_TIMEOUT'].includes(String(error?.code || ''))
            || [502, 503, 504].includes(Number(error?.status));
    }
    function jobMatchStatusLabel(state, payload = {}) {
        if (state === 'ready-model') return 'Phân tích vừa xong';
        if (state === 'stale-model') return 'Đang hiển thị kết quả gần nhất';
        if (state === 'no-matches') return payload.near_match ? 'Đã phân tích vị trí gần ngưỡng nhất' : 'Chưa có vị trí đạt ngưỡng';
        if (state === 'catalog-insufficient') return 'Chưa có vị trí phù hợp';
        if (state === 'consent-required') return 'Cần quyền dữ liệu AI';
        if (state === 'insufficient-data') return 'Chưa đủ dữ liệu hồ sơ';
        if (state === 'loading') return 'AI đang phân tích';
        if (state === 'not-generated') return 'Sẵn sàng phân tích';
        return 'Phân tích chưa khả dụng';
    }
    function labelCode(value) {
        const labels = { php: 'PHP', sql: 'SQL', python: 'Python', mlops: 'MLOps', machine_learning: 'Machine Learning', algorithms: 'Giải thuật', git: 'Git', teamwork: 'Làm việc nhóm', communication: 'Giao tiếp', data_analysis: 'Phân tích dữ liệu' };
        const code = text(value).toLowerCase();
        if (labels[code]) return labels[code];
        const words = code.replace(/[_-]+/g, ' ');
        return words ? words.charAt(0).toUpperCase() + words.slice(1) : '';
    }
    function stringList(value, limit = 8) { return Array.isArray(value) ? value.map(text).filter(Boolean).slice(0, limit) : []; }
    function normalizeStrengthDetails(value) {
        if (!Array.isArray(value)) return [];
        const details = []; const seen = new Set();
        for (const raw of value.slice(0, 20)) {
            const code = text(raw?.code).toLowerCase(); const label = text(raw?.label);
            const current = integerScore(raw?.current_score); const target = integerScore(raw?.target_score);
            if (!/^[a-z0-9]+(?:_[a-z0-9]+)*$/.test(code) || !label || current === null || target === null || current < target || seen.has(code)) continue;
            seen.add(code); details.push({ code, label, current_score: current, target_score: target });
        }
        return details;
    }
    function normalizeStrengthSummary(raw) {
        const strength_details = normalizeStrengthDetails(raw?.strength_details);
        let met_skill_count = boundedCount(raw?.met_skill_count);
        let benchmark_skill_count = boundedCount(raw?.benchmark_skill_count);
        if (met_skill_count === null || benchmark_skill_count === null || met_skill_count > benchmark_skill_count
            || (strength_details.length > 0 && met_skill_count !== strength_details.length)) {
            met_skill_count = null; benchmark_skill_count = null;
        }
        return { strength_details, met_skill_count, benchmark_skill_count };
    }
    function emptyStrengthGuidance(position) {
        let msg = 'Hiện tại hồ sơ chưa ghi nhận kỹ năng nào đạt mức benchmark yêu cầu';
        if (position?.title) msg += ` của vị trí ${position.title}`;
        const gapNames = Array.isArray(position?.gap_explanations) && position.gap_explanations.length > 0
            ? position.gap_explanations.map((item) => item.skill).filter(Boolean)
            : (Array.isArray(position?.gaps) ? position.gaps.filter(Boolean) : []);
        if (gapNames.length > 0) {
            const topGaps = gapNames.slice(0, 3).join(', ');
            msg += `. Để mở rộng cơ hội ứng tuyển, bạn nên bắt đầu tích lũy nền tảng ở các kỹ năng cốt lõi như ${topGaps}.`;
        } else {
            msg += '. Để nâng cao mức độ phù hợp và mở rộng cơ hội ứng tuyển, bạn nên tích lũy thêm các kỹ năng chuyên môn cốt lõi.';
        }
        msg += ' Hãy tích cực tham gia các dự án môn học, hoạt động thực tế hoặc làm bài đánh giá năng lực cùng giảng viên để hệ thống ghi nhận đầy đủ hồ sơ chuyên môn.';
        return msg;
    }
    const EMPTY_STRENGTH_GUIDANCE = emptyStrengthGuidance(null);
    function jobStrengthDisplayItems(values, rawDetails = [], position = null) {
        const details = normalizeStrengthDetails(rawDetails);
        if (details.length > 0) return details.map((detail) => `${detail.label} — ${detail.current_score}/${detail.target_score} · Đạt chuẩn`);
        return Array.isArray(values) && values.length > 0 ? values : [emptyStrengthGuidance(position)];
    }
    function jobStrengthProgressMessage(position) {
        const met = boundedCount(position?.met_skill_count); const total = boundedCount(position?.benchmark_skill_count);
        if (met === null || total === null || met <= 0 || met >= total) return '';
        const strengthNames = Array.isArray(position?.strength_details) && position.strength_details.length > 0
            ? position.strength_details.map((item) => item.label).filter(Boolean)
            : (Array.isArray(position?.strengths) ? position.strengths.filter(Boolean) : []);
        const gapNames = Array.isArray(position?.gap_explanations) && position.gap_explanations.length > 0
            ? position.gap_explanations.map((item) => item.skill).filter(Boolean)
            : (Array.isArray(position?.gaps) ? position.gaps.filter(Boolean) : []);
        let msg = `Hồ sơ hiện đã đạt chuẩn ${met}/${total} kỹ năng theo khung benchmark`;
        if (position?.title) msg += ` của vị trí ${position.title}`;
        msg += '. ';
        if (strengthNames.length > 0) {
            const topStrengths = strengthNames.slice(0, 3).join(', ');
            msg += `Bạn đã thể hiện năng lực tốt ở nhóm kỹ năng (${topStrengths}), tạo nền tảng chuyên môn bước đầu. `;
        } else {
            msg += 'Bạn đã có nền tảng ban đầu ở kỹ năng đạt chuẩn. ';
        }
        if (gapNames.length > 0) {
            const topGaps = gapNames.slice(0, 2).join(', ');
            msg += `Để nâng cao mức độ phù hợp và đáp ứng toàn diện hơn, bạn nên ưu tiên bồi dưỡng thêm các kỹ năng trọng yếu còn thiếu như ${topGaps}. `;
        } else {
            msg += 'Để nâng cao mức độ phù hợp, bạn nên tiếp tục trau dồi các kỹ năng chuyên môn còn lại. ';
        }
        msg += 'Hãy chủ động tham gia các dự án thực chiến, hoàn thành chứng chỉ chuyên môn hoặc đề xuất giảng viên đánh giá trực tiếp để bổ sung dữ liệu năng lực hoàn chỉnh.';
        return msg.trim();
    }

    function isSafeInternshipUrl(value) {
        if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || /\\|%2e|%2f|%5c/i.test(value)) return false;
        try {
            const url = new URL(value, 'https://talenthub.invalid');
            const id = url.searchParams.get('id') || '';
            return url.origin === 'https://talenthub.invalid'
                && url.pathname === '/app/learner/opportunity.php'
                && url.searchParams.get('type') === 'internship'
                && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(id);
        } catch { return false; }
    }

    function fitLabel(score) { return score >= 80 ? 'Rất phù hợp' : score >= 60 ? 'Phù hợp' : 'Cần phát triển thêm'; }
    function fitClass(score) { return score >= 80 ? 'is-strong' : score >= 60 ? 'is-good' : 'is-developing'; }

    function normalizeNearMatch(raw) {
        const id = text(raw?.catalog_id); const score = integerScore(raw?.match_score);
        const title = text(raw?.title); const analysis = text(raw?.analysis);
        if (!id || score === null || score >= 40 || !title || !analysis || !isSafeInternshipUrl(raw?.url)) return null;
        const strengthSummary = normalizeStrengthSummary(raw);
        return {
            enterprise_id: text(raw?.enterprise_id), enterprise_name: text(raw?.enterprise_name, 'Doanh nghiệp tuyển dụng'),
            catalog_id: id, title, url: raw.url, match_score: score, fit_label: 'Chưa phù hợp', fit_class: 'is-low', analysis,
            strengths: stringList(raw?.strength_skill_codes).map(labelCode),
            ...strengthSummary,
            gaps: stringList(raw?.gap_skill_codes).map(labelCode),
            gap_explanations: Array.isArray(raw?.gap_explanations) ? raw.gap_explanations.filter((item) => item && typeof item === 'object').slice(0, 8).map((item) => ({ skill: labelCode(item.skill_code), explanation: text(item.explanation) })).filter((item) => item.skill && item.explanation) : [],
        };
    }

    function normalizeJobMatchPayload(payload) {
        const groups = [];
        for (const rawGroup of (Array.isArray(payload?.enterprise_groups) ? payload.enterprise_groups.slice(0, 10) : [])) {
            const positions = [];
            const seen = new Set();
            for (const raw of (Array.isArray(rawGroup?.positions) ? rawGroup.positions.slice(0, 10) : [])) {
                const id = text(raw?.catalog_id); const score = integerScore(raw?.match_score);
                if (!id || seen.has(id) || score === null || score < 40 || !isSafeInternshipUrl(raw?.url)) continue;
                const title = text(raw?.title); const analysis = text(raw?.analysis);
                if (!title || !analysis) continue;
                seen.add(id);
                const strengthSummary = normalizeStrengthSummary(raw);
                positions.push({
                    catalog_id: id, title, url: raw.url, match_score: score,
                    fit_label: fitLabel(score), fit_class: fitClass(score), analysis,
                    strengths: stringList(raw?.strength_skill_codes).map(labelCode),
                    ...strengthSummary,
                    gaps: stringList(raw?.gap_skill_codes).map(labelCode),
                    gap_explanations: Array.isArray(raw?.gap_explanations) ? raw.gap_explanations.filter((item) => item && typeof item === 'object').slice(0, 8).map((item) => ({ skill: labelCode(item.skill_code), explanation: text(item.explanation) })).filter((item) => item.skill && item.explanation) : [],
                });
            }
            if (positions.length === 0) continue;
            groups.push({ enterprise_id: text(rawGroup?.enterprise_id), enterprise_name: text(rawGroup?.enterprise_name, 'Doanh nghiệp tuyển dụng'), positions });
        }
        return { ...payload, enterprise_groups: groups, near_match: normalizeNearMatch(payload?.near_match) };
    }

    function jobProgressAt(elapsedMs) {
        const seconds = Math.max(0, Math.floor((Number(elapsedMs) || 0) / 1000));
        const activeIndex = seconds < 5 ? 0 : seconds < 18 ? 1 : seconds < 40 ? 2 : 3;
        const ranges = [[0, 5, 8, 20], [5, 18, 20, 44], [18, 40, 44, 78], [40, 75, 78, 94]];
        const [from, to, start, end] = ranges[activeIndex];
        const percent = Math.min(94, Math.round(start + ((end - start) * Math.min(1, (seconds - from) / (to - from)))));
        return { activeIndex, percent, stages: STAGES.map((label, index) => ({ label, status: index < activeIndex ? 'done' : index === activeIndex ? 'active' : 'upcoming' })) };
    }

    function createJobMatchController({ api, view, createIdempotencyKey }) {
        if (!api || typeof api.get !== 'function' || typeof api.send !== 'function') throw new TypeError('Job matching requires an API client.');
        if (!view || typeof view.render !== 'function') throw new TypeError('Job matching requires a view.');
        let generation = null;
        const renderPayload = (payload) => {
            const state = mapJobMatchState(payload?.state);
            const normalized = state === 'ready-model' || state === 'stale-model' || state === 'no-matches' ? normalizeJobMatchPayload(payload) : payload;
            if ((state === 'ready-model' || state === 'stale-model') && normalized.enterprise_groups.length === 0) view.render('source-error', {});
            else view.render(state, normalized || {});
            return normalized;
        };
        return {
            async load() { view.render('loading', { initial: true }); try { return renderPayload(await api.get(ENDPOINT)); } catch { view.render('source-error', {}); return null; } },
            generate() {
                if (generation) return generation;
                view.render('loading', { initial: false });
                const request = async () => {
                    let lastError = null;
                    let requestKey = createIdempotencyKey();
                    for (let attempt = 1; attempt <= 2; attempt += 1) {
                        try {
                            const response = await api.send('POST', ENDPOINT, {}, {
                                idempotencyKey: requestKey, timeoutMs: 90000,
                            });
                            if (!isRecoverableGenerationState(response) || attempt === 2) return response;
                            requestKey = createIdempotencyKey();
                        } catch (error) {
                            lastError = error;
                            if (attempt === 2 || !isRetryableTransportError(error)) throw error;
                        }
                    }
                    throw lastError || new Error('Job analysis did not complete.');
                };
                generation = Promise.resolve().then(request)
                    .then(renderPayload).catch(() => { view.render('source-error', {}); return null; }).finally(() => { generation = null; });
                return generation;
            },
        };
    }

    function createJobMatchView(root) {
        const doc = root.ownerDocument || global.document;
        const status = root.querySelector('[data-job-ai-status]'); const progress = root.querySelector('[data-job-ai-progress]');
        const progressText = root.querySelector('[data-job-ai-progress-text]'); const progressPct = root.querySelector('[data-job-ai-progress-pct]');
        const progressBar = root.querySelector('[data-job-ai-progress-bar]'); const stageRoot = root.querySelector('[data-job-ai-progress-stages]');
        const list = root.querySelector('[data-job-ai-list]'); const nearMatch = root.querySelector('[data-job-ai-near-match]'); let timer = null; let started = 0;
        const panels = Object.fromEntries(['not-generated','consent-required','insufficient-data','catalog-insufficient','no-matches','source-error'].map((name) => [name, root.querySelector(`[data-job-ai-${name}]`)]));
        function clear(node) { if (node) while (node.firstChild) node.removeChild(node.firstChild); }
        function node(tag, className, value) { const item = doc.createElement(tag); if (className) item.className = className; if (value !== undefined) item.textContent = String(value); return item; }
        function hideAll() { Object.values(panels).forEach((item) => { if (item) item.hidden = true; }); progress.hidden = true; list.hidden = true; if (nearMatch) nearMatch.hidden = true; }
        function stopProgress() { if (timer !== null) global.clearInterval(timer); timer = null; }
        function updateProgress() { const snapshot = jobProgressAt(Date.now() - started); progressPct.textContent = `${snapshot.percent}%`; progressBar.style.width = `${snapshot.percent}%`; progress.querySelector('[role="progressbar"]')?.setAttribute('aria-valuenow', String(snapshot.percent)); progressText.textContent = `Đang ${snapshot.stages[snapshot.activeIndex].label.toLowerCase()}...`; Array.from(stageRoot.children).forEach((item, index) => { item.className = snapshot.stages[index]?.status === 'active' ? 'is-active' : snapshot.stages[index]?.status === 'done' ? 'is-done' : ''; }); }
        function renderFactStrengthList(items) {
            const strengthList = node('ul');
            items.forEach((value) => {
                const li = node('li');
                const raw = String(value || '').trim();
                const match = raw.match(/^(.+?)\s*[—–-]\s*(\d+\/\d+)\s*·\s*(.+)$/);
                if (match) {
                    const title = node('span', 'learner-job-fact-title', match[1]);
                    const meta = node('span', 'learner-job-fact-meta');
                    meta.append(node('span', 'learner-job-fact-score', match[2]), node('span', 'learner-job-fact-status', match[3]));
                    li.append(title, meta);
                } else {
                    li.append(node('span', 'learner-job-fact-title', raw));
                }
                strengthList.appendChild(li);
            });
            return strengthList;
        }
        function renderFactGapList(position) {
            const gapList = node('ul');
            const gaps = position.gap_explanations && position.gap_explanations.length > 0
                ? position.gap_explanations.map((item) => ({ skill: item.skill, explanation: item.explanation }))
                : (position.gaps && position.gaps.length > 0
                    ? position.gaps.map((skill) => ({ skill, explanation: '' }))
                    : []);
            if (gaps.length === 0) {
                const li = node('li');
                li.appendChild(node('span', 'learner-job-gap-desc', 'Không có khoảng kỹ năng đáng kể.'));
                gapList.appendChild(li);
                return gapList;
            }
            gaps.forEach((item) => {
                const li = node('li');
                if (item.skill && item.explanation) {
                    li.append(
                        node('strong', 'learner-job-gap-title', item.skill),
                        node('span', 'learner-job-gap-desc', item.explanation)
                    );
                } else {
                    li.append(node('span', 'learner-job-gap-desc', item.skill || item.explanation));
                }
                gapList.appendChild(li);
            });
            return gapList;
        }
        function renderGuidanceBox(position) {
            const progressMessage = jobStrengthProgressMessage(position);
            if (!progressMessage) return null;
            const guidance = node('div', 'learner-job-strength-guidance');
            const guidanceHeader = node('div', 'learner-job-strength-guidance__header');
            guidanceHeader.append(node('span', 'learner-job-strength-guidance__icon', '💡'), node('strong', '', 'Khuyến nghị định hướng phát triển năng lực'));
            guidance.append(guidanceHeader, node('p', '', progressMessage));
            return guidance;
        }
        function renderResults(payload) {
            clear(list);
            for (const group of payload.enterprise_groups) {
                const article = node('article', 'learner-job-enterprise learner-card');
                const heading = node('header', 'learner-job-enterprise__header');
                const headingCopy = node('div'); headingCopy.append(node('span', 'learner-eyebrow', 'Doanh nghiệp phù hợp'), node('h3', '', group.enterprise_name));
                heading.append(headingCopy, node('span', 'learner-job-enterprise__count', `${group.positions.length} vị trí phù hợp`)); article.appendChild(heading);
                const positions = node('div', 'learner-job-enterprise__positions');
                for (const position of group.positions) {
                    const card = node('section', 'learner-job-position');
                    const top = node('div', 'learner-job-position__top'); const identity = node('div'); identity.append(node('h4', '', position.title), node('span', `learner-job-fit ${position.fit_class}`, position.fit_label));
                    const score = node('div', `learner-job-score ${position.fit_class}`); score.append(node('strong', '', String(position.match_score)), node('span', '', '/100')); top.append(identity, score); card.appendChild(top);
                    const track = node('div', 'learner-job-score__track'); const fill = node('span', ''); fill.style.width = `${position.match_score}%`; track.appendChild(fill); card.appendChild(track);
                    const narrative = node('div', 'learner-job-position__analysis'); narrative.append(node('strong', '', 'Gemini phân tích'), node('p', '', position.analysis)); card.appendChild(narrative);
                    const isNearMatch = false;
                    const hasMetSkills = (Array.isArray(position.strength_details) && position.strength_details.length > 0)
                        || (Array.isArray(position.strengths) && position.strengths.length > 0);

                    if (!hasMetSkills) {
                        const notice = node('div', 'learner-job-strength-notice');
                        const noticeHeader = node('div', 'learner-job-strength-notice__header');
                        noticeHeader.append(node('span', 'learner-job-strength-notice__icon', '🎯'), node('strong', '', 'Năng lực đã có'));
                        notice.append(noticeHeader, node('p', '', emptyStrengthGuidance(position)));
                        card.appendChild(notice);

                        const gaps = node('div', 'learner-job-position__facts is-gap is-full-width');
                        const heading = isNearMatch ? 'Nguyên nhân chưa phù hợp' : 'Khoảng cần cải thiện';
                        gaps.appendChild(node('h5', '', heading));
                        gaps.appendChild(renderFactGapList(position));
                        card.appendChild(gaps);
                    } else {
                        const columns = node('div', 'learner-job-position__columns');
                        const strengthsHeading = isNearMatch ? 'Năng lực đã có' : 'Điểm mạnh phù hợp';
                        const gapsHeading = isNearMatch ? 'Nguyên nhân chưa phù hợp' : 'Khoảng cần cải thiện';
                        const strengths = node('div', 'learner-job-position__facts is-strength');
                        strengths.appendChild(node('h5', '', strengthsHeading));
                        strengths.appendChild(renderFactStrengthList(jobStrengthDisplayItems(position.strengths, position.strength_details, position)));
                        const gaps = node('div', 'learner-job-position__facts is-gap');
                        gaps.appendChild(node('h5', '', gapsHeading));
                        gaps.appendChild(renderFactGapList(position));
                        columns.append(strengths, gaps);
                        card.appendChild(columns);
                        const guidance = renderGuidanceBox(position);
                        if (guidance) card.appendChild(guidance);
                    }
                    const actions = node('div', 'learner-job-position__actions'); const link = node('a', 'learner-btn learner-btn--primary', 'Xem chi tiết vị trí'); link.href = position.url; actions.appendChild(link); card.appendChild(actions); positions.appendChild(card);
                }
                article.appendChild(positions); list.appendChild(article);
            }
            list.hidden = false;
        }
        function renderNearMatch(position) {
            clear(nearMatch);
            const article = node('article', 'learner-job-near-match learner-card');
            const banner = node('div', 'learner-job-near-match__banner');
            const bannerCopy = node('div'); bannerCopy.append(node('span', 'learner-eyebrow', 'VỊ TRÍ GẦN NGƯỠNG NHẤT'), node('h3', '', position.enterprise_name));
            banner.append(bannerCopy, node('span', 'learner-job-fit is-low', 'Chưa đạt ngưỡng 40')); article.appendChild(banner);
            const card = node('section', 'learner-job-position is-near-match');
            const top = node('div', 'learner-job-position__top'); const identity = node('div'); identity.append(node('h4', '', position.title), node('span', 'learner-job-fit is-low', position.fit_label));
            const score = node('div', 'learner-job-score is-low'); score.append(node('strong', '', String(position.match_score)), node('span', '', '/100')); top.append(identity, score); card.appendChild(top);
            const track = node('div', 'learner-job-score__track is-low'); const fill = node('span'); fill.style.width = `${position.match_score}%`; track.appendChild(fill); card.appendChild(track);
            const narrative = node('div', 'learner-job-position__analysis is-low'); narrative.append(node('strong', '', 'Gemini phân tích vì sao chưa phù hợp'), node('p', '', position.analysis)); card.appendChild(narrative);
            const isNearMatch = true;
            const hasMetSkills = (Array.isArray(position.strength_details) && position.strength_details.length > 0)
                || (Array.isArray(position.strengths) && position.strengths.length > 0);

            if (!hasMetSkills) {
                const notice = node('div', 'learner-job-strength-notice');
                const noticeHeader = node('div', 'learner-job-strength-notice__header');
                noticeHeader.append(node('span', 'learner-job-strength-notice__icon', '🎯'), node('strong', '', 'Năng lực đã có'));
                notice.append(noticeHeader, node('p', '', emptyStrengthGuidance(position)));
                card.appendChild(notice);

                const gaps = node('div', 'learner-job-position__facts is-gap is-full-width');
                const heading = isNearMatch ? 'Nguyên nhân chưa phù hợp' : 'Khoảng cần cải thiện';
                gaps.appendChild(node('h5', '', heading));
                gaps.appendChild(renderFactGapList(position));
                card.appendChild(gaps);
            } else {
                const columns = node('div', 'learner-job-position__columns');
                const strengthsHeading = isNearMatch ? 'Năng lực đã có' : 'Điểm mạnh phù hợp';
                const gapsHeading = isNearMatch ? 'Nguyên nhân chưa phù hợp' : 'Khoảng cần cải thiện';
                const strengths = node('div', 'learner-job-position__facts is-strength');
                strengths.appendChild(node('h5', '', strengthsHeading));
                strengths.appendChild(renderFactStrengthList(jobStrengthDisplayItems(position.strengths, position.strength_details, position)));
                const gaps = node('div', 'learner-job-position__facts is-gap');
                gaps.appendChild(node('h5', '', gapsHeading));
                gaps.appendChild(renderFactGapList(position));
                columns.append(strengths, gaps);
                card.appendChild(columns);
                const guidance = renderGuidanceBox(position);
                if (guidance) card.appendChild(guidance);
            }
            const actions = node('div', 'learner-job-position__actions'); const detail = node('a', 'learner-btn learner-btn--outline', 'Xem chi tiết vị trí'); detail.href = position.url; const skillGap = node('a', 'learner-btn learner-btn--primary', 'Xem Skill Gap và hoạt động'); skillGap.href = 'ai-recommendations.php'; actions.append(detail, skillGap); card.appendChild(actions);
            article.appendChild(card); nearMatch.appendChild(article); nearMatch.hidden = false;
        }
        return { render(state, payload = {}) { stopProgress(); hideAll(); root.dataset.state = state; status.textContent = jobMatchStatusLabel(state, payload); if (state === 'loading') { progress.hidden = false; started = Date.now(); updateProgress(); if (!payload.initial) timer = global.setInterval(updateProgress, 1000); return; } if (state === 'ready-model' || state === 'stale-model') { renderResults(payload); return; } if (state === 'no-matches' && payload.near_match) { renderNearMatch(payload.near_match); return; } const panel = panels[state] || panels['source-error']; if (panel) panel.hidden = false; } };
    }

    function createJobAiCollapse({ button, body, root } = {}) {
        if (!button || !body) return null;
        const targetRoot = root || button.closest('[data-job-matches]');

        function apply(expanded) {
            button.setAttribute('aria-expanded', String(expanded));
            button.textContent = expanded ? 'Thu gọn' : 'Mở rộng';
            body.hidden = !expanded;
            if (targetRoot) targetRoot.classList.toggle('is-collapsed', !expanded);
        }

        button.addEventListener('click', () => {
            apply(button.getAttribute('aria-expanded') !== 'true');
        });
        apply(true);
        return { apply, button, body };
    }

    function defaultKey() { return `job-ui-${global.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`}`; }
    function mountJobMatches() {
        const root = global.document?.querySelector('[data-job-matches]'); if (!root || root.__jobController) return root?.__jobController || null;
        const api = global.TalentHubLearnerClient; const view = createJobMatchView(root);
        createJobAiCollapse({
            button: root.querySelector('[data-job-ai-collapse]'),
            body: root.querySelector('[data-job-ai-body]'),
            root,
        });
        if (!api) { view.render('source-error'); return null; }
        const controller = createJobMatchController({ api, view, createIdempotencyKey: defaultKey });
        global.document.querySelectorAll('[data-job-ai-trigger]').forEach((button) => button.addEventListener('click', () => { button.disabled = true; controller.generate().finally(() => { button.disabled = false; }); }));
        controller.load(); root.__jobController = controller; return controller;
    }

    const exported = { mapJobMatchState, jobMatchStatusLabel, normalizeJobMatchPayload, isSafeInternshipUrl, jobProgressAt, jobStrengthDisplayItems, jobStrengthProgressMessage, createJobMatchController, createJobMatchView, createJobAiCollapse, mountJobMatches };
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    global.TalentHubJobMatches = exported;
    if (global.document) { if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', mountJobMatches, { once: true }); else mountJobMatches(); }
})(typeof window !== 'undefined' ? window : globalThis);
