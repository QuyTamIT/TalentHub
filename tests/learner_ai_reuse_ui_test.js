'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('C:/Users/CHI NGUYEN/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const jobs = require('../assets/js/learner-job-matches.js');
const projects = require('../assets/js/learner-opportunity-matches.js');
const roadmap = require('../assets/js/learner-ai-roadmap.js');
const summary = require('../assets/js/learner-ai-summary.js');
const notice = 'Dữ liệu không thay đổi. Đang hiển thị kết quả phân tích trước đó.';
const reused = { reused: true, data_changed: false, reuse_reason: 'inputs_unchanged', message: notice };

for (const [name, factory, result] of [
    ['job', jobs.createJobMatchController, { state: 'no_matching_jobs', enterprise_groups: [] }],
    ['project', projects.createOpportunityMatchController, { state: 'no_fit_model', items: [], analysis: { headline: 'Chưa phù hợp', explanation: 'Cần thêm kỹ năng.' } }],
    ['roadmap', roadmap.createRoadmapController, { state: 'ready_model', executive_summary: 'Bản đã lưu.' }],
]) {
    test(`${name} posts for server validation and returns confirmed reuse without polling or waiting`, async () => {
        const renders = []; let posts = 0;
        const controller = factory({
            api: { get: async () => assert.fail('must not poll a completed response'), send: async () => { posts++; return { ...result, ...reused }; } },
            view: { render: (state, payload) => renders.push({ state, payload }) },
            createIdempotencyKey: () => 'synthetic-reuse-key',
            wait: () => assert.fail('must not wait on completed reuse'),
            schedule: () => assert.fail('must not schedule polling on completed reuse'),
        });
        await controller.generate();
        assert.equal(posts, 1);
        assert.equal(renders.at(-1).payload.reuse_reason, 'inputs_unchanged');
        assert.equal(renders.at(-1).payload.data_changed, false);
    });
}

test('summary propagates confirmed reuse immediately without a pending wait', async () => {
    const renders = [];
    const controller = summary.createAiSummaryController({
        api: { get: async () => ({ state: 'stale_model' }), send: async () => ({ state: 'ready_model', executive_summary: 'Bản đã lưu.', ...reused }) },
        view: { open() {}, close() {}, render: (state, payload) => renders.push({ state, payload }) },
        wait: () => assert.fail('must not wait on completed reuse'),
    });
    await controller.run();
    assert.equal(renders.at(-1).payload.reuse_reason, 'inputs_unchanged');
});

test('browser shows confirmed reuse, keeps visible results, and skips only cache-hit success animation', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        const region = (id, prefix, names) => `<section id="${id}">${names.split(' ').map(name => `<div data-${prefix}-${name} hidden></div>`).join('')}</section>`;
        await page.setContent(region('job', 'job-ai', 'status progress progress-text progress-pct progress-bar progress-stages list near-match not-generated consent-required insufficient-data catalog-insufficient no-matches source-error')
            + region('project', 'opportunity-ai', 'status loading list results progress-text progress-pct progress-bar progress-stages not-generated consent insufficient catalog-insufficient low-fit no-fit error')
            + region('roadmap', 'roadmap', 'status processing processing-title processing-copy processing-percent processing-elapsed processing-bar processing-steps processing-note processing-retry ready loading not-generated consent insufficient pending error summary-text')
            + region('summary', 'ai-summary', 'eyebrow title live spinner message text retry detail'));
        for (const file of ['learner-job-matches', 'learner-opportunity-matches', 'learner-ai-roadmap', 'learner-ai-summary']) {
            await page.addScriptTag({ path: path.join(__dirname, '..', 'assets/js', `${file}.js`) });
        }
        const observed = await page.evaluate(({ reused }) => {
            const el = selector => document.querySelector(selector);
            const job = TalentHubJobMatches.createJobMatchView(el('#job'));
            const project = TalentHubOpportunityMatches.createOpportunityMatchView(el('#project'));
            const scheduled = [];
            const road = TalentHubLearnerAiRoadmap.createDomView(el('#roadmap'), { schedule: (fn, delay) => { scheduled.push(delay); return scheduled.length; }, cancelSchedule() {} });
            const sum = TalentHubLearnerAiSummary.createDomAiSummaryView(el('#summary'));
            const model = TalentHubLearnerAiRoadmap.buildRoadmapViewModel({ state: 'ready_model', executive_summary: 'Bản đã lưu.', ...reused });
            job.render('no-matches', reused);
            project.render('no-fit-model', { ...reused, items: [], analysis: {} });
            road.render('processing', {});
            road.render('ready-model', model);
            sum.render('ready_model', model);
            const notices = ['job-ai-status', 'opportunity-ai-status', 'roadmap-status', 'ai-summary-message'].map(name => el(`[data-${name}]`).textContent);
            const cachedProcessingHidden = el('[data-roadmap-processing]').hidden;
            const cacheScheduledSuccess = scheduled.includes(1500);
            const summaryVisible = el('[data-ai-summary-text]').textContent;
            job.render('loading', { initial: false });
            project.render('loading', {});
            sum.render('loading', {});
            const preserved = [!el('[data-job-ai-no-matches]').hidden, !el('[data-opportunity-ai-no-fit]').hidden, el('[data-ai-summary-text]').textContent === summaryVisible];
            const checkingCopy = ['job-ai-status', 'opportunity-ai-status', 'ai-summary-message'].map(name => el(`[data-${name}]`).textContent);
            const unconfirmedNotices = [];
            for (const flags of [{ reused: true }, { reused: true, data_changed: true, reuse_reason: 'inputs_unchanged' }, { reused: true, data_changed: false, reuse_reason: 'pending' }]) {
                job.render('no-matches', flags); project.render('no-fit-model', flags);
                road.render('ready-model', { ...model, ...flags, message: undefined, data_changed: flags.data_changed, reuse_reason: flags.reuse_reason });
                sum.render('ready_model', flags);
                unconfirmedNotices.push(...['job-ai-status', 'opportunity-ai-status', 'roadmap-status', 'ai-summary-message'].map(name => el(`[data-${name}]`).textContent));
            }
            job.render('pending', reused); project.render('pending', reused); sum.render('pending', reused);
            const pendingNotices = ['job-ai-status', 'opportunity-ai-status', 'ai-summary-message'].map(name => el(`[data-${name}]`).textContent);
            road.render('processing', {});
            road.render('ready-model', { ...model, reused: false, data_changed: true, reuse_reason: undefined });
            const freshScheduledSuccess = scheduled.includes(1500);
            road.dispose();
            return { notices, cachedProcessingHidden, cacheScheduledSuccess, preserved, checkingCopy, unconfirmedNotices, pendingNotices, freshScheduledSuccess };
        }, { reused });
        assert.deepEqual(observed.notices, Array(4).fill(notice));
        assert.equal(observed.cachedProcessingHidden, true);
        assert.equal(observed.cacheScheduledSuccess, false);
        assert.deepEqual(observed.preserved, [true, true, true]);
        assert.ok([...observed.checkingCopy, ...observed.unconfirmedNotices, ...observed.pendingNotices].every(copy => copy !== notice));
        assert.equal(observed.freshScheduledSuccess, true);
    } finally { await browser.close(); }
});
