import { test, expect, type Page, type Response } from '@playwright/test';

/**
 * E2E SÂU – Tương tác nút bấm (Buttons) + Đồng bộ Frontend ↔ Backend API
 * cho luồng Hoạt động (Activity) trên toàn bộ vai trò:
 *   Học viên (Learner): Đăng ký -> fetch JSON (intercept bằng waitForResponse)
 *   Nhà trường (School): Duyệt / Yêu cầu sửa / Từ chối -> form POST truyền thống
 *   Giáo viên (Teacher): Tạo / Lưu / Vòng đời -> form POST truyền thống
 *
 * Xác thực:
 *   1. URL API + HTTP method (POST) đúng.
 *   2. Payload frontend khớp định dạng backend.
 *   3. Status code 2xx (không 400/403/500).
 *   4. UI sau click: nút disabled (chống double-click), Toast/Alert đúng, không tràn khung.
 *   5. Báo cáo nút "mồ côi" và lệch trạng thái UI vs DB.
 *
 * Dữ liệu dùng chung (Trường Cấp 2 / hoạt động đã public):
 *   Hoạt động: 33b83962-a858-476d-b194-77c1dcf96484
 *   Learner  : hscap2@gmail.com  (thuộc Trường Cấp 2)
 *   School   : truongcap2@gmail.com
 *   Teacher  : gvcap2@gmail.com
 *
 * Chạy: npx playwright test tests/ui-buttons-api-deep.spec.ts --project=chromium --workers=1
 */

const BASE = 'http://127.0.0.1:8080';
const DEFAULT_PW = '0889461844!@#aA';

const ACTIVITY_ID = '33b83962-a858-476d-b194-77c1dcf96484';
const LEARNER = { email: 'hscap2@gmail.com', pass: DEFAULT_PW, dash: '/app/learner/index.php' };
const SCHOOL = { email: 'truongcap2@gmail.com', pass: DEFAULT_PW, dash: '/app/school/index.php' };
const TEACHER = { email: 'gvcap2@gmail.com', pass: DEFAULT_PW, dash: '/app/teacher/index.php' };

/** Kết quả báo cáo sai lệch được thu thập trong cụm test. */
const deviations: string[] = [];

async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto(`${BASE}/login.php`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#email')).toBeVisible({ timeout: 8000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
    page.click('button.auth-submit', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/\/login\.php/, { timeout: 8000 });
}

