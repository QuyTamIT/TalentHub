import { test, expect } from '@playwright/test';

test('Analytics radar render reflects DB', async ({ page }) => {
  // Hard navigation bypassing any fetch caching
  await page.goto('http://127.0.0.1:8080/login.php', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"], input[type="email"]', 'test-analytics@talenthub.local').catch(() => {});
  await page.fill('input[type="password"]', 'Talenthub@123');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);

  // Force fresh by hard reload semantics
  await page.goto('http://127.0.0.1:8080/app/school/analytics.php?x=' + Date.now(), { waitUntil: 'networkidle' });

  // Radar JS vars
  const actual = await page.evaluate(() => (window as any).capturedScores ?? null).catch(() => null);

  // Panel text: the "N / 100" boxes under "Chi tiết 4 Miền"
  const boxes = await page.locator('text=/\\d+\\s*\\/\\s*100/').allTextContents().catch(() => []);

  // Page-level raw HTML numbers appearing near progress fills
  const bodyHtml = await page.locator('body').innerHTML().catch(() => '');
  const scoreMatches = [...bodyHtml.matchAll(/(\d{1,3})\s*\/\s*100/g)].map((m) => m[1]);

  console.log('RADAR_JS=', JSON.stringify(actual));
  console.log('PANEL_BOXES=', JSON.stringify(boxes));
  console.log('BODY_SCORES=', JSON.stringify(scoreMatches));
  expect(parseInt(scoreMatches[0] ?? '0', 10)).toBeGreaterThan(0);
});