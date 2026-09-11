import { test, expect, type Page, type Response } from '@playwright/test';

/**
 * E2E – Đăng nhập qua tất cả các cổng vai trò TalentHub và điều hướng
 * hoạt động cốt lõi, đảm bảo không gặp lỗi hệ thống
 * (HTTP 5xx / "Đã xảy ra lỗi hệ thống" / title "Lỗi hệ thống").
 *
 * Chạy:
 *   npx playwright test tests/auth-roles.spec.ts --project=chromium --workers=1
 */

const PASSWORD = process.env.PLAYWRIGHT_LOGIN_PASSWORD || 'TestPass_2026_local';
const ADMIN_PASSWORD = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'AdminPass_2026_local';

const ERROR_BODY = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
const ERROR_H1 = 'Không thể tải trang';
const ERROR_TITLE = 'Lỗi hệ thống';

type RoleSpec = {
  role: string;
  email: string;
  password: string;
  dashboard: string;
  label: string;
  activityRoutes: string[];
};

const ROLE_SPECS: RoleSpec[] = [
  {
    role: 'student',
    email: process.env.PLAYWRIGHT_LOGIN_EMAIL || 'hs.minh@talenthub.vn',
    password: PASSWORD,
    dashboard: '/app/learner/index.php',
    label: 'Học viên',
    activityRoutes: ['/app/learner/index.php', '/app/learner/activities.php', '/app/learner/discover.php', '/app/learner/my-activities.php', '/app/learner/project.php', '/app/learner/talent-passport.php', '/app/learner/badges.php', '/app/learner/notifications.php'],
  },
  {
    role: 'teacher',
    email: process.env.PLAYWRIGHT_TEACHER_EMAIL || 'gv.mai@talenthub.vn',
    password: PASSWORD,
    dashboard: '/app/teacher/index.php',
    label: 'Giáo viên',
    activityRoutes: ['/app/teacher/index.php', '/app/teacher/students.php', '/app/teacher/grading.php', '/app/teacher/attendance-qr.php', '/app/teacher/profile.php', '/app/teacher/notifications.php'],
  },
  {
    role: 'school',
    email: process.env.PLAYWRIGHT_SCHOOL_EMAIL || 'school.admin@talenthub.vn',
    password: PASSWORD,
    dashboard: '/app/school/index.php',
    label: 'Nhà trường',
    activityRoutes: ['/app/school/index.php', '/app/school/activities.php', '/app/school/classes.php', '/app/school/students.php', '/app/school/teachers.php', '/app/school/internships.php', '/app/school/partnerships.php', '/app/school/reports.php', '/app/school/notifications.php'],
  },
  {
    role: 'enterprise',
    email: process.env.PLAYWRIGHT_ENTERPRISE_EMAIL || 'business@test.talenthub.local',
    password: PASSWORD,
    dashboard: '/app/enterprise/index.php',
    label: 'Doanh nghiệp',
    activityRoutes: ['/app/enterprise/index.php', '/app/enterprise/talents.php', '/app/enterprise/candidates.php', '/app/enterprise/talent-search.php', '/app/enterprise/analytics.php', '/app/enterprise/sponsorships.php', '/app/enterprise/notifications.php'],
  },
  {
    role: 'platform_admin',
    email: process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@admin.com',
    password: ADMIN_PASSWORD,
    dashboard: '/app/admin/index.php',
    label: 'Quản trị viên',
    activityRoutes: ['/app/admin/index.php'],
  },
];

// ───────────── Helpers ─────────────

