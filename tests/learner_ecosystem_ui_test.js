'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

require('../assets/js/learner.js');

const learnerSource = fs.readFileSync(path.join(__dirname, '..', 'assets/js/learner.js'), 'utf8');
const ecosystemPage = fs.readFileSync(path.join(__dirname, '..', 'app/learner/ecosystem.php'), 'utf8');
const partnerPage = fs.readFileSync(path.join(__dirname, '..', 'app/learner/partner.php'), 'utf8');
const opportunityPage = fs.readFileSync(path.join(__dirname, '..', 'app/learner/opportunity.php'), 'utf8');
const ecosystemData = fs.readFileSync(path.join(__dirname, '..', 'app/learner/includes/ecosystem-data.php'), 'utf8');
const activityData = fs.readFileSync(path.join(__dirname, '..', 'app/learner/includes/activity-data.php'), 'utf8');

function learnerPhpSources(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const itemPath = path.join(directory, entry.name);
        if (entry.isDirectory()) return learnerPhpSources(itemPath);
        return entry.isFile() && entry.name.endsWith('.php')
            ? [{ path: itemPath, source: fs.readFileSync(itemPath, 'utf8') }]
            : [];
    });
}
assert.doesNotMatch(learnerSource, /data-save-opportunity/, 'learner shell has no UI-only saved-opportunity mutation');
assert.doesNotMatch(opportunityPage, /data-save-opportunity/, 'opportunity page does not expose fake persistence without an endpoint');
assert.doesNotMatch(ecosystemPage, /Dữ liệu demo|FPT Software/, 'ecosystem database copy does not advertise mock data');
assert.doesNotMatch(partnerPage, /Dữ liệu demo|mock data|FPT Software/, 'partner database copy does not advertise mock data');
assert.match(ecosystemPage, /Chưa có doanh nghiệp đã xác minh/, 'database enterprise empty state is authoritative');
assert.match(ecosystemPage, /Chưa có trường học đang hoạt động/, 'database school empty state is authoritative');
assert.match(ecosystemPage, /Chưa có cơ hội đang mở/, 'database opportunity empty state is authoritative');
assert.match(
    ecosystemPage,
    /\$isDatabaseSource\s*&&\s*\$schools\s*===\s*\[\]/,
    'school source-empty copy is restricted to an empty database collection',
);
assert.match(partnerPage, /learner_ecosystem_http_url/, 'partner normalizes website through the http/https allowlist');
assert.doesNotMatch(
    partnerPage,
    /href="<\?=\s*learner_escape\(\$partner\['website'\]\)/,
    'partner never writes the stored website value directly into href',
);
assert.equal(
    (partnerPage.match(/href="<\?=\s*learner_escape\(\$partnerWebsiteUrl\)/g) || []).length,
    2,
    'both partner website links use the allowlisted URL',
);
assert.match(ecosystemPage, /learner_ecosystem_partner_has_value/, 'ecosystem cards use the shared source-aware optional predicate');
assert.match(partnerPage, /learner_ecosystem_partner_has_value/, 'partner detail uses the shared source-aware optional predicate');
assert.match(ecosystemPage, /data-ecosystem-item-type="internship"/, 'ecosystem marks enterprise internship items');
assert.doesNotMatch(ecosystemPage, /learner_ecosystem_school_activities/, 'ecosystem opportunities do not load school activities');
assert.doesNotMatch(ecosystemPage, /data-ecosystem-item-type="school-activity"/, 'ecosystem opportunities exclude school activity items');
assert.doesNotMatch(ecosystemPage, /activity-detail\.php\?id=/, 'ecosystem opportunities never route into school QR activities');
assert.match(partnerPage, /activity-detail\.php\?id=/, 'school partner routes activities to the activity workflow');
assert.doesNotMatch(partnerPage, /opportunity\.php\?type=activity/, 'school activities never enter the internship application workflow');
for (const { path: sourcePath, source } of learnerPhpSources(path.join(__dirname, '..', 'app/learner'))) {
    assert.doesNotMatch(source, /\$factory->activity\(\)->(?:all|findById)\(\)/, `${sourcePath} does not read global activities`);
    assert.doesNotMatch(source, /learner_activity_repository\(\)->(?:all|findById)\(\)/, `${sourcePath} does not read global activities`);
}
assert.doesNotMatch(activityData, /learner_activity_repository\(\)->(?:all|findById)\(\)/, 'production activity include never reads the global activity catalog');
assert.match(ecosystemData, /discoverForStudent\(/, 'school partner activities originate from the student-scoped discovery contract');
assert.match(learnerSource, /emptyReason\s*=\s*ecosystemItems\.length\s*===\s*0\s*\?\s*'source'\s*:\s*'filter'/, 'source-empty remains distinct from filter-empty');

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
