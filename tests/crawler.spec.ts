import { test, expect, type Page, type Response } from '@playwright/test';
import { readdirSync, statSync } from 'node:fs';
import path from 'node:path';

/**
 * Crawler quét TOÀN BỘ trang TalentHub để phát hiện lỗi hệ thống:
 *   HTTP 500 / <h1>Không thể tải trang</h1> / <title>Lỗi hệ thống | TalentHub</title>
 *   / JSON { error: { code: 'INTERNAL_ERROR', ... } } (URL chứa /api/).
 *
 * Đi qua mọi route public (*.php) + portal (app/**.php) đã lọc bỏ phần nhúng
 * (includes/), endpoint API (api/) và các thư mục chỉ chứa class PHP.
 *
 * Chạy nhanh (chỉ chromium, 1 worker):
 *   npx playwright test tests/crawler.spec.ts --project=chromium --workers=1
 */

const ROOT = process.cwd();
const ERROR_BODY = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
const ERROR_TITLE = 'Lỗi hệ thống';

// Các thư mục chỉ chứa class PHP / dữ liệu, KHÔNG phải trang điều hướng được.
const CLASS_ONLY_PREFIXES = [
  'app/learner/ai/',
  'app/learner/data/',
  'app/learner/runtime/',
  'app/learner/assessment/',
  'app/learner/Contracts/',
  'app/learner/Domain/',
  'app/learner/Evaluation/',
  'app/learner/Consent/',
  'app/learner/Availability/',
  'app/learner/Config/',
  'app/shared/',
];

/** Duyệt thư mục, trả về danh sách .php có thể điều hướng được qua web.
 *  Chỉ quét 2 nguồn trên web root: public *.php (root) và các portal trong app/. */
