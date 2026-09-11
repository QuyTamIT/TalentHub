import { test, expect, type Page, type Response } from '@playwright/test';

/**
 * Máy phát hiện "trang lỗi hệ thống" của TalentHub.
 *
 * Nguồn gốc lỗi: src/Http/UnhandledExceptionHandler.php trả HTTP 500 và render
 *   <h1>Không thể tải trang</h1>
 *   <p>Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.</p>
 * (hoặc JSON { error: { code: 'INTERNAL_ERROR', ... } } với URL chứa /api/).
 *
 * Cách dùng:
 *   npx playwright test tests/system-error-detector.spec.ts --project=chromium
 *   PLAYWRIGHT_ROUTES="/login.php,app/learner/dashboard.php" npx playwright test tests/system-error-detector.spec.ts
 */

const SYSTEM_ERROR_TITLE = 'Không thể tải trang';
const SYSTEM_ERROR_BODY = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';

const DEFAULT_ROUTES = ['/', '/login.php', '/register.php', '/role-selection.php'];

const ROUTES =
  (process.env.PLAYWRIGHT_ROUTES || '')
    .split(',')
    .map((r) => r.trim())
    .filter(Boolean) || DEFAULT_ROUTES;

/** Thêm "assert no 500" cho mọi response của 1 page. */
function attachResponse500Detector(page: Page, failed: string[]) {
  page.on('response', (res: Response) => {
    if (res.status() >= 500) {
      failed.push(`${res.status()} ${res.url()}`);
    }
  });
}

test('duyệt các route chính KHÔNG xuất hiện trang lỗi hệ thống', async ({ page }) => {
  const serverErrors: string[] = [];
  attachResponse500Detector(page, serverErrors);

  for (const route of ROUTES) {
    const response = await page.goto(route, { waitUntil: 'domcontentloaded' });

    // Lấy bodyText để phát hiện trang lỗi hệ thống.
    const bodyText = await page.locator('body').innerText().catch(() => '');

    // 1) HTTP 500 bị chặn.
    expect(response?.status(), `[${route}] không được trả về 500`).toBeLessThan(500);

    // 2) Không được render trang "Không thể tải trang".
    expect(
      bodyText,
      `[${route}] đang hiện trang lỗi hệ thống: \n${bodyText}\nCác request 5xx: ${serverErrors.join('; ')}`,
    ).not.toContain(SYSTEM_ERROR_BODY);

    // 3) Tiêu đề trang không được là "Lỗi hệ thống | TalentHub".
    const title = await page.title();
    expect(title, `[${route}] tiêu đề là trang lỗi: "${title}"`).not.toContain('Lỗi hệ thống');
  }

  // 4) Không được có bất kỳ request nào trả 5xx trong suốt phiên duyệt.
  expect(serverErrors, 'Có request trả về 5xx trong lúc duyệt trang').toEqual([]);
});

test('chẩn đoán: ghi lại tình trạng thực tế của các route (không fail)', async ({ page }) => {
  const results: string[] = [];

  for (const route of ROUTES) {
    try {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      const status = response?.status() ?? 0;
      const title = await page.title().catch(() => '(không lấy được title)');
      const body = (await page.locator('body').innerText().catch(() => ''))
        .replace(/\s+/g, ' ')
        .trim();

      const isErrorPage = body.includes(SYSTEM_ERROR_BODY);
      results.push(`[${route}] HTTP ${status} | title="${title}" | lỗi_hệ_thống=${isErrorPage}`);
      console.log(`INFO ${route} -> HTTP ${status} lỗi_hệ_thống=${isErrorPage}`);
    } catch (e) {
      console.error(`INFO ${route} -> goto lỗi: ${(e as Error).message}`);
    }
  }

  return results;
});