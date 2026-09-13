import { test, expect, type Page } from '@playwright/test';

/**
 * Login tai khoan co san → lam 4 bai assessment qua API
 *   npx playwright test tests/student-all-assessments.spec.ts --project=chromium --headed
 */
const EMAIL = 'hscap2@gmail.com';
const PASSWORD = '0889461844!@#aA';
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
test.describe('Student: Login + Complete ALL 4 assessments', () => {
  test('Login + lam 4 bai (moi cau = 3) + xac minh completed', async ({ page }) => {
    // Login with existing account
    await loginAs(page, EMAIL, PASSWORD);
    console.log('[LOGIN] OK ' + EMAIL);

    // Navigate to assessment - for new students this may trigger onboarding acceptance first
    await page.goto('/app/learner/assessment.php?code=holland', { waitUntil: 'domcontentloaded', timeout: 15000 });
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

    console.log('\n=== ALL 4 ASSESSMENTS COMPLETE ===\n');
  });
});