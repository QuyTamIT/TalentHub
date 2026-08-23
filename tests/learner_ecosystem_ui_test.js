'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

require('../assets/js/learner.js');

const learnerSource = fs.readFileSync(path.join(__dirname, '..', 'assets/js/learner.js'), 'utf8');
const opportunityPage = fs.readFileSync(path.join(__dirname, '..', 'app/learner/opportunity.php'), 'utf8');
assert.doesNotMatch(learnerSource, /data-save-opportunity/, 'learner shell has no UI-only saved-opportunity mutation');
assert.doesNotMatch(opportunityPage, /data-save-opportunity/, 'opportunity page does not expose fake persistence without an endpoint');

const {
    ecosystemItemMatches,
    applicationMatches,
    canApplyToOpportunity,
    validateApplication,
} = global.LearnerUI;

test('ecosystem search is accent insensitive and honors filters', () => {
    const item = {
        search: 'Đại học Bách khoa Hà Nội Kỹ thuật máy tính',
        field: 'Kỹ thuật Công nghệ',
        location: 'Hà Nội',
    };

    assert.equal(ecosystemItemMatches(item, { query: 'bach khoa', field: 'all', location: 'all' }), true);
    assert.equal(ecosystemItemMatches(item, { query: '', field: 'Công nghệ', location: 'Hà Nội' }), true);
    assert.equal(ecosystemItemMatches(item, { query: '', field: 'Kinh doanh', location: 'all' }), false);
});

test('application filtering combines status and query', () => {
    const application = { search: 'FPT Software Frontend Developer', status: 'reviewing' };
    assert.equal(applicationMatches(application, 'frontend', 'reviewing'), true);
    assert.equal(applicationMatches(application, 'frontend', 'interview'), false);
    assert.equal(applicationMatches(application, 'không có', 'all'), false);
});

test('application availability rejects closed and expired opportunities', () => {
    assert.equal(canApplyToOpportunity({ status: 'active', deadline: '2026-08-30' }, '2026-08-13'), true);
    assert.equal(canApplyToOpportunity({ status: 'closed', deadline: '2026-08-30' }, '2026-08-13'), false);
    assert.equal(canApplyToOpportunity({ status: 'active', deadline: '2026-08-01' }, '2026-08-13'), false);
});

test('application validation requires consent and enforces message length', () => {
    assert.deepEqual(validateApplication({ consent: false, message: '' }), {
        valid: false,
        field: 'consent',
        message: 'Bạn cần đồng ý chia sẻ hồ sơ trước khi ứng tuyển.',
    });
    assert.equal(validateApplication({ consent: true, message: 'a'.repeat(500) }).valid, true);
    assert.equal(validateApplication({ consent: true, message: 'a'.repeat(501) }).valid, false);
    assert.deepEqual(validateApplication({ consent: true, message: 'Tôi rất quan tâm.' }), {
        valid: true,
        field: '',
        message: '',
    });
});
