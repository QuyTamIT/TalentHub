(function () {
    'use strict';

    var qrContainer = document.querySelector('[data-qr-token]');
    if (qrContainer && typeof window.QRCode === 'function') {
        try {
            new window.QRCode(qrContainer, {
                text: qrContainer.getAttribute('data-qr-token') || '',
                width: 256,
                height: 256,
                colorDark: '#102a43',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.M
            });
        } catch (error) {
            qrContainer.textContent = 'Không thể dựng mã QR trên trình duyệt này.';
        }
    }

    // UI-only double-click guard; server-side idempotency is intentionally out of Phase 1.
    var postForms = document.querySelectorAll('form[method="post"]');
    Array.prototype.forEach.call(postForms, function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }

            var submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton || submitButton.disabled) {
                return;
            }

            submitButton.disabled = true;
            form.setAttribute('aria-busy', 'true');

            var actionInput = form.querySelector('input[name="form_action"]');
            if (actionInput && actionInput.value === 'create_session') {
                submitButton.textContent = 'Đang tạo phiên QR...';
            } else {
                submitButton.textContent = 'Đang thu hồi...';
            }
        });
    });

    var copyButton = document.querySelector('[data-copy-qr-token]');
    if (!copyButton) {
        return;
    }

    var status = document.querySelector('[data-copy-status]');
    var token = copyButton.getAttribute('data-token') || '';

    function showStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function fallbackCopy() {
        var tokenElement = document.getElementById('teacher-qr-token-value');
        if (!tokenElement) {
            return false;
        }

        var selection = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(tokenElement);
        selection.removeAllRanges();
        selection.addRange(range);
        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }
        selection.removeAllRanges();
        return copied;
    }

    copyButton.addEventListener('click', function () {
        if (!token) {
            showStatus('Token không khả dụng.');
            return;
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(token).then(function () {
                showStatus('Đã sao chép token.');
            }).catch(function () {
                showStatus(fallbackCopy() ? 'Đã sao chép token.' : 'Không thể sao chép tự động.');
            });
            return;
        }

        showStatus(fallbackCopy() ? 'Đã sao chép token.' : 'Không thể sao chép tự động.');
    });
}());
