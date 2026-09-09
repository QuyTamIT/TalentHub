const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('ecosystem.php initialises completed filter and applies data-status to cards', () => {
    const html = fs.readFileSync(path.join(__dirname, '../app/learner/ecosystem.php'), 'utf8');
    
    // Lifecycle filter initialization
    assert.match(html, /\$initialLifecycleFilter\s*=/);
    assert.match(html, /data-ecosystem-filter="status"/);
    assert.match(html, /<option value="completed"/);
    
    // Enterprise cards have data-status attribute reflecting completed internships
    assert.match(html, /data-status=.*completed/s);
    
    // Project cards have data-status attribute reflecting project status
    assert.match(html, /data-ecosystem-item-type="project"/);
});

test('profile.php has bidirectional links pointing to ecosystem completed filters', () => {
    const profileHtml = fs.readFileSync(path.join(__dirname, '../app/learner/profile.php'), 'utf8');
    
    // Links to opportunities tab with filter=completed
    assert.match(profileHtml, /ecosystem\.php\?tab=opportunities(&|&amp;)filter=completed/);
    
    // Links to enterprises tab with filter=completed
    assert.match(profileHtml, /ecosystem\.php\?tab=enterprises(&|&amp;)filter=completed/);
});

test('learner.js ecosystemItemMatches strictly filters completed status', () => {
    const js = fs.readFileSync(path.join(__dirname, '../assets/js/learner.js'), 'utf8');
    
    // Evaluates ecosystemItemMatches implementation
    assert.match(js, /function ecosystemItemMatches/);
    assert.match(js, /status === 'completed'/);
    
    // Extract ecosystemItemMatches implementation
    const fnMatch = js.match(/function ecosystemItemMatches\([\s\S]*?\n\s*return\s+[\s\S]*?;\s*\}/);
    assert.ok(fnMatch, 'ecosystemItemMatches should be defined');
    
    const normalizeSearchText = (text) => String(text || '').trim().toLowerCase();
    const evalFn = new Function('normalizeSearchText', `
        ${fnMatch[0]}
        return ecosystemItemMatches;
    `)(normalizeSearchText);
    
    // Filter by completed
    const completedFilter = { status: 'completed' };
    
    // 1. Completed item must match
    assert.equal(evalFn({ status: 'completed' }, completedFilter), true);
    assert.equal(evalFn({ status: 'recruiting completed' }, completedFilter), true);
    
    // 2. Non-completed items must NOT match
    assert.equal(evalFn({ status: 'recruiting' }, completedFilter), false);
    assert.equal(evalFn({ status: 'active' }, completedFilter), false);
    assert.equal(evalFn({ status: '' }, completedFilter), false);
    assert.equal(evalFn({}, completedFilter), false);
    
    // 3. Filter by all matches everything
    assert.equal(evalFn({ status: 'completed' }, { status: 'all' }), true);
    assert.equal(evalFn({ status: 'recruiting' }, { status: 'all' }), true);
    assert.equal(evalFn({ status: '' }, { status: 'all' }), true);
});
