'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const assessment = require('../assets/js/learner-assessment.js');
const { createAssessmentController, presentationState } = assessment;

test('Holland uses the generic server-authoritative assessment contract', () => {
    assert.equal(typeof createAssessmentController, 'function');
    assert.equal(typeof presentationState, 'function');
    assert.equal(global.TalentHubLearnerAssessment, assessment);
    assert.equal(global.LearnerAssessment, undefined);
});

test('Holland detail and attempt flows use canonical learner API endpoints', async () => {
    const calls = [];
    const rendered = [];
    const api = {
        async get(endpoint) {
            calls.push(['GET', endpoint]);
            if (endpoint.startsWith('/assessments.php')) {
                return { assessment: { code: 'holland' }, questions: [] };
            }
            return {
                id: 'attempt-holland-1',
                assessment_id: 'holland-test-id',
                assessment_version: '1.0.0',
                status: 'in_progress',
                answers: {},
                questions: [],
            };
        },
        async send(method, endpoint, body) {
            calls.push([method, endpoint, body]);
            return {
                id: 'attempt-holland-1',
                assessment_id: 'holland-test-id',
                assessment_version: '1.0.0',
                status: 'in_progress',
                answers: {},
                questions: [],
            };
        },
    };
    const controller = createAssessmentController({
        api,
        view: { render: (state, payload) => rendered.push([state, payload]) },
        createIdempotencyKey: () => 'assessment-submit-holland-test',
    });

    const detail = await controller.loadDetail('holland', 'high');
    const attempt = await controller.startOrResume('holland', 'high');

    assert.equal(detail.assessment.code, 'holland');
    assert.equal(attempt.id, 'attempt-holland-1');
    assert.deepEqual(calls.slice(0, 2), [
        ['GET', '/assessments.php?code=holland&band=high'],
        ['POST', '/assessment-attempts.php', { assessmentCode: 'holland', educationBand: 'high' }],
    ]);
    assert.equal(rendered.some(([state]) => state === 'ready'), true);
});

test('assessment presentation keeps server lifecycle states stable', () => {
    assert.deepEqual(
        ['loading', 'saving', 'save-error', 'submitting', 'validation-error', 'expired', 'source-error', 'complete']
            .map((status) => presentationState({ status })),
        ['loading', 'saving', 'save-error', 'submitting', 'validation-error', 'expired', 'source-error', 'complete'],
    );
    assert.equal(presentationState({ status: 'in_progress' }), 'ready');
    assert.equal(presentationState({ status: 'unexpected' }), 'source-error');
});
