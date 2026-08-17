'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

require('../assets/js/learner-assessment.js');

const {
    normalizeQuestionOptions,
    validateHollandAssessment,
    scoreHolland,
    getUnansweredQuestionIds,
    getRemainingSeconds,
    createAssessmentStorage,
    createAttempt,
    canSubmitAttempt,
    answerAttempt,
    submitAttempt,
    filterCompletedAttempts,
    mergeAssessmentHistory,
} = global.LearnerAssessment;

function questions() {
    return ['R', 'I', 'A', 'S', 'E', 'C'].flatMap((dimension) => [1, 2, 3, 4].map((number) => ({
        id: `holland-${dimension.toLowerCase()}-${String(number).padStart(2, '0')}`,
        dimension,
        prompt: `Câu hỏi ${dimension} ${number}`,
        options: [1, 2, 3, 4, 5].map((value) => ({ value, label: String(value) })),
    })));
}

function memoryStorage(initial = {}) {
    const data = { ...initial };
    return {
        getItem(key) { return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null; },
        setItem(key, value) { data[key] = String(value); },
        removeItem(key) { delete data[key]; },
    };
}

test('Holland scoring normalizes each dimension and returns top-three code', () => {
    const answers = {};
    questions().forEach((question) => {
        answers[question.id] = { R: 5, I: 4, A: 3, S: 2, E: 1, C: 3 }[question.dimension];
    });
    const result = scoreHolland(questions(), answers);
    assert.deepEqual(result.scores, { R: 100, I: 75, A: 50, S: 25, E: 0, C: 50 });
    assert.equal(result.code, 'RIA');
    assert.equal(result.primary_dimension, 'R');
});

test('numeric and object schema options normalize to value-label objects', () => {
    assert.deepEqual(normalizeQuestionOptions([1, { value: 2, label: 'Hơi giống tôi' }]), [
        { value: 1, label: '1' },
        { value: 2, label: 'Hơi giống tôi' },
    ]);
});

test('Holland readiness requires exactly 24 complete RIASEC questions', () => {
    assert.equal(validateHollandAssessment(questions()).available, true);
    assert.equal(validateHollandAssessment(questions().slice(0, 23)).available, false);
    assert.equal(validateHollandAssessment(questions().map((question, index) => (
        index === 0 ? { ...question, dimension: undefined } : question
    ))).available, false);
    assert.equal(validateHollandAssessment(questions().map((question, index) => (
        index === 0 ? { ...question, prompt: '   ' } : question
    ))).available, false);
});

test('Holland readiness rejects 24 questions from only the R dimension', () => {
    const allRealistic = questions().map((question) => ({ ...question, dimension: 'R' }));
    assert.equal(validateHollandAssessment(allRealistic).available, false);
});

test('Holland readiness rejects a 5R/3I dimension distribution', () => {
    const skewed = questions().map((question, index) => (
        index === 4 ? { ...question, dimension: 'R' } : question
    ));
    assert.equal(validateHollandAssessment(skewed).available, false);
});

test('Holland readiness rejects a question set missing one RIASEC dimension group', () => {
    const withoutConventional = questions().map((question) => (
        question.dimension === 'C' ? { ...question, dimension: 'E' } : question
    ));
    assert.equal(validateHollandAssessment(withoutConventional).available, false);
});

test('Holland readiness only accepts the exact Likert values 1, 2, 3, 4, 5', () => {
    const invalidLikert = questions().map((question, index) => (
        index === 0 ? { ...question, options: [1, 2, 3, 4, 4.5] } : question
    ));
    assert.equal(validateHollandAssessment(invalidLikert).available, false);
    assert.equal(validateHollandAssessment(questions()).available, true);
});

test('database-mode Holland boot validates normalized numeric options without inventing dimensions', () => {
    const databaseQuestions = questions().map((question) => ({ ...question, options: [1, 2, 3, 4, 5] }));
    const ready = validateHollandAssessment(databaseQuestions);
    assert.equal(ready.available, true);
    assert.deepEqual(ready.questions[0].options[0], { value: 1, label: '1' });

    const withoutDimension = databaseQuestions.map((question) => ({ ...question }));
    delete withoutDimension[0].dimension;
    assert.equal(validateHollandAssessment(withoutDimension).available, false);
});

