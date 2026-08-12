'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const scriptPath = path.join(__dirname, '..', 'assets', 'js', 'learner.js');

global.window = {};
global.document = undefined;

if (fs.existsSync(scriptPath)) {
    require(scriptPath);
}

const learnerUI = global.window.LearnerUI || {};

test('profile validation rejects blank required fields', () => {
    assert.equal(typeof learnerUI.validateProfile, 'function');
    const result = learnerUI.validateProfile({
        name: ' ',
        class: 'Lớp 11A2',
        school: 'THPT Nguyễn Du',
        email: 'a.nguyen@school.edu.vn',
        location: 'Hà Nội',
    });

    assert.deepEqual(result, {
        valid: false,
        field: 'name',
        message: 'Vui lòng nhập họ và tên.',
    });
});

test('profile validation rejects malformed email', () => {
    assert.equal(typeof learnerUI.validateProfile, 'function');
    const result = learnerUI.validateProfile({
        name: 'Nguyễn Văn A',
        class: 'Lớp 11A2',
        school: 'THPT Nguyễn Du',
        email: 'email-khong-hop-le',
        location: 'Hà Nội',
    });

    assert.equal(result.valid, false);
    assert.equal(result.field, 'email');
});

test('assessment start advances to continue state', () => {
    assert.equal(typeof learnerUI.nextAssessmentState, 'function');
    assert.equal(learnerUI.nextAssessmentState('start'), 'continue');
    assert.equal(learnerUI.nextAssessmentState('continue'), 'continue');
    assert.equal(learnerUI.nextAssessmentState('result'), 'result');
});

test('only implemented learner routes navigate directly', () => {
    assert.equal(typeof learnerUI.isImplementedRoute, 'function');
    assert.equal(learnerUI.isImplementedRoute('/app/learner/profile.php'), true);
    assert.equal(learnerUI.isImplementedRoute('/app/learner/discover.php'), true);
    assert.equal(learnerUI.isImplementedRoute('/app/learner/activities.php'), true);
    assert.equal(learnerUI.isImplementedRoute('/app/learner/checkin.php'), true);
    assert.equal(learnerUI.isImplementedRoute('/app/learner/evaluation.php'), true);
    assert.equal(learnerUI.isImplementedRoute('/app/learner/ai-suggestions.php'), false);
});

test('activity matching combines accent-insensitive query and category', () => {
    assert.equal(typeof learnerUI.activityMatches, 'function');
    const activity = {
        title: 'IoT Lab — Cảm biến thông minh',
        category: 'Công nghệ',
        filterCategory: 'Kỹ thuật',
        location: 'Phòng B305',
    };

    assert.equal(learnerUI.activityMatches(activity, 'cam bien', 'Kỹ thuật'), true);
    assert.equal(learnerUI.activityMatches(activity, 'PHONG B305', 'Tất cả'), true);
    assert.equal(learnerUI.activityMatches(activity, 'ho tay', 'Kỹ thuật'), false);
    assert.equal(learnerUI.activityMatches(activity, '', 'Cộng đồng'), false);
});

test('evaluation term resolver returns published and empty terms safely', () => {
    assert.equal(typeof learnerUI.getEvaluationTerm, 'function');
    const terms = {
        published: { status: 'Đã công bố', evaluation: { total: 90 } },
        empty: { status: 'Chưa có dữ liệu', evaluation: null },
    };

    assert.equal(learnerUI.getEvaluationTerm(terms, 'published').evaluation.total, 90);
    assert.equal(learnerUI.getEvaluationTerm(terms, 'empty').evaluation, null);
    assert.equal(learnerUI.getEvaluationTerm(terms, 'missing'), null);
    assert.equal(learnerUI.getEvaluationTerm(null, 'published'), null);
});

test('AI recommendation state resolves ready and insufficient data', () => {
    assert.equal(typeof learnerUI.getAiRecommendationState, 'function');
    assert.equal(learnerUI.getAiRecommendationState({ sufficient: true }), 'ready');
    assert.equal(learnerUI.getAiRecommendationState({ sufficient: false }), 'insufficient');
    assert.equal(learnerUI.getAiRecommendationState(null), 'insufficient');
});

test('badge status matching supports all and exact states', () => {
    assert.equal(typeof learnerUI.badgeMatchesStatus, 'function');
    assert.equal(learnerUI.badgeMatchesStatus('achieved', 'all'), true);
    assert.equal(learnerUI.badgeMatchesStatus('achieved', 'achieved'), true);
    assert.equal(learnerUI.badgeMatchesStatus('locked', 'achieved'), false);
});

test('statistics period resolver rejects unknown periods', () => {
    assert.equal(typeof learnerUI.getStatisticsPeriod, 'function');
    const periods = { six: { kpis: [] } };

    assert.equal(learnerUI.getStatisticsPeriod(periods, 'six'), periods.six);
    assert.equal(learnerUI.getStatisticsPeriod(periods, 'missing'), null);
    assert.equal(learnerUI.getStatisticsPeriod(null, 'six'), null);
});

test('line chart points stay inside the requested SVG area', () => {
    assert.equal(typeof learnerUI.buildLineChartPoints, 'function');
    assert.deepEqual(
        learnerUI.buildLineChartPoints([0, 10, 20], 200, 100, 20),
        [[0, 100], [100, 50], [200, 0]]
    );
    assert.deepEqual(learnerUI.buildLineChartPoints([], 200, 100, 20), []);
});
