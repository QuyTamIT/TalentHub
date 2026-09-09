const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('learner-portfolio.js handles completed filter and renders compact cards', () => {
    const js = fs.readFileSync(path.join(__dirname, '../assets/js/learner-portfolio.js'), 'utf8');
    assert.match(js, /filter=completed/);
    assert.match(js, /data-open-internship-detail/);
    assert.match(js, /partner\.php\?type=enterprise/);
});

test('portfolio-panel.php has completed filter attribute and updated heading for profile showcase', () => {
    const php = fs.readFileSync(path.join(__dirname, '../app/learner/includes/portfolio-panel.php'), 'utf8');
    assert.match(php, /Quá trình thực tập đã hoàn thành/);
    assert.match(php, /data-filter="completed"/);
    assert.match(php, /ecosystem\.php\?tab=enterprises(&|&amp;)filter=completed/);
});
