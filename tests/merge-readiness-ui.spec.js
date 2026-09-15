const { test, expect } = require('@playwright/test');
const path = require('node:path');

test('skill gap renders missing, unknown target, zero and met states accurately', async ({ page }) => {
  await page.setContent(`<section id="fixture">
    <div data-skill-gap-target></div><div data-skill-gap-scores></div>
    <div data-skill-gap-status></div><div data-skill-gap-content>
    <div data-skill-gap-met></div><div data-skill-gap-missing></div>
    <div data-skill-gap-activities></div></div></section>`);
  await page.addScriptTag({ path: path.join(__dirname, '../assets/js/learner-skill-gap.js') });
  await page.evaluate(() => {
    const model = window.TalentHubSkillGap.normalizeSkillGapPayload({
      state: 'ready_model', enterprise_groups: [], skill_gap: {
        state: 'ok', role: { code: 'developer', title: 'Developer' },
        skills_met: [{ code: 'git', label: 'Git', current_score: 80, target_score: 70, gap_score: 0 }],
        skills_missing: [
          { code: 'sql', label: 'SQL', current_score: null, target_score: 70, gap_score: null },
          { code: 'php', label: 'PHP', current_score: 80, target_score: null, gap_score: null, impact: 'Bạn chưa có kỹ năng này trong hồ sơ.' },
          { code: 'js', label: 'JavaScript', current_score: 0, target_score: 50, gap_score: 50 },
        ], recommended_activities: [],
      },
    });
    window.TalentHubSkillGap.createSkillGapView(document.querySelector('#fixture')).render('ready-model', model);
  });
  const cards = page.locator('[data-skill-gap-missing] article');
  const sql = cards.filter({ has: page.getByRole('heading', { name: 'SQL', exact: true }) });
  await expect(sql).toContainText('Chưa có');
  await expect(sql).toContainText('Bạn chưa có kỹ năng này trong hồ sơ.');
  await expect(sql.locator('.learner-skill-gap__skill-metrics')).toHaveCount(0);
  const php = cards.filter({ has: page.getByRole('heading', { name: 'PHP', exact: true }) });
  await expect(php).toContainText('Cần đối chiếu');
  await expect(php).toContainText('Vị trí chưa công bố mức yêu cầu.');
  await expect(php).toContainText('80/100');
  await expect(php).not.toContainText('Bạn chưa có kỹ năng');
  const js = cards.filter({ has: page.getByRole('heading', { name: 'JavaScript', exact: true }) });
  await expect(js).toContainText('Thiếu 50đ');
  await expect(js).toContainText('Hiện tại: 0/100');
  await expect(page.locator('[data-skill-gap-met]')).toContainText('Đã đạt yêu cầu');
});
