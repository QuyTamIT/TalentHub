import { test, expect, type Page } from '@playwright/test';

/**
 * E2E cho thay đổi mới (nhánh update-school): classes.gradeLevel → VARCHAR(50)
 * với Khối hiển thị theo tier trường:
 *   - Trường THCS/THPT tỉ số: "Khối" là dropdown 6-9 / 10-12.
 *   - Trường CĐ/ĐH/BTEC:       "Khối" là ô nhập tự do (vd "Năm 1", "K24-CNTT").
 *
 * Dùng tài khoản school demo và sẽ TẠO thật một lớp (redirect về classes.php?msg=created).
 * Chạy: npx playwright test tests/school-grade-level.spec.ts --project=chromium --workers=1
 */

const DEFAULT_PW = '0889461844!@#aA';
const THCS_EMAIL   = process.env.PLAYWRIGHT_SCHOOL_THCS_EMAIL   || 'thcsanhiep3@gmail.com';
const COLLEGE_EMAIL = process.env.PLAYWRIGHT_SCHOOL_COLLEGE_EMAIL || 'btecfpt@gmail.com';
const THPT_EMAIL   = process.env.PLAYWRIGHT_SCHOOL_THPT_EMAIL   || 'school@talenthub.local';
const THCS_PW      = process.env.PLAYWRIGHT_SCHOOL_THCS_PASSWORD   || DEFAULT_PW;
const COLLEGE_PW   = process.env.PLAYWRIGHT_SCHOOL_COLLEGE_PASSWORD || DEFAULT_PW;
const THPT_PW      = process.env.PLAYWRIGHT_SCHOOL_THPT_PASSWORD   || 'Talenthub@123';

