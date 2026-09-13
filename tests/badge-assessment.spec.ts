import { test, expect, type Page } from '@playwright/test';
import { writeFile } from 'node:fs/promises';

const EMAIL = 'badge.test@talenthub.local';
const PASSWORD = 'Talenthub@123';
const CODES = ['holland','mbti','disc','multiple_intelligence'] as const;

async function af(page: Page, p: string, m: 'GET'|'POST'|'PATCH', b?: unknown) {
  return page.evaluate(async ({ path, method, body }) => {
    const g = (): string => {
      const m1=(document.querySelector('meta[name=\"csrf-token\"]') as HTMLMetaElement | null)?.content||'';
      if (m1) return m1;
      const m2=(document.querySelector('meta[name=\"csrfToken\"]') as HTMLMetaElement | null)?.content||'';
      if (m2) return m2;
      const s1=document.getElementById('learner-session-boot'); if (s1?.textContent){ try{ const j=JSON.parse(s1.textContent) as { csrfToken?: string }; if(j.csrfToken) return String(j.csrfToken);}catch{}}
      const s2=document.getElementById('learner-assessment-boot'); if (s2?.textContent){ try{ const j=JSON.parse(s2.textContent) as { csrfToken?: string }; if(j.csrfToken) return String(j.csrfToken);}catch{}}
      return '';
    };
    const csrf=g(); const headers: Record<string,string>={ 'Content-Type':'application/json' };
    if (csrf&&(method==='POST'||method==='PATCH')) headers['x-csrf-token']=csrf;
    if (path.includes('assessment-submit')&&method==='POST'){ try{ headers['x-idempotency-key']=(globalThis.crypto as Crypto).randomUUID(); }catch{ headers['x-idempotency-key']=`${Date.now()}-${Math.random()}`; }}
    const res=await fetch(path,{ method, headers, credentials:'same-origin', body: body!==undefined?JSON.stringify(body):undefined });
    let t=''; try{ t=await res.clone().text(); }catch{}
    let j: unknown=null; try{ j=t?JSON.parse(t):null; }catch{ j={ _raw:t };}
    return { status: res.status, ok: res.ok, json: j, text: t.slice(0,900) };
  }, { path: p, method: m, body: b as Record<string,unknown> | undefined });
}
async function loginAs(page: Page, email: string, pass: string) {
  await page.goto('/login.php', { waitUntil:'domcontentloaded' });
  await expect(page.locator('#email')).toBeVisible({ timeout:10_000 });
  await page.fill('#email',email); await page.fill('#password',pass);
  await Promise.all([page.waitForNavigation({ waitUntil:'domcontentloaded', timeout:12000 }).catch(()=>{}), page.click('button.auth-submit, button[data-submit]',{ force:true })]);
  await page.waitForLoadState('domcontentloaded'); expect(page.url()).not.toContain('/login.php');
}
test.describe('badge.test — Assessments (4 frameworks, q=3)', () => {
  test('login -> 4 baì (holland/mbti/disc/multiple_intelligence)', async ({ page }) => {
    test.setTimeout(120_000);
    await loginAs(page, EMAIL, PASSWORD);
    await page.goto('/app/learner/assessment.php?code=holland', { waitUntil:'domcontentloaded', timeout:15000 });
    await expect(page.locator('[data-assessment-runner]')).toBeVisible({ timeout:12000 });
    const detailLines: string[]=[]; const attemptLines: string[]=[];
    for (const code of CODES) {
      const detail=await af(page, `/app/learner/api/v1/assessments.php?code=${code}`, 'GET');
      expect(detail.status, `GET detail ${code} -> ${detail.status} ${detail.text.slice(0,300)}`).toBe(200);
      const dj=detail.json as { data?: { assessment?: { education_band?: string }; questions?: Array<{ id?: string; question_id?: string }> } } | null;
      const qs=dj?.data?.questions ?? []; const band=dj?.data?.assessment?.education_band ?? '';
      expect(qs.length, `questions empty ${code}`).toBeGreaterThan(0);
      detailLines.push(`${code} (${band||'unknown'}): ${qs.length} cau`);
      const start=await af(page, '/app/learner/api/v1/assessment-attempts.php', 'POST', { assessmentCode: code });
      expect([200,201].includes(start.status), `POST attempt ${code} -> ${start.status} ${start.text.slice(0,520)}`).toBeTruthy();
      const sj=start.json as { data?: unknown } | null; const inner=sj?.data as { id?: string; attempt_id?: string; attempt?: { id?: string } } | undefined;
      const aid:string=(inner?.id as string)||(inner?.attempt_id as string)||(inner?.attempt?.id as string)||((start.json as { id?: string })?.id as string)||'';
      expect(aid, `attempt id missing ${code}`).toBeTruthy();
      for (const q of qs){ const qid=(q?.id as string)||(q?.question_id as string)||''; const ans=await af(page, '/app/learner/api/v1/assessment-answers.php','PATCH',{ attemptId: aid, questionId: qid, answer: 3 }); expect(ans.status, `PATCH ${code} ${qid.slice(0,8)}`).toBe(200); }
      const sub=await af(page, '/app/learner/api/v1/assessment-submit.php','POST',{ attemptId: aid });
      expect(sub.status, `POST submit ${code} -> ${sub.status} ${sub.text.slice(0,520)}`).toBe(200);
      const subJ=sub.json as { data?: { onboarding?: { status?: string; next_code?: string|null } } } | null;
      attemptLines.push(`${code}: ${aid.slice(0,8)} onboard=${subJ?.data?.onboarding?.status??'?'} next=${subJ?.data?.onboarding?.next_code??'—'}`);
      await page.waitForTimeout(250);
    }
    { const hist=await af(page,'/app/learner/api/v1/assessments.php?view=history','GET'); expect(hist.status).toBe(200); const hj=hist.json as { data?: { assessment_history?: { count?: number } } }|null; expect((hj?.data?.assessment_history?.count??-1)).toBeGreaterThanOrEqual(4); }
    for (const code of CODES){ await page.goto(`/app/learner/assessment-result.php?code=${code}`,{ waitUntil:'domcontentloaded' }); const html=await page.content(); expect(html).not.toContain('Lỗi hệ thống'); expect(html).not.toContain('ASSESSMENT_NOT_FOUND'); }
    const out=['================ badge.test — 4 BAÌ ======================','Email:    '+EMAIL,'Password: '+PASSWORD,'Ket qua HOLLAND: http://127.0.0.1:8080/app/learner/assessment-result.php?code=holland','— Chi tiet —',...detailLines,'— Attempts —',...attemptLines,'Ghi chu: moi cau = 3 (Likert).','=============================================================='].join('\n');
    console.log('\n'+out+'\n'); await writeFile('/tmp/badge_assessment_creds.txt',out,'utf8'); await test.info().attach('badge-assessment-creds.txt',{ body: Buffer.from(out), contentType:'text/plain' });
  });
});