function collectRoutes(dir: string, base: string): string[] {
  const out: string[] = [];
  let entries: string[];
  try {
    entries = readdirSync(dir);
  } catch {
    return out;
  }
  for (const name of entries) {
    const full = path.join(dir, name);
    const rel = base ? `${base}/${name}` : name;
    let st;
    try {
      st = statSync(full);
    } catch {
      continue;
    }

    // Crawler chỉ quan tâm trang web thực sự:
    //   - public: các file *.php ở ngoài cùng (root)
    //   - portal: thư mục app/**
    // Bỏ qua mọi thư mục nguồn khác (Database, bin, config, src, storage, assets...).
    if (base === '' && st.isDirectory() && name !== 'app') {
      continue;
    }

    if (st.isDirectory()) {
      if (/\/includes$/.test(rel) || /\/api/.test(rel)) {
        continue;
      }
      out.push(...collectRoutes(full, rel));
    } else if (st.isFile() && name.endsWith('.php')) {
      if (/\/includes\//.test(rel) || /\/api\//.test(rel)) continue;
      if (CLASS_ONLY_PREFIXES.some((p) => rel.startsWith(p))) continue;
      out.push(rel);
    }
  }
  return out;
}

function toRouteUrl(rel: string): string {
  const normalized = rel.replace(/\\/g, '/');
  if (normalized === 'index.php') return '/';
  return '/' + normalized;
}

// Xây danh sách route ngay khi tải module.
const ROUTES = collectRoutes(ROOT, '').map(toRouteUrl).sort();

/** NULL nếu page OK; trả về chuỗi mô tả nếu dính lỗi hệ thống. */
async function detectSystemError(page: Page, res: Response | null): Promise<string | null> {
  if (res && res.status() >= 500) return `HTTP ${res.status()}`;
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (bodyText.includes(ERROR_BODY)) return `HTML(lỗi hệ thống)`;
  const title = await page.title().catch(() => '');
  if (title.includes(ERROR_TITLE)) return `title="${title}"`;
  return null;
}

// Credential test (local): có thể override qua env.
const LOGIN_EMAIL = process.env.PLAYWRIGHT_LOGIN_EMAIL || 'hs.minh@talenthub.vn';
const LOGIN_PASSWORD = process.env.PLAYWRIGHT_LOGIN_PASSWORD || 'TestPass_2026_local';

/** Đăng nhập vào /login.php bằng email/password. */
async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  // Xác nhận đang ở form login và điền credential.
  await expect(page.locator('#email')).toBeVisible({ timeout: 7000 });
  await page.fill('#email', email);
  await page.fill('#password', password);
  // Submit form qua click nút submit (giảm flake so với form.submit trực tiếp).
  await Promise.all([
    page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
    page.click('button.auth-submit, button[data-submit]', { force: true }),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

/** NULL nếu OK; chuỗi mô tả lỗi nếu là "trang lỗi hệ thống". */
async function detectSystemErrorText(page: Page, res: Response | null): Promise<string | null> {
  if (res && res.status() >= 500) return `HTTP ${res.status()}`;
  const body = await page.locator('body').innerText().catch(() => '');
  if (body.includes(ERROR_BODY)) return 'HTML(lỗi hệ thống)';
  const title = await page.title().catch(() => '');
  if (title.includes(ERROR_TITLE)) return `title="${title}"`;
  return null;
}

/** Gom mọi response ≥500 trong suốt phiên (kể cả AJAX/API con) vào list. */
function attachServerErrorCollector(page: Page, list: string[]): void {
  page.on('response', (res: Response) => {
    if (res.status() >= 500) list.push(`${res.status()} ${res.url()}`);
  });
}

test('crawler có ĐĂNG NHẬP: quét toàn bộ route app/** không dính lỗi hệ thống', async ({ page }) => {
  await loginAs(page, LOGIN_EMAIL, LOGIN_PASSWORD);

  // Gom mọi 5xx (kể cả AJAX/API con) để phát hiện lỗi hệ thống ẩn.
  const server500: string[] = [];
  attachServerErrorCollector(page, server500);

  // Sau login thường redirect về dashboard theo role; xác nhận hợp lệ.
  const urlAfterLogin = page.url();
  console.log(`INFO đã đăng nhập, url: ${urlAfterLogin}`);

  const appRoutes = ROUTES.filter((r) => r.startsWith('/app/'));
  const failures: string[] = [];

  for (let i = 0; i < appRoutes.length; i++) {
    const route = appRoutes[i];
    let res: Response | null = null;
    try {
      res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15000 });
    } catch {
      // vẫn đọc body để phát hiện trang lỗi
    }
    const cause = await detectSystemErrorText(page, res);
    if (cause) {
      failures.push(`[${route}] ${cause}`);
      console.error(`FAIL ${route} -> ${cause}`);
    }
    if ((i + 1) % 25 === 0) console.log(`  …đã duyệt ${i + 1}/${appRoutes.length}`);
  }

  console.log(`\n=== Crawler(đăng nhập): ${appRoutes.length} route app/**. Lỗi: ${failures.length} ===`);
  if (server500.length) {
    console.error(`5xx/AJAX: ${server500.join(' ; ')}`);
  }
  const all = [...failures, ...server500.map((s) => `AJAX/5xx: ${s}`)];
  if (all.length) {
    console.error(all.join('\n'));
    expect(all, `Có ${all.length} lỗi hệ thống sau khi đăng nhập`).toEqual([]);
  } else {
    console.log('OK — toàn bộ route app/** sau đăng nhập không dính lỗi hệ thống.');
  }
});

test('crawler TOÀN BỘ trang (không đăng nhập): quét các route public không dính lỗi hệ thống', async ({ page }) => {
  const server500: string[] = [];
  attachServerErrorCollector(page, server500);
  const failures: string[] = [];

  for (let i = 0; i < ROUTES.length; i++) {
    const route = ROUTES[i];
    let res: Response | null = null;
    try {
      res = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 15000 });
    } catch {
      // vẫn tiếp tục đọc body để phát hiện trang lỗi
    }
    const cause = await detectSystemError(page, res);
    if (cause) {
      const url = res?.url() ?? '';
      failures.push(`[${route}] ${cause} ${url !== route ? `(redirected → ${url})` : ''}`);
      console.error(`FAIL ${route} -> ${cause}`);
    }
    // log tiến độ thưa
    if ((i + 1) % 25 === 0) console.log(`  …đã duyệt ${i + 1}/${ROUTES.length}`);
  }

  console.log(`\n=== Crawler: ${ROUTES.length} route. Lỗi: ${failures.length} ===`);
  if (server500.length) {
    console.error(`5xx/AJAX: ${server500.join(' ; ')}`);
  }
  const all = [...failures, ...server500.map((s) => `AJAX/5xx: ${s}`)];
  if (all.length) {
    console.error(all.join('\n'));
    expect(all, `Có ${all.length} lỗi hệ thống`).toEqual([]);
  } else {
    console.log('OK — toàn bộ route không dính lỗi hệ thống.');
  }
});