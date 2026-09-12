import { test, expect } from '@playwright/test';

/**
 * E2E Test: School Registration with Grade Level Selection
 * Run: npx playwright test tests/school-level-registration.spec.ts --project=chromium --headed
 */

test.describe('School Registration - Grade Level Selection', () => {
  const BASE = 'http://127.0.0.1:8080';
  const ts = Date.now();
  const schoolEmail = `school_level_test_${ts}@talenthub.test`;
  const schoolName = `THPT Level Test ${ts}`;
  const schoolFullName = `Admin Level Test ${ts}`;
  const schoolPhone = `090000${ts % 10000}`;
  const schoolAddress = `${ts} Duong Test, Quan Test, TP Test`;
  const schoolPassword = `SchoolPass_${ts}!`;

  test('Step 1: Verify school level dropdown appears on register-school.php', async ({ page }) => {
    await page.goto(`${BASE}/register-school.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('domcontentloaded');

    // Verify dropdown exists
    const schoolLevelSelect = page.locator('#schoolLevel');
    await expect(schoolLevelSelect).toBeVisible({ timeout: 8000 });

    // Verify dropdown options
    const options = page.locator('#schoolLevel option');
    await expect(options).toHaveCount(4); // 1 placeholder + 3 options

    // Verify option values
    const optionValues = await options.evaluateAll((opts: HTMLOptionElement[]) => 
      opts.map(opt => opt.value)
    );
    expect(optionValues).toEqual(['', 'cap2', 'cap3', 'cao_dang_dai_hoc']);

    console.log('[Step 1] ✅ School level dropdown is visible with correct options');
  });

  test('Step 2: Register school with Cap 2 (Middle School)', async ({ page }) => {
    await page.goto(`${BASE}/register-school.php`, { waitUntil: 'domcontentloaded' });

    // Fill form
    await page.fill('#organizationName', schoolName + ' - Cap 2');
    await page.fill('#fullName', schoolFullName);
    await page.fill('#email', schoolEmail);
    await page.fill('#phone', schoolPhone);
    await page.fill('#address', schoolAddress);
    
    // Select school level
    await page.selectOption('#schoolLevel', 'cap2');
    
    // Fill password
    await page.fill('#password', schoolPassword);
    await page.fill('#passwordConfirmation', schoolPassword);

    // Submit
    await page.click('button.auth-submit, button[data-submit]');

    // Verify redirect to login
    await page.waitForURL(/\/login\.php/, { timeout: 10000 });
    console.log('[Step 2] ✅ School registered with Cap 2 level');
  });

  test('Step 3: Register school with Cap 3 (High School)', async ({ page }) => {
    const ts2 = Date.now();
    await page.goto(`${BASE}/register-school.php`, { waitUntil: 'domcontentloaded' });

    await page.fill('#organizationName', `THPT Level Test ${ts2} - Cap 3`);
    await page.fill('#fullName', `Admin Level Test ${ts2}`);
    await page.fill('#email', `school_cap3_${ts2}@talenthub.test`);
    await page.fill('#phone', `090000${ts2 % 10000}`);
    await page.fill('#address', `${ts2} Duong Test`);
    
    // Select Cap 3
    await page.selectOption('#schoolLevel', 'cap3');
    
    await page.fill('#password', schoolPassword);
    await page.fill('#passwordConfirmation', schoolPassword);

    await page.click('button.auth-submit, button[data-submit]');
    await page.waitForURL(/\/login\.php/, { timeout: 10000 });
    console.log('[Step 3] ✅ School registered with Cap 3 level');
  });

  test('Step 4: Register school with Cao đẳng / Đại Học', async ({ page }) => {
    const ts3 = Date.now();
    await page.goto(`${BASE}/register-school.php`, { waitUntil: 'domcontentloaded' });

    await page.fill('#organizationName', `DH Level Test ${ts3}`);
    await page.fill('#fullName', `Admin DH Test ${ts3}`);
    await page.fill('#email', `school_dh_${ts3}@talenthub.test`);
    await page.fill('#phone', `090000${ts3 % 10000}`);
    await page.fill('#address', `${ts3} Duong Test`);
    
    // Select Cao dang / Dai hoc
    await page.selectOption('#schoolLevel', 'cao_dang_dai_hoc');
    
    await page.fill('#password', schoolPassword);
    await page.fill('#passwordConfirmation', schoolPassword);

    await page.click('button.auth-submit, button[data-submit]');
    await page.waitForURL(/\/login\.php/, { timeout: 10000 });
    console.log('[Step 4] ✅ School registered with Cao dang / Dai hoc level');
  });

  test('Step 5: Verify enterprise registration does NOT show school level', async ({ page }) => {
    await page.goto(`${BASE}/register-enterprise.php`, { waitUntil: 'domcontentloaded' });

    const schoolLevelSelect = page.locator('#schoolLevel');
    await expect(schoolLevelSelect).not.toBeVisible({ timeout: 5000 });
    console.log('[Step 5] ✅ Enterprise form does not show school level dropdown');
  });

  test('Step 6: Responsive check - Mobile view', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 }); // iPhone SE
    await page.goto(`${BASE}/register-school.php`, { waitUntil: 'domcontentloaded' });

    const schoolLevelSelect = page.locator('#schoolLevel');
    await expect(schoolLevelSelect).toBeVisible({ timeout: 8000 });

    // Check if dropdown is fully visible (not cut off)
    const box = await schoolLevelSelect.boundingBox();
    expect(box).toBeTruthy();
    expect(box!.width).toBeGreaterThan(200); // Should be readable on mobile

    console.log('[Step 6] ✅ School level dropdown is responsive on mobile');
  });
});