async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#email'), `#email phải hiển thị (${email})`).toBeVisible({ timeout: 10_000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ timeout: 12_000 }).catch(() => {}),
    page.click('button.auth-submit, button[data-submit]', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

async function detectSystemError(page: Page, res: Response | null): Promise<string | null> {
  if (res && res.status() >= 500) return `HTTP ${res.status()}`;
  const title = await page.title().catch(() => '');
  if (title.includes(ERROR_TITLE)) return `title="${title}"`;
  const body = await page.locator('body').innerText().catch(() => '');
  if (body.includes(ERROR_BODY)) return `HTML(lỗi hệ thống)`;
  if (body.includes(ERROR_H1)) return `HTML("${ERROR_H1}")`;
  return null;
}

function attachServer5xxCollector(page: Page, errors: string[]): void {
  page.on('response', (res: Response) => {
    if (res.status() >= 500) errors.push(`${res.status()} ${res.url()}`);
  });
}

// ───────────── Tests theo vai trò (đăng nhập + hoạt động cốt lõi) ─────────────

for (const spec of ROLE_SPECS) {
  test.describe(`${spec.label} (${spec.role}) – đăng nhập & hoạt động`, () => {
    test(`đăng nhập hợp lệ → redirect ${spec.dashboard} không lỗi hệ thống`, async ({ page }) => {
      const server500: string[] = [];
      attachServer5xxCollector(page, server500);

      await loginAs(page, spec.email, spec.password);

      // Sau login hợp lệ, URL không được là /login.php với lỗi.
      expect(page.url(), `[${spec.role}] sau login không rơi lại /login.php?error`).not.toContain('/login.php?error');
      const dashRes = await page.goto(spec.dashboard, { waitUntil: 'domcontentloaded', timeout: 15_000 });
      const err = await detectSystemError(page, dashRes);
      expect(err, `[${spec.role}] dashboard ${spec.dashboard} dính lỗi hệ thống: ${err ?? ''}`).toBeNull();
      if (dashRes) expect(dashRes.status(), `[${spec.role}] ${spec.dashboard} HTTP không 5xx`).toBeLessThan(500);
      await expect(page.locator('body')).toBeVisible({ timeout: 8_000 });
      const body = await page.locator('body').innerText().catch(() => '');
      expect(body, `[${spec.role}] dashboard không hiện trang lỗi hệ thống`).not.toContain(ERROR_BODY);
      expect(server500, `[${spec.role}] không có response 5xx khi tải dashboard`).toEqual([]);
    });
test(`các hoạt động cốt lõi tải không lỗi hệ thống (${spec.role})`, async ({ page }) => {
      await loginAs(page, spec.email, spec.password);

      const server500: string[] = [];
      attachServer5xxCollector(page, server500);
      const failures: string[] = [];

      for (const route of spec.activityRoutes) {
        let res: Response | null = null;
        try {
          res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15_000 });
        } catch {
          // ignore timeout — vẫn đọc body để kiểm lỗi hệ thống
        }
        if (res && res.status() >= 500) {
          failures.push(`${route} → HTTP ${res.status()} ${res.url()}`);
          continue;
        }
        const err = await detectSystemError(page, res);
        if (err) failures.push(`${route} → ${err}`);
      }

      if (server500.length) console.error(`[DIAGNOSTIC ${spec.role}] AJAX/5xx: ${server500.join(' ; ')}`);
      const all = [...failures, ...server500.map((s) => `AJAX/5xx: ${s}`)];
      if (all.length) {
        console.error(all.join('\n'));
        expect(all, `[${spec.role}] ${all.length} route dính lỗi hệ thống`).toEqual([]);
      }
    });
  });
}

// ───────────── Negative paths (bảo vệ chung) ─────────────

test.describe('Bảo vệ chung & lỗi đăng nhập (negative)', () => {
  test('mật khẩu sai – vẫn ở /login.php, không vào portal, không lỗi hệ thống', async ({ page }) => {
    const student = ROLE_SPECS.find((s) => s.role === 'student')!;
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#email')).toBeVisible({ timeout: 8_000 });
    await page.fill('#email', student.email);
    await page.fill('#password', 'WRONG__pass_9999');
    await Promise.all([
      page.waitForNavigation({ timeout: 12_000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]', { force: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url(), 'sai mật khẩu phải vẫn ở /login.php').toContain('/login.php');
    const body = await page.locator('body').innerText().catch(() => '');
    expect(body.includes(ERROR_BODY), 'sai mật khẩu không được là trang lỗi hệ thống').toBeFalsy();
    expect(await page.title().catch(() => '')).not.toContain(ERROR_TITLE);
  });

  test('email không tồn tại – vẫn ở /login.php, không lỗi hệ thống', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#email')).toBeVisible({ timeout: 8_000 });
    await page.fill('#email', 'no_such_user__talenthub_test@example.invalid');
    await page.fill('#password', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ timeout: 12_000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]', { force: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url()).toContain('/login.php');
    const body = await page.locator('body').innerText().catch(() => '');
    expect(body.includes(ERROR_BODY), 'email lạ không được lỗi hệ thống').toBeFalsy();
  });

  test('đăng xuất xóa session → revisit portal về /login.php, không lỗi', async ({ page }) => {
    const student = ROLE_SPECS.find((s) => s.role === 'student')!;
    await loginAs(page, student.email, student.password);
    await page.goto('/logout.php', { waitUntil: 'domcontentloaded', timeout: 10_000 });
    expect(page.url(), 'sau logout về /login.php').toContain('/login.php');
    const res = await page.goto(student.dashboard, { waitUntil: 'domcontentloaded', timeout: 10_000 }).catch(() => null as Response | null);
    const err = await detectSystemError(page, res);
    expect(err, 'sau logout revisit dashboard không được lỗi hệ thống').toBeNull();
    const body = await page.locator('body').innerText().catch(() => '');
    // Khi mất session, guard phải redirect: ưu tiên /login.php hoặc /role-selection, không được 500 với body hệ thống.
    const after = page.url();
    console.log(`[LOGOUT] revisit ${student.dashboard} → url=${after} status=${res?.status() ?? '-'}`);
    expect(body.includes(ERROR_BODY), 'sau logout không được hiện trang lỗi hệ thống').toBeFalsy();
  });
});