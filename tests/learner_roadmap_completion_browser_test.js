'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

test('completion renders in a real browser with blocked storage and honest duration labels', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.setContent(`<main id="roadmap" class="learner-app">
            <div data-roadmap-ready><div class="learner-roadmap-plan__controls"></div>
            <section data-roadmap-celebration hidden></section><div data-roadmap-phases></div></div>
            </main><div data-roadmap-complete-modal hidden><button data-roadmap-complete-confirm>Confirm</button></div>`);
        await page.addStyleTag({ path: path.join(__dirname, '../assets/css/learner.css') });
        await page.addScriptTag({ path: path.join(__dirname, '../assets/js/learner-ai-roadmap.js') });
        const result = await page.evaluate(() => {
            const api = window.TalentHubLearnerAiRoadmap;
            const blocked = {};
            Object.defineProperty(blocked, 'localStorage', { get() { throw new DOMException('Blocked', 'SecurityError'); } });
            const root = document.querySelector('#roadmap');
            const view = api.createDomView(root, { global: blocked });
            const model = api.buildRoadmapViewModel({
                roadmap_id: 'browser-roadmap-a', progress: { completed_tasks: 3, total_tasks: 3 },
                phases: [1, 2, 3].map(position => ({ position, start_day: (position - 1) * 30 + 1,
                    end_day: position * 30, title: `Chặng ${position}`, progress: { completed_tasks: 1, total_tasks: 1 },
                    tasks: [{ task_id: `task-${position}`, title: 'Bài tập', estimated_minutes: 60, status: 'completed' }] })),
            });
            view.render('ready-model', model);
            view.continuePhase(3);
            const modalOpened = !document.querySelector('[data-roadmap-complete-modal]').hidden;
            view.confirmCompleteRoadmap();
            const summary = root.querySelector('[data-roadmap-celebration]');
            const completed = { visible: !summary.hidden, text: summary.textContent, confirmed: view.isRoadmapCompletedConfirmed() };
            view.updateTask('task-1', 'in_progress');
            view.confirmCompleteRoadmap(); // Stale confirm cannot complete unfinished work.
            const undone = summary.hidden && !view.isRoadmapCompletedConfirmed();
            view.render('ready-model', { ...model, roadmap_id: 'browser-roadmap-b' });
            const otherRoadmapConfirmed = view.isRoadmapCompletedConfirmed();
            view.dispose();
            return { modalOpened, completed, undone, otherRoadmapConfirmed };
        });
        assert.equal(result.modalOpened, true);
        assert.equal(result.completed.visible, true);
        assert.equal(result.completed.confirmed, true);
        assert.match(result.completed.text, /Thời lượng dự kiến.*3 giờ/);
        assert.doesNotMatch(result.completed.text, /đã nghiệm thu|Thời lượng tích lũy|Giai đoạn đạt chuẩn/);
        assert.match(result.completed.text, /có thể sử dụng lại lộ trình hiện tại/);
        assert.equal(result.undone, true);
        assert.equal(result.otherRoadmapConfirmed, false);
    } finally {
        await browser.close();
    }
});
