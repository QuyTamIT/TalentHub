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

if ($token !== '') {
    try {
        $pdo = isset($GLOBALS['__TALENTHUB_TEST_PDO__']) && $GLOBALS['__TALENTHUB_TEST_PDO__'] instanceof PDO
            ? $GLOBALS['__TALENTHUB_TEST_PDO__']
            : (new Connection(require dirname(__DIR__, 2) . '/config/database.php'))->connect();
        $sharingService = new ProfileSharingService($pdo);
        $resolved = $sharingService->resolveShare($token);
    } catch (\Throwable) {
        $resolved = null;
    }
}

function shared_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $resolved ? shared_escape($resolved['student']['fullName'] ?? 'Hồ sơ học viên') : 'Hồ sơ không khả dụng' ?> | TalentHub</title>
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app shared-profile-page">
  <div class="learner-layout" style="grid-template-columns: 1fr;">
    <main class="learner-main" style="max-width: 900px; margin: 2rem auto; padding: 0 1rem; width: 100%;">
      <?php if (!$resolved): ?>
        <section class="learner-card learner-not-found" style="text-align: center; padding: 3rem 1rem;">
          <h1>Không tìm thấy hồ sơ</h1>
          <p>Liên kết chia sẻ không tồn tại, đã hết hạn hoặc đã bị chủ sở hữu thu hồi.</p>
          <a class="learner-btn learner-btn--primary" href="/" style="display: inline-block; margin-top: 1rem;">Về trang chủ</a>
        </section>
      <?php else: ?>
        <?php $student = $resolved['student']; ?>
        <section class="learner-card learner-profile-header-card" style="margin-bottom: 1.5rem;">
          <div style="display: flex; gap: 1.5rem; align-items: center;">
            <div class="learner-avatar" style="width: 80px; height: 80px; font-size: 2rem; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center;">
              <?php $avatarUrl = shared_safe_image_url($student['avatarUrl'] ?? null); ?>
              <?php if ($avatarUrl !== null): ?>
                <img src="<?= shared_escape($avatarUrl) ?>" alt="<?= shared_escape($student['fullName']) ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
              <?php else: ?>
                <?= mb_substr(shared_escape($student['fullName'] ?? 'S'), 0, 1) ?>
              <?php endif; ?>
            </div>
            <div>
              <h1 style="margin: 0; font-size: 1.75rem;"><?= shared_escape($student['fullName'] ?? '') ?></h1>
              <?php if (!empty($student['headline'])): ?>
                <p style="margin: 0.25rem 0 0.5rem; color: #4b5563; font-weight: 500;"><?= shared_escape($student['headline']) ?></p>
              <?php endif; ?>
              <div class="learner-meta-list" style="display: flex; gap: 1rem; flex-wrap: wrap; color: #6b7280; font-size: 0.875rem;">
                <?php if (!empty($student['school'])): ?>
                  <span><?= learner_icon('school', 16) ?> <?= shared_escape($student['school']) ?></span>
                <?php endif; ?>
                <?php if (!empty($student['class'])): ?>
                  <span><?= learner_icon('users', 16) ?> <?= shared_escape($student['class']) ?></span>
                <?php endif; ?>
                <?php if (!empty($student['location'])): ?>
                  <span><?= learner_icon('map-pin', 16) ?> <?= shared_escape($student['location']) ?></span>
                <?php endif; ?>
                <?php if (!empty($student['email'])): ?>
                  <span><?= learner_icon('mail', 16) ?> <?= shared_escape($student['email']) ?></span>
                <?php endif; ?>
                <?php if (!empty($student['phone'])): ?>
                  <span><?= learner_icon('phone', 16) ?> <?= shared_escape($student['phone']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if (!empty($student['bio'])): ?>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
              <p style="margin: 0; color: #374151;"><?= nl2br(shared_escape($student['bio'])) ?></p>
            </div>
          <?php endif; ?>
        </section>

        <?php if (isset($resolved['skills'])): ?>
          <section class="learner-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Kỹ năng</h2>
            <?php if (empty($resolved['skills'])): ?>
              <p class="learner-empty-text">Chưa có dữ liệu kỹ năng.</p>
            <?php else: ?>
              <div class="learner-skill-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
                <?php foreach ($resolved['skills'] as $skill): ?>
                  <div class="learner-card learner-card--subtle" style="padding: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; font-weight: 500;">
                      <span><?= shared_escape($skill['name'] ?? '') ?></span>
                      <span><?= shared_escape($skill['score'] ?? 0) ?>%</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if (isset($resolved['experience'])): ?>
          <section class="learner-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Trải nghiệm đã xác nhận</h2>
            <p style="margin: 0 0 1rem; color: #4b5563;">Tổng giờ: <?= shared_escape($resolved['experience']['confirmed_hours'] ?? 0) ?></p>
            <?php $experienceEntries = $resolved['experience']['confirmed_entries'] ?? []; ?>
            <?php if (empty($experienceEntries)): ?>
              <p class="learner-empty-text">Chưa có trải nghiệm được chia sẻ.</p>
            <?php else: ?>
              <div style="display: grid; gap: 0.75rem;">
                <?php foreach ($experienceEntries as $entry): ?>
                  <div class="learner-card learner-card--subtle" style="padding: 1rem;">
                    <h3 style="margin: 0 0 0.25rem; font-size: 1rem;"><?= shared_escape($entry['activity_title'] ?? $entry['activityTitle'] ?? '') ?></h3>
                    <p style="margin: 0; font-size: 0.875rem; color: #6b7280;"><?= shared_escape($entry['hours'] ?? 0) ?> giờ · <?= shared_escape($entry['activity_category'] ?? $entry['activityCategory'] ?? '') ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if (isset($resolved['certificates'])): ?>
          <section class="learner-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Chứng chỉ & Chứng nhận</h2>
            <?php if (empty($resolved['certificates'])): ?>
              <p class="learner-empty-text">Chưa có chứng chỉ nào được chia sẻ.</p>
            <?php else: ?>
              <div style="display: grid; gap: 0.75rem;">
                <?php foreach ($resolved['certificates'] as $cert): ?>
                  <div class="learner-card learner-card--subtle" style="padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                      <h3 style="margin: 0 0 0.25rem; font-size: 1rem;"><?= shared_escape($cert['title'] ?? '') ?></h3>
                      <p style="margin: 0; font-size: 0.875rem; color: #6b7280;"><?= shared_escape($cert['issuingOrganization'] ?? $cert['issuer'] ?? '') ?> · Ngày cấp: <?= shared_escape($cert['issueDate'] ?? '') ?></p>
                    </div>
                    <?php $credentialUrl = shared_safe_https_url($cert['credentialUrl'] ?? null); ?>
                    <?php if ($credentialUrl !== null): ?>
                      <a href="<?= shared_escape($credentialUrl) ?>" target="_blank" rel="noopener noreferrer" class="learner-btn learner-btn--outline" style="font-size: 0.875rem; padding: 0.35rem 0.75rem;">Xem</a>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if (isset($resolved['projects'])): ?>
          <section class="learner-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Dự án tiêu biểu</h2>
            <?php if (empty($resolved['projects'])): ?>
              <p class="learner-empty-text">Chưa có dự án nào được chia sẻ.</p>
            <?php else: ?>
              <div style="display: grid; gap: 0.75rem;">
                <?php foreach ($resolved['projects'] as $project): ?>
                  <div class="learner-card learner-card--subtle" style="padding: 1rem;">
                    <h3 style="margin: 0 0 0.25rem; font-size: 1rem;"><?= shared_escape($project['title'] ?? '') ?></h3>
                    <?php if (!empty($project['description'])): ?>
                      <p style="margin: 0.25rem 0; font-size: 0.875rem; color: #4b5563;"><?= shared_escape($project['description']) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
