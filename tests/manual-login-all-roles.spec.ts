import { test, expect, type Page } from '@playwright/test';

/**
 * Manual verification script - opens 5 Chromium windows, one per role,
 * logs in and keeps browser open for hand-testing.
 *
 * Run: npx playwright test tests/manual-login-all-roles.spec.ts --project=chromium --headed
 */

const ROLES = [
  { label: 'Student', email: 'hs.minh@talenthub.vn', pass: 'TestPass_2026_local', dash: '/app/learner/index.php' },
  { label: 'Teacher', email: 'teacher.manual@talenthub.local', pass: 'TestPass_2026_local', dash: '/app/teacher/index.php' },
  { label: 'School', email: 'school.manual@talenthub.local', pass: 'TestPass_2026_local', dash: '/app/school/index.php' },
  { label: 'Enterprise', email: 'enterprise.manual@talenthub.local', pass: 'TestPass_2026_local', dash: '/app/enterprise/index.php' },
  { label: 'Admin', email: 'admin@admin.com', pass: 'AdminPass_2026_local', dash: '/app/admin/index.php' },
] as const;

test.describe('Manual: login all roles (browser stays open for hand-test)', () => {
  for (const r of ROLES) {
    test(`[${r.label}] ${r.email} -> ${r.dash}`, async ({ page }) => {
      await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#email')).toBeVisible({ timeout: 10_000 });
      await page.fill('#email', r.email);
      await page.fill('#password', r.pass);
      await Promise.all([
        page.waitForNavigation({ timeout: 12_000 }).catch(() => {}),
        page.click('button.auth-submit'),
      ]);
      await page.waitForLoadState('domcontentloaded');
      // Assert we left /login.php (Bug-2 safeguard)
      expect(page.url(), `[${r.label}] still on login.php`).not.toContain('/login.php');
      // Navigate to dashboard to confirm content
      await page.goto(r.dash, { waitUntil: 'domcontentloaded', timeout: 15_000 });
      await expect(page.locator('body')).not.toContainText('Lỗi hệ thống', { timeout: 5_000 });
      // Keep page open until test timeout (default 30s) - manual inspection window
      await page.waitForTimeout(30_000);
    });
  }
});
