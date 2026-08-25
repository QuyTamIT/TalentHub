/**
 * TalentHub - Enterprise Talent Passport Detail Controller
 * Handles detail page interactions: bookmark save state toggle,
 * contact request modal open/close/submit via API, and toast feedback.
 */

document.addEventListener('DOMContentLoaded', () => {
    initTalentDetailModule();
});

function initTalentDetailModule() {
    const saveBtn = document.getElementById('detail-save-btn');
    const contactBtn = document.getElementById('detail-contact-btn');
    
    const contactModal = document.getElementById('contact-modal');
    const closeContactBtn = document.getElementById('close-contact-modal-btn');
    const contactBackdrop = document.getElementById('contact-modal-backdrop');
    const cancelContactBtn = document.getElementById('cancel-contact-btn');
    const submitContactBtn = document.getElementById('submit-contact-btn');
    const messageInput = document.getElementById('contact-message-input');

    // Read session configuration
    let sessionBoot = {
        csrfToken: '',
        studentId: '',
        apiBase: '/api/v1/businesses/me',
        contactAllowed: false,
        hasPendingContactRequest: false,
    };
    const bootEl = document.getElementById('enterprise-talent-detail-boot');
    if (bootEl) {
        try {
            sessionBoot = Object.assign(sessionBoot, JSON.parse(bootEl.textContent));
        } catch (e) {
            console.error('Failed to parse talent detail boot data:', e);
        }
    }

    // If candidate has pending contact request, update UI
    if (sessionBoot.hasPendingContactRequest && contactBtn) {
        contactBtn.classList.remove('btn-primary');
        contactBtn.classList.add('btn-secondary');
        contactBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span>Đã gửi yêu cầu</span>
        `;
    }

    // 1. Bookmark / Save Profile Toggle Handler
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            const isSaved = saveBtn.classList.contains('is-saved');
            const btnText = saveBtn.querySelector('.btn-text');
            const svgIcon = saveBtn.querySelector('svg');

            if (isSaved) {
                saveBtn.classList.remove('is-saved');
                if (btnText) btnText.textContent = 'Lưu hồ sơ';
                if (svgIcon) svgIcon.setAttribute('fill', 'none');
                showToast('Đã bỏ lưu hồ sơ nhân tài.');
            } else {
                saveBtn.classList.add('is-saved');
                if (btnText) btnText.textContent = 'Đã lưu hồ sơ';
                if (svgIcon) svgIcon.setAttribute('fill', 'currentColor');
                showToast('Đã lưu hồ sơ vào danh sách quan tâm.');
            }
        });
    }

    // 2. Contact Request Modal Handlers
    function openContactModal() {
        if (!contactModal) return;
        contactModal.style.display = 'block';
        contactModal.setAttribute('aria-hidden', 'false');
        if (messageInput) messageInput.focus();
    }

    function closeContactModal() {
        if (!contactModal) return;
        contactModal.style.display = 'none';
        contactModal.setAttribute('aria-hidden', 'true');
        if (messageInput) messageInput.value = '';
    }

    if (contactBtn) {
        contactBtn.addEventListener('click', openContactModal);
    }
    if (closeContactBtn) {
        closeContactBtn.addEventListener('click', closeContactModal);
    }
    if (contactBackdrop) {
        contactBackdrop.addEventListener('click', closeContactModal);
    }
    if (cancelContactBtn) {
        cancelContactBtn.addEventListener('click', closeContactModal);
    }

    if (submitContactBtn) {
        submitContactBtn.addEventListener('click', async () => {
            const candidateName = submitContactBtn.getAttribute('data-talent-name') || 'ứng viên';
            const message = messageInput ? messageInput.value.trim() : '';
            const studentId = sessionBoot.studentId || new URLSearchParams(window.location.search).get('id');

            if (!studentId) {
                showToast('Không xác định được mã ứng viên.', 'error');
                return;
            }

            // Generate UUID v4 for idempotency
            const idempotencyKey = generateUuidV4();
            submitContactBtn.disabled = true;
            submitContactBtn.textContent = 'Đang gửi...';

            try {
                const endpoint = `${sessionBoot.apiBase}/talents/${encodeURIComponent(studentId)}/contact-requests`;
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': sessionBoot.csrfToken,
                    },
                    body: JSON.stringify({
                        idempotencyKey: idempotencyKey,
                        message: message,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok || (data.status && data.status !== 'success' && !data.data)) {
                    const errorMsg = data.error?.message || data.message || 'Không thể gửi yêu cầu kết nối.';
                    showToast(errorMsg, 'error');
                    submitContactBtn.disabled = false;
                    submitContactBtn.textContent = 'Gửi yêu cầu';
                    return;
                }

                closeContactModal();
                showToast(`Đã gửi yêu cầu kết nối tới ${candidateName}. Hệ thống đã gửi thông báo đến ứng viên.`);

                if (contactBtn) {
                    contactBtn.classList.remove('btn-primary');
                    contactBtn.classList.add('btn-secondary');
                    contactBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Đã gửi yêu cầu</span>
                    `;
                }
            } catch (err) {
                console.error('Contact request error:', err);
                showToast('Lỗi mạng hoặc kết nối máy chủ. Vui lòng thử lại.', 'error');
                submitContactBtn.disabled = false;
                submitContactBtn.textContent = 'Gửi yêu cầu';
            }
        });
    }

    function showToast(msg) {
        if (typeof window.showEntToast === 'function') {
            window.showEntToast(msg);
        } else if (typeof showEntToast === 'function') {
            showEntToast(msg);
        } else {
            alert(msg);
        }
    }

    function generateUuidV4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
}
