'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');

test('learner applications tracker is rendered inside ecosystem enterprises tab', () => {
    const ecosystem = fs.readFileSync(path.join(root, 'app/learner/ecosystem.php'), 'utf8');

    // Section and toggle elements
    assert.match(ecosystem, /class="[^"]*learner-applications-tracker[^"]*"/);
    assert.match(ecosystem, /data-applications-tracker/);
    assert.match(ecosystem, /data-tracker-toggle/);
    assert.match(ecosystem, /data-tracker-body/);
    assert.match(ecosystem, /data-app-card/);
    assert.match(ecosystem, /data-app-id=/);
    assert.match(ecosystem, /learner-application-timeline/);
    assert.match(ecosystem, /learner-applications-tracker\.js/);

    // Strict compliance with Phase 7 contract
    assert.doesNotMatch(ecosystem, /data-application-id=/);
    assert.doesNotMatch(ecosystem, /learner-application-drawer/);
    assert.doesNotMatch(ecosystem, /Hồ sơ tại thời điểm ứng tuyển/);
});

test('learner-applications-tracker.js handles toggle, auto-expand, and withdrawal confirmation', () => {
    const trackerJs = fs.readFileSync(path.join(root, 'assets/js/learner-applications-tracker.js'), 'utf8');

    assert.match(trackerJs, /data-applications-tracker/);
    assert.match(trackerJs, /data-tracker-toggle/);
    assert.match(trackerJs, /data-tracker-body/);
    assert.match(trackerJs, /urlParams\.get\('view'\) === 'applications'/);
    assert.match(trackerJs, /window\.confirm/);
    assert.match(trackerJs, /send\('PATCH', '\/applications\.php'/);
    assert.match(trackerJs, /action:\s*'withdraw'/);
});

test('my-applications.php redirects to ecosystem with applications view', () => {
    const myApps = fs.readFileSync(path.join(root, 'app/learner/my-applications.php'), 'utf8');
    assert.match(myApps, /header\('Location:\s*ecosystem\.php\?tab=enterprises&view=applications'/);
});

test('ecosystem repository joins internship_applications for participation status', () => {
    const repo = fs.readFileSync(path.join(root, 'app/learner/data/Database/DatabaseEcosystemRepository.php'), 'utf8');
    assert.match(repo, /LEFT JOIN internship_applications ia ON ia\.postId = ip\.id AND ia\.studentId = :student_id/);
    assert.match(repo, /user_participation_status/);
    assert.match(repo, /membership_status/);
});
