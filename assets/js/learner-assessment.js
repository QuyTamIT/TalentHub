/**
 * TalentHub learner assessment API state controller and UI view.
 * Connects assessment flows (catalog, start/resume, autosave, submit, result)
 * to the authoritative learner API endpoints.
 */
(function initLearnerAssessment(global) {
    'use strict';

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
            return renderState('source-error', { error, attempt: currentAttempt });
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
                    try {
                        const fullAttempt = await api.get(`/assessment-attempts.php?attemptId=${encodeURIComponent(response.id)}`);
                        if (fullAttempt?.id) {
                            setAttempt(fullAttempt);
                        } else if (Array.isArray(fullAttempt?.questions)) {
                            currentAttempt.questions = fullAttempt.questions;
                        }
                    } catch {
                        // Fall back to existing attempt data
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
            startOrResume,
            loadAttempt,
            saveAnswer,
            submit,
            retry,
            setAttempt,
            getAttempt,
        };
    }

    function createDomView(root) {
        const nodes = {
            loading: root.querySelector('[data-assessment-loading]'),
            errorState: root.querySelector('[data-assessment-error]'),
            errorMessage: root.querySelector('[data-assessment-error-message]'),
            intro: root.querySelector('[data-assessment-intro]'),
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
            submitModal: document.querySelector('[data-assessment-submit-modal]'),
            submitAnswered: document.querySelector('[data-submit-answered]'),
            submitUnanswered: document.querySelector('[data-submit-unanswered]'),
            submitError: document.querySelector('[data-assessment-submit-error]'),
            submitButton: document.querySelector('[data-assessment-submit]'),
        };

        function setHidden(node, hidden) {
            if (node) node.hidden = hidden;
        }

        function render(state, payload) {
            setHidden(nodes.loading, state !== 'loading' && state !== 'submitting');
            setHidden(nodes.errorState, state !== 'source-error' && state !== 'save-error' && state !== 'validation-error');
            setHidden(nodes.expired, state !== 'expired');

            if (state === 'saving') {
                if (nodes.saveStatus) nodes.saveStatus.textContent = 'Đang lưu câu trả lời...';
            } else if (state === 'ready') {
                if (nodes.saveStatus) nodes.saveStatus.textContent = 'Đã lưu tất cả câu trả lời.';
                setHidden(nodes.intro, false);
                setHidden(nodes.active, false);
            } else if (state === 'save-error') {
                if (nodes.errorMessage) {
                    nodes.errorMessage.textContent = payload?.error?.message || 'Không thể lưu câu trả lời. Vui lòng thử lại.';
                }
            } else if (state === 'validation-error') {
                if (nodes.errorMessage) {
                    nodes.errorMessage.textContent = payload?.error?.message || 'Dữ liệu không hợp lệ hoặc chưa trả lời hết các câu hỏi.';
                }
                if (nodes.submitError) {
                    setHidden(nodes.submitError, false);
                    nodes.submitError.textContent = payload?.error?.message || 'Cần hoàn thành các câu hỏi trước khi nộp bài.';
                }
            } else if (state === 'source-error') {
                if (nodes.errorMessage) {
                    nodes.errorMessage.textContent = payload?.error?.message || 'Đã xảy ra lỗi kết nối với máy chủ.';
                }
            } else if (state === 'complete') {
                if (nodes.saveStatus) nodes.saveStatus.textContent = 'Bài đánh giá đã hoàn thành.';
            }
        }

        return { render };
    }

    function boot() {
        if (typeof document === 'undefined') return;
        const runnerRoot = document.querySelector('[data-assessment-runner]');
        if (!runnerRoot || !global.TalentHubLearnerApi) return;

        const bootNode = document.getElementById('learner-session-boot');
        let csrfToken = '';
        try {
            csrfToken = JSON.parse(bootNode?.textContent || '{}').csrfToken || '';
        } catch {
            csrfToken = '';
        }

        let api;
        try {
            api = global.TalentHubLearnerApi.createLearnerApiClient({
                baseUrl: '/app/learner/api/v1',
                csrfToken,
            });
        } catch {
            return;
        }

        const controller = createAssessmentController({
            api,
            view: createDomView(runnerRoot),
        });

        runnerRoot.querySelector('[data-assessment-retry]')?.addEventListener('click', () => controller.retry());
    }

    const exported = {
        presentationState,
        createAssessmentController,
        createDomView,
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
