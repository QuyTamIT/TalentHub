import { test, expect, type Page } from '@playwright/test';

/**
 * E2E – Đồng bộ giao diện /login.php với các trang auth & frontend khác
 * (logo FTalentHub, thứ tự CSS, cấu trúc layout auth).
 *
 * Chạy:
 *   npx playwright test tests/login-ui-consistency.spec.ts
 */

const AUTH_CSS_ORDER = [
  'home.css',
  'global.css',
  'auth.css',
  'polish.css',
];

/** Lấy src thật của `<img>` bên trong logo (bỏ domain giữa test). */
async function logoImgSrc(page: Page, selector: string): Promise<string | null> {
  const src = await page
    .locator(selector)
    .locator('img')
    .getAttribute('src')
    .catch(() => null);
  if (!src) return null;
  return src.split('/').pop() ?? src;
}

test.describe('Trang đăng nhập /login.php', () => {
  test('title, favicon và tiêu đề đúng nhận diện TalentHub', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });

    await expect(page).toHaveTitle(/Đăng nhập \| TalentHub/);
    const favicon = page.locator('link[rel="icon"]');
    await expect(favicon).toHaveAttribute('href', /logo\.svg/);

    await expect(page.locator('h1#auth-brand-title')).toContainText('phát triển tài năng');
    await expect(page.locator('h2#login-title')).toHaveText('Đăng nhập tài khoản');
  });

  test('logo desktop (auth-brand__logo) dùng logo.svg giống các trang auth', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });

    const logo = page.locator('.auth-brand__logo');
    await expect(logo).toBeVisible();
    await expect(logo).toHaveAttribute('href', './index.php');
    await expect(logo.locator('img')).toHaveAttribute('src', './assets/images/logo.svg');
    await expect(logo.locator('img')).toHaveAttribute('alt', 'FTalentHub');
  });

  test('logo mobile (auth-mobile-logo) dùng logo.svg giống trang register', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await page.setViewportSize({ width: 390, height: 844 });

    const mobileLogo = page.locator('.auth-mobile-logo');
    // Mobile thay thế banner trái → phải hiển thị như tất cả trang auth
    await expect(mobileLogo).toBeVisible();
    await expect(mobileLogo.locator('img')).toHaveAttribute('src', './assets/images/logo.svg');

    // Banner trái ẩn trên mobile (giống register.php)
    await expect(page.locator('.auth-brand')).toBeHidden();
  });

  test('CSS được nạp theo đúng thứ tự chung của các trang auth', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    const hrefs = await page.evaluate(() =>
      [...document.querySelectorAll('link[rel="stylesheet"]')]
        .map((l) => (l as HTMLLinkElement).getAttribute('href') ?? ''),
    );

    const names = hrefs.map((h) => h.split('?')[0].split('/').pop() ?? h);
    // home.css luôn đi trước (chứa design tokens), auth.css sau cùng quyền override.
    expect(names[0], `CSS[0] phải là home.css, thực tế: ${names.join(', ')}`).toBe('home.css');
    for (const required of AUTH_CSS_ORDER) {
      expect(names, `thiếu stylesheet ${required}`).toContain(required);
    }
    const authIdx = names.indexOf('auth.css');
    const polishIdx = names.indexOf('polish.css');
    expect(authIdx, 'auth.css phải nằm sau home.css').toBeGreaterThan(names.indexOf('home.css'));
    expect(polishIdx, 'polish.css phải nạp sau auth.css (class cuối thắng)').toBeGreaterThan(authIdx);
  });

  test('cấu trúc layout auth khớp register.php (container/panel/brand)', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('body.auth-page')).toBeVisible();
    await expect(page.locator('main.auth-layout')).toBeVisible();
    await expect(page.locator('section.auth-brand')).toBeVisible();
    await expect(page.locator('section.auth-panel > .auth-panel__inner')).toBeVisible();
    await expect(page.locator('a.skip-link[href="#main-content"]')).toBeVisible();
  });

  test('computed style layout: banner trái + panel phải cạnh nhau (desktop)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });

    const brand = page.locator('.auth-brand');
    const panel = page.locator('.auth-panel');
    const brandBox = (await brand.boundingBox())!;
    const panelBox = (await panel.boundingBox())!;

    expect(brandBox.width, 'banner trái chiếm ~42% chiều rộng').toBeGreaterThan(panelBox.width * 0.5);
    expect(panelBox.x, 'panel phải nằm bên phải banner').toBeGreaterThanOrEqual(brandBox.x + brandBox.width - 1);
    expect(Math.abs((brandBox.y + brandBox.height) - (panelBox.y + panelBox.height))).toBeLessThan(4);
  });

  test('form đăng nhập hoạt động: field + toggle mật khẩu + nút submit', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });

    const email = page.locator('#email');
    const password = page.locator('#password');
    await expect(email).toBeVisible();
    await expect(password).toBeVisible();
    await expect(password).toHaveAttribute('type', 'password');

    // Toggle mật khẩu: ẩn → hiện
    const toggle = page.locator('[data-password-toggle]');
    await toggle.click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await toggle.click();
    await expect(password).toHaveAttribute('type', 'password');

    await expect(page.locator('button[data-submit]')).toContainText('Đăng nhập');
  });

  test('liên kết điều hướng giống các trang auth (role-selection, home)', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });

    const switchLink = page.locator('.auth-switch a[href="./role-selection.php"]');
    await expect(switchLink).toBeVisible();

    const back = page.locator('a.auth-back[href="./index.php"]');
    await expect(back).toBeVisible();
    await expect(back).toContainText('Về trang chủ');
  });
});

