const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('profile.php has detail modals for project and internship', () => {
    const html = fs.readFileSync(path.join(__dirname, '../app/learner/profile.php'), 'utf8');
    assert.match(html, /id="learner-project-detail-modal"/);
    assert.match(html, /id="learner-internship-detail-modal"/);
});
