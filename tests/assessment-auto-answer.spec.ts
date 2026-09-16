import { test, expect, type Page } from '@playwright/test';
import { writeFile } from 'node:fs/promises';

/**
 * Tao 1 hoc vien moi, lan luot lam 4 bai bat buoc theo dung thu tu
 * onboarding (holland → mbti → disc → multiple_intelligence),
 * moi cau tra loi gia tri 3, roi ban giao tai khoan.
 *
 * Creds + chi tiet attempts ghi ra /tmp/talenthub_assessment_creds.txt
 *   npx playwright test tests/assessment-auto-answer.spec.ts --project=chromium --workers=1
 */
const CREDS_FILE = '/tmp/talenthub_assessment_creds.txt';
const PASSWORD = 'TestPass_2026_e2e';
const SUFFIX = Math.random().toString(36).slice(2, 6) + Date.now().toString(36).slice(-6);
const EMAIL = `pw.assess.${SUFFIX}@talenthub.test`;
const FULL_NAME = `Hoc vien Assess ${SUFFIX}`;
const PHONE = `090${Math.floor(1000000 + Math.random() * 9000000)}`;
const DOB = '2004-03-10';
const CODES = ['holland', 'mbti', 'disc', 'multiple_intelligence'] as const;

async function authedFetch(
  page: Page,
  path: string,
  method: 'GET' | 'POST' | 'PATCH',
  body?: unknown,
) {
  return page.evaluate(
    async ({ p, m, b }) => {
      const getCsrf = (): string => {
        const m1 = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
        if (m1?.content) return m1.content;
        const m2 = document.querySelector('meta[name="csrfToken"]') as HTMLMetaElement | null;
        if (m2?.content) return m2.content;
        const s1 = document.getElementById('learner-session-boot');
        if (s1?.textContent) {
          try {
            const j = JSON.parse(s1.textContent);
            if (j.csrfToken) return String(j.csrfToken);
          } catch {}
        }
        const s2 = document.getElementById('learner-assessment-boot');
        if (s2?.textContent) {
          try {
            const j = JSON.parse(s2.textContent);
            if (j.csrfToken) return String(j.csrfToken);
          } catch {}
        }
        return '';
      };
      const csrf = getCsrf();
      const headers: Record<string, string> = { 'Content-Type': 'application/json' };
      if (csrf && (m === 'POST' || m === 'PATCH')) headers['x-csrf-token'] = csrf;
      if (p.includes('assessment-submit') && m === 'POST') {
        try {
          headers['x-idempotency-key'] = (globalThis.crypto as Crypto).randomUUID();
        } catch {
          headers['x-idempotency-key'] = `${Date.now()}-${Math.random()}`;
        }
      }
      const res = await fetch(p, { method: m, headers, credentials: 'same-origin', body: b !== undefined ? JSON.stringify(b) : undefined });
      let json: unknown = null;
      let text = '';
      try { text = await res.clone().text(); } catch {}
      try { json = text ? JSON.parse(text) : null; } catch { json = { _raw: text }; }
      return { status: res.status, ok: res.ok, json, text: text.slice(0, 900) };
    },
    { p: path, m: method, b: body as Record<string, unknown> | undefined },
  );
}
async function loginAs(page: Page, email: string, pass: string) {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#email')).toBeVisible({ timeout: 10_000 });
  await page.fill('#email', email);
  await page.fill('#password', pass);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 12_000 }).catch(() => {}),
    page.click('button.auth-submit, button[data-submit]', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
  expect(page.url()).not.toContain('/login.php');
}
test.describe('Assessment auto-answer -> ban giao tai khoan da hoan thanh 4 bai', () => {
  test('dang ky + dang nhap + lam 4 bai (moi cau = 3) + xac minh completed', async ({ page }) => {
    test.setTimeout(180_000);

    // 1) Dang ky moi qua UI
    {
      const r = await page.goto('/register.php', { waitUntil: 'domcontentloaded' });
      expect(r?.status()).toBeLessThan(500);
      await expect(page.locator('#fullName')).toBeVisible({ timeout: 10_000 });
      await page.fill('#fullName', FULL_NAME);
      await page.fill('#email', EMAIL);
      await page.fill('#phone', PHONE);
      await page.fill('#dateOfBirth', DOB);
      const school = page.locator('#schoolId');
      const vals = await school.locator('option').evaluateAll((els) =>
        (els as HTMLOptionElement[]).map((o) => o.value).filter((v) => v && v.trim() !== ''),
      );
      expect(vals.length).toBeGreaterThan(0);
      await school.selectOption(vals[0]);
      await page.waitForTimeout(300);
      const klass = page.locator('#classId');
      await expect(klass).toBeEnabled({ timeout: 10_000 });
      const match = await klass.locator('option[data-school-id]').evaluateAll((els, sid) => {
        return (els as HTMLOptionElement[])
          .filter((o) => !o.hidden && !o.disabled && o.value && o.dataset.schoolId === sid)
          .map((o) => o.value);
      }, vals[0]);
      expect(match.length).toBeGreaterThan(0);
      await klass.selectOption(match[0]);
      await page.fill('#password', PASSWORD);
      await page.fill('#passwordConfirmation', PASSWORD);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => {}),
        page.click('button.auth-submit, button[data-submit]', { force: true }),
      ]);
      await page.waitForLoadState('domcontentloaded');
      expect(page.url()).toContain('/login.php');
      await expect(page.locator('.auth-alert--success')).toBeVisible({ timeout: 10_000 });
    }

    await page.fill('#password', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 12_000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]', { force: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');
    expect(page.url()).not.toContain('/login.php');
    // Pending students see stat overlay on index.php and must accept onboarding before assessment.
    await page.goto('/app/learner/index.php', { waitUntil: 'domcontentloaded' });
    const acceptBtn = page.locator('[data-onboarding-dialog] button').filter({ hasText: /Đồng ý và bắt đầu/i });
    if ((await acceptBtn.count()) > 0 && (await acceptBtn.first().isVisible().catch(() => false))) {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 12_000 }).catch(() => {}),
        acceptBtn.first().click(),
      ]);
      await page.waitForLoadState('domcontentloaded');
      // Server redirects to assessment.php?code=holland after accept
      expect(page.url()).toContain('/app/learner/');
    } else {
      // Fallback: API direct accept if dialog not rendered (already accepted or edge markup)
      const acc = await authedFetch(page, '/app/learner/onboarding.php', 'POST', { action: 'accept' });
      if (acc.status === 303 || acc.status === 200) {
        // Direct API accept path — navigate explicitly if no redirect captured
        if (!page.url().includes('/assessment.php')) {
          await page.goto('/app/learner/assessment.php?code=holland', { waitUntil: 'domcontentloaded' });
        }
      } else {
        // No-op if already accepted/404
        await page.goto('/app/learner/assessment.php?code=holland', { waitUntil: 'domcontentloaded' });
      }
    }
    // Ensure runner ready if we did not already navigate to it above
    if (!page.url().includes('/assessment.php')) {
      await page.goto('/app/learner/assessment.php?code=holland', { waitUntil: 'domcontentloaded' });
    }
    await expect(page.locator('[data-assessment-runner]')).toBeVisible({ timeout: 12_000 });

    const detailLines: string[] = [];
    const attemptLines: string[] = [];

    for (const code of CODES) {
      const detail = await authedFetch(page, `/app/learner/api/v1/assessments.php?code=${code}`, 'GET');
      expect(detail.status, `GET detail ${code} -> ${detail.status} ${detail.text.slice(0, 300)}`).toBe(200);
      const dj = detail.json as { data?: { assessment?: { education_band?: string }; questions?: Array<{ id?: string; question_id?: string }> } } | null;
      const qs = dj?.data?.questions ?? [];
      const band = dj?.data?.assessment?.education_band ?? '';
      expect(qs.length).toBeGreaterThan(0);
      detailLines.push(`${code} (${band}): ${qs.length} cau`);

      const start = await authedFetch(page, '/app/learner/api/v1/assessment-attempts.php', 'POST', { assessmentCode: code });
      expect([200, 201].includes(start.status), `POST attempt ${code} -> ${start.status} ${start.text.slice(0, 500)}`).toBeTruthy();
      const sj = start.json as { data?: unknown } | null;
      const inner = sj?.data as { id?: string; attempt_id?: string; attempt?: { id?: string } } | undefined;
      const aid: string = (inner?.id as string) || (inner?.attempt_id as string) || (inner?.attempt?.id as string) || ((start.json as { id?: string })?.id as string) || '';
      expect(aid, `attempt id missing ${code}`).toBeTruthy();

      for (const q of qs) {
        const qid = (q?.id as string) || (q?.question_id as string) || '';
        const ans = await authedFetch(page, '/app/learner/api/v1/assessment-answers.php', 'PATCH', { attemptId: aid, questionId: qid, answer: 3 });
        expect(ans.status, `PATCH ${qid.slice(0, 8)} ${code}`).toBe(200);
      }

      const sub = await authedFetch(page, '/app/learner/api/v1/assessment-submit.php', 'POST', { attemptId: aid });
      expect(sub.status, `POST submit ${code} -> ${sub.status} ${sub.text.slice(0, 500)}`).toBe(200);
      const subJ = sub.json as { data?: { onboarding?: { status?: string; next_code?: string | null } } } | null;
      attemptLines.push(`${code}: ${aid.slice(0, 8)}… onboarding=${subJ?.data?.onboarding?.status ?? '?'} next=${subJ?.data?.onboarding?.next_code ?? '—'}`);
      await page.waitForTimeout(250);
    }
