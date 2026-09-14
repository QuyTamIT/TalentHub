'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

test('failed refresh retains the old roadmap without claiming an update or ongoing analysis', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.route('http://roadmap.test/', route => route.fulfill({ body: '<html></html>', contentType: 'text/html' }));
        await page.goto('http://roadmap.test/');
        const names = 'status processing processing-label processing-title processing-copy processing-percent processing-elapsed processing-bar processing-steps processing-note processing-retry ready freshness version-changes summary-text';
        await page.setContent(`<section id="roadmap">${names.split(' ').map(name => `<div data-roadmap-${name}></div>`).join('')}<button data-roadmap-generate="refresh"></button></section>`);
        await page.locator('[data-roadmap-processing-steps]').evaluate(el => { el.innerHTML = '<div data-processing-step="0"></div>'; });
        await page.addScriptTag({ path: path.join(__dirname, '../assets/js/learner-ai-roadmap.js') });
        const result = await page.evaluate(async () => {
            const api = TalentHubLearnerAiRoadmap;
            const root = document.querySelector('#roadmap');
            const view = api.createDomView(root, { schedule: () => 1, cancelSchedule() {} });
            view.render('processing', {});
            const activeWhileRunning = root.querySelector('[data-processing-step]').classList.contains('is-active');
            const saved = { state: 'ready_model', roadmap_id: 'saved-roadmap', version: 18, executive_summary: 'Lộ trình cũ.', generated_at: '2026-09-14', changed_sections_from_previous: ['phases'] };
            const controller = api.createRoadmapController({
                api: { get: async () => saved, send: async () => ({ ...saved, state: 'stale_model', refresh_state: 'fallback_not_applied', availability_reason: 'invalid_model_response' }) },
                view,
                createIdempotencyKey: () => 'retry-test',
            });
            await controller.load();
            await controller.generate('refresh');
            const read = name => root.querySelector(`[data-roadmap-${name}]`).textContent;
            const failed = { summary: read('summary-text'), changed: read('version-changes'), freshness: read('freshness'), label: read('processing-label'), active: root.querySelector('[data-processing-step]').classList.contains('is-active'), processing: !root.querySelector('[data-roadmap-processing]').hidden, retry: !root.querySelector('[data-roadmap-processing-retry]').hidden, disabled: root.querySelector('button').disabled };
            controller.render({ ...saved, version: 19, executive_summary: 'Lộ trình mới.' });
            const success = { summary: read('summary-text'), changed: read('version-changes'), freshness: read('freshness') };
            controller.dispose();
            return { failed, success, activeWhileRunning };
        });
        assert.equal(result.failed.summary, 'Lộ trình cũ.');
        assert.equal(result.failed.processing, true);
        assert.equal(result.failed.retry, true);
        assert.equal(result.failed.disabled, false);
        assert.equal(result.activeWhileRunning, true);
        assert.equal(result.failed.active, false);
        assert.doesNotMatch(result.failed.changed, /đã được cập nhật/);
        assert.doesNotMatch(result.failed.freshness, /Đang thử cập nhật/);
        assert.equal(result.failed.label, 'CẬP NHẬT CHƯA HOÀN TẤT');
        assert.equal(result.success.summary, 'Lộ trình mới.');
        assert.match(result.success.changed, /đã được cập nhật/);
        assert.match(result.success.freshness, /Cập nhật ngày/);
    } finally {
        await browser.close();
    }
});
