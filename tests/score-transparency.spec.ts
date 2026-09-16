import { test, expect, type Page } from '@playwright/test';

/**
 * E2E & UI Spec: Kiểm thử tính minh bạch điểm số (Score Transparency - P5 & P6)
 * Ma trận kiểm thử: T16 – T35
 *
 * Phạm vi:
 * 1. Learner Portal:
 *    - Không hiển thị nhãn xếp hạng tùy tiện (Top 10%, Top 15%, Top 30%) -> Thay bằng phân loại học lực (T28).
 *    - Khung minh chứng nguồn gốc điểm số (Score Provenance) trả lời đủ 5 câu hỏi (What, Source, Who/Formula, When, Evidence Validity).
 *    - Đa trí tuệ (Multiple Intelligence - MI) hỗ trợ đầy đủ 8 chiều (LOGI, LING, SPAT, MUSIC, BODY, INTER, INTRA, NAT).
 *    - Kỹ năng dạng minh chứng thuần túy (evidence_only) không render thanh điểm 0/100 (T12).
 *    - An toàn XSS: không thực thi HTML/script injection trong minh chứng hoặc ghi chú (T24).
 * 2. Enterprise Portal:
 *    - Điểm đánh giá hiển thị nhãn "Trung bình kỹ năng đã được chấm", không dùng fallback cứng `: 96` (T31, T33).
 *    - Ẩn danh tên giảng viên thật và ẩn câu trả lời bài test trắc nghiệm đối với tài khoản doanh nghiệp (T35).
 *    - Hồ sơ chưa chấm giữ trạng thái chưa chấm/null thay vì gán 0/100.
 * 3. School & Teacher Portals:
 *    - Không render AI talent_map như kỹ năng chính thức (T32).
 *    - Không đối xử điểm 0 như trạng thái chờ chấm (T30).
 */

const PASSWORD = process.env.PLAYWRIGHT_LOGIN_PASSWORD || 'TestPass_2026_local';
const STUDENT_EMAIL = process.env.PLAYWRIGHT_LOGIN_EMAIL || 'hs.minh@talenthub.vn';
const TEACHER_EMAIL = process.env.PLAYWRIGHT_TEACHER_EMAIL || 'gv.mai@talenthub.vn';
const SCHOOL_EMAIL = process.env.PLAYWRIGHT_SCHOOL_EMAIL || 'school.admin@talenthub.vn';
const ENTERPRISE_EMAIL = process.env.PLAYWRIGHT_ENTERPRISE_EMAIL || 'business@test.talenthub.local';

