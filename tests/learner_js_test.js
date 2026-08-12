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
    assert.equal(learnerUI.isImplementedRoute('/app/learner/activities.php'), false);
});
