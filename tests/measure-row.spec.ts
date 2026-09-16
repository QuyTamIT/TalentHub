import { test, expect } from '@playwright/test';

const EMAIL = 'htng@gmail.com';
async function login(page: import('@playwright/test').Page): Promise<void> {
  const pw = process.env.TALENTHUB_TEST_PASSWORD ?? '';
  test.skip(!pw, 'TALENTHUB_TEST_PASSWORD not set');
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', EMAIL);
  await page.fill('#password', pw);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => null),
    page.click('button.auth-submit'),
  ]);
}

test('measure form-field + button + slots alignment', async ({ page, context }) => {
  await login(page);
  await page.goto('/app/enterprise/internships/create.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await page.waitForSelector('#form-field', {timeout:10000});
  const info = await page.evaluate(() => {
    const sel=document.getElementById('form-field') as HTMLElement;
    const btn=document.getElementById('btn-add-specialty') as HTMLElement;
    const slots=document.getElementById('form-slots') as HTMLElement;
    const grid=document.querySelector('.ent-create-form-grid') as HTMLElement;
    const r=(el: HTMLElement)=>{const b=el.getBoundingClientRect();return {x:Math.round(b.x),y:Math.round(b.y),w:Math.round(b.width),h:Math.round(b.height)}};
    const cs=(el: HTMLElement)=>getComputedStyle(el);
    const lbl8=sel.closest('.ent-col-8')!.querySelector('.ent-create-label') as HTMLElement;
    const lbl4=slots.closest('.ent-col-4')!.querySelector('.ent-create-label') as HTMLElement;
    return {
      select: {rect:r(sel), h_cs:cs(sel).height, mb:cs(sel).minBlockSize, pad:cs(sel).padding, box:cs(sel).boxSizing},
      button: {rect:r(btn), h_cs:cs(btn).height, mb:cs(btn).minBlockSize, box:cs(btn).boxSizing},
      slots:  {rect:r(slots), h_cs:cs(slots).height, mb:cs(slots).minBlockSize, pad:cs(slots).padding, box:cs(slots).boxSizing},
      fwa:    {rect:r(sel.closest('.ent-field-with-add') as HTMLElement), align:cs(sel.closest('.ent-field-with-add') as HTMLElement).alignItems, gap:cs(sel.closest('.ent-field-with-add') as HTMLElement).gap},
      col8:   {rect:r(sel.closest('.ent-col-8') as HTMLElement)},
      col4:   {rect:r(slots.closest('.ent-col-4') as HTMLElement)},
      label8: lbl8?{rect:r(lbl8)}:null,
      label4: lbl4?{rect:r(lbl4)}:null,
      grid:   grid?{rect:r(grid), alignItems:cs(grid).alignItems, gap:cs(grid).gap}:null,
    };
  });
  console.log(JSON.stringify(info, null, 2));
  // Screenshot the grid
  await page.locator('.ent-create-form-grid').first().screenshot({path:'/tmp/form-grid.png'});
  // Basic assertions: all three control tops should be within 1px
  const tol = 1.5;
  const topFwa = info.fwa.rect.y;
  const topSlots = info.slots.rect.y;
  const topBtn = info.button.rect.y;
  const topSel = info.select.rect.y;
  console.log(`tops: fwa=${topFwa} sel=${topSel} btn=${topBtn} slots=${topSlots}`);
  // They likely differ if column label height is accounted as offset
});
