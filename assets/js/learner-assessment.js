/** TalentHub learner Holland assessment domain, storage and page controller. */
(function initLearnerAssessment(global) {
    'use strict';

    const DEFAULT_STORAGE_KEY = 'talenthub.learner.assessments.v1';
    const SCHEMA_VERSION = 1;

    function scoreHolland(questions, answers) {
        const dimensions = ['R', 'I', 'A', 'S', 'E', 'C'];
        const totals = Object.fromEntries(dimensions.map((dimension) => [dimension, 0]));
        const counts = Object.fromEntries(dimensions.map((dimension) => [dimension, 0]));
        questions.forEach((question) => {
            const dimension = question.dimension;
            if (!dimensions.includes(dimension)) return;
            const value = Number(answers?.[question.id]);
            if (!Number.isFinite(value)) return;
            totals[dimension] += Math.max(1, Math.min(5, value));
            counts[dimension] += 1;
        });
        const scores = Object.fromEntries(dimensions.map((dimension) => {
            const count = counts[dimension];
            if (count === 0) return [dimension, 0];
            return [dimension, Math.round((totals[dimension] - count) / (count * 4) * 100)];
        }));
        const ranked = [...dimensions].sort((left, right) => scores[right] - scores[left]
            || dimensions.indexOf(left) - dimensions.indexOf(right));
        return {
            code: ranked.slice(0, 3).join(''),
            scores,
            primary_dimension: ranked[0],
            ranked_dimensions: ranked,
        };
    }

    function getUnansweredQuestionIds(questions, answers) {
        return questions
            .filter((question) => !Object.prototype.hasOwnProperty.call(answers || {}, question.id)
                || !Number.isFinite(Number(answers[question.id])))
            .map((question) => question.id);
    }

    function getRemainingSeconds(expiresAt, now = new Date().toISOString()) {
        const difference = new Date(expiresAt).getTime() - new Date(now).getTime();
        if (!Number.isFinite(difference)) return 0;
        return Math.max(0, Math.floor(difference / 1000));
    }

    function createAssessmentStorage(storage = global.localStorage, key = DEFAULT_STORAGE_KEY) {
        const read = () => {
            try {
                const parsed = JSON.parse(storage?.getItem(key) || 'null');
                if (!parsed || parsed.schema_version !== SCHEMA_VERSION || !Array.isArray(parsed.attempts)) {
                    return { schema_version: SCHEMA_VERSION, attempts: [] };
                }
                return parsed;
            } catch (error) {
                return { schema_version: SCHEMA_VERSION, attempts: [] };
            }
        };
        const write = (state) => {
            storage?.setItem(key, JSON.stringify(state));
        };
        return {
            getAttempts() { return read().attempts; },
            getAttempt(id) { return read().attempts.find((attempt) => attempt.id === id) || null; },
            getLatestAttempt(studentId, assessmentId, statuses = []) {
                return read().attempts
                    .filter((attempt) => attempt.student_id === studentId
                        && attempt.assessment_id === assessmentId
                        && (statuses.length === 0 || statuses.includes(attempt.status)))
                    .sort((left, right) => String(right.updated_at || '').localeCompare(String(left.updated_at || '')))[0] || null;
            },
            saveAttempt(attempt) {
                const state = read();
                const index = state.attempts.findIndex((item) => item.id === attempt.id);
                if (index >= 0) state.attempts[index] = attempt;
                else state.attempts.push(attempt);
                write(state);
                return attempt;
            },
            removeAttempt(id) {
                const state = read();
                state.attempts = state.attempts.filter((attempt) => attempt.id !== id);
                write(state);
            },
        };
    }

    function createAttempt({ studentId, assessmentId, assessmentVersion, durationMinutes, now, id }) {
        const started = new Date(now || Date.now());
        const attemptId = id || `attempt-${assessmentId}-${started.getTime()}-${Math.random().toString(36).slice(2, 8)}`;
        return {
            id: attemptId,
            student_id: studentId,
            assessment_id: assessmentId,
            assessment_version: assessmentVersion,
            status: 'in_progress',
            started_at: started.toISOString(),
            updated_at: started.toISOString(),
            expires_at: new Date(started.getTime() + Number(durationMinutes) * 60000).toISOString(),
            submitted_at: null,
            answers: {},
            result: null,
            current_question_index: 0,
        };
    }

    function canSubmitAttempt(questions, attempt) {
        return attempt?.status === 'in_progress'
            && getUnansweredQuestionIds(questions, attempt.answers).length === 0;
    }

    function answerAttempt(attempt, questionId, value, currentQuestionIndex, now = new Date().toISOString()) {
        return {
            ...attempt,
            answers: { ...(attempt?.answers || {}), [questionId]: Number(value) },
            current_question_index: Number(currentQuestionIndex) || 0,
            updated_at: new Date(now).toISOString(),
        };
    }

    function submitAttempt(questions, attempt, now = new Date().toISOString()) {
        if (!canSubmitAttempt(questions, attempt)) return null;
        const submittedAt = new Date(now).toISOString();
        return {
            ...attempt,
            status: 'submitted',
            updated_at: submittedAt,
            submitted_at: submittedAt,
            result: {
                ...scoreHolland(questions, attempt.answers),
                scoring_version: 'holland-riasec-1.0',
            },
        };
    }

    function mergeAssessmentHistory(mockHistory, localHistory) {
        const byId = new Map();
        [...(mockHistory || []), ...(localHistory || [])].forEach((attempt) => byId.set(attempt.id, attempt));
        return Array.from(byId.values()).sort((left, right) => String(right.submitted_at || '')
            .localeCompare(String(left.submitted_at || '')));
    }

    global.LearnerAssessment = {
        scoreHolland,
        getUnansweredQuestionIds,
        getRemainingSeconds,
        createAssessmentStorage,
        createAttempt,
        canSubmitAttempt,
        answerAttempt,
        submitAttempt,
        mergeAssessmentHistory,
    };

    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', () => {
        const runnerRoot = document.querySelector('[data-assessment-runner]');
        const resultRoot = document.querySelector('[data-assessment-result-page]');
        const parseBoot = (id) => {
            const element = document.getElementById(id);
            if (!element) return null;
            try { return JSON.parse(element.textContent || 'null'); } catch (error) { return null; }
        };
        const setHidden = (element, hidden) => { if (element) element.hidden = hidden; };

        if (runnerRoot) {
            const boot = parseBoot('learner-assessment-boot');
            const loading = runnerRoot.querySelector('[data-assessment-loading]');
            const errorState = runnerRoot.querySelector('[data-assessment-error]');
            const errorMessage = runnerRoot.querySelector('[data-assessment-error-message]');
            const intro = runnerRoot.querySelector('[data-assessment-intro]');
            const active = runnerRoot.querySelector('[data-assessment-active]');
            const expired = runnerRoot.querySelector('[data-assessment-expired]');
            const storage = createAssessmentStorage();
            let attempt = null;
            let currentIndex = 0;
            let timerId = null;

            const fail = (message) => {
                setHidden(loading, true); setHidden(intro, true); setHidden(active, true); setHidden(expired, true);
                if (errorMessage) errorMessage.textContent = message;
                setHidden(errorState, false);
            };

            const expireAttempt = () => {
                if (!attempt || attempt.status !== 'in_progress') return;
                attempt = { ...attempt, status: 'expired', updated_at: new Date().toISOString() };
                storage.saveAttempt(attempt);
                global.clearInterval(timerId);
                setHidden(active, true); setHidden(intro, true); setHidden(expired, false);
            };

            const updateTimer = () => {
                if (!attempt) return;
                const seconds = getRemainingSeconds(attempt.expires_at);
                const timer = runnerRoot.querySelector('[data-assessment-timer]');
                if (timer) {
                    timer.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
                    timer.parentElement?.classList.toggle('is-warning', seconds > 0 && seconds <= 120);
                }
                if (seconds === 0) expireAttempt();
            };

            const save = () => {
                if (!attempt) return;
                storage.saveAttempt(attempt);
                const status = runnerRoot.querySelector('[data-assessment-save-status]');
                if (status) status.textContent = `Đã lưu bản nháp lúc ${new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}.`;
            };

            const renderQuestion = () => {
                if (!attempt || !boot?.questions?.length) return;
                const question = boot.questions[currentIndex];
                const questionHeading = runnerRoot.querySelector('[data-assessment-question]');
                const position = runnerRoot.querySelector('[data-assessment-position]');
                const options = runnerRoot.querySelector('[data-assessment-options]');
                const previous = runnerRoot.querySelector('[data-assessment-previous]');
                const next = runnerRoot.querySelector('[data-assessment-next]');
                const openSubmit = runnerRoot.querySelector('[data-assessment-open-submit]');
                const unanswered = getUnansweredQuestionIds(boot.questions, attempt.answers);
                const answeredCount = boot.questions.length - unanswered.length;
                if (questionHeading) questionHeading.textContent = question.prompt;
                if (position) position.textContent = String(currentIndex + 1);
                if (options) {
                    options.innerHTML = question.options.map((option) => {
                        const checked = Number(attempt.answers[question.id]) === Number(option.value) ? ' checked' : '';
                        return `<label class="learner-likert-option"><input type="radio" name="assessment-answer" value="${option.value}"${checked}><span><b>${option.value}</b>${option.label}</span></label>`;
                    }).join('');
                    options.querySelectorAll('input').forEach((input) => input.addEventListener('change', () => {
                        attempt = answerAttempt(attempt, question.id, input.value, currentIndex);
                        save(); renderQuestion();
                    }));
                }
                if (previous) previous.disabled = currentIndex === 0;
                setHidden(next, currentIndex === boot.questions.length - 1);
                setHidden(openSubmit, currentIndex !== boot.questions.length - 1);
                const count = runnerRoot.querySelector('[data-assessment-answered-count]');
                if (count) count.textContent = String(answeredCount);
                const progress = runnerRoot.querySelector('[data-assessment-progress]');
                if (progress) {
                    progress.setAttribute('aria-valuenow', String(answeredCount));
                    const bar = progress.querySelector('span');
                    if (bar) bar.style.setProperty('--learner-progress', `${answeredCount / boot.questions.length * 100}%`);
                }
                runnerRoot.querySelectorAll('[data-question-index]').forEach((button, index) => {
                    button.classList.toggle('is-current', index === currentIndex);
                    button.classList.toggle('is-answered', Object.prototype.hasOwnProperty.call(attempt.answers, boot.questions[index].id));
                    button.setAttribute('aria-current', index === currentIndex ? 'step' : 'false');
                });
                const questionError = runnerRoot.querySelector('[data-assessment-question-error]');
                if (questionError) questionError.hidden = true;
            };

            const startRunner = (existingAttempt) => {
                attempt = existingAttempt || createAttempt({
                    studentId: boot.student.id,
                    assessmentId: boot.definition.id,
                    assessmentVersion: boot.definition.version,
                    durationMinutes: boot.definition.duration_minutes,
                });
                currentIndex = Math.max(0, Math.min(boot.questions.length - 1, Number(attempt.current_question_index) || 0));
                save();
                setHidden(loading, true); setHidden(errorState, true); setHidden(intro, true); setHidden(expired, true); setHidden(active, false);
                renderQuestion(); updateTimer();
                global.clearInterval(timerId);
                timerId = global.setInterval(updateTimer, 1000);
                runnerRoot.querySelector('#assessment-question-heading')?.focus();
            };

            global.setTimeout(() => {
                if (!boot?.definition || !Array.isArray(boot.questions) || boot.questions.length !== 24) {
                    fail('Bộ câu hỏi Holland không đầy đủ hoặc sai phiên bản.');
                    return;
                }
                try {
                    const draft = storage.getLatestAttempt(boot.student.id, boot.definition.id, ['in_progress']);
                    const expiredDraft = storage.getLatestAttempt(boot.student.id, boot.definition.id, ['expired']);
                    setHidden(loading, true);
                    if (draft && getRemainingSeconds(draft.expires_at) === 0) {
                        attempt = draft; expireAttempt(); return;
                    }
                    setHidden(intro, false);
                    const resume = runnerRoot.querySelector('[data-assessment-resume]');
                    if (resume) {
                        resume.hidden = !draft;
                        resume.textContent = draft ? `Tiếp tục bản nháp · ${Object.keys(draft.answers || {}).length}/24 câu` : '';
                        resume.addEventListener('click', () => startRunner(draft));
                    }
                    if (expiredDraft) setHidden(expired, true);
                } catch (error) {
                    fail('Trình duyệt không cho phép lưu bản nháp. Hãy kiểm tra quyền lưu trữ và thử lại.');
                }
            }, 250);

            runnerRoot.querySelector('[data-assessment-start]')?.addEventListener('click', () => startRunner(null));
            runnerRoot.querySelector('[data-assessment-retry]')?.addEventListener('click', () => global.location.reload());
            runnerRoot.querySelector('[data-assessment-restart]')?.addEventListener('click', () => startRunner(null));
            runnerRoot.querySelector('[data-assessment-previous]')?.addEventListener('click', () => {
                if (currentIndex > 0) { currentIndex -= 1; attempt.current_question_index = currentIndex; save(); renderQuestion(); runnerRoot.querySelector('#assessment-question-heading')?.focus(); }
            });
            runnerRoot.querySelector('[data-assessment-next]')?.addEventListener('click', () => {
                const question = boot.questions[currentIndex];
                if (!Object.prototype.hasOwnProperty.call(attempt?.answers || {}, question.id)) {
                    const message = runnerRoot.querySelector('[data-assessment-question-error]');
                    if (message) message.hidden = false;
                    runnerRoot.querySelector('[name="assessment-answer"]')?.focus();
                    return;
                }
                if (currentIndex < boot.questions.length - 1) { currentIndex += 1; attempt.current_question_index = currentIndex; save(); renderQuestion(); runnerRoot.querySelector('#assessment-question-heading')?.focus(); }
            });
            runnerRoot.querySelectorAll('[data-question-index]').forEach((button) => button.addEventListener('click', () => {
                currentIndex = Number(button.dataset.questionIndex) || 0;
                attempt.current_question_index = currentIndex; save(); renderQuestion(); runnerRoot.querySelector('#assessment-question-heading')?.focus();
            }));

            const submitModal = document.querySelector('[data-assessment-submit-modal]');
            runnerRoot.querySelector('[data-assessment-open-submit]')?.addEventListener('click', () => {
                const unanswered = getUnansweredQuestionIds(boot.questions, attempt?.answers || {});
                const answered = submitModal?.querySelector('[data-submit-answered]');
                const missing = submitModal?.querySelector('[data-submit-unanswered]');
                if (answered) answered.textContent = `${boot.questions.length - unanswered.length}/${boot.questions.length}`;
                if (missing) missing.textContent = String(unanswered.length);
                const error = submitModal?.querySelector('[data-assessment-submit-error]');
                if (error) error.hidden = unanswered.length === 0;
            });
            submitModal?.querySelector('[data-assessment-submit]')?.addEventListener('click', () => {
                const submitted = submitAttempt(boot.questions, attempt);
                if (!submitted) {
                    const missing = getUnansweredQuestionIds(boot.questions, attempt?.answers || {});
                    currentIndex = Math.max(0, boot.questions.findIndex((question) => question.id === missing[0]));
                    renderQuestion();
                    submitModal.querySelector('[data-assessment-submit-error]').hidden = false;
                    return;
                }
                attempt = submitted; storage.saveAttempt(attempt); global.clearInterval(timerId);
                global.location.href = `${boot.result_url}&attempt=${encodeURIComponent(attempt.id)}`;
            });
        }

        if (resultRoot) {
            const boot = parseBoot('learner-assessment-result-boot');
            if (!boot) return;
            const storage = createAssessmentStorage();
            const localHistory = storage.getAttempts().filter((attempt) => attempt.student_id === boot.student_id
                && attempt.assessment_id === boot.assessment_id && attempt.status === 'submitted');
            const history = mergeAssessmentHistory(boot.mock_history, localHistory);
            const requestedAttemptId = new URLSearchParams(global.location.search).get('attempt');
            const current = history.find((attempt) => attempt.id === requestedAttemptId) || history[0];
            if (!current?.result) return;
            const dimension = boot.dimensions[current.result.primary_dimension];
            const code = resultRoot.querySelector('[data-result-code]');
            const name = resultRoot.querySelector('[data-result-primary-name]');
            const summary = resultRoot.querySelector('[data-result-primary-summary]');
            const source = resultRoot.querySelector('[data-result-source]');
            if (code) code.textContent = current.result.code;
            if (name) name.textContent = dimension.name;
            if (summary) summary.textContent = dimension.summary;
            if (source) source.textContent = localHistory.some((attempt) => attempt.id === current.id) ? 'Kết quả lưu trên trình duyệt này' : 'Lịch sử mẫu dùng chung';
            resultRoot.querySelectorAll('[data-result-dimension]').forEach((row) => {
                const score = Number(current.result.scores[row.dataset.resultDimension] || 0);
                const bar = row.querySelector('.learner-progress span');
                if (bar) bar.style.setProperty('--learner-progress', `${score}%`);
                const value = row.querySelector('b:last-child');
                if (value) value.textContent = String(score);
            });
            const suggestions = resultRoot.querySelector('[data-result-suggestions]');
            if (suggestions) suggestions.innerHTML = dimension.suggestions.map((item) => `<li>${item}</li>`).join('');
            const list = resultRoot.querySelector('[data-assessment-history-list]');
            if (list && localHistory.length) {
                localHistory.forEach((item) => {
                    if (list.querySelector(`[data-history-attempt-id="${item.id}"]`)) return;
                    const itemDimension = boot.dimensions[item.result.primary_dimension];
                    const article = document.createElement('article');
                    article.dataset.historyAttemptId = item.id;
                    article.innerHTML = `<span class="learner-result-mini-code">${item.result.code}</span><div><strong>${itemDimension.name}</strong><span>${new Date(item.submitted_at).toLocaleString('vi-VN')} · Phiên bản ${item.assessment_version}</span></div><span class="learner-verified-pill">Đã hoàn thành</span>`;
                    list.prepend(article);
                });
            }
        }

        const latestHolland = document.querySelector('[data-holland-latest]');
        if (latestHolland) {
            const storage = createAssessmentStorage();
            const latest = storage.getLatestAttempt('student-demo-001', 'holland', ['submitted']);
            if (latest?.result) {
                latestHolland.hidden = false;
                const code = latestHolland.querySelector('[data-holland-latest-code]');
                const date = latestHolland.querySelector('[data-holland-latest-date]');
                const link = latestHolland.querySelector('[data-holland-latest-link]');
                if (code) code.textContent = latest.result.code;
                if (date) date.textContent = `Hoàn thành lúc ${new Date(latest.submitted_at).toLocaleString('vi-VN')}.`;
                if (link) link.href = `assessment-result.php?id=holland&attempt=${encodeURIComponent(latest.id)}`;
            }
        }
    });
})(typeof window !== 'undefined' ? window : globalThis);