async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#email')).toBeVisible({ timeout: 8000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
    page.click('button.auth-submit', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

/** Điều hướng tới trang tạo lớp; trả về phần tử Khối đã xác định dạng. */
async function openCreateClass(page: Page): Promise<void> {
  await page.goto('/app/school/class-edit.php', { waitUntil: 'domcontentloaded' });
  // Không được rơi vào trang lỗi hệ thống.
  await expect(page.locator('body')).not.toContainText('Đã xảy ra lỗi hệ thống', { timeout: 8000 });
  await expect(page.locator('form.school-form')).toBeVisible({ timeout: 8000 });
}

test('Đại học/Cao đẳng: label "Khoá" + ô nhập tự do và tạo lớp với khối chữ thành công', async ({ page }) => {
  await loginAs(page, COLLEGE_EMAIL, COLLEGE_PW);
  await openCreateClass(page);

  // Label phải là "Khoá" (không phải "Khối") cho trường CĐ/ĐH.
  await expect(page.locator('label.school-form__field span').filter({ hasText: /^Khóa/ })).toBeVisible({ timeout: 8000 });

  // Trường CĐ/ĐH: gradeLevel phải là INPUT text, không phải SELECT.
  const gradeInput = page.locator('input[name="gradeLevel"]');
  const gradeSelect = page.locator('select[name="gradeLevel"]');
  await expect(gradeInput).toBeVisible({ timeout: 8000 });
  await expect(gradeSelect).toHaveCount(0);

  // Placeholder phải gợi ý đúng cho CĐ/ĐH.
  await expect(gradeInput).toHaveAttribute('placeholder', 'K1');

  // Khi tạo mới, ô Khoá phải rỗng (hiện placeholder K1), KHÔNG được pre-fill số 10.
  await expect(gradeInput).toHaveValue('');

  // Placeholder tên lớp cũng phải gợi ý đúng cho CĐ/ĐH.
  const nameInput = page.locator('input[name="name"]');
  await expect(nameInput).toHaveAttribute('placeholder', /K1|K2-CNTT/);

  const className = `E2E-ĐH-${Date.now().toString(36)}`;
  await nameInput.fill(className);
  await gradeInput.fill('K1');
  await page.fill('input[name="academicYear"]', '2025 - 2026');

  await Promise.all([
    page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
    page.click('form.school-form button[type="submit"]'),
  ]);

  // Trang thành công redirect về danh sách lớp; không dính lỗi hệ thống.
  expect(page.url()).toContain('classes.php');
  await expect(page.locator('body')).not.toContainText('Đã xảy ra lỗi hệ thống');
  console.log(`OK ĐH tạo lớp "${className}" với khối "K1" → ${page.url()}`);
});

test('THPT: label "Khối" + dropdown 10-12 và tạo lớp với khối hợp lệ thành công', async ({ page }) => {
  await loginAs(page, THPT_EMAIL, THPT_PW);
  await openCreateClass(page);

  // Label vẫn là "Khối" cho THCS/THPT.
  await expect(page.locator('label.school-form__field span').filter({ hasText: /^Khối/ })).toBeVisible({ timeout: 8000 });

  // THPT: gradeLevel phải là SELECT input, không phải text.
  const gradeSelect = page.locator('select[name="gradeLevel"]');
  await expect(gradeSelect).toBeVisible({ timeout: 8000 });
  await expect(page.locator('input[name="gradeLevel"]')).toHaveCount(0);

  // Options dropdown phải đúng {10,11,12}.
  const optionValues = await gradeSelect.locator('option').evaluateAll((opts) =>
    opts.map((o) => (o as HTMLOptionElement).value)
  );
  expect(optionValues.sort()).toEqual(['10', '11', '12']);

  const className = `E2E-THPT-${Date.now().toString(36)}`;
  await page.fill('input[name="name"]', className);
  await gradeSelect.selectOption('12');
  await page.fill('input[name="academicYear"]', '2025 - 2026');

  await Promise.all([
    page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
    page.click('form.school-form button[type="submit"]'),
  ]);

  expect(page.url()).toContain('classes.php');
  await expect(page.locator('body')).not.toContainText('Đã xảy ra lỗi hệ thống');
  console.log(`OK THPT tạo lớp "${className}" với khối 12 → ${page.url()}`);
});

test('THCS (Cấp 2): label "Khối" + dropdown 6-9 (BUG: từng hiện Năm 1-4)', async ({ page }) => {
  await loginAs(page, THCS_EMAIL, THCS_PW);
  await openCreateClass(page);

  // Label phải là "Khối" (không phải "Khoá") cho trường THCS/Cấp 2.
  await expect(page.locator('label.school-form__field span').filter({ hasText: /^Khối/ })).toBeVisible({ timeout: 8000 });
  await expect(page.locator('label.school-form__field span').filter({ hasText: /^Khóa/ })).toHaveCount(0);

  // THCS: gradeLevel phải là SELECT, không phải text input.
  const gradeSelect = page.locator('select[name="gradeLevel"]');
  await expect(gradeSelect).toBeVisible({ timeout: 8000 });
  await expect(page.locator('input[name="gradeLevel"]')).toHaveCount(0);

  // Options dropdown phải đúng {6,7,8,9} — KHÔNG phải {Năm 1..4}.
  const optionValues = await gradeSelect.locator('option').evaluateAll((opts) =>
    opts.map((o) => (o as HTMLOptionElement).value)
  );
  expect(optionValues.sort()).toEqual(['6', '7', '8', '9']);
  const optionLabels = await gradeSelect.locator('option').evaluateAll((opts) =>
    opts.map((o) => (o as HTMLOptionElement).textContent)
  );
  expect(optionLabels).toEqual(['Khối 6', 'Khối 7', 'Khối 8', 'Khối 9']);
  expect(optionLabels.join(' ')).not.toContain('Năm');

  const className = `E2E-THCS-${Date.now().toString(36)}`;
  await page.fill('input[name="name"]', className);
  await gradeSelect.selectOption('8');
  await page.fill('input[name="academicYear"]', '2025 - 2026');

  await Promise.all([
    page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
    page.click('form.school-form button[type="submit"]'),
  ]);

  expect(page.url()).toContain('classes.php');
  await expect(page.locator('body')).not.toContainText('Đã xảy ra lỗi hệ thống');
  console.log(`OK THCS tạo lớp "${className}" với khối 8 → ${page.url()}`);
});