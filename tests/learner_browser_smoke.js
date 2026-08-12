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
    const page = await context.newPage();

    await page.goto(`${baseUrl}/role-selection.php`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Chọn vai trò Học sinh / Sinh viên' }).click();
    await page.waitForURL('**/app/learner/index.php');
    check(await page.getByText('Chào mừng trở lại, Nguyễn Văn A', { exact: false }).isVisible(), 'Role selection navigates into the Learner overview');

    await page.goto(`${baseUrl}/app/learner/index.php`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Mở danh mục điều hướng' }).click();
    check(await page.locator('#learner-sidebar').evaluate((element) => element.classList.contains('is-open')), 'Mobile sidebar opens');
    await page.locator('#learner-sidebar-backdrop').click({ position: { x: 380, y: 420 } });
    check(!(await page.locator('#learner-sidebar').evaluate((element) => element.classList.contains('is-open'))), 'Mobile sidebar closes from backdrop');

    const registration = page.locator('[data-register-activity]').first();
    await registration.click();
    check((await registration.textContent()).trim() === 'Đã đăng ký', 'Activity registration updates button state');

    await page.getByText('Khám phá hoạt động', { exact: false }).click();
    check(await page.locator('#learner-toast').evaluate((element) => element.classList.contains('is-visible')), 'Pending activity route shows toast feedback');

    await page.goto(`${baseUrl}/app/learner/profile.php`, { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /Chỉnh sửa/ }).click();
    check(await page.locator('#learner-edit-modal').isVisible(), 'Edit profile modal opens');
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
    await page.getByRole('button', { name: 'Đóng cửa sổ chia sẻ' }).click();

    await page.goto(`${baseUrl}/app/learner/discover.php`, { waitUntil: 'domcontentloaded' });
    const discCard = page.locator('[data-assessment-card="disc"]');
    await discCard.locator('[data-assessment-action]').click();
    check(await page.locator('#learner-assessment-modal').isVisible(), 'Assessment modal opens');
    await page.locator('[data-confirm-assessment]').click();
    check((await discCard.locator('[data-assessment-action]').textContent()).trim() === 'Tiếp tục', 'Starting DISC changes CTA to continue');

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
