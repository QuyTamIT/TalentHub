const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('profile.php renders Dự án đã hoàn thành with compact card and modal triggers', () => {
    const html = fs.readFileSync(path.join(__dirname, '../app/learner/profile.php'), 'utf8');
    assert.match(html, /Dự án đã hoàn thành/);
    assert.doesNotMatch(html, /<h2 id="projects-title">Dự án đã tham gia<\/h2>/);
    assert.match(html, /data-open-project-detail/);
    assert.match(html, /ecosystem\.php\?tab=opportunities(&|&amp;)filter=completed/);
});