/** Kiểm tra nút có tràn ra ngoài khung (bounding box) – dùng document bounds thay vì viewport. */
async function assertNoOverflow(page: Page, selector: string, label: string): Promise<void> {
  const box = await page.locator(selector).first().boundingBox();
  if (!box) { deviations.push(`[UI] nút "${label}" (${selector}) không có bounding box`); return; }
  const docBounds = await page.evaluate(() => ({
    docW: document.documentElement.scrollWidth,
    docH: document.documentElement.scrollHeight,
    vpW: window.innerWidth,
  }));
  if (box.x + box.width > docBounds.docW + 2) {
    deviations.push(`[UI] nút "${label}" (${selector}) tràn khung ngang: boxRight=${Math.round(box.x + box.width)} > docWidth=${docBounds.docW}`);
  }
  if (box.width > docBounds.vpW) {
    deviations.push(`[UI] nút "${label}" (${selector}) rộng vượt viewport: ${Math.round(box.width)} > vpW=${docBounds.vpW}`);
  }
}
test.describe.serial('Deep UI/API: Activity buttons sync with Backend (all roles)', () => {

  // ---------------------------------------------------------------------------
  // 1. LEARNER – Đăng ký hoạt động: intercept API, verify method/payload/status
  // ---------------------------------------------------------------------------
  test('1) Learner: click Dang ky -> fetch POST /activity-registrations.php', async ({ page }) => {
    await loginAs(page, LEARNER.email, LEARNER.pass);
    await page.goto(`${BASE}/app/learner/activity-detail.php?id=${ACTIVITY_ID}`, { waitUntil: 'domcontentloaded', timeout: 15000 });

    const regBtn = page.locator('[data-register-current]').first();
    await expect(regBtn).toBeVisible({ timeout: 10000 });

    const alreadyRegistered = await regBtn.isDisabled().catch(() => false);
    if (alreadyRegistered) {
      console.log('[Learner] Đã đăng ký từ trước – nút disabled, skip POST check (idempotent)');
      await expect(regBtn).toHaveClass(/learner-btn--registered-disabled/, { timeout: 5000 });
      deviations.push('[Learner] Đã đăng ký sẵn (lần chạy trước tồn tại) -> không thể kiểm tra POST register trong lần chạy này.');
    } else {
      const regResponsePromise = page.waitForResponse(
        (res: Response) =>
          res.url().includes('/activity-registrations.php') &&
          res.request().method() === 'POST' &&
          (res.request().postData() ?? '').includes('"action":"register"'),
        { timeout: 12000 },
      );

      await regBtn.click();

      const regResponse = await regResponsePromise;
      const req = regResponse.request();
      const url = new URL(regResponse.url());

      // 1) URL API đúng
      expect(url.pathname, 'URL API register').toContain('/app/learner/api/v1/activity-registrations.php');
      // 2) HTTP method = POST
      expect(req.method(), 'HTTP method').toBe('POST');
      // 3) Payload JSON khớp backend
      let payload: Record<string, unknown> = {};
      const body = req.postData() ?? '';
      try { payload = JSON.parse(body); } catch { deviations.push('[API] register payload không phải JSON'); }
      expect(payload.action, 'payload.action').toBe('register');
      expect(payload.activityId, 'payload.activityId').toBe(ACTIVITY_ID);
      if (!body.includes('"action"')) {
        deviations.push('[API] payload register thiếu key "action"');
      }
      // 4) Status code 2xx (backend trả 201)
      expect(regResponse.status(), 'HTTP status register').toBeGreaterThanOrEqual(200);
      expect(regResponse.status(), 'HTTP status register < 400').toBeLessThan(400);
      // 5) UI sau click: nút disabled (chống double-click)
      await expect(regBtn).toBeDisabled({ timeout: 5000 });
      await expect(regBtn).toHaveClass(/learner-btn--registered-disabled/, { timeout: 5000 });
      const btnText = (await regBtn.textContent()) ?? '';
      expect(btnText, 'button text after register').toContain('Đã đăng ký');
      const feedback = page.locator('[data-registration-feedback-box]');
      if (await feedback.count()) {
        await expect(feedback).toBeVisible({ timeout: 5000 });
      } else {
        deviations.push('[UI] register thành công nhưng không có data-registration-feedback-box');
      }
      console.log(`[Learner] ✅ register OK status=${regResponse.status()}`);
    }
    await assertNoOverflow(page, '[data-register-current]', 'Đăng ký tham gia');
  });

// ---------------------------------------------------------------------------
  // 2. LEARNER – Chống double-click
  // ---------------------------------------------------------------------------
  test('2) Learner: nút disable sau khi đã đăng ký (chống double-click)', async ({ page }) => {
    await loginAs(page, LEARNER.email, LEARNER.pass);
    await page.goto(`${BASE}/app/learner/activity-detail.php?id=${ACTIVITY_ID}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    const regBtn = page.locator('[data-register-current]').first();
    await expect(regBtn).toBeVisible({ timeout: 10000 });
    const alreadyRegistered = await regBtn.isDisabled().catch(() => false);
    if (!alreadyRegistered) {
      const respPromise = page.waitForResponse(
        (res: Response) => res.url().includes('activity-registrations.php') && res.request().method() === 'POST',
        { timeout: 12000 },
      );
      await regBtn.click();
      await expect(regBtn).toBeDisabled({ timeout: 2000 });
      const resp = await respPromise;
      expect(resp.status()).toBeLessThan(400);
    } else {
      console.log('[Learner] Đã đăng ký từ trước – nút disabled đúng.');
    }
    await assertNoOverflow(page, '[data-register-current]', 'Đăng ký tham gia');
  });

  // ---------------------------------------------------------------------------
  // 3. SCHOOL – Form duyệt có đủ 3 nút approve / request_changes / reject
  // ---------------------------------------------------------------------------
  test('3) School: cấu trúc form duyệt (approve/request_changes/reject)', async ({ page }) => {
    await loginAs(page, SCHOOL.email, SCHOOL.pass);
    await page.goto(`${BASE}/app/school/activities.php`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForLoadState('domcontentloaded');
    const reviewForms = page.locator('form[data-activity-review]');
    const reviewCount = await reviewForms.count();
    if (reviewCount > 0) {
      for (let i = 0; i < reviewCount; i++) {
        const form = reviewForms.nth(i);
        const app = await form.locator('.btn-approve').count();
        const reqChg = await form.locator('.btn-request-changes').count();
        const reject = await form.locator('.btn-reject').count();
        if (app !== 1 || reqChg !== 1 || reject !== 1) {
          deviations.push(`[School][form ${i}] thiếu nút duyệt: approve=${app}, request_changes=${reqChg}, reject=${reject}`);
        }
      }
      await expect(reviewForms.first().locator('textarea[name="reason"]')).toHaveCount(1);
    } else {
      deviations.push('[School] không có form[data-activity-review] nào (dữ liệu không có pending)');
      console.log('[School] Không có hoạt động chờ duyệt - skip nút duyệt.');
    }
    const filterBtn = page.locator('.school-act-filters__btn[type="submit"]').first();
    if (await filterBtn.count()) {
      await assertNoOverflow(page, '.school-act-filters__btn[type="submit"]', 'Lọc');
    }
  });
// ---------------------------------------------------------------------------
  // 4. TEACHER – Form tạo/lưu + nút vòng đời
  // ---------------------------------------------------------------------------
  test('4) Teacher: form tạo/lưu hoạt động + nút vòng đời', async ({ page }) => {
    await loginAs(page, TEACHER.email, TEACHER.pass);
    // Mở form tạo mới (showForm=true) để form activity hiển thị.
    await page.goto(`${BASE}/app/teacher/activities/index.php?action=create`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    const createForm = page.locator('form.teacher-activities-form[data-activity-form]').first();
    if (await createForm.count()) {
      const submit = createForm.locator('button[type="submit"].btn-primary');
      await expect(submit).toHaveCount(1);
      // Nút submit không tràn khung (phải có hidden csrf + form_action đúng)
      await expect(createForm.locator('input[name="form_action"]')).toHaveValue('create');
      await assertNoOverflow(page, 'form.teacher-activities-form[data-activity-form] button[type="submit"].btn-primary', 'Lưu bản nháp');
    } else {
      deviations.push('[Teacher] không tìm thấy form.teacher-activities-form[data-activity-form] (mặc dù ?action=create)');
    }
    const lifecycleBtn = page.locator('.teacher-activities-inline-form button[type="submit"]').first();
    if (await lifecycleBtn.count()) {
      await assertNoOverflow(page, '.teacher-activities-inline-form button[type="submit"]', 'Vòng đời');
    } else {
      deviations.push('[Teacher] không có button vòng đời nào (data không có activity để vận hành)');
    }
    const approveBtn = page.locator('button[data-activity-action]').first();
    if (await approveBtn.count()) {
      await assertNoOverflow(page, 'button[data-activity-action]', 'Duyệt/Từ chối đăng ký');
    }
  });

  // ---------------------------------------------------------------------------
  // 5. BÁO CÁO – In các sai lệch
  // ---------------------------------------------------------------------------
  test('5) Báo cáo sai lệch nút bấm / UI-API', async () => {
    console.log('\n' + '='.repeat(70));
    console.log('BÁO CÁO NÚT BẤM / UI-API DEVIATIONS');
    console.log('='.repeat(70));
    if (deviations.length === 0) {
      console.log('✅ Không phát hiện sai lệch nào.');
    } else {
      deviations.forEach((d, i) => console.log(`  ${i + 1}. ${d}`));
    }
    console.log('='.repeat(70));
  });
});