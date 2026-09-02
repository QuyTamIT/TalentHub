<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once __DIR__ . '/data/bootstrap.php';
require_once __DIR__ . '/includes/icons.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Service\ProfileSharingService;

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");

$token = trim((string) ($_GET['token'] ?? ''));
$resolved = null;

try {
    $pdo = isset($GLOBALS['__TALENTHUB_TEST_PDO__']) && $GLOBALS['__TALENTHUB_TEST_PDO__'] instanceof PDO
        ? $GLOBALS['__TALENTHUB_TEST_PDO__']
        : (new Connection(require dirname(__DIR__, 2) . '/config/database.php'))->connect();

    learner_configure_data(['source' => 'database', 'pdo' => $pdo]);

    if ($token !== '') {
        $sharingService = new ProfileSharingService($pdo);
        $resolved = $sharingService->resolveShare($token);
    }
} catch (\Throwable) {
    $resolved = null;
}

http_response_code($resolved === null ? 404 : 200);

if (!function_exists('shared_escape')) {
    function shared_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('shared_safe_https_url')) {
    function shared_safe_https_url(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = parse_url($value, PHP_URL_HOST);
        $user = parse_url($value, PHP_URL_USER);
        $password = parse_url($value, PHP_URL_PASS);
        if ($scheme !== 'https' || !is_string($host) || $host === '' || $user !== null || $password !== null) {
            return null;
        }
        return $value;
    }
}

if (!function_exists('shared_safe_image_url')) {
    function shared_safe_image_url(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '\\')) {
            return $value;
        }
        return shared_safe_https_url($value);
    }
}

