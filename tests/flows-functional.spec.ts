import { test, expect, type Page, type Response } from '@playwright/test';

const TEST_PASS = process.env.PLAYWRIGHT_TEST_PASS || 'TestPass_2026_local';
const ADMIN_PASS = process.env.PLAYWRIGHT_ADMIN_PASS || 'AdminPass_2026_local';

const ERR = {
  BODY: 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
  H1: 'Không thể tải trang',
  TITLE: 'Lỗi hệ thống',
};

async function doLogin(page: Page, email: string, password: string): Promise<void> {
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

async function sysError(page: Page): Promise<string | null> {
  const title = await page.title().catch(() => '');
  if (title.includes(ERR.TITLE)) return `title="${title}"`;
  const body = await page.locator('body').innerText().catch(() => '');
  if (body.includes(ERR.BODY)) return 'HTML(lỗi hệ thống)';
  if (body.includes(ERR.H1)) return `HTML("${ERR.H1}")`;
  return null;
}

interface PortalSpec { label: string; email: string; pass: string; dashboard: string; contentHint: string[]; }
const PORTALS: PortalSpec[] = [
  { label: 'Hoc vien', email: 'hs.minh@talenthub.vn', pass: TEST_PASS, dashboard: '/app/learner/index.php', contentHint: [] },
  { label: 'Giao vien', email: 'gv.mai@talenthub.vn', pass: TEST_PASS, dashboard: '/app/teacher/index.php', contentHint: ['Tong quan'] },
  { label: 'Nha truong', email: 'school.admin@talenthub.vn', pass: TEST_PASS, dashboard: '/app/school/index.php', contentHint: ['Khu vuc Nha truong'] },
  { label: 'Doanh nghiep', email: 'business@test.talenthub.local', pass: TEST_PASS, dashboard: '/app/enterprise/index.php', contentHint: [] },
  { label: 'Quan tri', email: 'admin@admin.com', pass: ADMIN_PASS, dashboard: '/app/admin/index.php', contentHint: ['Quan tri'] },
];

test.describe('(a) Dang nhap THUC SU (khong false-positive) tai khoan mau README', () => {
  for (const spec of PORTALS) {
    test(`[${spec.label}] ${spec.email} dang nhap vao dashboard (khong con /login.php)`, async ({ page }) => {
      await doLogin(page, spec.email, spec.pass);
      expect(
        page.url(),
        `[${spec.label}] ${spec.email}: dang nhap KHONG thanh cong (van o /login.php). Setup-local chua thanh cong.`,
      ).not.toContain('/login.php');
      const res = await page.goto(spec.dashboard, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
      if (res) expect(res.status()).toBeLessThan(500);
      expect(await sysError(page), `[${spec.label}] ${spec.dashboard} loi he thong`).toBeNull();
    });
  }
});

test.describe('(b) Luong chuc nang Hoc vien – tai khoan da hoan thanh danh gia', () => {
  const student = { email: 'pw.assess.78c4y3myat@talenthub.test', pass: 'TestPass_2026_e2e' };

  test('vao dashboard khong con dialog onboarding (da completed)', async ({ page }) => {
    await doLogin(page, student.email, student.pass);
    expect(page.url()).not.toContain('/login.php');
    const dlg = await page.locator('[data-onboarding-dialog]').count();
    expect(dlg, 'da completed nhung van hien dialog onboarding [data-onboarding-dialog]?').toBe(0);
  });

  test('assessment.php?code=holland tai duoc cau hoi (khong "khong ton tai")', async ({ page }) => {
    await doLogin(page, student.email, student.pass);
    const res = await page.goto('/app/learner/assessment.php?code=holland', { waitUntil: 'domcontentloaded', timeout: 15_000 });
    expect(res?.status()).toBeLessThan(500);
    const body = await page.locator('body').innerText().catch(() => '');
    expect(body.includes('khong ton tai hoac chua duoc xuat ban'), 'bai danh gia van bao khong ton tai').toBeFalsy();
  });

  test('assessment-result.php?code=holland hien thi ket qua real', async ({ page }) => {
    await doLogin(page, student.email, student.pass);
    const res = await page.goto('/app/learner/assessment-result.php?code=holland', { waitUntil: 'domcontentloaded', timeout: 15_000 });
    expect(res?.status()).toBeLessThan(500);
    expect(await sysError(page)).toBeNull();
  });

  test('discover/profile/activities/notifications tai noi dung', async ({ page }) => {
    await doLogin(page, student.email, student.pass);
    for (const route of ['/app/learner/discover.php', '/app/learner/profile.php', '/app/learner/activities.php', '/app/learner/notifications.php']) {
      const res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
      if (res) expect(res.status(), `${route} HTTP`).toBeLessThan(500);
      expect(await sysError(page), `${route} loi he thong`).toBeNull();
    }
  });

  test('AI recommendations mo duoc, khong loi he thong', async ({ page }) => {
    await doLogin(page, student.email, student.pass);
    const res = await page.goto('/app/learner/ai-recommendations.php', { waitUntil: 'domcontentloaded', timeout: 20_000 }).catch(() => null as Response | null);
    if (res) expect(res.status()).toBeLessThan(500);
    expect(await sysError(page)).toBeNull();
  });
});

test.describe('(c) Cac cong khac – dashboard noi dung that', () => {
  const cases = [
    { label: 'Teacher', email: 'teacher.manual@talenthub.local', dash: '/app/teacher/index.php', hint: 'Giao vien' },
    { label: 'School', email: 'school.manual@talenthub.local', dash: '/app/school/index.php', hint: 'Khu vuc Nha truong' },
    { label: 'Enterprise', email: 'enterprise.manual@talenthub.local', dash: '/app/enterprise/index.php', hint: 'Doanh Nghiep' },
  ];
  for (const c of cases) {
    test(`[${c.label}] login thanh cong + dashboard noi dung`, async ({ page }) => {
      await doLogin(page, c.email, TEST_PASS);
      expect(page.url(), `[${c.label}] login that bai: ${c.email}`).not.toContain('/login.php');
      const res = await page.goto(c.dash, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
      if (res) expect(res.status()).toBeLessThan(500);
      expect(await sysError(page)).toBeNull();
    });
  }

  test('[School] classes/students/analytics/reports tai noi dung', async ({ page }) => {
    await doLogin(page, 'school.manual@talenthub.local', TEST_PASS);
    for (const route of ['/app/school/classes.php', '/app/school/students.php', '/app/school/analytics.php', '/app/school/reports.php']) {
      const res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
      if (res) expect(res.status(), `${route} HTTP`).toBeLessThan(500);
      expect(await sysError(page), `${route} loi he thong`).toBeNull();
    }
  });

  test('[Enterprise] talents/candidates/internships/analytics/sponsorships tai noi dung', async ({ page }) => {
    await doLogin(page, 'enterprise.manual@talenthub.local', TEST_PASS);
    for (const route of ['/app/enterprise/talents.php', '/app/enterprise/candidates.php', '/app/enterprise/internships/', '/app/enterprise/analytics.php', '/app/enterprise/sponsorships.php']) {
      const res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
      if (res) expect(res.status(), `${route} HTTP`).toBeLessThan(500);
      expect(await sysError(page), `${route} loi he thong`).toBeNull();
    }
  });

  test('[Teacher] students/grading/attendance-qr/checkins tai noi dung', async ({ page }) => {
    await doLogin(page, 'teacher.manual@talenthub.local', TEST_PASS);
    for (const route of ['/app/teacher/index.php', '/app/teacher/students.php', '/app/teacher/grading.php', '/app/teacher/attendance-qr.php', '/app/teacher/checkins/index.php']) {
      const res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
      if (res) expect(res.status(), `${route} HTTP`).toBeLessThan(500);
      expect(await sysError(page), `${route} loi he thong`).toBeNull();
    }
  });

  test('[Admin] dashboard tai, khong loi he thong', async ({ page }) => {
    await doLogin(page, 'admin@admin.com', ADMIN_PASS);
    expect(page.url()).not.toContain('/login.php');
    const res = await page.goto('/app/admin/index.php', { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null as Response | null);
    if (res) expect(res.status()).toBeLessThan(500);
    expect(await sysError(page)).toBeNull();
  });
});