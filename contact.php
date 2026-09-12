<?php

declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Contact\Exception\ConsultationValidationException;
use TalentHub\Modules\Contact\Repository\ConsultationRequestRepository;
use TalentHub\Modules\Contact\Service\ConsultationFormGuard;
use TalentHub\Modules\Contact\Service\ConsultationRateLimiter;
use TalentHub\Modules\Contact\Service\ConsultationRequestService;

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();
$formGuard = null;

/** @param mixed $value */
function contact_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string,mixed> $input @return array<string,string> */
function contact_old_input(array $input): array
{
    $old = [];
    foreach (['fullName', 'audience', 'email', 'phone', 'message', 'contactConsent'] as $field) {
        $old[$field] = is_scalar($input[$field] ?? null) ? (string) $input[$field] : '';
    }
    return $old;
}

/** @param array<string,mixed> $flash */
function contact_redirect_with_flash(array $flash): never
{
    $_SESSION['consultation_flash'] = $flash;
    header('Location: ' . app_href('/contact.php'), true, 303);
    exit;
}

$tokens = is_array($_SESSION['consultation_form_tokens'] ?? null) ? $_SESSION['consultation_form_tokens'] : [];
$minimumCreatedAt = time() - 7200;
$tokens = array_filter($tokens, static fn (mixed $createdAt): bool => is_int($createdAt) && $createdAt >= $minimumCreatedAt);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $input = is_array($_POST) ? $_POST : [];
    $formToken = is_string($input['formToken'] ?? null) ? $input['formToken'] : '';
    $csrfToken = is_string($input['_csrf'] ?? null) ? $input['_csrf'] : '';
    $old = contact_old_input($input);
    $baseFlash = ['old' => $old, 'formToken' => $formToken];

    if (!class_exists(ConsultationFormGuard::class)) {
        contact_redirect_with_flash($baseFlash + ['errors' => ['form' => 'Chức năng tư vấn đang được cập nhật. Vui lòng thử lại sau.']]);
    }

    $formGuard = new ConsultationFormGuard();

    try {
        $formGuard->assertCsrf($session->csrfToken(), $csrfToken);
    } catch (RuntimeException) {
        contact_redirect_with_flash($baseFlash + ['errors' => ['form' => 'Phiên bảo mật đã hết hạn. Vui lòng thử lại.']]);
    }
    try {
        $formGuard->assertFormToken($tokens, $formToken);
    } catch (RuntimeException) {
        contact_redirect_with_flash($baseFlash + ['errors' => ['form' => 'Biểu mẫu đã được gửi hoặc hết hạn. Vui lòng tải lại trang.']]);
    }

    if (!class_exists(ConsultationRequestService::class) || !class_exists(ConsultationRequestRepository::class) || !class_exists(ConsultationRateLimiter::class)) {
        contact_redirect_with_flash($baseFlash + ['errors' => ['form' => 'Chức năng tư vấn đang được cập nhật. Vui lòng thử lại sau.']]);
    }

    try {
        $pdo = (new Connection(require __DIR__ . '/config/database.php'))->connect();
        $service = new ConsultationRequestService(
            new ConsultationRequestRepository($pdo),
            new ConsultationRateLimiter($pdo),
        );
        $service->submit($input, $formToken, $_SERVER['REMOTE_ADDR'] ?? null);
        unset($tokens[$formToken]);
        $_SESSION['consultation_form_tokens'] = $tokens;
        contact_redirect_with_flash(['success' => 'Yêu cầu tư vấn của bạn đã được ghi nhận.']);
    } catch (ConsultationValidationException $exception) {
        contact_redirect_with_flash($baseFlash + ['errors' => $exception->errors]);
    } catch (ApiException $exception) {
        contact_redirect_with_flash($baseFlash + ['errors' => ['form' => $exception->getMessage()]]);
    } catch (Throwable) {
        contact_redirect_with_flash($baseFlash + ['errors' => ['form' => 'Chưa thể lưu yêu cầu lúc này. Vui lòng thử lại sau.']]);
    }
}

$flash = is_array($_SESSION['consultation_flash'] ?? null) ? $_SESSION['consultation_flash'] : [];
unset($_SESSION['consultation_flash']);
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$success = is_string($flash['success'] ?? null) ? $flash['success'] : '';
$formToken = is_string($flash['formToken'] ?? null) && isset($tokens[$flash['formToken']])
    ? $flash['formToken']
    : bin2hex(random_bytes(32));
$tokens[$formToken] = $tokens[$formToken] ?? time();
if (count($tokens) > 5) {
    asort($tokens);
    $tokens = array_slice($tokens, -5, null, true);
}
$_SESSION['consultation_form_tokens'] = $tokens;

