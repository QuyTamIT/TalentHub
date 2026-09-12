import { test, expect, type Page, type Response } from '@playwright/test';
import { writeFile, readFile } from 'node:fs/promises';

/**
 * E2E - Dang ky Hoc vien moi qua /register.php roi dang nhap qua /login.php.
 * Tai khoan hoc vien duoc tao *active* ngay (teacher/school/enterprise can admin duyet).
 * UUID truong/lop duoc chon dong tu DOM -> ben voi seed moi.
 *
 * Chay (1 worker de tuan tu, dam bao login dung dung email vua tao):
 *   npx playwright test tests/register-and-login.spec.ts --project=chromium --workers=1
 *   npx playwright test ... --headed --workers=1   (quan sat)
 *
 * Credentials duoc ghi vao /tmp/talenthub_playwright_creds.txt de ban tu test lai.
 */

const PASSWORD = 'TestPass_2026_e2e';
const CREDS_FILE = '/tmp/talenthub_playwright_creds.txt';

const ERROR_BODY = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
const ERROR_TITLE = 'Lỗi hệ thống';

async function systemError(page: Page, res?: Response | null): Promise<string | null> {
  const title = await page.title().catch(() => '');
  if (title.includes(ERROR_TITLE)) return `title="${ERROR_TITLE}" @ ${page.url()}`;
  const body = await page.locator('body').innerText().catch(() => '');
  if (body.includes(ERROR_BODY)) return `body="${ERROR_BODY}" @ ${page.url()}`;
  if (res && res.status() >= 500) return `HTTP ${res.status()} @ ${res.url()}`;
  return null;
}

const suffix = Math.random().toString(36).slice(2, 6) + Date.now().toString(36).slice(-6);
const EMAIL = `pw.e2e.${suffix}@talenthub.test`;
const FULL_NAME = `Playwright E2E ${suffix}`;
const PHONE = `090${Math.floor(1000000 + Math.random() * 9000000)}`;
const DOB = '2003-06-15';
test.describe('Dang ky + Dang nhap Hoc vien moi', () => {
  test('dang ky hoc vien moi -> redirect /login.php + banner thanh cong + luu creds', async ({ page }) => {
    test.setTimeout(60_000);

    const res = await page.goto('/register.php', { waitUntil: 'domcontentloaded' });
    expect(await systemError(page, res), 'GET /register.php phai khong loi').toBeNull();
    await expect(page.locator('#fullName')).toBeVisible({ timeout: 10_000 });

    await page.fill('#fullName', FULL_NAME);
    await page.fill('#email', EMAIL);
    await page.fill('#phone', PHONE);
    await page.fill('#dateOfBirth', DOB);

    const school = page.locator('#schoolId');
    await expect(school).toBeVisible();
    const schoolVals = await school.locator('option').evaluateAll((els) =>
      (els as HTMLOptionElement[]).map((o) => o.value).filter((v) => v && v.trim() !== '')
    );
    expect(schoolVals.length, 'phai co >=1 truong active').toBeGreaterThan(0);
    await school.selectOption(schoolVals[0]);
    await page.waitForTimeout(300);

    const klass = page.locator('#classId');
    await expect(klass).toBeEnabled({ timeout: 10_000 });
    const match = await klass.locator('option[data-school-id]').evaluateAll((els, sid) => {
      return (els as HTMLOptionElement[])
        .filter((o) => !o.hidden && !o.disabled && o.value && o.dataset.schoolId === sid)
        .map((o) => o.value);
    }, schoolVals[0]);
    expect(match.length, `khong co lop active cho truong ${schoolVals[0]}`).toBeGreaterThan(0);
    await klass.selectOption(match[0]);

    await page.fill('#password', PASSWORD);
    await page.fill('#passwordConfirmation', PASSWORD);

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]', { force: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');

    const body = await page.locator('body').innerText().catch(() => '');
    expect(await systemError(page), 'dang ky khong duoc dinh loi he thong').toBeNull();
    expect(page.url(), `sau submit phai ve /login.php (got ${page.url()})\nbody: ${body.slice(0, 600)}`).toContain('/login.php');

    const success = page.locator('.auth-alert--success');
    await expect(success, 'banner Dang ky thanh cong phai hien').toBeVisible({ timeout: 10_000 });
    expect(await success.innerText()).toMatch(/Đăng ký thành công/);
    await expect(page.locator('#email')).toHaveValue(EMAIL, { timeout: 5_000 });

    const creds = [
      '================ TALENTHUB ACCOUNT (created by Playwright) ================',
      `Email:    ${EMAIL}`,
      `Password: ${PASSWORD}`,
      `FullName: ${FULL_NAME}`,
      `Phone:    ${PHONE}`,
      `DOB:      ${DOB}`,
      `SchoolId: ${schoolVals[0]}`,
      `ClassId:  ${match[0]}`,
      `Login:    http://127.0.0.1:8080/login.php`,
      `Created:  ${new Date().toISOString()}`,
      '=========================================================================',
    ].join('\n');

    console.log('\n' + creds + '\n');
    await writeFile(CREDS_FILE, creds, 'utf8');
    await test.info().attach('credentials.txt', { body: Buffer.from(creds), contentType: 'text/plain' });
  });
test('dang nhap bang tai khoan vua tao -> vao portal Hoc vien', async ({ page }) => {
    test.setTimeout(45_000);

    let email = EMAIL;
    try {
      const content = await readFile(CREDS_FILE, 'utf8');
      const m = content.match(/^Email:\s*(\S+)/m);
      if (m) email = m[1];
    } catch {
      // file chua co -> dung EMAIL mac dinh cung suite
    }

    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#email')).toBeVisible({ timeout: 8_000 });
    await page.fill('#email', email);
    await page.fill('#password', PASSWORD);

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]', { force: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');

    const body = await page.locator('body').innerText().catch(() => '');
    expect(await systemError(page), 'dang nhap khong duoc dinh loi he thong').toBeNull();
    expect(page.url(), `dang nhap phai roi /login.php (got ${page.url()})\nbody: ${body.slice(0, 600)}`).not.toContain('/login.php');

    const landed = page.url();
    const learnerHit = landed.includes('/app/learner/') || body.toLowerCase().includes('learner');
    expect(learnerHit, `sau login phai vao portal Hoc vien (url=${landed})`).toBeTruthy();

    const dash = await page.goto('/app/learner/index.php', { waitUntil: 'domcontentloaded' });
    expect(await systemError(page, dash), 'revisit /app/learner/index.php khong duoc loi').toBeNull();
    expect(page.url()).toContain('/app/learner');

    console.log(
      `\n================ LOGIN OK =================\nEmail: ${email}\nPassword: ${PASSWORD}\nLanded: ${landed}\nVerified: /app/learner/index.php\n===========================================\n`
    );
  });
});