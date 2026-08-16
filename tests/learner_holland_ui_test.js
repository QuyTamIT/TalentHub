const test = require('node:test');
const assert = require('node:assert/strict');

require('../assets/js/learner-assessment.js');

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
