/**
 * TalentHub learner assessment API state controller and UI view.
 * Connects assessment flows (catalog, start/resume, autosave, submit, result)
 * to the authoritative learner API endpoints.
 */
(function initLearnerAssessment(global) {
    'use strict';

    function safeOnboardingDestination(value) {
        return typeof value === 'string'
            && value.startsWith('/app/learner/')
            && !value.startsWith('//')
            ? value
            : null;
    }

    function presentationState(payload) {
        const status = typeof payload?.status === 'string' ? payload.status : '';
        if (status === 'loading') return 'loading';
        if (status === 'saving') return 'saving';
        if (status === 'save-error') return 'save-error';
        if (status === 'submitting') return 'submitting';
        if (status === 'validation-error') return 'validation-error';
        if (status === 'expired') return 'expired';
        if (status === 'source-error' || status === 'source_unavailable') return 'source-error';
        if (status === 'complete' || status === 'submitted') return 'complete';
        if (status === 'ready' || status === 'in_progress' || status === 'not_started' || status === 'retake_locked') return 'ready';
        return 'source-error';
    }

    function defaultIdempotencyKey() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return `assessment-submit-${global.crypto.randomUUID()}`;
        }
        return `assessment-submit-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
    }

    function renderLikertOption(doc, option, selectedValue) {
        const value = typeof option === 'object' && option !== null ? option.value : option;
        const labelText = typeof option === 'object' && option !== null ? (option.label ?? option.value) : option;
        const label = doc.createElement('label');
        label.className = 'learner-likert-option';

        const input = doc.createElement('input');
        input.type = 'radio';
        input.name = 'assessment-answer';
        input.value = String(value ?? '');
        input.checked = String(selectedValue ?? '') === String(value ?? '');

        const surface = doc.createElement('span');
        surface.className = 'learner-likert-option__surface';
        const badge = doc.createElement('b');
        badge.className = 'learner-likert-option__value';
        badge.textContent = String(value ?? '');
        badge.setAttribute('aria-hidden', 'true');
        const text = doc.createElement('span');
        text.className = 'learner-likert-option__label';
        text.textContent = String(labelText ?? '');
        const check = doc.createElement('span');
        check.className = 'learner-likert-option__check';
        check.textContent = '✓';
        check.setAttribute('aria-hidden', 'true');

        surface.append(badge, text, check);
        label.append(input, surface);
        return label;
    }

    function createAssessmentController({ api, view, createIdempotencyKey = defaultIdempotencyKey }) {
        if (!api || typeof api.get !== 'function' || typeof api.send !== 'function') {
            throw new TypeError('A learner assessment API client is required.');
        }
        if (!view || typeof view.render !== 'function') {
            throw new TypeError('A learner assessment view is required.');
        }

        let currentAttempt = null;
        let currentCatalog = null;
        let currentQuestions = [];
        let currentResult = null;
        let lastAction = null;
        let inFlightSubmit = null;
        const inFlightSaves = new Map();

        function setAttempt(attempt) {
            currentAttempt = attempt && typeof attempt === 'object' ? attempt : null;
            if (Array.isArray(currentAttempt?.questions)) {
                currentQuestions = currentAttempt.questions;
            }
        }

        function getAttempt() {
            return currentAttempt;
        }

        function renderState(state, payload) {
            view.render(state, payload);
            return payload;
        }

        function renderSourceError(error) {
            return renderState('source-error', { status: 'source-error', error, attempt: currentAttempt });
        }

        async function loadCatalog(band) {
            lastAction = () => loadCatalog(band);
            view.render('loading', { band });
            try {
                const endpoint = band ? `/assessments.php?band=${encodeURIComponent(band)}` : '/assessments.php';
                const response = await api.get(endpoint);
                currentCatalog = response;
                return renderState('ready', currentCatalog);
            } catch (error) {
                return renderSourceError(error);
            }
        }

        async function loadDetail(code, band) {
            lastAction = () => loadDetail(code, band);
            view.render('loading', { code, band });
            try {
                let endpoint = `/assessments.php?code=${encodeURIComponent(code)}`;
                if (band) endpoint += `&band=${encodeURIComponent(band)}`;
                const response = await api.get(endpoint);
                currentQuestions = Array.isArray(response.questions) ? response.questions : [];
                return renderState('ready', response);
            } catch (error) {
                return renderSourceError(error);
            }
        }

        async function startOrResume(assessmentCode, educationBand) {
            lastAction = () => startOrResume(assessmentCode, educationBand);
            view.render('loading', { assessmentCode, educationBand });
            try {
                const response = await api.send('POST', '/assessment-attempts.php', {
                    assessmentCode,
                    educationBand,
                });
                setAttempt(response);

                // If attempt doesn't contain full question details, fetch owned attempt with questions
                if (!Array.isArray(currentAttempt?.questions) || currentAttempt.questions.length === 0) {
                    const fullAttempt = await api.get(`/assessment-attempts.php?attemptId=${encodeURIComponent(response.id)}`);
                    if (fullAttempt?.id) {
                        setAttempt(fullAttempt);
                    } else if (Array.isArray(fullAttempt?.questions)) {
                        currentAttempt.questions = fullAttempt.questions;
                    }
                }

                if (currentAttempt?.status === 'expired') {
                    return renderState('expired', currentAttempt);
                }
                if (currentAttempt?.status === 'submitted') {
                    return renderState('complete', currentAttempt);
                }
                return renderState('ready', currentAttempt);
            } catch (error) {
                if (error?.status === 422 || error?.code === 'VALIDATION_FAILED' || error?.code === 'RETAKE_LOCKED') {
                    return renderState('validation-error', { error, assessmentCode, educationBand });
                }
                return renderSourceError(error);
            }
        }

        async function loadAttempt(attemptId) {
            lastAction = () => loadAttempt(attemptId);
            view.render('loading', { attemptId });
            try {
                const response = await api.get(`/assessment-attempts.php?attemptId=${encodeURIComponent(attemptId)}`);
                setAttempt(response);

                if (response.status === 'expired') {
                    return renderState('expired', response);
                }
                if (response.status === 'submitted') {
                    return renderState('complete', response);
                }
                return renderState('ready', response);
            } catch (error) {
                return renderSourceError(error);
            }
        }

        async function loadResult(assessmentCode, educationBand, attemptId = '') {
            lastAction = () => loadResult(assessmentCode, educationBand, attemptId);
            view.render('loading', { assessmentCode, educationBand, attemptId });
            try {
                let endpoint = `/assessments.php?code=${encodeURIComponent(assessmentCode)}`;
                if (educationBand) endpoint += `&band=${encodeURIComponent(educationBand)}`;
                const detail = await api.get(endpoint);
                const history = Array.isArray(detail?.history) ? detail.history : [];
                const selected = history.find((item) => String(item?.id || '') === String(attemptId))
                    || history.find((item) => ['submitted', 'completed'].includes(item?.status));
                currentResult = selected?.result || selected || null;
                return renderState(currentResult ? 'complete' : 'ready', {
                    ...detail,
                    result: currentResult,
                    selectedAttempt: selected || null,
                });
            } catch (error) {
                return renderSourceError(error);
            }
        }

        async function loadHistory() {
            return api.get('/assessments.php?view=history');
        }

        function saveAnswer(questionId, answer) {
            if (!currentAttempt?.id) {
                const error = new Error('No active assessment attempt to save answers.');
                renderState('save-error', { error });
                return Promise.reject(error);
            }

            if (!currentAttempt.answers) {
                currentAttempt.answers = {};
            }
            currentAttempt.answers[questionId] = answer;

            const existingEntry = inFlightSaves.get(questionId);
            if (existingEntry) {
                existingEntry.latestAnswer = answer;
                return existingEntry.promise;
            }

            const attemptId = currentAttempt.id;
            view.render('saving', { questionId, answer, attempt: currentAttempt });

            const entry = { latestAnswer: answer, promise: null };
            const sendLatest = async (sentAnswer, response) => {
                let latestResponse = response;
                let latestSentAnswer = sentAnswer;
                while (entry.latestAnswer !== latestSentAnswer) {
                    latestSentAnswer = entry.latestAnswer;
                    latestResponse = await api.send('PATCH', '/assessment-answers.php', {
                        attemptId,
                        questionId,
                        answer: latestSentAnswer,
                    });
                }
                if (latestResponse?.answers) {
                    currentAttempt.answers = latestResponse.answers;
                }
                view.render('ready', currentAttempt);
                inFlightSaves.delete(questionId);
                return latestResponse;
            };

            entry.promise = Promise.resolve(
                api.send('PATCH', '/assessment-answers.php', {
                    attemptId,
                    questionId,
                    answer,
                })
            )
                .then((response) => {
                    if (entry.latestAnswer !== answer) {
                        // Keep the public in-flight promise compatible with the
                        // coalescing contract while flushing the newest answer.
                        view.render('ready', currentAttempt);
                        void sendLatest(answer, response).catch((error) => {
                            view.render('save-error', {
                                error,
                                questionId,
                                answer: entry.latestAnswer,
                                attempt: currentAttempt,
                            });
                            inFlightSaves.delete(questionId);
                        });
                        return response;
                    }
                    if (response?.answers) {
                        currentAttempt.answers = response.answers;
                    }
                    view.render('ready', currentAttempt);
                    inFlightSaves.delete(questionId);
                    return response;
                })
                .catch((error) => {
                    view.render('save-error', { error, questionId, answer: entry.latestAnswer, attempt: currentAttempt });
                    inFlightSaves.delete(questionId);
                    throw error;
                });

            inFlightSaves.set(questionId, entry);
            return entry.promise;
        }

        function submit() {
            if (inFlightSubmit !== null) {
                return inFlightSubmit;
            }

            if (!currentAttempt?.id) {
                const error = new Error('No active assessment attempt to submit.');
                renderState('validation-error', { error });
                return Promise.resolve({ status: 'error', error });
            }

            const idempotencyKey = createIdempotencyKey();
            view.render('submitting', currentAttempt);

            inFlightSubmit = Promise.resolve(
                api.send(
                    'POST',
                    '/assessment-submit.php',
                    { attemptId: currentAttempt.id },
                    { idempotencyKey }
                )
            )
                .then((response) => {
                    setAttempt(response);
                    currentResult = response.result || response;
                    view.render('complete', response);
                    return response;
                })
                .catch((error) => {
                    if (error?.status === 422 || error?.code === 'VALIDATION_FAILED') {
                        view.render('validation-error', { error, attempt: currentAttempt });
                    } else {
                        view.render('source-error', { error, attempt: currentAttempt });
                    }
                    return { status: 'error', error };
                })
                .finally(() => {
                    inFlightSubmit = null;
                });

            return inFlightSubmit;
        }

        function retry() {
            if (typeof lastAction === 'function') {
                return lastAction();
            }
            if (currentAttempt?.id) {
                return loadAttempt(currentAttempt.id);
            }
            return loadCatalog();
        }

        return {
            loadCatalog,
            loadDetail,
            loadHistory,
            startOrResume,
            loadAttempt,
            loadResult,
            saveAnswer,
            submit,
            retry,
            setAttempt,
            getAttempt,
            getCatalog: () => currentCatalog,
            getQuestions: () => currentQuestions,
            getResult: () => currentResult,
        };
    }

    function parseBoot(id, suppliedDocument = null) {
        const targetDocument = suppliedDocument || (typeof document !== 'undefined' ? document : null);
        if (!targetDocument) return {};
        try {
            const node = targetDocument.getElementById(id);
            const value = JSON.parse(node?.textContent || '{}');
            return value && typeof value === 'object' ? value : {};
        } catch {
            return {};
        }
    }

    function createApiClient() {
        if (!global.TalentHubLearnerApi || typeof global.TalentHubLearnerApi.createLearnerApiClient !== 'function') {
            return null;
        }
        const session = parseBoot('learner-session-boot');
        try {
            return global.TalentHubLearnerApi.createLearnerApiClient({
                baseUrl: '/app/learner/api/v1',
                csrfToken: session.csrfToken || '',
            });
        } catch {
            return null;
        }
    }

    const ASSESSMENT_META = Object.freeze({
        holland: {
            name: 'Holland — Sở thích nghề nghiệp',
            description: 'Khám phá nhóm sở thích nghề nghiệp để định hướng môi trường học tập phù hợp.',
            tone: 'primary',
        },
        mbti: {
            name: 'MBTI — Xu hướng tính cách',
            description: 'Tìm hiểu cách bạn tiếp nhận thông tin, học tập và phối hợp với người khác.',
            tone: 'secondary',
        },
        disc: {
            name: 'DISC — Hành vi học tập',
            description: 'Nhận diện xu hướng hành vi, giao tiếp và làm việc nhóm trong học tập.',
            tone: 'success',
        },
        multiple_intelligence: {
            name: 'Đa trí thông minh — Đa diện năng khiếu',
            description: 'Khám phá các dạng trí thông minh để chọn trải nghiệm phát triển phù hợp.',
            tone: 'warning',
        },
    });

    const DISCOVERY_DIMENSIONS = Object.freeze({
        holland: Object.freeze([
            { code: 'R', label: 'Kỹ thuật — Thực tế', tone: 'secondary' },
            { code: 'I', label: 'Nghiên cứu — Phân tích', tone: 'primary' },
            { code: 'A', label: 'Nghệ thuật — Sáng tạo', tone: 'warning' },
            { code: 'S', label: 'Xã hội — Hỗ trợ', tone: 'success' },
            { code: 'E', label: 'Quản lý — Thuyết phục', tone: 'secondary' },
            { code: 'C', label: 'Nghiệp vụ — Tổ chức', tone: 'primary' },
        ]),
        multiple_intelligence: Object.freeze([
            { code: 'LING', label: 'Ngôn ngữ', tone: 'primary' },
            { code: 'LOGI', label: 'Logic — Toán học', tone: 'secondary' },
            { code: 'SPAT', label: 'Không gian — Hình ảnh', tone: 'warning' },
            { code: 'BODY', label: 'Vận động — Cơ thể', tone: 'success' },
            { code: 'MUSIC', label: 'Âm nhạc', tone: 'warning' },
            { code: 'INTER', label: 'Tương tác xã hội', tone: 'success' },
            { code: 'INTRA', label: 'Nội tâm', tone: 'primary' },
            { code: 'NAT', label: 'Tự nhiên', tone: 'success' },
        ]),
    });

    function normalizeAssessmentType(item) {
        const declaredType = String(item?.assessment_type || '').trim().toLowerCase();
        if (Object.prototype.hasOwnProperty.call(ASSESSMENT_META, declaredType)) return declaredType;
        const rawCode = String(item?.assessment_code || item?.code || '').trim().toLowerCase();
        const baseCode = rawCode.replace(/_(middle|high|college)$/, '');
        if (Object.prototype.hasOwnProperty.call(ASSESSMENT_META, baseCode)) return baseCode;
        return declaredType || baseCode;
    }

    function latestSubmittedHistory(historyPayload) {
        const items = Array.isArray(historyPayload?.assessment_history?.items)
            ? historyPayload.assessment_history.items
            : [];
        const latestByType = new Map();
        items.forEach((item) => {
            if (!item || typeof item !== 'object') return;
            const status = String(item.status || '').toLowerCase();
            if (status && status !== 'submitted') return;
            const type = normalizeAssessmentType(item);
            if (!type) return;
            const timestamp = Date.parse(item.submitted_at || item.result_created_at || item.created_at || '') || 0;
            const existing = latestByType.get(type);
            const existingTimestamp = Date.parse(existing?.submitted_at || existing?.result_created_at || existing?.created_at || '') || 0;
            if (!existing || timestamp > existingTimestamp) latestByType.set(type, item);
        });
        return latestByType;
    }

    function mergeCatalogWithHistory(catalog, historyPayload) {
        const source = catalog && typeof catalog === 'object' ? catalog : {};
        const latestByType = latestSubmittedHistory(historyPayload);
        const assessments = Array.isArray(source.assessments) ? source.assessments : [];
        return {
            ...source,
            assessments: assessments.map((item) => {
                const latestResult = latestByType.get(normalizeAssessmentType(item)) || null;
                return {
                    ...item,
                    latest_result: latestResult,
                    can_view_result: Boolean(latestResult?.id),
                };
            }),
        };
    }

    function deriveDiscoverySummary(historyPayload) {
        const latestByType = latestSubmittedHistory(historyPayload);
        const present = (assessmentType) => {
            const item = latestByType.get(assessmentType);
            const scores = item?.dimension_scores && typeof item.dimension_scores === 'object'
                ? item.dimension_scores
                : {};
            return (DISCOVERY_DIMENSIONS[assessmentType] || []).map((dimension) => ({
                ...dimension,
                score: Number(scores[dimension.code]) || 0,
            }));
        };
        return {
            career: latestByType.has('holland') ? present('holland') : [],
            talents: latestByType.has('multiple_intelligence') ? present('multiple_intelligence') : [],
        };
    }

    function deriveDiscoveryProgress(historyPayload, total = 4) {
        const latestByType = latestSubmittedHistory(historyPayload);
        const completed = Math.min(Math.max(0, Number(total) || 0), latestByType.size);
        let latestSubmittedAt = null;
        latestByType.forEach((item) => {
            const candidate = item?.submitted_at || item?.result_created_at || item?.created_at || null;
            if (!candidate) return;
            if (!latestSubmittedAt || (Date.parse(candidate) || 0) > (Date.parse(latestSubmittedAt) || 0)) {
                latestSubmittedAt = candidate;
            }
        });
        const normalizedTotal = Math.max(0, Number(total) || 0);
        return {
            completed,
            total: normalizedTotal,
            percent: normalizedTotal > 0 ? Math.round((completed / normalizedTotal) * 100) : 0,
            latestSubmittedAt,
        };
    }

    function formatDiscoveryDate(value) {
        const parsed = normalizeDiscoveryDate(value);
        if (Number.isNaN(parsed.getTime())) return 'Chưa có dữ liệu';
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: 'Asia/Ho_Chi_Minh',
        }).format(parsed);
    }

    function normalizeDiscoveryDate(value) {
        const raw = String(value || '').trim();
        const offsetlessDatabaseTimestamp = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/;
        const normalized = offsetlessDatabaseTimestamp.test(raw)
            ? `${raw.replace(' ', 'T')}+07:00`
            : raw;
        return new Date(normalized);
    }

    function renderDiscoveryProgress(root, historyPayload) {
        const progress = deriveDiscoveryProgress(historyPayload);
        const completedCount = root.querySelector('[data-discovery-completed-count]');
        const latestDate = root.querySelector('[data-discovery-latest-date]');
        const progressMeter = root.querySelector('[data-discovery-progress]');
        const progressBar = root.querySelector('[data-discovery-progress-bar]');
        if (completedCount) completedCount.textContent = `${progress.completed}/${progress.total} bài đánh giá`;
        if (latestDate) latestDate.textContent = formatDiscoveryDate(progress.latestSubmittedAt);
        if (progressMeter) progressMeter.setAttribute('aria-valuenow', String(progress.percent));
        if (progressBar) progressBar.style.setProperty('--learner-progress', `${progress.percent}%`);
    }

    function setHidden(node, hidden) {
        if (node) node.hidden = hidden;
    }

    function normalizeEducationBand(value) {
        const band = String(value || '').trim().toLowerCase();
        return ['middle', 'high', 'college'].includes(band) ? band : '';
    }

    function createTextElement(doc, tag, text, className = '') {
        const element = doc.createElement(tag);
        if (className) element.className = className;
        element.textContent = String(text ?? '');
        return element;
    }

    function createDomView(root) {
        const doc = root.ownerDocument || document;
        const nodes = {
            loading: root.querySelector('[data-assessment-loading]'),
            errorState: root.querySelector('[data-assessment-error]'),
            errorMessage: root.querySelector('[data-assessment-error-message]'),
            saveError: root.querySelector('[data-assessment-save-error]'),
            saveErrorMessage: root.querySelector('[data-assessment-save-error-message]'),
            validationError: root.querySelector('[data-assessment-validation-error]'),
            validationMessage: root.querySelector('[data-assessment-validation-message]'),
            intro: root.querySelector('[data-assessment-intro]'),
            introName: root.querySelector('[data-assessment-intro-name]'),
            introDescription: root.querySelector('[data-assessment-intro-desc]'),
            introCount: root.querySelector('[data-assessment-intro-count]'),
            introDuration: root.querySelector('[data-assessment-intro-duration]'),
            active: root.querySelector('[data-assessment-active]'),
            expired: root.querySelector('[data-assessment-expired]'),
            saveStatus: root.querySelector('[data-assessment-save-status]'),
            questionHeading: root.querySelector('[data-assessment-question]'),
            position: root.querySelector('[data-assessment-position]'),
            options: root.querySelector('[data-assessment-options]'),
            previous: root.querySelector('[data-assessment-previous]'),
            next: root.querySelector('[data-assessment-next]'),
            openSubmit: root.querySelector('[data-assessment-open-submit]'),
            answeredCount: root.querySelector('[data-assessment-answered-count]'),
            progress: root.querySelector('[data-assessment-progress]'),
            timer: root.querySelector('[data-assessment-timer]'),
            questionError: root.querySelector('[data-assessment-question-error]'),
            navigator: root.querySelector('[data-assessment-navigator]'),
            submitModal: doc.querySelector('[data-assessment-submit-modal]'),
            submitAnswered: doc.querySelector('[data-submit-answered]'),
            submitUnanswered: doc.querySelector('[data-submit-unanswered]'),
            submitError: doc.querySelector('[data-assessment-submit-error]'),
            submitButton: doc.querySelector('[data-assessment-submit]'),
            bandModal: doc.querySelector('[data-assessment-band-confirmation]'),
            bandError: doc.querySelector('[data-assessment-band-error]'),
        };
        let questionIndex = 0;

        function currentQuestion(attempt) {
            const questions = Array.isArray(attempt?.questions) ? attempt.questions : [];
            if (questions.length === 0) return null;
            questionIndex = Math.max(0, Math.min(questionIndex, questions.length - 1));
            return questions[questionIndex];
        }

        function renderQuestion(attempt) {
            const questions = Array.isArray(attempt?.questions) ? attempt.questions : [];
            const question = currentQuestion(attempt);
            if (!question) return;
            const answers = attempt.answers && typeof attempt.answers === 'object' ? attempt.answers : {};
            if (nodes.questionHeading) nodes.questionHeading.textContent = String(question.prompt || question.content || '');
            if (nodes.position) nodes.position.textContent = String(questionIndex + 1);
            if (nodes.options) {
                while (nodes.options.firstChild) nodes.options.removeChild(nodes.options.firstChild);
                const options = Array.isArray(question.options) ? question.options : [];
                options.forEach((option) => {
                    nodes.options.appendChild(renderLikertOption(doc, option, answers[question.id]));
                });
            }
            if (nodes.previous) nodes.previous.disabled = questionIndex === 0;
            if (nodes.next) setHidden(nodes.next, questionIndex >= questions.length - 1);
            if (nodes.openSubmit) setHidden(nodes.openSubmit, questionIndex < questions.length - 1);
            if (nodes.questionError) nodes.questionError.hidden = true;
            if (nodes.answeredCount) nodes.answeredCount.textContent = String(Object.keys(answers).length);
            if (nodes.progress) {
                const percent = questions.length > 0 ? Math.round((Object.keys(answers).length / questions.length) * 100) : 0;
                nodes.progress.setAttribute('aria-valuenow', String(percent));
                const bar = nodes.progress.querySelector('span');
                if (bar) bar.style.setProperty('--learner-progress', `${percent}%`);
            }
            if (nodes.navigator) {
                while (nodes.navigator.firstChild) nodes.navigator.removeChild(nodes.navigator.firstChild);
                questions.forEach((item, index) => {
                    const button = doc.createElement('button');
                    button.type = 'button';
                    button.className = 'learner-question-navigator__item';
                    button.dataset.questionIndex = String(index);
                    button.textContent = String(index + 1);
                    button.classList.toggle('is-current', index === questionIndex);
                    button.classList.toggle('is-answered', Object.prototype.hasOwnProperty.call(answers, item.id));
                    button.setAttribute('aria-label', `Câu ${index + 1}`);
                    nodes.navigator.appendChild(button);
                });
            }
        }

        function renderDetail(detail) {
            const assessment = detail?.assessment || {};
            if (nodes.introName) nodes.introName.textContent = assessment.name || 'Bài đánh giá năng khiếu';
            if (nodes.introDescription) nodes.introDescription.textContent = assessment.description || 'Khám phá năng khiếu qua các câu hỏi được phê duyệt trên hệ thống.';
            if (nodes.introCount) nodes.introCount.textContent = `${Number(assessment.question_count || detail?.questions?.length || 0)} câu`;
            if (nodes.introDuration) nodes.introDuration.textContent = `${Number(assessment.duration_minutes || 12)} phút`;
        }

        function render(state, payload) {
            setHidden(nodes.loading, !['loading', 'submitting'].includes(state));
            setHidden(nodes.errorState, state !== 'source-error');
            setHidden(nodes.saveError, state !== 'save-error');
            setHidden(nodes.validationError, state !== 'validation-error');
            setHidden(nodes.expired, state !== 'expired');
            if (state === 'loading' || state === 'submitting') {
                setHidden(nodes.intro, true);
                setHidden(nodes.active, true);
            }
            if (state === 'source-error' && nodes.errorMessage) nodes.errorMessage.textContent = payload?.error?.message || 'Đã xảy ra lỗi kết nối với máy chủ.';
            if (state === 'save-error' && nodes.saveErrorMessage) nodes.saveErrorMessage.textContent = payload?.error?.message || 'Không thể lưu câu trả lời. Vui lòng thử lại.';
            if (state === 'validation-error' && nodes.validationMessage) nodes.validationMessage.textContent = payload?.error?.message || 'Vui lòng hoàn thành các câu hỏi bắt buộc.';
            if (state === 'saving' && nodes.saveStatus) nodes.saveStatus.textContent = 'Đang lưu câu trả lời...';
            if (state === 'ready') {
                const isAttempt = Boolean(payload?.id && Array.isArray(payload?.questions));
                setHidden(nodes.intro, isAttempt);
                setHidden(nodes.active, !isAttempt);
                if (isAttempt) renderQuestion(payload);
                if (nodes.saveStatus) nodes.saveStatus.textContent = 'Đã sẵn sàng.';
            }
            if (state === 'complete') {
                if (nodes.saveStatus) nodes.saveStatus.textContent = 'Bài đánh giá đã hoàn thành.';
                setHidden(nodes.intro, true);
                setHidden(nodes.active, true);
            }
        }

        return {
            render,
            renderDetail,
            renderQuestion,
            setQuestionIndex: (index) => { questionIndex = Number(index) || 0; },
            getQuestionIndex: () => questionIndex,
            showBandModal: () => {
                if (typeof global.LearnerUI?.openModal === 'function') {
                    global.LearnerUI.openModal(nodes.bandModal);
                } else {
                    setHidden(nodes.bandModal, false);
                }
                setHidden(nodes.bandError, true);
            },
            hideBandModal: () => {
                if (typeof global.LearnerUI?.closeModal === 'function') {
                    global.LearnerUI.closeModal(nodes.bandModal);
                } else {
                    setHidden(nodes.bandModal, true);
                }
            },
            showBandError: () => setHidden(nodes.bandError, false),
            hideBandError: () => setHidden(nodes.bandError, true),
            nodes,
        };
    }

    function renderCatalog(root, payload) {
        const doc = root.ownerDocument || document;
        const loading = root.querySelector('[data-catalog-loading]');
        const empty = root.querySelector('[data-empty-catalog]');
        const error = root.querySelector('[data-catalog-error]');
        const cards = root.querySelector('[data-catalog-cards]');
        setHidden(loading, true);
        setHidden(error, true);
        setHidden(cards, false);
        if (cards) while (cards.firstChild) cards.removeChild(cards.firstChild);
        const items = Array.isArray(payload?.assessments) ? payload.assessments : [];
        const educationBand = normalizeEducationBand(payload?.education_band);
        const bandQuery = educationBand ? `&band=${encodeURIComponent(educationBand)}` : '';
        setHidden(empty, items.length !== 0);
        if (!cards) return;
        items.forEach((item) => {
            const code = String(item?.code || '').toLowerCase();
            const meta = ASSESSMENT_META[code] || { name: code, description: 'Bài đánh giá năng khiếu.', tone: 'primary' };
            const article = doc.createElement('article');
            article.className = 'learner-card learner-assessment-card';
            article.dataset.assessmentCard = code;
            const icon = createTextElement(doc, 'span', code === 'multiple_intelligence' ? 'MI' : code.slice(0, 1).toUpperCase(), 'learner-assessment-card__icon');
            icon.setAttribute('aria-hidden', 'true');
            article.appendChild(icon);
            const status = doc.createElement('span');
            status.className = 'learner-assessment-card__status';
            const published = String(item?.status || '').toLowerCase() === 'published';
            const locked = item?.attempt_status === 'retake_locked';
            const complete = ['submitted', 'retake_locked'].includes(String(item?.attempt_status || '').toLowerCase())
                || Boolean(item?.latest_result?.id);
            status.className += complete ? ' is-complete' : (published && !locked ? ' is-experimental' : ' is-unpublished');
            status.textContent = complete ? 'Đã hoàn thành' : (locked ? 'Chưa đến ngày làm lại' : (published ? 'Sẵn sàng' : 'Chưa có phiên bản được duyệt'));
            article.appendChild(status);
            const title = createTextElement(doc, 'h2', item?.name || item?.test_name || meta.name);
            const description = createTextElement(doc, 'p', item?.description || meta.description);
            article.appendChild(title);
            article.appendChild(description);
            if (complete && item?.latest_result?.result_code) {
                article.appendChild(createTextElement(doc, 'span', item.latest_result.result_code, 'learner-assessment-card__result'));
            }
            const action = doc.createElement(published && !locked ? 'a' : 'button');
            action.className = `learner-btn learner-btn--${published && !locked ? 'primary' : 'secondary'} learner-btn--block`;
            action.textContent = locked ? 'Chưa thể làm lại' : (published ? (item?.attempt_status === 'in_progress' ? 'Tiếp tục bài test' : 'Bắt đầu bài test') : 'Chưa có phiên bản được duyệt');
            if (action.tagName === 'A') action.href = `assessment.php?code=${encodeURIComponent(code)}${bandQuery}`;
            else { action.type = 'button'; action.disabled = true; }
            article.appendChild(action);
            if (complete && item?.can_view_result && item?.latest_result?.id) {
                const resultLink = doc.createElement('a');
                resultLink.className = 'learner-btn learner-btn--outline learner-btn--block';
                resultLink.href = `assessment-result.php?code=${encodeURIComponent(code)}&attempt=${encodeURIComponent(item.latest_result.id)}${bandQuery}`;
                resultLink.textContent = `Xem kết quả ${item.latest_result.result_code || ''}`.trim();
                article.appendChild(resultLink);
            }
            cards.appendChild(article);
        });
    }

    function createSvgElement(doc, tag, attributes = {}) {
        const element = doc.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
        return element;
    }

    function renderTalentRadar(doc, container, talents) {
        const width = 520;
        const height = 320;
        const centerX = width / 2;
        const centerY = height / 2;
        const radius = 105;
        const count = talents.length;
        const pointAt = (index, scale = 1) => {
            const angle = (-Math.PI / 2) + ((Math.PI * 2 * index) / count);
            return [centerX + Math.cos(angle) * radius * scale, centerY + Math.sin(angle) * radius * scale];
        };
        const points = (scaleFor) => talents.map((item, index) => {
            const [x, y] = pointAt(index, scaleFor(item));
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');

        const svg = createSvgElement(doc, 'svg', {
            class: 'learner-radar',
            viewBox: `0 0 ${width} ${height}`,
            role: 'img',
            'aria-label': talents.map((item) => `${item.label} ${item.score}`).join(', '),
        });
        [0.25, 0.5, 0.75, 1].forEach((scale) => {
            svg.appendChild(createSvgElement(doc, 'polygon', {
                class: 'learner-radar__grid',
                points: points(() => scale),
            }));
        });
        talents.forEach((_item, index) => {
            const [x, y] = pointAt(index);
            svg.appendChild(createSvgElement(doc, 'line', {
                class: 'learner-radar__axis',
                x1: centerX,
                y1: centerY,
                x2: x,
                y2: y,
            }));
        });
        svg.appendChild(createSvgElement(doc, 'polygon', {
            class: 'learner-radar-data',
            points: points((item) => Math.max(0, Math.min(100, Number(item.score) || 0)) / 100),
        }));
        talents.forEach((item, index) => {
            const scoreScale = Math.max(0, Math.min(100, Number(item.score) || 0)) / 100;
            const [pointX, pointY] = pointAt(index, scoreScale);
            svg.appendChild(createSvgElement(doc, 'circle', {
                class: 'learner-radar__point',
                cx: pointX,
                cy: pointY,
                r: 4,
            }));
            const [labelX, labelY] = pointAt(index, 1.28);
            const label = createSvgElement(doc, 'text', {
                class: 'learner-radar__labels',
                x: labelX,
                y: labelY,
                'text-anchor': labelX < centerX - 8 ? 'end' : (labelX > centerX + 8 ? 'start' : 'middle'),
                'dominant-baseline': 'middle',
            });
            label.textContent = `${item.label} ${item.score}`;
            svg.appendChild(label);
        });
        container.appendChild(svg);
    }

    function renderDiscoverySummary(root, summary) {
        const doc = root.ownerDocument || root;
        const renderEmpty = (container, message) => {
            container.appendChild(createTextElement(doc, 'p', message, 'learner-discovery-empty'));
        };
        const talents = root.querySelector('[data-discovery-talents]');
        if (talents) {
            while (talents.firstChild) talents.removeChild(talents.firstChild);
            if (!Array.isArray(summary?.talents) || summary.talents.length === 0) {
                renderEmpty(talents, 'Hoàn thành bài Đa trí thông minh để xem bản đồ năng khiếu.');
            } else {
                renderTalentRadar(doc, talents, summary.talents);
            }
        }
        const career = root.querySelector('[data-discovery-career]');
        if (career) {
            while (career.firstChild) career.removeChild(career.firstChild);
            if (!Array.isArray(summary?.career) || summary.career.length === 0) {
                renderEmpty(career, 'Hoàn thành bài Holland để xem định hướng phù hợp.');
            } else {
                summary.career.forEach((item) => {
                    const article = doc.createElement('article');
                    article.className = 'learner-direction-row';
                    const content = doc.createElement('div');
                    content.className = 'learner-direction-row__content';
                    const heading = doc.createElement('div');
                    heading.appendChild(createTextElement(doc, 'span', item.label));
                    heading.appendChild(createTextElement(doc, 'strong', `${item.score}%`));
                    const progress = doc.createElement('div');
                    progress.className = 'learner-progress';
                    progress.setAttribute('role', 'progressbar');
                    progress.setAttribute('aria-label', item.label);
                    progress.setAttribute('aria-valuemin', '0');
                    progress.setAttribute('aria-valuemax', '100');
                    progress.setAttribute('aria-valuenow', String(item.score));
                    const bar = doc.createElement('span');
                    bar.className = `learner-progress--${item.tone}`;
                    bar.style.setProperty('--learner-progress', `${item.score}%`);
                    progress.appendChild(bar);
                    content.appendChild(heading);
                    content.appendChild(progress);
                    article.appendChild(content);
                    career.appendChild(article);
                });
            }
        }
    }

    function renderResult(root, payload) {
        setHidden(root.querySelector('[data-assessment-result-loading]'), true);
        setHidden(root.querySelector('[data-assessment-result-error]'), true);
        const content = root.querySelector('[data-assessment-result-content]');
        const empty = root.querySelector('[data-assessment-result-empty]');
        const result = payload?.result || payload?.selectedAttempt?.result || null;
        const scores = result?.dimension_scores || result?.scores || result?.dimensionScores || {};
        if (!result || Object.keys(scores).length === 0) {
            setHidden(content, true);
            setHidden(empty, false);
            return;
        }
        setHidden(empty, true);
        setHidden(content, false);
        const codeNode = root.querySelector('[data-result-code]');
        const summaryNode = root.querySelector('[data-result-primary-summary]');
        if (codeNode) codeNode.textContent = result.result_code || result.code || '—';
        if (summaryNode) summaryNode.textContent = result.summary || 'Kết quả đã được lưu trên hệ thống.';
        const list = root.querySelector('[data-result-dimension-list]');
        if (list) {
            while (list.firstChild) list.removeChild(list.firstChild);
            Object.entries(scores).forEach(([dimension, score]) => {
                const row = document.createElement('div');
                row.className = 'learner-result-score';
                const label = createTextElement(document, 'strong', dimension);
                const value = createTextElement(document, 'b', Number(score) || 0);
                row.appendChild(label);
                row.appendChild(value);
                list.appendChild(row);
            });
        }
        const historyList = root.querySelector('[data-assessment-history-list]');
        if (historyList) {
            while (historyList.firstChild) historyList.removeChild(historyList.firstChild);
            (Array.isArray(payload?.history) ? payload.history : []).forEach((item) => {
                const row = document.createElement('article');
                row.dataset.historyAttemptId = String(item?.id || '');
                row.appendChild(createTextElement(document, 'strong', item?.result_code || item?.result?.code || '—'));
                row.appendChild(createTextElement(document, 'span', item?.submitted_at || 'Đã hoàn thành'));
                historyList.appendChild(row);
            });
        }
    }

    function bootRunner(root, suppliedApi = null, suppliedDocument = null) {
        const api = suppliedApi || createApiClient();
        if (!api) return Promise.resolve(null);
        const doc = suppliedDocument || root.ownerDocument || document;
        const boot = parseBoot('learner-assessment-boot', doc);
        const code = String(root.dataset.assessmentCode || boot.assessmentCode || 'holland');
        const view = createDomView(root);
        const controller = createAssessmentController({ api, view });
        let selectedBand = normalizeEducationBand(new URLSearchParams(global.location?.search || '').get('band'));
        let currentAttempt = null;
        const resultUrl = boot.result_url || `assessment-result.php?code=${encodeURIComponent(code)}`;
        let retryRunnerAction = null;

        const start = async (band) => {
            const confirmedBand = normalizeEducationBand(band || selectedBand);
            if (confirmedBand === '') {
                view.showBandModal();
                view.showBandError();
                return { code: 'EDUCATION_BAND_REQUIRED', requires_education_band: true };
            }
            selectedBand = confirmedBand;
            retryRunnerAction = () => start(selectedBand);
            view.hideBandError();
            view.hideBandModal();
            const attempt = await controller.startOrResume(code, selectedBand);
            if (attempt?.id) {
                currentAttempt = attempt;
                view.render('ready', attempt);
            }
        };
        const loadDetail = async (band) => {
            retryRunnerAction = () => loadDetail(selectedBand);
            const detail = await controller.loadDetail(code, band);
            if (detail?.status === 'source-error') {
                view.hideBandModal();
                return detail;
            }
            if (detail?.assessment) {
                selectedBand = normalizeEducationBand(
                    detail.assessment.education_band || detail.education_band
                );
                if (selectedBand === '') {
                    const error = new Error('Assessment response is missing its education band.');
                    view.render('source-error', { status: 'source-error', error });
                    view.hideBandModal();
                    return { status: 'source-error', error };
                }
                view.renderDetail(detail);
                view.hideBandModal();
            } else if (detail?.requires_education_band === true) {
                view.showBandModal();
            } else {
                view.hideBandModal();
            }
            return detail;
        };

        root.querySelector('[data-assessment-start]')?.addEventListener('click', () => start(selectedBand));
        root.querySelector('[data-assessment-resume]')?.addEventListener('click', () => start(selectedBand));
        root.querySelector('[data-assessment-restart]')?.addEventListener('click', () => start(selectedBand));
        doc.querySelector('[data-confirm-band]')?.addEventListener('click', () => {
            const selected = doc.querySelector('[name="education_band"]:checked');
            return start(selected?.value || '');
        });
        root.querySelector('[data-assessment-retry]')?.addEventListener('click', () => (
            typeof retryRunnerAction === 'function' ? retryRunnerAction() : loadDetail(selectedBand)
        ));
        root.querySelector('[data-assessment-retry-save]')?.addEventListener('click', () => controller.retry());
        root.querySelector('[data-assessment-back-to-questions]')?.addEventListener('click', () => {
            if (currentAttempt) view.render('ready', currentAttempt);
        });
        root.querySelector('[data-assessment-previous]')?.addEventListener('click', () => {
            if (!currentAttempt) return;
            view.setQuestionIndex(view.getQuestionIndex() - 1);
            view.renderQuestion(currentAttempt);
        });
        root.querySelector('[data-assessment-next]')?.addEventListener('click', () => {
            if (!currentAttempt) return;
            const question = currentAttempt.questions?.[view.getQuestionIndex()];
            if (!question || !Object.prototype.hasOwnProperty.call(currentAttempt.answers || {}, question.id)) {
                const error = root.querySelector('[data-assessment-question-error]');
                if (error) error.hidden = false;
                return;
            }
            view.setQuestionIndex(view.getQuestionIndex() + 1);
            view.renderQuestion(currentAttempt);
        });
        view.nodes.options?.addEventListener('change', (event) => {
            const input = event.target;
            if (!input || input.type !== 'radio' || !currentAttempt?.questions) return;
            const question = currentAttempt.questions[view.getQuestionIndex()];
            if (!question) return;
            controller.saveAnswer(question.id, input.value).catch(() => {});
        });
        view.nodes.navigator?.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-question-index]');
            if (!button || !currentAttempt) return;
            view.setQuestionIndex(button.dataset.questionIndex);
            view.renderQuestion(currentAttempt);
        });
        view.nodes.openSubmit?.addEventListener('click', () => {
            const answers = currentAttempt?.answers || {};
            const total = currentAttempt?.questions?.length || 0;
            if (view.nodes.submitAnswered) view.nodes.submitAnswered.textContent = `${Object.keys(answers).length}/${total}`;
            if (view.nodes.submitUnanswered) view.nodes.submitUnanswered.textContent = String(Math.max(0, total - Object.keys(answers).length));
            setHidden(view.nodes.submitModal, false);
        });
        view.nodes.submitButton?.addEventListener('click', async () => {
            const response = await controller.submit();
            const isSubmittedResult = response?.status === 'submitted'
                || response?.result
                || response?.result_id
                || (response?.attempt_id && response?.result_code);
            if (isSubmittedResult) {
                const onboardingDestination = safeOnboardingDestination(response?.next_url);
                if (onboardingDestination) {
                    global.location.href = onboardingDestination;
                    return;
                }
                const attemptId = response.attempt_id || currentAttempt?.id || response.id;
                const attemptQuery = `${resultUrl.includes('?') ? '&' : '?'}attempt=${encodeURIComponent(attemptId || '')}`;
                const bandQuery = selectedBand ? `&band=${encodeURIComponent(selectedBand)}` : '';
                global.location.href = `${resultUrl}${attemptQuery}${bandQuery}`;
            }
        });
        doc.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => {
            const modal = button.closest('.learner-modal');
            setHidden(modal, true);
        }));

        return loadDetail(selectedBand);
    }

    function bootCatalog(root, suppliedApi = null) {
        const api = suppliedApi || createApiClient();
        const doc = root.ownerDocument || document;
        const loading = root.querySelector('[data-catalog-loading]');
        const empty = root.querySelector('[data-empty-catalog]');
        const error = root.querySelector('[data-catalog-error]');
        const cards = root.querySelector('[data-catalog-cards]');
        const historyWarning = root.querySelector('[data-catalog-history-warning]');
        const bandConfirmation = root.querySelector('[data-catalog-band-confirmation]');
        const bandError = root.querySelector('[data-catalog-band-error]');
        let currentCatalog = null;
        let selectedBand = '';

        const renderCatalogError = (sourceError) => {
            setHidden(loading, true);
            setHidden(empty, true);
            setHidden(error, false);
            setHidden(cards, true);
            setHidden(historyWarning, true);
            setHidden(bandConfirmation, true);
            setHidden(bandError, true);
            return { status: 'source-error', error: sourceError };
        };

        const renderBandRequired = (payload) => {
            currentCatalog = null;
            setHidden(loading, true);
            setHidden(empty, true);
            setHidden(error, true);
            setHidden(cards, true);
            setHidden(historyWarning, true);
            setHidden(bandConfirmation, false);
            setHidden(bandError, true);
            return payload;
        };

        const renderHistory = (historyPayload) => {
            renderCatalog(root, mergeCatalogWithHistory(currentCatalog, historyPayload));
            renderDiscoverySummary(doc, deriveDiscoverySummary(historyPayload));
            renderDiscoveryProgress(doc, historyPayload);
            setHidden(historyWarning, true);
            setHidden(bandConfirmation, true);
            return historyPayload;
        };

        const loadHistory = async () => {
            if (!currentCatalog) return loadCatalog();
            setHidden(historyWarning, true);
            try {
                return renderHistory(await api.get('/assessments.php?view=history'));
            } catch (historyError) {
                renderCatalog(root, currentCatalog);
                renderDiscoverySummary(doc, deriveDiscoverySummary({}));
                renderDiscoveryProgress(doc, {});
                setHidden(historyWarning, false);
                return { status: 'source-error', error: historyError };
            }
        };

        const loadCatalog = async (band = selectedBand) => {
            selectedBand = normalizeEducationBand(band);
            setHidden(loading, false);
            setHidden(empty, true);
            setHidden(error, true);
            setHidden(cards, true);
            setHidden(historyWarning, true);
            setHidden(bandConfirmation, true);
            setHidden(bandError, true);
            if (!api) return renderCatalogError(new Error('Assessment API client is unavailable.'));

            const catalogEndpoint = selectedBand
                ? `/assessments.php?band=${encodeURIComponent(selectedBand)}`
                : '/assessments.php';
            const [catalogResult, historyResult] = await Promise.allSettled([
                api.get(catalogEndpoint),
                api.get('/assessments.php?view=history'),
            ]);
            if (catalogResult.status === 'rejected') {
                return renderCatalogError(catalogResult.reason);
            }

            currentCatalog = catalogResult.value;
            if (currentCatalog?.requires_education_band === true) {
                return renderBandRequired(currentCatalog);
            }
            if (historyResult.status === 'fulfilled') {
                renderHistory(historyResult.value);
                return { catalog: currentCatalog, history: historyResult.value };
            }

            renderCatalog(root, currentCatalog);
            renderDiscoverySummary(doc, deriveDiscoverySummary({}));
            renderDiscoveryProgress(doc, {});
            setHidden(historyWarning, false);
            return { catalog: currentCatalog, history: { status: 'source-error', error: historyResult.reason } };
        };

        root.querySelector('[data-catalog-retry]')?.addEventListener('click', () => loadCatalog(selectedBand));
        root.querySelector('[data-catalog-history-retry]')?.addEventListener('click', () => loadHistory());
        root.querySelector('[data-catalog-band-confirm]')?.addEventListener('click', () => {
            const selected = root.querySelector('[name="catalog_education_band"]:checked');
            const confirmedBand = normalizeEducationBand(selected?.value);
            if (confirmedBand === '') {
                setHidden(bandError, false);
                return { status: 'validation-error', code: 'EDUCATION_BAND_REQUIRED' };
            }
            return loadCatalog(confirmedBand);
        });
        return loadCatalog();
    }

    function bootResult(root, suppliedApi = null) {
        const api = suppliedApi || createApiClient();
        if (!api) return Promise.resolve([]);
        const code = String(root.dataset.assessmentCode || 'holland');
        const search = new URLSearchParams(global.location?.search || '');
        const attemptId = search.get('attempt') || '';
        const educationBand = normalizeEducationBand(search.get('band'));
        const resultView = {
            render: (state, payload) => {
                if (state === 'complete' || state === 'ready') renderResult(root, payload);
                if (state === 'source-error') {
                    setHidden(root.querySelector('[data-assessment-result-loading]'), true);
                    setHidden(root.querySelector('[data-assessment-result-content]'), true);
                    setHidden(root.querySelector('[data-assessment-result-error]'), false);
                }
            },
        };
        const controller = createAssessmentController({ api, view: resultView });
        const resultPromise = controller.loadResult(code, educationBand, attemptId).catch(() => {
            setHidden(root.querySelector('[data-assessment-result-content]'), true);
            setHidden(root.querySelector('[data-assessment-result-empty]'), false);
        });
        const historyPromise = controller.loadHistory().then((payload) => {
            const automated = Array.isArray(payload?.assessment_history?.items) ? payload.assessment_history.items : null;
            const teacher = Array.isArray(payload?.teacher_evaluations?.items) ? payload.teacher_evaluations.items : null;
            const renderCollection = (loadingSel, emptySel, errorSel, listSel, items, renderItem) => {
                const loading = root.querySelector(loadingSel);
                const empty = root.querySelector(emptySel);
                const error = root.querySelector(errorSel);
                const list = root.querySelector(listSel);
                setHidden(loading, true);
                setHidden(error, true);
                if (!Array.isArray(items)) {
                    setHidden(empty, false);
                    setHidden(list, true);
                    return;
                }
                if (list) while (list.firstChild) list.removeChild(list.firstChild);
                if (items.length === 0) {
                    setHidden(empty, false);
                    setHidden(list, true);
                    return;
                }
                setHidden(empty, true);
                setHidden(list, false);
                items.forEach((item) => {
                    const node = renderItem(item);
                    if (node) list.appendChild(node);
                });
            };
            renderCollection('[data-assessment-complete-history-loading]', '[data-assessment-complete-history-empty]', '[data-assessment-complete-history-error]', '[data-assessment-complete-history-list]', automated, (item) => {
                const article = document.createElement('article');
                article.className = 'learner-assessment-history__item';
                const meta = document.createElement('div');
                meta.className = 'learner-assessment-history__meta';
                const title = document.createElement('strong');
                title.textContent = item?.assessment_name || 'Chưa có dữ liệu';
                const when = document.createElement('span');
                when.textContent = item?.submitted_at || 'Chưa có dữ liệu';
                meta.appendChild(title);
                meta.appendChild(when);
                const result = document.createElement('div');
                result.className = 'learner-assessment-history__result';
                const badge = document.createElement('span');
                badge.className = 'learner-badge';
                badge.textContent = item?.result_code || 'Chưa có dữ liệu';
                result.appendChild(badge);
                const version = document.createElement('span');
                version.textContent = 'Phiên bản ' + (item?.assessment_version || 'Chưa có dữ liệu') + ' · Thang điểm ' + (item?.scoring_version || 'Chưa có dữ liệu');
                result.appendChild(version);
                const summary = document.createElement('p');
                summary.textContent = item?.summary || 'Chưa có dữ liệu';
                article.appendChild(meta);
                article.appendChild(result);
                article.appendChild(summary);
                return article;
            });
            renderCollection('[data-teacher-published-evaluation-loading]', '[data-teacher-published-evaluation-empty]', '[data-teacher-published-evaluation-error]', '[data-teacher-published-evaluation-list]', teacher, (item) => {
                const article = document.createElement('article');
                article.className = 'learner-assessment-history__item';
                const meta = document.createElement('div');
                meta.className = 'learner-assessment-history__meta';
                const title = document.createElement('strong');
                title.textContent = item?.activity_title || 'Chưa có dữ liệu';
                const when = document.createElement('span');
                when.textContent = item?.published_at || 'Chưa có dữ liệu';
                meta.appendChild(title);
                meta.appendChild(when);
                const result = document.createElement('div');
                result.className = 'learner-assessment-history__result';
                const badge = document.createElement('span');
                badge.className = 'learner-badge';
                badge.textContent = String(item?.overall_score ?? 'Chưa có dữ liệu') + '/100';
                result.appendChild(badge);
                const reviewer = document.createElement('span');
                reviewer.textContent = '— ' + (item?.reviewer_name || 'Chưa có dữ liệu');
                result.appendChild(reviewer);
                article.appendChild(meta);
                article.appendChild(result);
                if (Array.isArray(item?.scores) && item.scores.length > 0) {
                    const list = document.createElement('ul');
                    item.scores.forEach((scoreItem) => {
                        const li = document.createElement('li');
                        li.textContent = `${scoreItem?.criteria_name || 'Chưa có dữ liệu'}: ${scoreItem?.score ?? 'Chưa có dữ liệu'}/${scoreItem?.max_score ?? 'Chưa có dữ liệu'}`;
                        list.appendChild(li);
                    });
                    article.appendChild(list);
                }
                const comment = document.createElement('p');
                comment.textContent = item?.comment || 'Chưa có dữ liệu';
                article.appendChild(comment);
                return article;
            });
        }).catch(() => {
            setHidden(root.querySelector('[data-assessment-complete-history-loading]'), true);
            setHidden(root.querySelector('[data-assessment-complete-history-list]'), true);
            setHidden(root.querySelector('[data-assessment-complete-history-empty]'), true);
            setHidden(root.querySelector('[data-assessment-complete-history-error]'), false);
            setHidden(root.querySelector('[data-teacher-published-evaluation-loading]'), true);
            setHidden(root.querySelector('[data-teacher-published-evaluation-list]'), true);
            setHidden(root.querySelector('[data-teacher-published-evaluation-empty]'), true);
            setHidden(root.querySelector('[data-teacher-published-evaluation-error]'), false);
        });
        return Promise.allSettled([resultPromise, historyPromise]);
    }

    function boot() {
        if (typeof document === 'undefined') return;
        const runnerRoot = document.querySelector('[data-assessment-runner]');
        const catalogRoot = document.querySelector('[data-assessment-catalog]');
        const resultRoot = document.querySelector('[data-assessment-result-page]');
        if (runnerRoot) bootRunner(runnerRoot);
        if (catalogRoot) bootCatalog(catalogRoot);
        if (resultRoot) bootResult(resultRoot);
    }

    const exported = {
        presentationState,
        createAssessmentController,
        createDomView,
        renderLikertOption,
        mergeCatalogWithHistory,
        deriveDiscoverySummary,
        deriveDiscoveryProgress,
        normalizeDiscoveryDate,
        bootCatalog,
        bootRunner,
        bootResult,
        safeOnboardingDestination,
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = exported;
    }
    global.TalentHubLearnerAssessment = exported;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot, { once: true });
        } else {
            boot();
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
