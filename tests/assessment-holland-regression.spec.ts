import { test, expect, type Page, type Response } from '@playwright/test';
import { readFile } from 'node:fs/promises';

/**
 * Regression — /app/learner/assessment.php?code=holland (and siblings) were 404
 * ASSESSMENT_NOT_FOUND until AssessmentCatalogMasterSeeder populated talent_tests.
 *
 * Precondition: seed already run (12 talent_tests + 366 test_questions).
 * Runs against the live webServer (reuseExistingServer) on 127.0.0.1:8080.
 *
 *   npx playwright test tests/assessment-holland-regression.spec.ts --project=chromium --workers=1
 */
const CREDS_FILE = '/tmp/talenthub_playwright_creds.txt';
const FALLBACK_EMAIL = 'pw.e2e.5aany2p6mj@talenthub.test';
const FALLBACK_PASSWORD = 'TestPass_2026_e2e';
const ERROR_BODY = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
const ERROR_TITLE = 'Lỗi hệ thống';

async function readE2ECreds(): Promise<{ email: string; password: string }> {
  try {
    const raw = await readFile(CREDS_FILE, 'utf8');
    const e = raw.match(/^Email:\s*(\S+)/m)?.[1];
    const p = raw.match(/^Password:\s*(\S+)/m)?.[1];
    if (e) return { email: e, password: p ?? FALLBACK_PASSWORD };
  } catch {}
  return { email: FALLBACK_EMAIL, password: FALLBACK_PASSWORD };
}

async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#email')).toBeVisible({ timeout: 10_000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 12_000 }).catch(() => {}),
    page.click('button.auth-submit, button[data-submit]', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
  const url = page.url();
  const body = await page.locator('body').innerText().catch(() => '');
  expect(url, `login ${email} failed: ${body.slice(0, 900)}`).not.toContain('/login.php');
}

/**
 * Fetch an API endpoint from INSIDE the page (same-origin) so the browser auto-sends
 * the login session cookie. `page.request` would open a separate context WITHOUT the
 * learner session, which is why a naive `page.request.get(...)` returns ONBOARDING_REQUIRED.
 */
async function fetchJson(page: Page, path: string): Promise<{ status: number; json: unknown }> {
  return page.evaluate(async (p) => {
    const res = await fetch(p, { credentials: 'same-origin' });
    let json: unknown = null;
    try {
      json = await res.json();
    } catch {
      /* empty */
    }
    return { status: res.status, json };
  }, path);
}

async function expectDetailApiOk(page: Page, code: string): Promise<void> {
  const { status, json } = await fetchJson(page, `/app/learner/api/v1/assessments.php?code=${code}`);
  const j = json as { data?: unknown; error?: { code?: string } } | null;
  const errCode = (j as { error?: { code?: string } } | null)?.error?.code ?? '';
  const dataShape = (j as { data?: unknown } | null)?.data;
  expect(status, `GET code=${code} → status=${status} json=${JSON.stringify(json).slice(0, 400)}`).toBe(200);
  expect(dataShape, `code=${code} should return data.assessment`).not.toBeUndefined();
  expect(errCode, `code=${code}`).not.toBe('ASSESSMENT_NOT_FOUND');
  expect(errCode, `code=${code}`).not.toBe('ONBOARDING_REQUIRED');
}

const CODES = ['holland', 'mbti', 'disc', 'multiple_intelligence'] as const;

test.describe('Assessment catalog regression (404 → loads after seed)', () => {
  test('GET detail API for each framework returns 200 with data (no 404/onboarding)', async ({ page }) => {
    test.setTimeout(45_000);
    const { email, password } = await readE2ECreds();
    await loginAs(page, email, password);
    for (const code of CODES) {
      await expectDetailApiOk(page, code);
    }
  });

  test('assessment.php?code=holland renders questions and no banner', async ({ page }) => {
    test.setTimeout(45_000);
    const { email, password } = await readE2ECreds();
    await loginAs(page, email, password);

    const res: Response | null = await page.goto('app/learner/assessment.php?code=holland', {
      waitUntil: 'domcontentloaded',
    });
    await expect(page.locator('[data-assessment-runner]')).toBeVisible({ timeout: 12_000 });
    const title = await page.title().catch(() => '');
    const body = await page.locator('body').innerText().catch(() => '');
    expect(title).not.toContain(ERROR_TITLE);
    expect(body).not.toContain(ERROR_BODY);
    expect(res?.status()).toBe(200);

    // Wait for the JS to fetch + render the assessment (questions / start state)
    await page.waitForTimeout(1_200);
    const html = await page.content().catch(() => '');
    expect(html, 'should not contain ASSESSMENT_NOT_FOUND banner text').not.toContain(
      'Bài đánh giá không tồn tại hoặc chưa được xuất bản.'
    );
    expect(html, 'should not show onboarding gate banner').not.toContain(
      'Bạn cần hoàn thành quy trình đánh giá bắt buộc'
    );

    const { json } = await fetchJson(page, '/app/learner/api/v1/assessments.php?code=holland');
    const d = json as {
      data?: { assessment?: { code?: string }; questions?: unknown[] };
    } | null;
    expect(d?.data?.assessment?.code ?? '', 'assessment.code after fix').toMatch(/holland/i);
    expect(d?.data?.questions?.length ?? 0, 'holland questions seeded').toBeGreaterThan(0);
  });
});