import { test, expect, type Page } from '@playwright/test';

/**
 * E2E — Enterprise "Thêm lĩnh vực" (internship specialties) modal on
 * /app/enterprise/internships/create.php
 *
 * Guards the regressions that broke this feature:
 *  - the modal must use the shared .ent-modal / .ent-modal__backdrop /
 *    .ent-modal__dialog markup + the `.is-open` state class (not
 *    .ent-modal-overlay / .ent-modal--open, which have no CSS)
 *  - the API response has no top-level `success` flag; success means `json.data`
 *  - the new option's value must equal the exact specialty name so the form
 *    submit and the server-side lookup stay in sync
 *
 * Run: npx playwright test tests/enterprise-specialty-modal.spec.ts
 */

const EMAIL = 'htng@gmail.com';

async function login(page: Page): Promise<void> {
  const password = process.env.TALENTHUB_TEST_PASSWORD ?? '';
  test.skip(!password, 'TALENTHUB_TEST_PASSWORD is not set.');

  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', EMAIL);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => null),
    page.click('button.auth-submit'),
  ]);
}

async function gotoCreatePage(page: Page): Promise<void> {
  await login(page);
  const response = await page.goto('/app/enterprise/internships/create.php', { waitUntil: 'domcontentloaded' });
  test.skip(response?.status() !== 200, 'Enterprise create page did not render (no enterprise session).');
}

test.describe('Modal Thêm lĩnh vực (enterprise internship create)', () => {
  test('modal ẩn khi mới tải và dùng đúng markup + state class dùng chung', async ({ page }) => {
    await gotoCreatePage(page);

    const modal = page.locator('#modal-add-specialty');
    await expect(modal).toHaveCount(1);
    await expect(modal).toHaveClass(/ent-modal/);
    await expect(modal).toHaveAttribute('aria-hidden', 'true');
    await expect(modal.locator('.ent-modal__backdrop')).toHaveCount(1);
    await expect(modal.locator('.ent-modal__dialog')).toHaveCount(1);
    // Legacy classes that had no CSS must be gone.
    await expect(page.locator('.ent-modal-overlay')).toHaveCount(0);
    await expect(page.locator('.ent-modal--open')).toHaveCount(0);

    // Closed modal must be invisible, not permanently overlaid on the page.
    await expect(page.locator('#specialty-name')).toBeHidden();
  });

  test('nút + mở modal, các class form được style (ô nhập hiển thị được)', async ({ page }) => {
    await gotoCreatePage(page);

    await page.click('#btn-add-specialty');
    const modal = page.locator('#modal-add-specialty');
    await expect(modal).toHaveAttribute('aria-hidden', 'false');
    await expect(modal).toHaveClass(/is-open/);

    const nameInput = page.locator('#specialty-name');
    await expect(nameInput).toBeVisible();
    await expect(nameInput).toBeFocused();

    // Styling assertion: the missing CSS previously left a 0-height input.
    const box = await nameInput.boundingBox();
    expect(box, 'input phải có kích thước hiển thị').not.toBeNull();
    expect(box!.height).toBeGreaterThanOrEqual(32);
  });

  test('đóng modal bằng Escape, backdrop và nút Hủy', async ({ page }) => {
    await gotoCreatePage(page);
    const modal = page.locator('#modal-add-specialty');

    await page.click('#btn-add-specialty');
    await expect(modal).toHaveClass(/is-open/);
    await page.keyboard.press('Escape');
    await expect(modal).not.toHaveClass(/is-open/);

    await page.click('#btn-add-specialty');
    await expect(modal).toHaveClass(/is-open/);
    await page.click('#modal-add-specialty-cancel');
    await expect(modal).not.toHaveClass(/is-open/);

    await page.click('#btn-add-specialty');
    await expect(modal).toHaveClass(/is-open/);
    await page.locator('#modal-add-specialty .ent-modal__backdrop').click({ position: { x: 5, y: 5 } });
    await expect(modal).not.toHaveClass(/is-open/);
  });

  test('validate client chặn tên < 2 ký tự', async ({ page }) => {
    await gotoCreatePage(page);

    await page.click('#btn-add-specialty');
    await page.fill('#specialty-name', 'x');
    await page.click('#modal-add-specialty-submit');

    await expect(page.locator('#specialty-name-error')).not.toBeEmpty();
    await expect(page.locator('#modal-add-specialty')).toHaveClass(/is-open/);
  });

  test('tạo lĩnh vực thành công: modal đóng, option mới được chọn đúng value = tên', async ({ page }) => {
    await gotoCreatePage(page);

    const before = await page.locator('#form-field option').count();
    const name = `Lĩnh vực E2E ${Date.now()}`;

    await page.click('#btn-add-specialty');
    await page.fill('#specialty-name', name);
    await page.click('#modal-add-specialty-submit');

    const modal = page.locator('#modal-add-specialty');
    await expect(modal).not.toHaveClass(/is-open/, { timeout: 15000 });

    const options = page.locator('#form-field option');
    await expect(options).toHaveCount(before + 1);

    // The selected option must carry the exact specialty name as its value,
    // otherwise the submitted `field` value would not match the DB record.
    await expect(options.filter({ hasText: name })).toHaveCount(1);
    const selected = await page.locator('#form-field').inputValue();
    expect(selected).toBe(name);
  });
});