test.describe('Đối chiếu nhận diện với các trang khác (parity)', () => {
  test('logo img dùng cùng 1 asset giữa login, register và mobile', async ({ page }) => {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    const loginDesktop = await logoImgSrc(page, '.auth-brand__logo');
    const loginMobile = await logoImgSrc(page, '.auth-mobile-logo');

    await page.goto('/register.php', { waitUntil: 'domcontentloaded' });
    const registerDesktop = await logoImgSrc(page, '.auth-brand__logo');
    const registerMobile = await logoImgSrc(page, '.auth-mobile-logo');

    expect(loginDesktop, 'login desktop logo').toBe('logo.svg');
    expect(loginMobile, 'login mobile logo').toBe('logo.svg');
    expect(registerDesktop, 'register desktop logo').toBe('logo.svg');
    expect(registerMobile, 'register mobile logo').toBe('logo.svg');
    expect(loginDesktop, 'login/register dùng chung asset').toBe(registerDesktop);
  });

  test('login nạp đúng nhóm stylesheet auth như register.php', async ({ page }) => {
    const getNames = async (path: string) => {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      return page.evaluate(() =>
        [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) =>
          (l as HTMLLinkElement).getAttribute('href')?.split('?')[0].split('/').pop() ?? '',
        ),
      );
    };

    const loginCss = await getNames('/login.php');
    const registerCss = await getNames('/register.php');
    // Nhóm CSS cốt lõi của tất cả trang auth phải có ở cả hai.
    for (const file of ['home.css', 'global.css', 'auth.css', 'polish.css']) {
      expect(loginCss, `login thiếu ${file}`).toContain(file);
      expect(registerCss, `register thiếu ${file}`).toContain(file);
    }
    // register.php kèm thêm brand-component.css (sidebar brand), login không dùng sidebar nên không bắt buộc.
    expect(loginCss.includes('home.css') && registerCss.includes('home.css')).toBe(true);
    const loginExtras = loginCss.filter((n) => !['home.css', 'global.css', 'auth.css', 'polish.css', 'typeui-selects.css', 'brand-component.css'].includes(n));
    expect(loginExtras, `login nạp thêm file lạ so với nhóm auth quen thuộc: ${loginExtras.join(', ')}`).toEqual([]);
  });

  test('chụp screenshot login ở desktop & mobile để đối chiếu trực quan', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('button[data-submit]')).toBeVisible();
    await page.screenshot({ path: 'test-results/login-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.auth-mobile-logo')).toBeVisible();
    await page.screenshot({ path: 'test-results/login-mobile.png', fullPage: true });
  });
});