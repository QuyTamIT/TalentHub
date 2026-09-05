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
$formGuard = new ConsultationFormGuard();

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
<body class="contact-page">
<a class="contact-skip-link" href="#consultation-form">Bỏ qua điều hướng</a>
<header class="site-header" id="site-header">
    <div class="container site-header__container">
        <a href="<?= contact_escape(app_href('/index.php')) ?>" class="site-header__brand" aria-label="TalentHub">
            <div class="site-header__brand-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <div class="site-header__brand-text">Talent<span>Hub</span></div>
        </a>
        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="<?= contact_escape(app_href('/index.php')) ?>" class="site-nav__link">Trang chủ</a>
            <a href="<?= contact_escape(app_href('/index.php#hero')) ?>" class="site-nav__link">Giới thiệu</a>
            <a href="<?= contact_escape(app_href('/contact.php')) ?>" class="site-nav__link site-nav__link--active" aria-current="page">Liên hệ</a>
        </nav>
        <div class="site-header__actions">
            <a href="<?= contact_escape(app_href('/login.php')) ?>" class="btn btn-secondary site-header__login-btn">Đăng nhập</a>
            <button class="site-header__mobile-toggle" id="mobile-toggle-btn" aria-label="Mở menu điều hướng" aria-controls="mobile-menu" aria-expanded="false">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" class="hamburger-line line-top"></line><line x1="3" y1="6" x2="21" y2="6" class="hamburger-line line-mid"></line><line x1="3" y1="18" x2="21" y2="18" class="hamburger-line line-bot"></line></svg>
            </button>
        </div>
    </div>
    <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
        <nav class="mobile-menu__nav" aria-label="Điều hướng di động">
            <a href="<?= contact_escape(app_href('/index.php')) ?>" class="mobile-menu__link">Trang chủ</a>
            <a href="<?= contact_escape(app_href('/index.php#hero')) ?>" class="mobile-menu__link">Giới thiệu</a>
            <a href="<?= contact_escape(app_href('/contact.php')) ?>" class="mobile-menu__link mobile-menu__link--active" aria-current="page">Liên hệ</a>
            <div class="mobile-menu__actions">
                <a href="<?= contact_escape(app_href('/login.php')) ?>" class="btn btn-secondary mobile-menu__btn">Đăng nhập</a>
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

<footer class="footer contact-footer">
    <div class="container contact-footer__inner">
        <div class="footer-brand contact-footer__brand">
                <a href="<?= contact_escape(app_href('/index.php')) ?>" class="brand-logo" aria-label="TalentHub">
                    <div class="brand-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </div>
                    <div class="brand-text">Talent<span>Hub</span></div>
                </a>
                <p>Nền tảng phát triển và kết nối năng khiếu.</p>
        </div>
        <div class="contact-footer__meta">
            <div class="contact-footer__policies" aria-label="Chính sách">
                <span>Chính sách bảo mật</span>
                <span aria-hidden="true">|</span>
                <span>Điều khoản sử dụng</span>
            </div>
            <p>&copy; <?= date('Y') ?> TalentHub. Tất cả quyền được bảo lưu.</p>
        </div>
    </div>
</footer>
</body>
</html>
