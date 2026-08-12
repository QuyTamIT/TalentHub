'use strict';

const { chromium } = require('playwright');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const baseUrl = process.env.LEARNER_BASE_URL || 'http://127.0.0.1:8765';
const outputDir = process.env.LEARNER_QA_DIR || path.join(os.tmpdir(), 'talenthub-learner-qa');
const failures = [];

const pages = [
    { slug: 'overview', path: '/app/learner/index.php', marker: 'Chào mừng trở lại, Nguyễn Văn A' },
    { slug: 'profile', path: '/app/learner/profile.php', marker: 'Hồ sơ năng lực' },
    { slug: 'discover', path: '/app/learner/discover.php', marker: 'Khám phá năng khiếu' },
    { slug: 'activities', path: '/app/learner/activities.php', marker: 'Khám phá hoạt động' },
    { slug: 'checkin', path: '/app/learner/checkin.php', marker: 'Check-in trải nghiệm' },
    { slug: 'evaluation', path: '/app/learner/evaluation.php', marker: 'Đánh giá năng lực' },
];

const viewports = [
    { name: 'desktop', width: 1600, height: 1000 },
    { name: 'tablet', width: 834, height: 1112 },
    { name: 'mobile', width: 390, height: 844 },
];

function check(condition, message) {
    if (condition) {
        process.stdout.write(`PASS: ${message}\n`);
        return;
    }

    failures.push(message);
    process.stderr.write(`FAIL: ${message}\n`);
}

