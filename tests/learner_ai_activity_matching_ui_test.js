const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const ui = require('../assets/js/learner-activity-matches.js');

test('controller loads saved stale cards without generating and refreshes explicitly', async () => {
    const renders = [], calls = [];
    const saved = { state: 'stale_model', items: [{ activity_id: 'a', title: 'Workshop', score: 70 }] };
    const controller = ui.createController({
        api: { get: async () => saved, send: async (...args) => { calls.push(args); return { ...saved, state: 'completed' }; } },
        render: (payload) => renders.push(payload),
    });
    await controller.load();
    assert.equal(calls.length, 0);
    assert.equal(renders.at(-1).state, 'stale_model');
    await controller.generate();
    assert.equal(calls[0][0], 'POST');
    assert.equal(calls[0][1], '/activity-matches.php');
    assert.equal(renders.at(-1).state, 'completed');
});
test('failed refresh preserves saved cards and allows retry', async () => {
    const renders = [];
    const controller = ui.createController({ api: { get: async () => ({ state: 'completed', items: [{ activity_id: 'a' }] }), send: async () => { throw Error('offline'); } }, render: p => renders.push(p) });
    await controller.load(); await controller.generate();
    assert.equal(renders.at(-1).state, 'stale_model');
    assert.equal(renders.at(-1).items[0].activity_id, 'a');
});

test('revalidation refreshes stale data without requiring another button click', async () => {
    let writes = 0, rendered;
    const c = ui.createController({ api: { get: async () => ({state:'stale_model',items:[]}), send: async () => { writes++; return {state:'completed',items:[]}; } }, render: p => {rendered=p;} });
    await c.revalidate();
    assert.equal(writes, 1);
    assert.equal(rendered.state, 'completed');
});
test('warning states do not masquerade as completed', async () => {
    for (const state of ['not_generated', 'insufficient_data', 'consent_required', 'no_matches']) {
        let rendered;
        await ui.createController({ api: { get: async () => ({ state, items: [] }) }, render: p => { rendered = p; } }).load();
        assert.equal(rendered.state, state);
    }
});

test('duplicate clicks cannot submit concurrently; result is capped at three cards', async () => {
    let release, calls = 0, result;
    const controller = ui.createController({ api: { send: () => { calls++; return new Promise(resolve => { release = resolve; }); } }, render: p => { result = p; } });
    const pending = controller.generate();
    await controller.generate();
    assert.equal(calls, 1);
    assert.equal(result.state, 'loading');
    assert.equal(result.progress, 0);
    release({ state: 'completed', items: Array.from({ length: 5 }, (_, i) => ({ activity_id: String(i) })) });
    await pending;
    assert.equal(result.items.length, 3);
    assert.equal(result.progress, 100);
});

test('authorization failure clears saved cards', async () => {
    let result;
    const controller = ui.createController({ api: { get: async () => ({ state: 'completed', items: [{ activity_id: 'a' }] }), send: async () => { throw Object.assign(Error('denied'), { status: 403 }); } }, render: p => { result = p; } });
    await controller.load(); await controller.generate();
    assert.deepEqual(result.items, []);
    assert.equal(result.state, 'error');
});

test('DOM cards use text, bounded score, local encoded link and accessible collapse', async () => {
    class Element {
        constructor() { this.children = []; this.attrs = {}; this.events = {}; this.hidden = false; }
        append(...children) { this.children.push(...children); }
        replaceChildren() { this.children = []; }
        setAttribute(key, value) { this.attrs[key] = value; }
        addEventListener(event, fn) { this.events[event] = fn; }
    }
    const nodes = Object.fromEntries(['[data-generate]', '[data-cards]', '[data-status]', 'progress', '[data-toggle]', '[data-body]', '[data-panel]'].map(key => [key, new Element()]));
    const root = new Element(); root.querySelector = key => nodes[key]; root.ownerDocument = { createElement: () => new Element() };
    let reads = 0;
    ui.mount(root, { get: async () => { reads++; }, send: async () => ({ state: 'completed', items: [{ activity_id: 'x&evil=1', title: '<script>bad</script>', score: 110, why_fit: 'Evidence', skills_to_develop: ['teamwork'] }] }) });
    assert.equal(reads, 0, 'mount must not request data before user clicks');
    assert.equal(nodes['[data-panel]'].hidden, true);
    await nodes['[data-generate]'].events.click();
    assert.equal(nodes['[data-panel]'].hidden, false);
    await new Promise(resolve => setImmediate(resolve));
    const card = nodes['[data-cards]'].children[0];
    assert.equal(card.children[0].textContent, '<script>bad</script>');
    assert.match(card.children[1].textContent, /100\/100/);
    assert.equal(card.children[4].href, 'activity-detail.php?id=x%26evil%3D1');
    assert.equal(nodes['[data-generate]'].disabled, false);
    nodes['[data-toggle]'].events.click();
    assert.equal(nodes['[data-body]'].hidden, true);
    assert.equal(nodes['[data-toggle]'].attrs['aria-expanded'], 'false');
});

test('analysis is between filters and activity cards, initially hidden', () => {
    const page = fs.readFileSync('app/learner/activities.php', 'utf8');
    assert.ok(page.indexOf('data-activity-matches') > page.indexOf('data-activity-availability-filter'));
    assert.ok(page.indexOf('data-activity-matches') < page.indexOf('data-activity-server-empty'));
    assert.match(page, /data-panel hidden/);
    assert.match(page, /learner-btn learner-btn--primary[^\n]*data-generate/);
});
test('API owns identity and guards mutation; candidates reuse school-scoped discovery', () => {
    const api = fs.readFileSync('app/learner/api/v1/activity-matches.php', 'utf8');
    assert.match(api, /studentId\('student_profile/);
    assert.match(api, /mutation\(/); assert.match(api, /PersistentActionRateLimiter/);
    assert.match(api, /ACTIVITY_MATCH_STORAGE_UNAVAILABLE/);
    const source = fs.readFileSync('app/learner/ai/Sources/Database/DatabaseActivityCandidateSource.php', 'utf8');
    assert.match(source, /discoverForStudent/);
    assert.match(source, /row\['school_id'\]/);
    assert.match(source, /schoolQuery->execute\(\[\$studentId\]\)/);
    assert.doesNotMatch(source, /project_submissions/);
    const page = fs.readFileSync('app/learner/activities.php', 'utf8');
    assert.match(page, /data-activity-matches/);
    const js = fs.readFileSync('assets/js/learner-activity-matches.js', 'utf8');
    assert.match(js, /textContent/); assert.doesNotMatch(js, /innerHTML/);
    assert.match(js, /encodeURIComponent/); assert.match(js, /aria-expanded/);
});