test('unavailable Holland question sets cannot create a submitted result', () => {
    const invalidQuestions = questions().slice(0, 23);
    const attempt = createAttempt({
        studentId: '11111111-1111-4111-8111-111111111111',
        assessmentId: '55555555-5555-4555-8555-555555555555',
        assessmentVersion: '1.0',
        durationMinutes: 12,
        now: '2026-08-13T10:00:00.000Z',
        id: 'attempt-invalid',
    });
    attempt.answers = Object.fromEntries(invalidQuestions.map((question) => [question.id, 4]));
    assert.equal(submitAttempt(invalidQuestions, attempt), null);
    assert.equal(attempt.result, null);
});

test('unanswered helper preserves question order', () => {
    const list = questions().slice(0, 3);
    assert.deepEqual(getUnansweredQuestionIds(list, { [list[1].id]: 4 }), [list[0].id, list[2].id]);
});

test('timer is rounded down and never negative', () => {
    assert.equal(getRemainingSeconds('2026-08-13T10:01:40Z', '2026-08-13T10:00:00Z'), 100);
    assert.equal(getRemainingSeconds('2026-08-13T09:59:00Z', '2026-08-13T10:00:00Z'), 0);
});

test('storage recovers from corrupt JSON and round-trips attempts', () => {
    const raw = memoryStorage({ assessment: '{broken' });
    const storage = createAssessmentStorage(raw, 'assessment');
    assert.deepEqual(storage.getAttempts(), []);
    const attempt = { id: 'attempt-1', assessment_id: 'holland', student_id: 'student-demo-001', status: 'in_progress' };
    storage.saveAttempt(attempt);
    assert.deepEqual(storage.getAttempt('attempt-1'), attempt);
    storage.removeAttempt('attempt-1');
    assert.equal(storage.getAttempt('attempt-1'), null);
});

test('canonical assessment UUID is used for localStorage lookup and route aliases do not leak into storage', () => {
    const canonicalId = '55555555-5555-4555-8555-555555555555';
    const storage = createAssessmentStorage(memoryStorage(), 'assessment-canonical');
    const attempt = createAttempt({
        studentId: '11111111-1111-4111-8111-111111111111',
        assessmentId: canonicalId,
        assessmentVersion: '1.0',
        durationMinutes: 12,
        now: '2026-08-13T10:00:00.000Z',
        id: 'attempt-canonical',
    });
    storage.saveAttempt(attempt);
    assert.equal(storage.getLatestAttempt(attempt.student_id, canonicalId, ['in_progress']).id, attempt.id);
    assert.equal(storage.getLatestAttempt(attempt.student_id, 'holland', ['in_progress']), null);
});

test('attempt lifecycle uses database-ready fields', () => {
    const attempt = createAttempt({
        studentId: 'student-demo-001',
        assessmentId: 'holland',
        assessmentVersion: '1.0',
        durationMinutes: 12,
        now: '2026-08-13T10:00:00.000Z',
        id: 'attempt-fixed',
    });
    assert.equal(attempt.id, 'attempt-fixed');
    assert.equal(attempt.status, 'in_progress');
    assert.equal(attempt.expires_at, '2026-08-13T10:12:00.000Z');
    assert.deepEqual(attempt.answers, {});
    assert.equal(canSubmitAttempt(questions(), attempt), false);
    attempt.answers = Object.fromEntries(questions().map((question) => [question.id, 3]));
    assert.equal(canSubmitAttempt(questions(), attempt), true);
});

test('answering updates timestamps without changing the storage contract', () => {
    const attempt = createAttempt({ studentId: 'student-demo-001', assessmentId: 'holland', assessmentVersion: '1.0', durationMinutes: 12, now: '2026-08-13T10:00:00.000Z', id: 'attempt-answer' });
    const updated = answerAttempt(attempt, 'holland-r-01', 5, 3, '2026-08-13T10:01:00.000Z');
    assert.equal(updated.answers['holland-r-01'], 5);
    assert.equal(updated.current_question_index, 3);
    assert.equal(updated.updated_at, '2026-08-13T10:01:00.000Z');
    assert.equal(attempt.answers['holland-r-01'], undefined);
});

test('submitting locks the attempt and stores a versioned result', () => {
    const attempt = createAttempt({ studentId: 'student-demo-001', assessmentId: 'holland', assessmentVersion: '1.0', durationMinutes: 12, now: '2026-08-13T10:00:00.000Z', id: 'attempt-submit' });
    attempt.answers = Object.fromEntries(questions().map((question) => [question.id, 4]));
    const submitted = submitAttempt(questions(), attempt, '2026-08-13T10:05:00.000Z');
    assert.equal(submitted.status, 'submitted');
    assert.equal(submitted.submitted_at, '2026-08-13T10:05:00.000Z');
    assert.equal(submitted.result.scoring_version, 'holland-riasec-1.0');
});

