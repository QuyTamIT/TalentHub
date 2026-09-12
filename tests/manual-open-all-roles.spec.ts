import { test, expect, type BrowserContext, type Page } from '@playwright/test';

/**
 * Mở 5 cửa sổ browser, mỗi vai trò 1 cửa sổ, giữ open để test tay.
 *
 * BƯỚC 1: Database đã được dọn dẹp và seed lại
 * BƯỚC 2: Chạy script này để mở tất cả các vai trò
 *
 * Run:
 *   npx playwright test tests/manual-open-all-roles.spec.ts --project=chromium --headed
 *   npx playwright test tests/manual-open-all-roles.spec.ts --project=chromium --headed --timeout=600000
 */

const BASE = 'http://127.0.0.1:8080';

const ROLES = [
  { label: '🎓 Student',  email: 'student@talenthub.local',    pass: 'Talenthub@123', dash: '/app/student/index.php',   register: '/register.php' },
  { label: '👨‍🏫 Teacher', email: 'teacher@talenthub.local',    pass: 'Talenthub@123', dash: '/app/teacher/index.php',   register: '/register-teacher.php' },
  { label: '🏫 School',   email: 'school@talenthub.local',     pass: 'Talenthub@123', dash: '/app/school/index.php',    register: '/register-school.php' },
  { label: '🏢 Enterprise',email: 'enterprise@talenthub.local', pass: 'Talenthub@123', dash: '/app/enterprise/index.php', register: '/register-enterprise.php' },
  { label: '🛡️ Admin',    email: 'admin@admin.com',            pass: 'AdminPass_2026_local', dash: '/app/admin/index.php', register: null },
] as const;

test.describe('Manual: Open all roles (browser stays open for hand-test)', () => {
  test('Open 5 windows for 5 roles', async ({ browser }) => {
    const pages: Page[] = [];
    const LOGIN = `${BASE}/login.php`;

    console.log('\n' + '='.repeat(70));
    console.log('🚀 Đang mở 5 cửa sổ browser cho 5 vai trò...');
    console.log('='.repeat(70));

    for (let i = 0; i < ROLES.length; i++) {
      const role = ROLES[i];
      const page = await browser.newPage();
      pages.push(page);

      // Login
      await page.goto(LOGIN, { waitUntil: 'domcontentloaded', timeout: 20_000 });
      await expect(page.locator('#email')).toBeVisible({ timeout: 10_000 });
      await page.fill('#email', role.email);
      await page.fill('#password', role.pass);

      await Promise.all([
        page.waitForNavigation({ timeout: 15_000, waitUntil: 'domcontentloaded' }).catch(() => {}),
        page.click('button.auth-submit'),
      ]);

      await page.waitForLoadState('domcontentloaded');

      // Nếu vẫn ở login, thử điều hướng thẳng đến dashboard
      if (page.url().includes('/login.php')) {
        await page.goto(`${BASE}${role.dash}`, { waitUntil: 'domcontentloaded', timeout: 15_000 });
      }

      await page.waitForLoadState('domcontentloaded');
      const finalUrl = page.url();
      const status = finalUrl.includes(role.dash) || !finalUrl.includes('/login.php') ? '✅' : '❌';
      console.log(`[${i + 1}/5] ${role.label} ${status} -> ${finalUrl}`);
      console.log(`      Email: ${role.email} | Pass: ${role.pass}`);

      // Set page title để dễ nhận biết
      await page.evaluate((label) => { document.title = `[TalentHub] ${label}`; }, role.label);
    }

    console.log('\n' + '='.repeat(70));
    console.log('✅ Tất cả 5 cửa sổ đã mở!');
    console.log('📌 Tài khoản demo:');
    ROLES.forEach(r => console.log(`   ${r.label}: ${r.email} | Pass: ${r.pass}`));
    console.log('\n📌 Routes đăng ký:');
    ROLES.forEach(r => {
      if (r.register) console.log(`   ${r.label}: ${BASE}${r.register}`);
    });
    console.log('\n⏸️  Browser sẽ giữ open trong 10 phút để test tay...');
    console.log('💡 Nhấn Ctrl+C trong terminal để đóng tất cả.');
    console.log('='.repeat(70));

    // Giữ browser open 10 phút (600 giây)
    await new Promise<void>((resolve) => setTimeout(resolve, 600_000));
  });
});