// 4) Xac minh history ≥ 4
    {
      const hist = await authedFetch(page, '/app/learner/api/v1/assessments.php?view=history', 'GET');
      expect(hist.status).toBe(200);
      const hj = hist.json as { data?: { assessment_history?: { count?: number } } } | null;
      expect(hj?.data?.assessment_history?.count ?? -1).toBeGreaterThanOrEqual(4);
    }
    await page.goto('/app/learner/assessment-result.php?code=holland', { waitUntil: 'domcontentloaded' });
    expect(await page.title().catch(() => '')).not.toContain('Lỗi hệ thống');

    // 5) Ghi creds ban giao
    const lines = [
      '================ TALENTHUB — ĐÃ HOÀN THÀNH 4 BÀI ĐÁNH GIÁ ================',
      `Email:    ${EMAIL}`,
      `Password: ${PASSWORD}`,
      `Họ tên:   ${FULL_NAME}`,
      `Phone:    ${PHONE}`,
      `DOB:      ${DOB}`,
      `Đăng nhập:     http://127.0.0.1:8080/login.php`,
      `Holland:       http://127.0.0.1:8080/app/learner/assessment.php?code=holland`,
      `Kết quả:       http://127.0.0.1:8080/app/learner/assessment-result.php?code=holland`,
      `Khám phá:      http://127.0.0.1:8080/app/learner/discover.php`,
      `Tổng quan:     http://127.0.0.1:8080/app/learner/index.php`,
      `Tạo lúc:       ${new Date().toISOString()}`,
      '— Chi tiết —', ...detailLines, ...attemptLines,
      'Ghi chú: mỗi câu = 3 (Likert 1..5). Đã nộp 4 bài; onboarding completed.',
      '===========================================================================',
    ];
    const out = lines.join('\n');
    console.log('\n' + out + '\n');
    await writeFile(CREDS_FILE, out, 'utf8');
    await test.info().attach('assessment-creds.txt', { body: Buffer.from(out), contentType: 'text/plain' });
  });
});