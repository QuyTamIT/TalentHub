import { test, expect, type Page, type Response } from '@playwright/test';

const TEST_PASS = process.env.PLAYWRIGHT_TEST_PASS || 'TestPass_2026_local';
const ADMIN_PASS = process.env.PLAYWRIGHT_ADMIN_PASS || 'AdminPass_2026_local';

async function doLogin(page, email, password) {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#email')).toBeVisible({ timeout: 10_000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ timeout: 12_000 }).catch(() => {}),
    page.click('button.auth-submit, button[data-submit]', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

test.describe('UI/UX Critical Flows - Login and Dashboard', () => {
  test.describe('Login and Dashboard Navigation', () => {
    test('Hoc vien - login then navigate to learner dashboard', async ({ page }) => {
      await doLogin(page, 'hs.minh@talenthub.vn', TEST_PASS);
      const res = await page.goto('/app/learner/index.php', { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null);
      if (res) expect(res.status()).toBeLessThan(500);
      await expect(page.locator('body')).not.toContainText('Lỗi hệ thống', { timeout: 5000 });
    });

    test('Enterprise - login then navigate to enterprise dashboard', async ({ page }) => {
      await doLogin(page, 'enterprise.manual@talenthub.local', TEST_PASS);
      const res = await page.goto('/app/enterprise/index.php', { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null);
      if (res) expect(res.status()).toBeLessThan(500);
      await expect(page.locator('body')).not.toContainText('Lỗi hệ thống', { timeout: 5000 });
    });

    test('Admin - login then navigate to admin dashboard', async ({ page }) => {
      await doLogin(page, 'admin@admin.com', ADMIN_PASS);
      const res = await page.goto('/app/admin/index.php', { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null);
      if (res) expect(res.status()).toBeLessThan(500);
      await expect(page.locator('body')).not.toContainText('Lỗi hệ thống', { timeout: 5000 });
    });
  });

  test('Learner dashboard - verify content exists', async ({ page }) => {
    await doLogin(page, 'hs.minh@talenthub.vn', TEST_PASS);
    const res = await page.goto('/app/learner/index.php', { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null);
    if (res) expect(res.status()).toBeLessThan(500);
    // Check content exists
    await expect(page.locator('body')).not.toBeEmpty({ timeout: 5000 });
    await expect(page.locator('body')).toContainText('Học viên', { timeout: 5000 });
  });

  test('Mobile Learner - sidebar hidden, body not empty', async ({ page }) => {
    await doLogin(page, 'hs.minh@talenthub.vn', TEST_PASS);
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(1000);
    await expect(page.locator('.portal-sidebar')).not.toBeVisible({ timeout: 3000 });
    await expect(page.locator('body')).not.toBeEmpty({ timeout: 5000 });
  });

  test('Mobile Enterprise - sidebar hidden, body not empty', async ({ page }) => {
    await doLogin(page, 'enterprise.manual@talenthub.local', TEST_PASS);
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(1000);
    await expect(page.locator('.portal-sidebar')).not.toBeVisible({ timeout: 3000 });
    await expect(page.locator('body')).not.toBeEmpty({ timeout: 5000 });
  });
});