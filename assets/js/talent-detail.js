/**
 * TalentHub - Enterprise Talent Passport Detail Controller
 * Handles detail page interactions: bookmark save state toggle,
 * contact request modal open/close/submit mock, and toast feedback.
 * 
 * Note for Developers:
 * - When database/API is ready, replace mock submit with fetch() requests to
 *   `contact_requests` and `privacy_consents` tables.
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
                (window.showEntToast || showEntToast)('Đã bỏ lưu hồ sơ nhân tài.');
            } else {
                saveBtn.classList.add('is-saved');
                if (btnText) btnText.textContent = 'Đã lưu hồ sơ';
                if (svgIcon) svgIcon.setAttribute('fill', 'currentColor');
                (window.showEntToast || showEntToast)('Đã lưu hồ sơ vào danh sách quan tâm.');
            }
        });
    }

    // 2. Contact Request Modal Handlers
    function openContactModal() {
        if (!contactModal) return;
        contactModal.style.display = 'block';
        contactModal.setAttribute('aria-hidden', 'false');
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
        submitContactBtn.addEventListener('click', () => {
            const candidateName = submitContactBtn.getAttribute('data-talent-name') || 'người học';
            closeContactModal();
            (window.showEntToast || showEntToast)(`Đã gửi yêu cầu kết nối tới ${candidateName}. Thông báo sẽ được chuyển tới người học để nhận chấp thuận.`);
        });
    }
}