async function captureViewport(browser, pageConfig, viewport) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const consoleErrors = [];

    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => consoleErrors.push(error.message));

    const response = await page.goto(`${baseUrl}${pageConfig.path}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(250);

    check(response?.status() === 200, `${pageConfig.slug} returns HTTP 200 at ${viewport.name}`);
    check(await page.getByText(pageConfig.marker, { exact: false }).first().isVisible(), `${pageConfig.slug} marker is visible at ${viewport.name}`);

    const menuVisible = await page.locator('#learner-sidebar-toggle').isVisible();
    check(
        viewport.width <= 1100 ? menuVisible : !menuVisible,
        `${pageConfig.slug} shows the sidebar toggle only below desktop width at ${viewport.name}`
    );

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(overflow <= 1, `${pageConfig.slug} has no horizontal overflow at ${viewport.name} (${overflow}px)`);
    check(consoleErrors.length === 0, `${pageConfig.slug} has no console errors at ${viewport.name}${consoleErrors.length ? `: ${consoleErrors.join(' | ')}` : ''}`);

    await page.screenshot({
        path: path.join(outputDir, `${pageConfig.slug}-${viewport.name}.png`),
        fullPage: true,
    });

    await context.close();
}

async function verifyInteractions(browser) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: baseUrl });
    const page = await context.newPage();

    await page.goto(`${baseUrl}/role-selection.php`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Chọn vai trò Học sinh / Sinh viên' }).click();
    await page.waitForURL('**/app/learner/index.php');
    check(await page.getByText('Chào mừng trở lại, Nguyễn Văn A', { exact: false }).isVisible(), 'Role selection navigates into the Learner overview');

    await page.goto(`${baseUrl}/app/learner/index.php`, { waitUntil: 'domcontentloaded' });
    check(await page.locator('#learner-sidebar').getAttribute('aria-hidden') === 'true', 'Closed mobile sidebar is hidden from assistive technology');
    check(await page.locator('#learner-sidebar').evaluate((element) => element.inert === true), 'Closed mobile sidebar is removed from the tab order');
    await page.getByRole('button', { name: 'Mở danh mục điều hướng' }).click();
    check(await page.locator('#learner-sidebar').evaluate((element) => element.classList.contains('is-open')), 'Mobile sidebar opens');
    check(await page.locator('#learner-sidebar-close').isVisible(), 'Open mobile sidebar provides an accessible close button');
    await page.waitForFunction(() => document.querySelector('#learner-sidebar')?.contains(document.activeElement));
    check(await page.locator('#learner-sidebar').evaluate((element) => element.contains(document.activeElement)), 'Opening the mobile sidebar moves focus into the drawer');
    await page.locator('#learner-sidebar-backdrop').click({ position: { x: 380, y: 420 } });
    check(!(await page.locator('#learner-sidebar').evaluate((element) => element.classList.contains('is-open'))), 'Mobile sidebar closes from backdrop');
    check(await page.locator('#learner-sidebar-toggle').evaluate((element) => element === document.activeElement), 'Closing the mobile sidebar restores focus to the toggle');
    await page.getByRole('button', { name: 'Mở danh mục điều hướng' }).click();
    await page.locator('#learner-sidebar-close').click();
    check(await page.locator('#learner-sidebar').getAttribute('aria-hidden') === 'true', 'Sidebar close button hides the drawer accessibly');
    await page.getByRole('button', { name: 'Mở danh mục điều hướng' }).click();
    await page.keyboard.press('Escape');
    check(await page.locator('#learner-sidebar').getAttribute('aria-hidden') === 'true', 'Escape closes the mobile sidebar');

    const registration = page.locator('[data-register-activity]').first();
    await registration.click();
    check((await registration.textContent()).trim() === 'Đã đăng ký', 'Activity registration updates button state');

    await page.getByRole('link', { name: /Khám phá hoạt động/ }).click();
    await page.waitForURL('**/app/learner/activities.php');
    check(await page.getByRole('heading', { name: 'Khám phá hoạt động' }).isVisible(), 'Overview activity CTA navigates to the implemented page');

    const creativeFilter = page.getByRole('button', { name: 'Sáng tạo', exact: true });
    await creativeFilter.click();
    check(await page.locator('[data-activity-card]:visible').count() === 2, 'Activity category filter shows the two creative activities');
    check(await creativeFilter.getAttribute('aria-pressed') === 'true', 'Active activity filter exposes pressed state');

    await page.getByRole('button', { name: 'Tất cả', exact: true }).click();
    const activitySearch = page.locator('#learner-search-input');
    await activitySearch.fill('cam bien');
    check(await page.locator('[data-activity-card]:visible').count() === 1, 'Activity search matches Vietnamese text without accents');
    check(await page.locator('[data-activity-card]:visible').getAttribute('data-title') === 'IoT Lab — Cảm biến thông minh', 'Activity search keeps the matching card');

    await activitySearch.fill('khong ton tai');
    check(await page.locator('[data-activity-empty]').isVisible(), 'Activity search exposes an empty state');
    await activitySearch.fill('');
    check(await page.locator('[data-activity-card]:visible').count() === 6, 'Clearing activity search restores all cards');

    const firstCatalogRegistration = page.locator('[data-activity-register]').first();
    await firstCatalogRegistration.click();
    check(await page.locator('#learner-registration-modal').isVisible(), 'Activity registration confirmation modal opens');
    check((await page.locator('[data-registration-name]').textContent()).includes('IoT Lab'), 'Registration modal identifies the selected activity');
    await page.getByRole('button', { name: 'Hủy' }).click();
    check((await firstCatalogRegistration.textContent()).trim() === 'Đăng ký ngay', 'Cancelling registration preserves the original state');
    await firstCatalogRegistration.click();
    await page.locator('[data-confirm-registration]').click();
    check(await firstCatalogRegistration.isDisabled(), 'Confirming registration disables the selected button');
    check((await firstCatalogRegistration.textContent()).trim() === 'Đã đăng ký', 'Confirming registration updates the selected button');

    const secondCatalogRegistration = page.locator('[data-activity-register]').nth(1);
    await secondCatalogRegistration.click();
    await page.keyboard.press('Escape');
    check(await page.locator('#learner-registration-modal').isHidden(), 'Escape closes the registration modal');
    check(await secondCatalogRegistration.evaluate((element) => element === document.activeElement), 'Registration modal restores focus to its trigger');

    await page.goto(`${baseUrl}/app/learner/profile.php`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /Chỉnh sửa/ }).click();
    check(await page.locator('#learner-edit-modal').isVisible(), 'Edit profile modal opens');
    await page.waitForFunction(() => document.querySelector('#learner-edit-modal')?.contains(document.activeElement));
    check(await page.locator('#learner-edit-modal').evaluate((element) => element.contains(document.activeElement)), 'Edit modal moves focus into the dialog');
    const firstEditControl = page.locator('#learner-edit-modal button, #learner-edit-modal input').first();
    const lastEditControl = page.locator('#learner-edit-modal button, #learner-edit-modal input').last();
    await lastEditControl.focus();
    await page.keyboard.press('Tab');
    check(await firstEditControl.evaluate((element) => element === document.activeElement), 'Edit modal traps forward keyboard focus');
    await page.locator('#learner-field-name').fill('');
    await page.getByRole('button', { name: 'Lưu thay đổi' }).click();
    const validationState = await page.locator('#learner-edit-modal').evaluate((modal) => ({
        hidden: modal.hidden,
        value: modal.querySelector('#learner-field-name')?.value,
        invalid: modal.querySelector('#learner-field-name')?.getAttribute('aria-invalid'),
        error: modal.querySelector('[data-error-for="name"]')?.textContent,
    }));
    check(validationState.invalid === 'true', 'Edit profile validates required name');
    if (validationState.hidden) {
        await page.getByRole('button', { name: /Chỉnh sửa/ }).click();
    }
    await page.locator('#learner-field-name').fill('Nguyễn Văn A');
    await page.getByRole('button', { name: 'Lưu thay đổi' }).click();
    check(await page.locator('#learner-edit-modal').isHidden(), 'Valid profile edit closes modal');

    await page.getByRole('button', { name: /Chia sẻ hồ sơ/ }).click();
    check(await page.locator('#learner-share-modal').isVisible(), 'Share profile modal opens');
    await page.locator('[data-copy-profile]').click();
    await page.waitForTimeout(250);
    const copyState = await page.evaluate(() => ({
        button: document.querySelector('[data-copy-profile]')?.textContent.trim(),
        clipboard: Boolean(navigator.clipboard),
        secure: window.isSecureContext,
        toast: document.querySelector('#learner-toast .learner-toast__message')?.textContent,
    }));
    check(
        copyState.button === 'Đã sao chép',
        copyState.button === 'Đã sao chép' ? 'Share profile copies the public link' : `Share profile copies the public link (${JSON.stringify(copyState)})`
    );
    await page.keyboard.press('Escape');
    check(await page.locator('#learner-share-modal').isHidden(), 'Escape closes the share modal');
    check(await page.locator('[data-open-modal="learner-share-modal"]').evaluate((element) => element === document.activeElement), 'Closing the share modal restores focus');

    await page.goto(`${baseUrl}/app/learner/discover.php`, { waitUntil: 'domcontentloaded' });
    const discCard = page.locator('[data-assessment-card="disc"]');
    await discCard.locator('[data-assessment-action]').click();
    check(await page.locator('#learner-assessment-modal').isVisible(), 'Assessment modal opens');
    await page.locator('[data-confirm-assessment]').click();
    check((await discCard.locator('[data-assessment-action]').textContent()).trim() === 'Tiếp tục', 'Starting DISC changes CTA to continue');

    await page.goto(`${baseUrl}/app/learner/checkin.php`, { waitUntil: 'domcontentloaded' });
    check(await page.locator('[data-checkin-record]').count() === 4, 'Check-in renders four confirmed history records');
    check(await page.getByText('Đã xác nhận', { exact: true }).count() === 4, 'Check-in history uses confirmed status');
    const scannerTrigger = page.getByRole('button', { name: /Mở camera quét/ });
    await scannerTrigger.click();
    check(await page.locator('#learner-scanner-modal').isVisible(), 'QR demo scanner modal opens');
    check(await page.getByText('Đây là giao diện demo.', { exact: false }).isVisible(), 'QR modal clearly identifies demo behavior');
    await page.keyboard.press('Escape');
    check(await page.locator('#learner-scanner-modal').isHidden(), 'Escape closes the QR demo modal');
    check(await scannerTrigger.evaluate((element) => element === document.activeElement), 'QR demo modal restores focus');

    await page.goto(`${baseUrl}/app/learner/evaluation.php`, { waitUntil: 'domcontentloaded' });
    const termSelectBorder = await page.locator('#learner-evaluation-term').evaluate((element) => getComputedStyle(element).borderTopWidth);
    check(termSelectBorder === '0px', 'Evaluation semester select does not render a duplicate native border');
    check((await page.locator('[data-evaluation-total]').textContent()).trim() === '90', 'Evaluation starts with total score 90');
    check((await page.locator('[data-evaluation-classification]').textContent()).trim() === 'Xuất sắc', 'Evaluation starts with excellent classification');
    await page.locator('#learner-evaluation-term').selectOption('2025-2026-1');
    check((await page.locator('[data-evaluation-total]').textContent()).trim() === '84', 'Changing semester updates the total score');
    check(await page.locator('[data-evaluation-criterion]').count() === 4, 'Published semester renders four updated criteria');
    await page.locator('#learner-evaluation-term').selectOption('2024-2025-2');
    check(await page.locator('[data-evaluation-empty]').isVisible(), 'Empty semester exposes the evaluation empty state');
    check(await page.locator('[data-evaluation-content]').isHidden(), 'Empty semester hides criteria content');
    await page.locator('#learner-evaluation-term').selectOption('2025-2026-2');
    check(await page.locator('[data-evaluation-criterion]').count() === 4, 'Returning to the current semester restores four criteria');

    await page.setViewportSize({ width: 1200, height: 800 });
    await page.goto(`${baseUrl}/app/learner/index.php`, { waitUntil: 'domcontentloaded' });
    await page.locator('#learner-search-input').fill('IoT');
    await page.locator('#learner-search-form').press('Enter');
    check((await page.locator('#learner-toast .learner-toast__message').textContent()).includes('IoT'), 'Header search provides frontend feedback');
    await page.locator('#learner-notification-button').click();
    check((await page.locator('#learner-toast .learner-toast__message').textContent()).includes('3 thông báo mới'), 'Notification button provides frontend feedback');

    await context.close();
}

async function main() {
    fs.mkdirSync(outputDir, { recursive: true });
    const browser = await chromium.launch({ headless: true });

    try {
        for (const pageConfig of pages) {
            for (const viewport of viewports) {
                await captureViewport(browser, pageConfig, viewport);
            }
        }
        await verifyInteractions(browser);
    } finally {
        await browser.close();
    }

    process.stdout.write(`Screenshots: ${outputDir}\n`);
    if (failures.length > 0) {
        process.stderr.write(`${failures.length} browser smoke assertion(s) failed.\n`);
        process.exit(1);
    }
}

main().catch((error) => {
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exit(1);
});
