const playwright = require('playwright');

(async () => {
  const browser = await playwright.chromium.launch({ headless: false });
  const pages = [];

  const baseURL = 'http://127.0.0.1:8080';

  const ROLES = [
    { label: 'Student', email: 'hs.minh@talenthub.vn', pass: 'TestPass_2026_local', dash: '/app/learner/index.php' },
    { label: 'Teacher', email: 'teacher.manual@talenthub.local', pass: 'TestPass_2026_local', dash: '/app/teacher/index.php' },
    { label: 'School', email: 'school.manual@talenthub.local', pass: 'TestPass_2026_local', dash: '/app/school/index.php' },
    { label: 'Enterprise', email: 'enterprise.manual@talenthub.local', pass: 'TestPass_2026_local', dash: '/app/enterprise/index.php' },
    { label: 'Admin', email: 'admin@admin.com', pass: 'AdminPass_2026_local', dash: '/app/admin/index.php' },
  ];

  for (const r of ROLES) {
    const page = await browser.newPage();
    pages.push(page);
    try {
      // Sử dụng baseURL đầy đủ
      await page.goto(`${baseURL}/login.php`, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForSelector('#email', { timeout: 10000 });
      await page.fill('#email', r.email);
      await page.fill('#password', r.pass);
      // Post login redirect
      const [response] = await Promise.all([
        page.waitForNavigation({ timeout: 20000, waitUntil: 'domcontentloaded' }).catch(() => null),
        page.click('button.auth-submit'),
      ]);
      await page.waitForLoadState('domcontentloaded');
      const afterLoginUrl = page.url();
      if (afterLoginUrl.includes('/login.php')) {
        console.error(`[${r.label}] ❌ Still on login.php after login`);
        const error = await page.locator('.auth-alert--error, [data-error-summary]').first().textContent();
        if (error) console.error(`Error message: ${error}`);
        continue;
      }
      console.log(`[${r.label}] ✅ Logged in, dashboard at: ${afterLoginUrl}`);
      // Nếu không đã trên dashboard, điều hướng đến nó
      if (!afterLoginUrl.includes(r.dash)) {
        await page.goto(`${baseURL}${r.dash}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForSelector('body', { timeout: 5000 });
        console.log(`[${r.label}] 📄 Dashboard loaded: ${page.url()}`);
      }
    } catch (e) {
      console.error(`[${r.label}] Lỗi không mong đợi:`, e);
    }
  }

  // Thông báo cho người dùng
  console.log('\n' + '='.repeat(60));
  console.log('Tất cả vai trò đã đăng nhập và giữ session trên browser');
  console.log('Bạn có thể truy cập các dashboard trong các tab đã mở');
  console.log('Các tài khoản có sẵn:');
  ROLES.forEach(r => console.log(`  ${r.label}: ${r.email} | Pass: ${r.pass}`));
  console.log('\nURL dashboard:');
  ROLES.forEach(r => console.log(`  ${r.label}: ${baseURL}${r.dash}`));
  console.log('\nĐể đóng tất cả cửa sổ này, nhấn Ctrl+C trong terminal.');
  console.log('='.repeat(60));

  // Giữ process chạy để không thoát
  await new Promise(() => {});
})();