test('history merge deduplicates and orders newest first', () => {
    const result = { code: 'RIA', scores: { R: 90, I: 80, A: 70, S: 60, E: 50, C: 40 }, primary_dimension: 'R' };
    const mock = [{ id: 'same', status: 'submitted', result, submitted_at: '2026-01-01T00:00:00Z' }, { id: 'old', status: 'completed', result, submitted_at: '2025-01-01T00:00:00Z' }];
    const local = [{ id: 'new', status: 'submitted', result, submitted_at: '2026-08-01T00:00:00Z' }, { id: 'same', status: 'submitted', result, submitted_at: '2026-02-01T00:00:00Z' }];
    assert.deepEqual(mergeAssessmentHistory(mock, local).map((item) => item.id), ['new', 'same', 'old']);
});

test('completed history excludes in-progress attempts and matches canonical UUID submissions', () => {
    const studentId = '11111111-1111-4111-8111-111111111111';
    const assessmentId = '55555555-5555-4555-8555-555555555555';
    const validResult = { code: 'RIA', scores: { R: 90, I: 80, A: 70, S: 60, E: 50, C: 40 }, primary_dimension: 'R' };
    const attempts = [
        { id: 'draft', student_id: studentId, assessment_id: assessmentId, status: 'in_progress', result: null },
        { id: 'legacy', student_id: studentId, assessment_id: 'holland', status: 'submitted', result: validResult },
        { id: 'invalid-result', student_id: studentId, assessment_id: assessmentId, status: 'completed', result: { code: 'RIA', scores: { X: 90 }, primary_dimension: 'R' } },
        { id: 'partial-result', student_id: studentId, assessment_id: assessmentId, status: 'completed', result: { code: 'RIA', scores: { R: 90 }, primary_dimension: 'R' } },
        { id: 'submitted', student_id: studentId, assessment_id: assessmentId, status: 'submitted', result: validResult },
    ];
    assert.deepEqual(
        filterCompletedAttempts(attempts, studentId, assessmentId).map((attempt) => attempt.id),
        ['submitted']
    );
});

test('injected assessment write client keeps only drafts locally and returns the canonical submitted result', async () => {
    const calls = [];
    const cached = [];
    const removed = [];
    const canonicalStart = {
        id: 'attempt-1', student_id: 'student-1', assessment_id: 'test-1', assessment_version: '1.0.0',
        scoring_version: 'holland-riasec-1.0', status: 'in_progress', answers: {}, result: null,
    };
    const canonicalAnswer = {
        ...canonicalStart, answers: { 'question-1': 5 }, updated_at: '2026-08-16T00:01:00.000Z',
    };
    const canonicalResult = {
        id: 'result-1', attempt_id: 'attempt-1', assessment_id: 'test-1', assessment_version: '1.0.0',
        scoring_version: 'holland-riasec-1.0', input_hash: 'a'.repeat(64), result_code: 'RIA',
    };
    const client = globalThis.LearnerAssessment.createAssessmentWriteClient({
        transport: {
            async start(studentId, testId, version) {
                calls.push(['start', studentId, testId, version]);
                return canonicalStart;
            },
            async saveAnswer(studentId, attemptId, questionId, answer) {
                calls.push(['saveAnswer', studentId, attemptId, questionId, answer]);
                return canonicalAnswer;
            },
            async submit(studentId, attemptId) {
                calls.push(['submit', studentId, attemptId]);
                return canonicalResult;
            },
        },
        storage: {
            saveAttempt: (attempt) => cached.push(attempt),
            removeAttempt: (attemptId) => removed.push(attemptId),
        },
    });

    assert.deepEqual(await client.start('student-1', 'test-1', '1.0.0'), canonicalStart);
    assert.deepEqual(await client.saveAnswer('student-1', 'attempt-1', 'question-1', 5), canonicalAnswer);
    assert.strictEqual(await client.submit('student-1', 'attempt-1'), canonicalResult);
    assert.deepEqual(calls, [
        ['start', 'student-1', 'test-1', '1.0.0'],
        ['saveAnswer', 'student-1', 'attempt-1', 'question-1', 5],
        ['submit', 'student-1', 'attempt-1'],
    ]);
    assert.deepEqual(cached, [canonicalStart, canonicalAnswer]);
    assert.deepEqual(removed, ['attempt-1']);
    assert.equal(cached.some((attempt) => attempt.status === 'submitted'), false);
});

test('assessment write client rejects an incomplete or unsafe injected transport', () => {
    assert.throws(
        () => globalThis.LearnerAssessment.createAssessmentWriteClient({ transport: { start() {} } }),
        /assessment write transport/i,
    );
});
