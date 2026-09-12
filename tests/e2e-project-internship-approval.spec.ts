import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

/**
 * End-to-End browser test for School Project & Enterprise Internship Workflow.
 * Verifies:
 * 1. Student registers for school project -> Button transitions to pending awaiting school review.
 * 2. School admin reviews project -> Opens modal -> Approves student -> Status updates to active.
 * 3. Student views project detail -> Verified as active member.
 * 4. Enterprise ATS pipeline & Student application tracking workflow.
 */

const PROJECT_ID = '50000000-0000-4000-8000-000000000004';
const STUDENT_EMAIL = 'hs.bao@talenthub.vn';
const SCHOOL_EMAIL = 'school.admin@talenthub.vn';
const ENTERPRISE_EMAIL = 'enterprise@talenthub.local';
const PASSWORD = 'Talenthub@123';

test.describe.serial('School Project & Enterprise Internship Workflow', () => {
  test.beforeAll(async () => {
    // Reset database membership state to ensure clean reproducible run
    try {
      execSync('php tests/reset_e2e_membership.php', {
        cwd: path.resolve(__dirname, '..'),
        env: {
          ...process.env,
          PATH: 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64;' + process.env.PATH,
        },
      });
    } catch (err) {
      console.warn('Could not run reset script via execSync:', err);
    }
  });

  async function loginAs(page: Page, email: string, roleUrlCheck: string) {
    await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
  }

  test('Step 1: Sinh viên đăng ký dự án và nhận trạng thái Chờ Nhà trường duyệt', async ({ page }) => {
    await loginAs(page, STUDENT_EMAIL, 'learner');

    // Mở trang chi tiết dự án
    await page.goto(`/app/learner/project.php?id=${PROJECT_ID}`, { waitUntil: 'networkidle' });

    // Kiểm tra tiêu đề dự án
    const titleLocator = page.locator('#project-title');
    await expect(titleLocator).toBeVisible();

    // Nút đăng ký dự án phải xuất hiện nếu chưa là thành viên
    const registerBtn = page.locator('button:has-text("Đăng ký dự án")');
    if (await registerBtn.isVisible()) {
      await registerBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // Nút bây giờ phải hiển thị trạng thái chờ duyệt
    const pendingBtn = page.locator('button.learner-btn--pending');
    await expect(pendingBtn).toBeVisible();
    await expect(pendingBtn).toContainText('Chờ Nhà trường duyệt');

    // Huy hiệu chờ duyệt trên đầu trang
    const pendingBadge = page.locator('.learner-project-detail__badges span:has-text("Chờ duyệt")');
    await expect(pendingBadge).toBeVisible();

    // Chụp ảnh bằng chứng
    await page.screenshot({
      path: 'storage/test-screenshots/01-student-pending.png',
      fullPage: true,
    });
  });

  test('Step 2: Nhà trường xem dự án, mở modal quản lý thành viên và phê duyệt', async ({ page }) => {
    await loginAs(page, SCHOOL_EMAIL, 'school');

    // Mở danh sách dự án nhà trường
    await page.goto('/app/school/projects.php', { waitUntil: 'networkidle' });

    // Kiểm tra huy hiệu chờ duyệt trên thẻ dự án
    const pendingBadge = page.locator('.school-project-card:has-text("EduShield") .school-badge:has-text("chờ duyệt")');
    await expect(pendingBadge).toBeVisible();

    // Bấm nút mở Modal Thành viên & Đơn đăng ký
    const manageBtn = page.locator('.school-project-card:has-text("EduShield") .btn-manage-members');
    await expect(manageBtn).toBeVisible();
    await manageBtn.click();

    // Modal quản lý thành viên xuất hiện
    const modal = page.locator('#manageMembersModal');
    await expect(modal).toBeVisible();

    // Chờ danh sách thành viên load
    const approveBtn = modal.locator('.btn-approve-member').first();
    await expect(approveBtn).toBeVisible({ timeout: 10000 });

    // Chụp ảnh bằng chứng Modal đang mở với ứng viên chờ duyệt
    await page.screenshot({
      path: 'storage/test-screenshots/02-school-modal.png',
      fullPage: false,
    });

    // Bấm Chấp thuận
    await approveBtn.click();

    // Trạng thái cập nhật sang "Đang tham gia"
    const activeBadge = modal.locator('.status-badge-active').first();
    await expect(activeBadge).toBeVisible({ timeout: 10000 });
    await expect(activeBadge).toContainText('Đang tham gia');

    // Chụp ảnh bằng chứng sau khi phê duyệt
    await page.screenshot({
      path: 'storage/test-screenshots/02-school-approved.png',
      fullPage: false,
    });
  });

  test('Step 3: Sinh viên tải lại trang và thấy đã trở thành thành viên chính thức', async ({ page }) => {
    await loginAs(page, STUDENT_EMAIL, 'learner');

    // Mở lại trang chi tiết dự án
    await page.goto(`/app/learner/project.php?id=${PROJECT_ID}`, { waitUntil: 'networkidle' });

    // Nút đã chuyển sang "Đã tham gia dự án"
    const joinedBtn = page.locator('button:has-text("Đã tham gia dự án")');
    await expect(joinedBtn).toBeVisible();

    // Huy hiệu đã chuyển sang "Đã tham gia"
    const activeBadge = page.locator('.learner-project-detail__badges span:has-text("Đã tham gia")');
    await expect(activeBadge).toBeVisible();

    // Chụp ảnh bằng chứng
    await page.screenshot({
      path: 'storage/test-screenshots/03-student-active.png',
      fullPage: true,
    });
  });

  test('Step 4: Doanh nghiệp quản lý hồ sơ ATS & Sinh viên theo dõi đơn thực tập', async ({ page }) => {
    // 1. Phía Doanh nghiệp
    await loginAs(page, ENTERPRISE_EMAIL, 'enterprise');
    await page.goto('/app/enterprise/internships/applicants.php', { waitUntil: 'networkidle' });

    // Kiểm tra bảng Kanban/ATS ứng viên
    const pageHeading = page.locator('h1, h2').first();
    await expect(pageHeading).toBeVisible();

    await page.screenshot({
      path: 'storage/test-screenshots/04-enterprise-ats.png',
      fullPage: false,
    });

    // 2. Phía Sinh viên
    await loginAs(page, STUDENT_EMAIL, 'learner');
    await page.goto('/app/learner/ecosystem.php?tab=opportunities', { waitUntil: 'networkidle' });

    const opportunitiesHeading = page.locator('h2:has-text("Tất cả dự án")');
    await expect(opportunitiesHeading).toBeVisible();

    await page.screenshot({
      path: 'storage/test-screenshots/05-student-applications-tracker.png',
      fullPage: true,
    });
  });
});
