import { test, expect } from '@playwright/test';

const BASE = 'http://127.0.0.1:8080';
const SCHOOL_USER = {
  email: 'school@talenthub.local',
  password: 'Talenthub@123',
};

test.describe('School Account - Level Select & Update', () => {
  test('should display 3 standard levels and allow updating level', async ({ page }) => {
    // 1. Log in as school
    await page.goto(`${BASE}/login.php`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#email')).toBeVisible({ timeout: 8_000 });
    await page.fill('#email', SCHOOL_USER.email);
    await page.fill('#password', SCHOOL_USER.password);
    await Promise.all([
      page.waitForNavigation({ timeout: 12_000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]'),
    ]);
    await page.waitForLoadState('domcontentloaded');

    // 2. Go to /app/school/account.php
    await page.goto(`${BASE}/app/school/account.php`, { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/app\/school\/account\.php/);

    // 3. Verify the select exists with name="level"
    const levelSelect = page.locator('select[name="level"]');
    await expect(levelSelect).toBeVisible();

    // 4. Verify exactly 3 options with the requested values and labels
    const options = levelSelect.locator('option');
    await expect(options).toHaveCount(3);

    const optionValues = await options.evaluateAll((opts) =>
      opts.map((o) => (o as HTMLOptionElement).value)
    );
    expect(optionValues).toEqual([
      'Trung Học Cơ Sở',
      'Trung Học Phổ Thông',
      'Cao Đẳng / Đại Học',
    ]);

    const optionTexts = await options.evaluateAll((opts) =>
      opts.map((o) => (o as HTMLOptionElement).textContent?.trim())
    );
    expect(optionTexts).toEqual([
      'Trung Học Cơ Sở',
      'Trung Học Phổ Thông',
      'Cao Đẳng / Đại Học',
    ]);

    // 5. Select 'Trung Học Cơ Sở' and submit the profile form
    await levelSelect.selectOption('Trung Học Cơ Sở');

    const saveButton = page.locator('button[type="submit"]:has-text("Lưu thông tin trường")');
    await expect(saveButton).toBeVisible();
    await saveButton.click();
    await page.waitForLoadState('domcontentloaded');

    // 6. Verify success alert/flash message
    const alertSuccess = page.locator('.school-flash--success');
    await expect(alertSuccess).toBeVisible();
    await expect(alertSuccess).toContainText('Đã lưu thông tin trường học thành công');

    // 7. Verify select maintains selected value 'Trung Học Cơ Sở'
    await expect(levelSelect).toHaveValue('Trung Học Cơ Sở');

    // 8. Reload page to ensure persistence from Database
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('select[name="level"]')).toHaveValue('Trung Học Cơ Sở');

    // 9. Now restore back to 'Trung Học Phổ Thông'
    await page.locator('select[name="level"]').selectOption('Trung Học Phổ Thông');
    await page.locator('button[type="submit"]:has-text("Lưu thông tin trường")').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.school-flash--success')).toBeVisible();
    await expect(page.locator('select[name="level"]')).toHaveValue('Trung Học Phổ Thông');
  });
});