$audiences = [
    'student' => 'Học sinh/Sinh viên',
    'teacher' => 'Giáo viên',
    'school' => 'Nhà trường',
    'enterprise' => 'Doanh nghiệp',
    'other' => 'Khác',
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gửi yêu cầu tư vấn đến TalentHub.">
    <title>Liên hệ tư vấn | TalentHub</title>
    <link rel="icon" href="<?= contact_escape(app_href('/assets/images/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= contact_escape(app_href('/assets/css/home.css')) ?>">
    <link rel="stylesheet" href="<?= contact_escape(app_href('/assets/css/contact.css')) ?>">
    <script src="<?= contact_escape(app_href('/assets/js/home.js')) ?>" defer></script>
</head>
<body class="landing-page contact-page">
<a class="contact-skip-link" href="#consultation-form">Bỏ qua điều hướng</a>
<header class="site-header" id="site-header">
    <div class="container site-header__container">
        <a href="<?= contact_escape(app_href('/index.php')) ?>" class="site-header__brand-link" aria-label="Trang chủ FTalentHub">
            <img src="<?= contact_escape(app_href('/assets/images/talenthub-brand-logo.png')) ?>" alt="FTalentHub" class="site-header__logo-img">
        </a>

        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="<?= contact_escape(app_href('/index.php#hero')) ?>" class="site-nav__link">Về TalentHub</a>
            <a href="<?= contact_escape(app_href('/index.php#statistics')) ?>" class="site-nav__link">Thống kê</a>
            <a href="<?= contact_escape(app_href('/index.php#modules')) ?>" class="site-nav__link">Tính năng (8 mô-đun)</a>
            <a href="<?= contact_escape(app_href('/index.php#audiences')) ?>" class="site-nav__link">Đối tượng</a>
        </nav>

        <div class="site-header__actions">
            <a href="<?= contact_escape(app_href('/login.php')) ?>" class="btn btn-secondary site-header__login-btn" data-cta="login">
                Đăng nhập
            </a>

            <a href="<?= contact_escape(app_href('/role-selection.php')) ?>" class="btn btn-primary site-header__app-btn">
                Đăng ký
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>

            <button class="site-header__mobile-toggle" id="mobile-toggle-btn" aria-label="Mở menu điều hướng" aria-controls="mobile-menu" aria-expanded="false">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12" class="hamburger-line line-top"></line>
                    <line x1="3" y1="6" x2="21" y2="6" class="hamburger-line line-mid"></line>
                    <line x1="3" y1="18" x2="21" y2="18" class="hamburger-line line-bot"></line>
                </svg>
            </button>
        </div>
    </div>

    <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
        <nav class="mobile-menu__nav" aria-label="Điều hướng di động">
            <a href="<?= contact_escape(app_href('/index.php#hero')) ?>" class="mobile-menu__link">Về TalentHub</a>
            <a href="<?= contact_escape(app_href('/index.php#statistics')) ?>" class="mobile-menu__link">Thống kê</a>
            <a href="<?= contact_escape(app_href('/index.php#modules')) ?>" class="mobile-menu__link">Tính năng (8 mô-đun)</a>
            <a href="<?= contact_escape(app_href('/index.php#audiences')) ?>" class="mobile-menu__link">Đối tượng</a>

            <div class="mobile-menu__actions">
                <a href="<?= contact_escape(app_href('/login.php')) ?>" class="btn btn-secondary mobile-menu__btn" data-cta="login">Đăng nhập</a>
                <a href="<?= contact_escape(app_href('/role-selection.php')) ?>" class="btn btn-primary mobile-menu__btn">
                    Đăng ký
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </nav>
    </div>
</header>

<main class="contact-main">
    <div class="container contact-container">
        <nav class="contact-breadcrumb" aria-label="Đường dẫn trang">
            <a href="<?= contact_escape(app_href('/index.php')) ?>">Trang chủ</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Liên hệ tư vấn</span>
        </nav>

        <div class="contact-layout">
            <section class="contact-intro" aria-labelledby="contact-title">
                <p class="contact-eyebrow">Liên hệ tư vấn</p>
                <h1 id="contact-title" class="contact-title">
                    <span>Cùng bạn</span>
                    <span>phát triển tài năng</span>
                </h1>
                <p class="contact-lead">Bạn cần tìm hiểu về TalentHub? Hãy để lại lời nhắn cho chúng tôi.</p>

                <div class="contact-support" aria-labelledby="support-title">
                    <h2 id="support-title" class="contact-support-title">Chúng tôi có thể hỗ trợ gì?</h2>
                    <div class="contact-points">
                        <article>
                            <span class="contact-point-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            </span>
                            <div><h3>Tìm hiểu nền tảng</h3><p>Cung cấp thông tin tổng quan, tính năng và cách hoạt động của TalentHub.</p></div>
                        </article>
                        <article>
                            <span class="contact-point-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><path d="M20 8v6"></path><path d="M23 11h-6"></path></svg>
                            </span>
                            <div><h3>Triển khai cho nhà trường</h3><p>Tư vấn giải pháp phù hợp với nhu cầu của nhà trường.</p></div>
                        </article>
                        <article>
                            <span class="contact-point-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m11 17 2 2a2.8 2.8 0 0 0 4-4l-3.1-3.1a2 2 0 0 0-2.8 0L10 13"></path><path d="m7 8 3-3a3 3 0 0 1 4.2 0L16 6.8"></path><path d="m16 6 1-1 5 5-6 6"></path><path d="m8 6-1-1-5 5 6 6 3-3"></path></svg>
                            </span>
                            <div><h3>Kết nối hợp tác doanh nghiệp</h3><p>Trao đổi về cơ hội hợp tác và đồng hành phát triển tài năng.</p></div>
                        </article>
                    </div>
                    <div class="contact-note">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5"></path><path d="M12 8h.01"></path></svg>
                        <span>Thông tin liên hệ chính thức sẽ được cập nhật.</span>
                    </div>
                </div>

                <div class="contact-hero-panel" aria-label="Tổng quan TalentHub">
                    <div class="hero-window-frame contact-hero-window">
                        <div class="window-bar">
                            <div class="window-dots">
                                <span class="dot red"></span>
                                <span class="dot yellow"></span>
                                <span class="dot green"></span>
                            </div>
                            <div class="window-address-bar">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                talenthub.vn/passport
                            </div>
                            <span class="window-tag">Live</span>
                        </div>
                        <div class="window-content contact-window-content">
                            <div class="contact-preview-header">
                                <div>
                                    <p class="contact-preview-label">Talent Passport 360°</p>
                                    <h3>Hồ sơ năng lực</h3>
                                </div>
                                <span class="contact-preview-pill">+84%</span>
                            </div>
                            <div class="contact-preview-stats">
                                <div>
                                    <strong>1.2K+</strong>
                                    <span>Hồ sơ đã được cập nhật</span>
                                </div>
                                <div>
                                    <strong>4.9/5</strong>
                                    <span>Đánh giá trải nghiệm</span>
                                </div>
                            </div>
                            <div class="contact-preview-bars" aria-hidden="true">
                                <span style="width: 88%"></span>
                                <span style="width: 74%"></span>
                                <span style="width: 92%"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="contact-card" aria-labelledby="form-title">
                <div class="contact-card__heading">
                    <h2 id="form-title">Gửi yêu cầu tư vấn</h2>
                    <p>Điền thông tin bên dưới để chúng tôi liên hệ với bạn.</p>
                </div>

                <?php if ($success !== ''): ?>
                    <div class="contact-alert contact-alert--success" role="status" tabindex="-1" data-contact-success><?= contact_escape($success) ?></div>
                <?php endif; ?>
                <?php if (isset($errors['form'])): ?>
                    <div class="contact-alert contact-alert--error" role="alert"><?= contact_escape($errors['form']) ?></div>
                <?php endif; ?>

                <form id="consultation-form" class="contact-form" method="post" action="<?= contact_escape(app_href('/contact.php')) ?>" novalidate>
                    <input type="hidden" name="_csrf" value="<?= contact_escape($session->csrfToken()) ?>">
                    <input type="hidden" name="formToken" value="<?= contact_escape($formToken) ?>">

                    <div class="form-field">
                        <label for="fullName">Họ và tên <span class="required-mark" aria-hidden="true">*</span></label>
                        <input id="fullName" name="fullName" type="text" maxlength="120" autocomplete="name" placeholder="Nhập họ và tên của bạn" required value="<?= contact_escape($old['fullName'] ?? '') ?>" aria-describedby="fullName-error" <?= isset($errors['fullName']) ? 'aria-invalid="true"' : '' ?>>
                        <p class="field-error" id="fullName-error"><?= contact_escape($errors['fullName'] ?? '') ?></p>
                    </div>

                    <div class="form-field">
                        <label for="audience">Bạn là <span class="required-mark" aria-hidden="true">*</span></label>
                        <select id="audience" name="audience" required aria-describedby="audience-help audience-error" <?= isset($errors['audience']) ? 'aria-invalid="true"' : '' ?>>
                            <option value="">Chọn đối tượng</option>
                            <?php foreach ($audiences as $value => $label): ?>
                                <option value="<?= contact_escape($value) ?>" <?= ($old['audience'] ?? '') === $value ? 'selected' : '' ?>><?= contact_escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-help" id="audience-help">Thông tin này chỉ phục vụ tư vấn.</p>
                        <p class="field-error" id="audience-error"><?= contact_escape($errors['audience'] ?? '') ?></p>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                            <input id="email" name="email" type="email" maxlength="254" autocomplete="email" placeholder="Nhập email của bạn" required value="<?= contact_escape($old['email'] ?? '') ?>" aria-describedby="email-error" <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                            <p class="field-error" id="email-error"><?= contact_escape($errors['email'] ?? '') ?></p>
                        </div>
                        <div class="form-field">
                            <label for="phone">Số điện thoại <span class="optional">(không bắt buộc)</span></label>
                            <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="Nhập số điện thoại (nếu có)" value="<?= contact_escape($old['phone'] ?? '') ?>" aria-describedby="phone-error" <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>>
                            <p class="field-error" id="phone-error"><?= contact_escape($errors['phone'] ?? '') ?></p>
                        </div>
                    </div>

                    <div class="form-field form-field--wide">
                        <label for="message">Nội dung cần tư vấn <span class="required-mark" aria-hidden="true">*</span></label>
                        <textarea id="message" name="message" rows="5" minlength="10" maxlength="3000" placeholder="Bạn muốn được hỗ trợ về vấn đề gì?" required aria-describedby="message-error" <?= isset($errors['message']) ? 'aria-invalid="true"' : '' ?>><?= contact_escape($old['message'] ?? '') ?></textarea>
                        <p class="field-error" id="message-error"><?= contact_escape($errors['message'] ?? '') ?></p>
                    </div>

                    <div class="form-field form-field--checkbox">
                        <div class="contact-consent">
                            <input id="contactConsent" type="checkbox" name="contactConsent" value="1" required <?= ($old['contactConsent'] ?? '') === '1' ? 'checked' : '' ?> <?= isset($errors['contactConsent']) ? 'aria-invalid="true" aria-describedby="contactConsent-error"' : '' ?>>
                            <label for="contactConsent">Tôi đồng ý để TalentHub liên hệ về yêu cầu này. <span class="required-mark" aria-hidden="true">*</span></label>
                        </div>
                        <?php if (isset($errors['contactConsent'])): ?>
                            <p class="field-error" id="contactConsent-error" role="alert"><?= contact_escape($errors['contactConsent']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button class="contact-submit" type="submit">Gửi yêu cầu tư vấn <span aria-hidden="true">→</span></button>
                </form>
            </section>
        </div>
    </div>
</main>

<footer class="footer contact-footer" id="contact">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?= contact_escape(app_href('/index.php#hero')) ?>" class="footer-brand__logo-link" aria-label="Trang chủ FTalentHub">
                    <img src="<?= contact_escape(app_href('/assets/images/talenthub-brand-logo.png')) ?>" alt="FTalentHub" class="footer-brand__logo-img">
                </a>
                <p>
                    Nền tảng phát triển và kết nối năng khiếu hàng đầu dành cho Học sinh, Giáo viên, Nhà trường và Doanh nghiệp.
                </p>
            </div>

            <div>
                <h4 class="footer-title">Khám phá</h4>
                <ul class="footer-links">
                    <li><a href="<?= contact_escape(app_href('/index.php#hero')) ?>">Về TalentHub</a></li>
                    <li><a href="<?= contact_escape(app_href('/index.php#statistics')) ?>">Thống kê nền tảng</a></li>
                    <li><a href="<?= contact_escape(app_href('/index.php#modules')) ?>">8 mô-đun hệ thống</a></li>
                    <li><a href="<?= contact_escape(app_href('/index.php#audiences')) ?>">Đối tượng người dùng</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Chính sách & hỗ trợ</h4>
                <ul class="footer-links">
                    <li><a href="#">Điều khoản sử dụng</a></li>
                    <li><a href="#">Chính sách bảo mật</a></li>
                    <li><a href="#">Hướng dẫn sử dụng</a></li>
                    <li><a href="#">Câu hỏi thường gặp</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Thông tin liên hệ</h4>
                <ul class="footer-links">
                    <li>Email: contact@talenthub.vn</li>
                    <li>Hotline: 1900 8899</li>
                    <li>Địa chỉ: Hà Nội & TP. Hồ Chí Minh</li>
                    <li>Thời gian: 8:00 - 17:30 (Thứ 2 - Thứ 6)</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> TalentHub. Tất cả quyền được bảo lưu.</p>
            <p>Thiết kế dành riêng cho hệ sinh thái giáo dục và phát triển tài năng.</p>
        </div>
    </div>
</footer>
</body>
</html>