if (!function_exists('shared_hours_label')) {
    /** Format số giờ: bỏ số 0 lẻ thừa (2.50 -> 2.5, 3.00 -> 3). */
    function shared_hours_label(mixed $hours): string
    {
        $value = round((float) $hours, 2);
        $formatted = number_format($value, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $resolved ? shared_escape($resolved['student']['fullName'] ?? 'Hồ sơ học viên') : 'Hồ sơ không khả dụng' ?> | Xác thực Năng lực TalentHub</title>
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/learner.css">
  <style>
    .shared-profile-container {
      max-width: 860px;
      margin: 2rem auto;
      padding: 0 1rem 3rem;
    }
    .verified-top-banner {
      background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 60%, #1D4ED8 100%);
      color: #FFFFFF;
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.75rem;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.1);
    }
    .verified-seal-tag {
      background: #10B981;
      color: #FFFFFF;
      font-size: 0.75rem;
      font-weight: 800;
      padding: 0.25rem 0.65rem;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .shared-header-card {
      background: #FFFFFF;
      border-radius: 14px;
      border: 1px solid #CBD5E1;
      padding: 1.75rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .shared-avatar {
      width: 85px;
      height: 85px;
      border-radius: 16px;
      background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
      color: #FFFFFF;
      font-size: 2rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
      border: 3px solid #EFF6FF;
      box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
    }
    .shared-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .shared-meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem 1.25rem;
      color: #475569;
      font-size: 0.8125rem;
      margin-top: 0.5rem;
    }
    .shared-section-card {
      background: #FFFFFF;
      border-radius: 12px;
      border: 1px solid #CBD5E1;
      padding: 1.35rem 1.5rem;
      margin-bottom: 1.25rem;
    }
    .shared-section-heading {
      font-size: 1.05rem;
      font-weight: 800;
      color: #0F172A;
      margin: 0 0 1rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border-bottom: 1px solid #F1F5F9;
      padding-bottom: 0.5rem;
    }
    .shared-empty {
      margin: 0;
      padding: 0.85rem 1rem;
      background: #F8FAFC;
      border: 1px dashed #CBD5E1;
      border-radius: 8px;
      color: #64748B;
      font-size: 0.85rem;
    }
  </style>
</head>
<body class="learner-app shared-profile-page" style="background: #F8FAFC; min-height: 100vh;">
  <div class="shared-profile-container">

    <?php if (!$resolved): ?>
      <section class="learner-card learner-not-found" style="text-align: center; padding: 3.5rem 1.5rem; background: #FFFFFF; border-radius: 14px; border: 1px solid #CBD5E1; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔍</div>
        <h1 style="font-size: 1.4rem; font-weight: 800; color: #0F172A; margin: 0 0 0.5rem 0;">Không tìm thấy hồ sơ học viên</h1>
        <p style="color: #64748B; font-size: 0.9rem; max-width: 480px; margin: 0 auto 1.5rem;">Mã xác thực hoặc liên kết chia sẻ không tồn tại trong hệ thống TalentHub hoặc đã hết hạn.</p>
        <a class="learner-btn learner-btn--primary" href="/" style="display: inline-flex; align-items: center; gap: 0.4rem; background: #2563EB; color: #FFF; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; text-decoration: none;">
          <?= learner_icon('arrow-left', 16) ?> Về trang chủ TalentHub
        </a>
      </section>
    <?php else: ?>
      <?php $student = $resolved['student']; ?>

      <!-- Verified Top Banner -->
      <div class="verified-top-banner">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
          <strong style="font-size: 0.95rem; letter-spacing: -0.01em;">XÁC THỰC HỒ SƠ NĂNG LỰC SỐ 360°</strong>
        </div>
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <?php if (!empty($resolved['passportCode'])): ?>
            <code style="background: rgba(255,255,255,0.18); color: #FFF; font-family: monospace; font-size: 0.825rem; padding: 0.2rem 0.55rem; border-radius: 4px;"><?= shared_escape($resolved['passportCode']) ?></code>
          <?php endif; ?>
          <span class="verified-seal-tag">
            VERIFIED
          </span>
        </div>
      </div>

      <!-- Student Header Profile Card -->
      <section class="shared-header-card">
        <div style="display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap;">
          <div class="shared-avatar">
            <?php $avatarUrl = shared_safe_image_url($student['avatarUrl'] ?? null); ?>
            <?php if ($avatarUrl !== null): ?>
              <img src="<?= shared_escape($avatarUrl) ?>" alt="<?= shared_escape($student['fullName'] ?? 'Student') ?>">
            <?php else: ?>
              <?= mb_substr(shared_escape($student['fullName'] ?? 'HV'), 0, 2) ?>
            <?php endif; ?>
          </div>
          <div style="flex: 1; min-width: 260px;">
            <h1 style="margin: 0; font-size: 1.45rem; font-weight: 800; color: #0F172A;"><?= shared_escape($student['fullName'] ?? 'Học viên') ?></h1>
            <?php if (!empty($student['headline'])): ?>
              <p style="margin: 0.2rem 0 0.4rem; color: #2563EB; font-weight: 600; font-size: 0.875rem;"><?= shared_escape($student['headline']) ?></p>
            <?php endif; ?>
            <div class="shared-meta-row">
              <?php if (!empty($student['school'])): ?>
                <span><?= learner_icon('school', 15) ?> <?= shared_escape($student['school']) ?></span>
              <?php endif; ?>
              <?php if (!empty($student['class'])): ?>
                <span><?= learner_icon('users', 15) ?> <?= shared_escape($student['class']) ?></span>
              <?php endif; ?>
              <?php if (!empty($student['location'])): ?>
                <span><?= learner_icon('map-pin', 15) ?> <?= shared_escape($student['location']) ?></span>
              <?php endif; ?>
              <?php if (!empty($student['email'])): ?>
                <span><?= learner_icon('mail', 15) ?> <?= shared_escape($student['email']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if (!empty($student['bio'])): ?>
          <div style="margin-top: 1rem; padding-top: 0.85rem; border-top: 1px solid #F1F5F9;">
            <p style="margin: 0; color: #334155; font-size: 0.85rem; line-height: 1.5;"><?= nl2br(shared_escape($student['bio'])) ?></p>
          </div>
        <?php endif; ?>
      </section>

      <!-- Section: Skills -->
      <?php if (isset($resolved['skills'])): ?>
        <section class="shared-section-card">
          <h2 class="shared-section-heading">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            Kỹ năng đã Thẩm định &amp; Xác thực
          </h2>
          <?php if (empty($resolved['skills'])): ?>
            <p class="shared-empty">Chưa có dữ liệu kỹ năng được xác thực.</p>
          <?php else: ?>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.75rem;">
            <?php foreach ($resolved['skills'] as $skill): ?>
              <?php $skillScore = max(0, min(100, (int) ($skill['score'] ?? $skill['levelScore'] ?? $skill['level_score'] ?? 0))); ?>
              <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.75rem 0.95rem; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.825rem; color: #0F172A; margin-bottom: 0.35rem;">
                  <span><?= shared_escape($skill['name'] ?? '') ?></span>
                  <span style="color: #2563EB;"><?= $skillScore ?>/100</span>
                </div>
                <div style="height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                  <div style="width: <?= $skillScore ?>%; height: 100%; background: #2563EB;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <!-- Section: Experience -->
      <?php if (isset($resolved['experience'])): ?>
        <section class="shared-section-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
            <h2 class="shared-section-heading" style="margin: 0; border: none; padding: 0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
              Trải nghiệm Thực tế Đã Tích lũy
            </h2>
            <span style="font-size: 0.8125rem; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 6px;">
              Tổng cộng: <?= shared_escape(shared_hours_label($resolved['experience']['confirmed_hours'] ?? 0)) ?> giờ
            </span>
          </div>
          <?php $experienceEntries = $resolved['experience']['confirmed_entries'] ?? []; ?>
          <?php if (empty($experienceEntries)): ?>
            <p class="shared-empty">Chưa có trải nghiệm thực tế được ghi nhận.</p>
          <?php else: ?>
          <div style="display: grid; gap: 0.6rem;">
            <?php foreach ($experienceEntries as $entry): ?>
              <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.75rem 1rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <strong style="font-size: 0.85rem; color: #0F172A;"><?= shared_escape($entry['activityTitle'] ?? $entry['activity_title'] ?? '') ?></strong>
                  <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;"><?= shared_escape($entry['activityCategory'] ?? $entry['activity_category'] ?? 'Chuyên môn') ?></div>
                </div>
                <span style="font-size: 0.8rem; font-weight: 700; color: #2563EB;"><?= shared_escape(shared_hours_label($entry['hours'] ?? 0)) ?> giờ</span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <!-- Section: Projects -->
      <?php if (isset($resolved['projects'])): ?>
        <section class="shared-section-card">
          <h2 class="shared-section-heading">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            Đề án Đổi mới Sáng tạo &amp; Bảo trợ Doanh nghiệp
          </h2>
          <?php if (empty($resolved['projects'])): ?>
            <p class="shared-empty">Chưa có đề án nào được ghi nhận.</p>
          <?php else: ?>
          <div style="display: grid; gap: 0.75rem;">
            <?php foreach ($resolved['projects'] as $project): ?>
              <div style="background: #F8FAFC; border: 1px solid #CBD5E1; padding: 0.9rem 1rem; border-radius: 8px;">
                <strong style="font-size: 0.875rem; color: #0F172A; display: block;"><?= shared_escape($project['title'] ?? $project['name'] ?? '') ?></strong>
                <?php $projectMeta = array_filter([trim((string) ($project['role'] ?? '')), trim((string) ($project['category'] ?? ''))]); ?>
                <?php if ($projectMeta !== []): ?>
                  <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;"><?= shared_escape(implode(' • ', $projectMeta)) ?></div>
                <?php endif; ?>
                <?php if (!empty($project['description'])): ?>
                  <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #334155; line-height: 1.45;"><?= shared_escape($project['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($project['sponsorName'])): ?>
                  <span style="display: inline-flex; align-items: center; gap: 4px; margin-top: 0.45rem; font-size: 0.725rem; font-weight: 700; color: #1E40AF; background: #DBEAFE; padding: 2px 8px; border-radius: 999px;">
                    Bảo trợ bởi: <?= shared_escape($project['sponsorName']) ?>
                  </span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <!-- Section: Certificates -->
      <?php if (isset($resolved['certificates'])): ?>
        <section class="shared-section-card">
          <h2 class="shared-section-heading">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
            Chứng chỉ &amp; Văn bằng Đã Xác thực
          </h2>
          <?php if (empty($resolved['certificates'])): ?>
            <p class="shared-empty">Chưa có chứng chỉ nào được xác minh.</p>
          <?php else: ?>
          <div style="display: grid; gap: 0.6rem;">
            <?php foreach ($resolved['certificates'] as $cert): ?>
              <?php $credentialUrl = shared_safe_https_url($cert['credentialUrl'] ?? null); ?>
              <div style="background: #F8FAFC; border: 1px solid #CBD5E1; padding: 0.75rem 1rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                  <strong style="font-size: 0.85rem; color: #0F172A; display: block;"><?= shared_escape($cert['title'] ?? $cert['name'] ?? '') ?></strong>
                  <?php $certMeta = array_filter([trim((string) ($cert['issuingOrganization'] ?? $cert['issuer'] ?? '')), trim((string) ($cert['issueDate'] ?? $cert['year'] ?? '')) !== '' ? 'Năm ' . trim((string) ($cert['issueDate'] ?? $cert['year'])) : '']); ?>
                  <?php if ($certMeta !== []): ?>
                    <span style="font-size: 0.75rem; color: #64748B;"><?= shared_escape(implode(' · ', $certMeta)) ?></span>
                  <?php endif; ?>
                </div>
                <span style="font-size: 0.725rem; font-weight: 700; color: #15803D; background: #DCFCE7; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                  Đã xác minh
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <!-- Section: Teacher Evaluation -->
      <?php if (isset($resolved['evaluations'])): ?>
        <section class="shared-section-card" style="border-left: 4px solid #2563EB;">
          <h2 class="shared-section-heading" style="color: #1E3A8A;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            Chứng thực từ Giảng viên Hướng dẫn
          </h2>
          <?php if (trim((string) ($resolved['evaluations']['comment'] ?? '')) === ''): ?>
            <p class="shared-empty">Chưa có nhận xét từ giảng viên.</p>
          <?php else: ?>
            <p style="font-size: 0.85rem; color: #334155; font-style: italic; line-height: 1.5; margin: 0 0 0.5rem 0;">
              "<?= shared_escape($resolved['evaluations']['comment'] ?? '') ?>"
            </p>
            <div style="font-size: 0.8rem; font-weight: 700; color: #0F172A;">
              <?= shared_escape($resolved['evaluations']['reviewer'] ?? 'Giảng viên hướng dẫn') ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <!-- Footer Info -->
      <footer style="text-align: center; color: #94A3B8; font-size: 0.75rem; margin-top: 2rem;">
        Hệ thống Xác thực Năng lực Số Toàn diện • TalentHub Ecosystem 2026
      </footer>

    <?php endif; ?>

  </div>
</body>
</html>