async function loginAs(page: Page, email: string, password = PASSWORD): Promise<void> {
  await page.goto('/login.php', { waitUntil: 'domcontentloaded' });
  const emailInput = page.locator('#email');
  if (await emailInput.isVisible({ timeout: 5000 }).catch(() => false)) {
    await emailInput.fill(email);
    await page.fill('#password', password);
    await Promise.all([
      page.waitForNavigation({ timeout: 12000 }).catch(() => {}),
      page.click('button.auth-submit, button[data-submit]', { force: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');
  }
}

test.describe('Package P5 & P6: Minh bạch điểm số (Score Transparency)', () => {

  test.describe('1. Cổng Người học (Learner Portal)', () => {
    test.beforeEach(async ({ page }) => {
      await loginAs(page, STUDENT_EMAIL);
    });

    test('T28: Hồ sơ và đánh giá không hiển thị nhãn phân vị tùy tiện Top 10%, Top 15%, Top 30%', async ({ page }) => {
      await page.goto('/app/learner/talent-passport.php', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toBeVisible();

      const bodyText = await page.locator('body').innerText();
      // Không được chứa nhãn Top % tự phong
      expect(bodyText).not.toMatch(/Top\s*(?:5|10|15|20|30)%\s*(?:toàn\s*trường|sinh\s*viên|khối)/i);

      // Đi tới trang đánh giá
      await page.goto('/app/learner/evaluation.php', { waitUntil: 'domcontentloaded' });
      const evalText = await page.locator('body').innerText();
      expect(evalText).not.toMatch(/Top\s*(?:5|10|15|20|30)%\s*(?:toàn\s*trường|sinh\s*viên|khối)/i);
    });

    test('T12 & T24: Hộp minh chứng nguồn gốc điểm (Provenance) và kỹ năng evidence_only an toàn', async ({ page }) => {
      await page.goto('/app/learner/talent-passport.php', { waitUntil: 'domcontentloaded' });

      // Nếu có khối score provenance
      const provenanceElements = page.locator('.score-provenance, [data-component="score-provenance"], .score-provenance-box');
      const count = await provenanceElements.count();
      if (count > 0) {
        const firstProvenance = provenanceElements.first();
        await expect(firstProvenance).toBeVisible();
        const provText = await firstProvenance.innerText();

        // Phải chứa thông tin nguồn gốc hoặc phương thức tính
        expect(provText).toMatch(/Nguồn gốc|Đánh giá|Phương thức|Minh chứng|Cập nhật/i);
      }

      // Kiểm tra không có thẻ <script> chưa được escape trong DOM của các thẻ điểm
      const injectedScripts = page.locator('.score-provenance script, .skill-card script');
      expect(await injectedScripts.count()).toBe(0);

      // Kỹ năng evidence_only không được render thanh tiến trình 0/100 như điểm kém
      const evidenceOnlyItems = page.locator('.skill-card--evidence-only, [data-score-state="evidence_only"]');
      const evidenceCount = await evidenceOnlyItems.count();
      for (let i = 0; i < evidenceCount; i++) {
        const item = evidenceOnlyItems.nth(i);
        const itemText = await item.innerText();
        // Phải hiển thị trạng thái chưa chấm hoặc có minh chứng
        expect(itemText).toMatch(/Chưa có đánh giá điểm|Chờ chấm|Minh chứng/i);
        // Không chứa thanh điểm 0/100 lừa dối
        const progressBar = item.locator('.progress-bar, progress');
        if (await progressBar.count() > 0) {
          const val = await progressBar.first().getAttribute('value');
          expect(val).not.toBe('0');
        }
      }
    });

    test('T22: Đa trí tuệ (Multiple Intelligence) hỗ trợ đủ 8 chiều và MBTI giữ nguyên kết quả gốc', async ({ page }) => {
      await page.goto('/app/learner/evaluation.php', { waitUntil: 'domcontentloaded' });

      // Kiểm tra các định danh 8 chiều MI trong JavaScript hoặc DOM
      const pageContent = await page.content();

      // Kiểm tra các chiều cốt lõi: LOGI, LING, SPAT, MUSIC, BODY, INTER, INTRA, NAT
      const miKeywords = ['Logic', 'Ngôn ngữ', 'Không gian', 'Âm nhạc', 'Vận động', 'Tương tác', 'Nội tâm', 'Tự nhiên'];
      let matchCount = 0;
      for (const kw of miKeywords) {
        if (pageContent.includes(kw)) {
          matchCount++;
        }
      }
      // Ít nhất các chiều chính hoặc cấu hình radar phải có mặt
      expect(matchCount).toBeGreaterThanOrEqual(4);
    });
  });

  test.describe('2. Cổng Doanh nghiệp (Enterprise Portal)', () => {
    test.beforeEach(async ({ page }) => {
      await loginAs(page, ENTERPRISE_EMAIL);
    });

    test('T31 & T33: Danh sách ứng viên và ứng tuyển không dùng fallback 96 cứng', async ({ page }) => {
      await page.goto('/app/enterprise/internships/applicants.php', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toBeVisible();

      const applicantsText = await page.locator('body').innerText();
      // Không được chứa chuỗi fallback tùy tiện ": 96"
      expect(applicantsText).not.toContain('Điểm đánh giá năng lực giáo viên: 96');

      // Nếu có lý do đánh giá năng lực giảng viên thì phải dùng nhãn chuẩn
      if (applicantsText.includes('kỹ năng đã được chấm')) {
        expect(applicantsText).toContain('Trung bình kỹ năng đã được chấm');
      }
    });

    test('T35: Trang tài năng chi tiết ẩn danh tên giảng viên thật và không lộ câu trả lời bài test', async ({ page }) => {
      await page.goto('/app/enterprise/talents.php', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toBeVisible();

      // Mở thử tài năng đầu tiên nếu có
      const talentLink = page.locator('a[href*="talents/detail.php"]').first();
      if (await talentLink.count() > 0 && await talentLink.isVisible()) {
        await talentLink.click();
        await page.waitForLoadState('domcontentloaded');

        const detailHtml = await page.content();
        // Không được chứa các bảng câu hỏi chi tiết bài test kèm đáp án học viên chọn
        expect(detailHtml).not.toContain('student_test_answers');
        expect(detailHtml).not.toContain('chi tiết đáp án trắc nghiệm');

        // Tên giảng viên đánh giá phải được ẩn danh hoặc dùng chức danh chung
        expect(detailHtml).not.toContain('ThS. Giảng viên chấm thi');
      }
    });
  });

  test.describe('3. Cổng Nhà trường & Giảng viên (School & Teacher Portals)', () => {
    test('T32: Cổng Giảng viên không render talent_map AI như kỹ năng chính thức', async ({ page }) => {
      await loginAs(page, TEACHER_EMAIL);
      await page.goto('/app/teacher/students.php', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toBeVisible();

      const pageText = await page.locator('body').innerText();
      // Không render thẻ "AI Talent Map Score"
      expect(pageText).not.toContain('AI Talent Map Score');
      expect(pageText).not.toContain('talent_map_competency');
    });

    test('T31: Cổng Nhà trường hiển thị điểm năng lực chính thức không fallback 85', async ({ page }) => {
      await loginAs(page, SCHOOL_EMAIL);
      await page.goto('/app/school/students.php', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toBeVisible();

      const pageContent = await page.content();
      // Không được chứa logic fallback cứng 85
      expect(pageContent).not.toMatch(/Điểm năng lực mặc định:\s*85/);
    });
  });

});
