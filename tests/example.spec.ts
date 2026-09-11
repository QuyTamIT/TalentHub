import { test, expect } from '@playwright/test';

test('trang chủ TalentHub tải được', async ({ page }) => {
  await page.goto('/');

  // Sử dụng baseURL đã cấu hình ('http://127.0.0.1:8080')
  await expect(page).toHaveURL('/');
  await expect(page.locator('body')).toBeVisible();
});
