/**
 * TalentHub Enterprise - Company Profile Management Script
 * Handles profile viewing, live completion updates, modal drawer, form validation, logo uploading/preview, and AJAX updates.
 */
document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('ent-edit-profile-modal');
    const openModalBtn = document.getElementById('btn-open-edit-profile');
    const closeModalBtn = document.getElementById('btn-close-edit-modal');
    const cancelModalBtn = document.getElementById('btn-cancel-edit-modal');
    const profileForm = document.getElementById('ent-profile-edit-form');
    const submitBtn = document.getElementById('btn-save-profile');
    const feedbackEl = document.getElementById('modal-feedback');
    const descTextarea = document.getElementById('field-description');
    const descCountEl = document.getElementById('desc-char-count');
    const toastEl = document.getElementById('ent-toast');

    // Logo elements
    const logoFileInput = document.getElementById('field-logo-file');
    const logoUrlHidden = document.getElementById('field-logoUrl');
    const logoUrlInput = document.getElementById('field-logoUrl-input');
    const logoRemoveBtn = document.getElementById('btn-remove-logo');
    const logoPreviewBox = document.getElementById('logo-preview-box');

    let pendingLogoDataUrl = null;
    let isLogoRemoved = false;
    let isSubmitting = false;

    // Resolve URL helper for assets/logos
    function resolveLogoUrl(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        if (trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('data:')) {
            return trimmed;
        }
        const hasTalentHubPrefix = window.location.pathname.includes('/TalentHub');
        if (hasTalentHubPrefix && !trimmed.startsWith('/TalentHub') && !trimmed.startsWith('TalentHub/')) {
            return '/TalentHub/' + trimmed.replace(/^\/+/, '');
        }
        return trimmed.startsWith('/') ? trimmed : '/' + trimmed;
    }

    // Toast helper
    function showToast(message, isSuccess = true) {
        if (!toastEl) return;
        const msgEl = toastEl.querySelector('.ent-toast__message');
        if (msgEl) msgEl.textContent = message;
        toastEl.className = 'ent-toast is-visible ' + (isSuccess ? 'ent-toast--success' : 'ent-toast--error');
        setTimeout(() => {
            toastEl.classList.remove('is-visible');
        }, 4000);
    }

    // Modal controls
    function openModal() {
        if (!editModal) return;
        editModal.classList.add('is-open');
        editModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        clearFeedback();
        isLogoRemoved = false;
        pendingLogoDataUrl = null;
        if (logoFileInput) logoFileInput.value = '';

        const firstInput = editModal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) setTimeout(() => firstInput.focus(), 100);
    }

    function closeModal(force = false) {
        if (!editModal || (!force && isSubmitting)) return;
        editModal.classList.remove('is-open');
        editModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        pendingLogoDataUrl = null;
        isLogoRemoved = false;
    }

    if (openModalBtn) openModalBtn.addEventListener('click', openModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', () => closeModal());
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', () => closeModal());

    // Close on backdrop click
    if (editModal) {
        const backdrop = editModal.querySelector('.ent-modal__backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', () => closeModal());
        }
        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && editModal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    // Logo preview update helper
    function setModalLogoPreview(srcUrl, initialsText) {
        if (!logoPreviewBox) return;
        if (srcUrl) {
            logoPreviewBox.innerHTML = `
                <img id="logo-preview-img" src="${encodeURI(srcUrl)}" alt="Logo Preview" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:4px;border-radius:10px;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                <span id="logo-preview-fallback" class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;color:#ffffff;">${initialsText || 'DN'}</span>
            `;
        } else {
            logoPreviewBox.innerHTML = `
                <span id="logo-preview-fallback" class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;color:#ffffff;">${initialsText || 'DN'}</span>
            `;
        }
    }

    // Handle logo file input change
    if (logoFileInput) {
        logoFileInput.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;

            // Validate file size (max 3MB)
            if (file.size > 3 * 1024 * 1024) {
                showFeedback('Dung lượng tệp logo vượt quá 3MB. Vui lòng chọn tệp nhỏ hơn.');
                logoFileInput.value = '';
                return;
            }

            // Validate MIME type
            const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
            if (!allowedTypes.includes(file.type)) {
                showFeedback('Định dạng tệp không được hỗ trợ. Vui lòng chọn ảnh PNG, JPG, WebP hoặc SVG.');
                logoFileInput.value = '';
                return;
            }

            clearFeedback();
            const reader = new FileReader();
            reader.onload = (event) => {
                const dataUrl = event.target?.result;
                if (typeof dataUrl === 'string') {
                    pendingLogoDataUrl = dataUrl;
                    isLogoRemoved = false;
                    const companyName = document.getElementById('field-name')?.value || '';
                    setModalLogoPreview(dataUrl, getInitials(companyName));
                    if (logoUrlInput) logoUrlInput.value = '';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // Handle manual Logo URL input
    if (logoUrlInput) {
        logoUrlInput.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            if (logoUrlHidden) logoUrlHidden.value = val;
            if (val) {
                pendingLogoDataUrl = null;
                isLogoRemoved = false;
                if (logoFileInput) logoFileInput.value = '';
                const companyName = document.getElementById('field-name')?.value || '';
                setModalLogoPreview(resolveLogoUrl(val), getInitials(companyName));
            }
        });
    }

    // Handle Remove Logo button
    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', () => {
            pendingLogoDataUrl = null;
            isLogoRemoved = true;
            if (logoFileInput) logoFileInput.value = '';
            if (logoUrlHidden) logoUrlHidden.value = '';
            if (logoUrlInput) logoUrlInput.value = '';
            const companyName = document.getElementById('field-name')?.value || '';
            setModalLogoPreview(null, getInitials(companyName));
        });
    }

    // Live character counter for description
    function updateDescCount() {
        if (!descTextarea || !descCountEl) return;
        const len = descTextarea.value.length;
        descCountEl.textContent = `${len} / 4000 ký tự`;
        if (len > 3800) {
            descCountEl.style.color = '#EA580C';
        } else {
            descCountEl.style.color = '#94A3B8';
        }
    }

    if (descTextarea) {
        descTextarea.addEventListener('input', updateDescCount);
        updateDescCount();
    }

    // Feedback banner in modal
    function showFeedback(message, type = 'error') {
        if (!feedbackEl) return;
        feedbackEl.className = 'ent-form-feedback ent-form-feedback--' + type;
        feedbackEl.textContent = message;
        feedbackEl.style.display = 'block';
        feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearFeedback() {
        if (!feedbackEl) return;
        feedbackEl.style.display = 'none';
        feedbackEl.textContent = '';
    }

    // Calculate initials from company name
    function getInitials(name) {
        if (!name) return 'DN';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    // Update DOM on successful save
    function updateDOMProfile(data) {
        if (!data) return;

        // Update company name in hero and page
        document.querySelectorAll('[data-bind="enterprise-name"]').forEach(el => {
            el.textContent = data.name || 'Chưa cập nhật';
        });

        // Update initials and avatar
        const initials = getInitials(data.name);
        const resolvedLogo = data.logoUrl ? resolveLogoUrl(data.logoUrl) : null;

        // Hero Avatar
        document.querySelectorAll('[data-bind="enterprise-initials"]').forEach(el => {
            if (resolvedLogo) {
                el.innerHTML = `
                    <img src="${encodeURI(resolvedLogo)}" alt="Logo ${encodeURI(data.name || '')}" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:4px;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                    <span class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">${initials}</span>
                `;
            } else {
                el.innerHTML = `<span class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;">${initials}</span>`;
            }
        });

        // Header & Dropdown identity
        document.querySelectorAll('.ent-header__company-name, .ent-account-menu__company-name').forEach(el => {
            el.textContent = data.name;
        });
        document.querySelectorAll('.ent-header__avatar, .ent-account-menu__avatar').forEach(el => {
            if (resolvedLogo) {
                el.innerHTML = `
                    <img src="${encodeURI(resolvedLogo)}" alt="Logo" style="width:100%;height:100%;object-fit:contain;background:#ffffff;padding:2px;border-radius:inherit;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                    <span class="ent-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">${initials}</span>
                `;
            } else {
                el.innerHTML = `<span class="ent-avatar-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;">${initials}</span>`;
            }
        });

        // Update modal preview
        setModalLogoPreview(resolvedLogo, initials);
        if (logoUrlHidden) logoUrlHidden.value = data.logoUrl || '';
        if (logoUrlInput) logoUrlInput.value = data.logoUrl || '';

        // Update Overview Fields
        const setVal = (attr, val, fallback = 'Chưa cập nhật') => {
            document.querySelectorAll(`[data-bind="${attr}"]`).forEach(el => {
                el.textContent = val || fallback;
            });
        };

        setVal('industry', data.industry);
        setVal('companySize', data.companySize);
        setVal('foundedYear', data.foundedYear ? String(data.foundedYear) : null);
        setVal('taxCode', data.taxCode);
        setVal('phone', data.phone);
        setVal('address', data.address);
        setVal('email', data.email);

        // Update website link
        document.querySelectorAll('[data-bind="website"]').forEach(el => {
            if (data.website) {
                const url = data.website.startsWith('http') ? data.website : `https://${data.website}`;
                el.innerHTML = `<a href="${encodeURI(url)}" target="_blank" rel="noopener noreferrer">${encodeURI(data.website)} <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>`;
            } else {
                el.textContent = 'Chưa cập nhật';
            }
        });

        // Update description
        document.querySelectorAll('[data-bind="description"]').forEach(el => {
            el.textContent = data.description || 'Doanh nghiệp chưa bổ sung mô tả giới thiệu.';
        });

        // Update completion percentage and bar
        if (typeof data.profileCompletion === 'number') {
            document.querySelectorAll('[data-bind="completion-percent"]').forEach(el => {
                el.textContent = `${data.profileCompletion}%`;
            });
            document.querySelectorAll('[data-bind="completion-bar"]').forEach(el => {
                el.style.width = `${data.profileCompletion}%`;
            });
        }
    }

    // Submit handler
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isSubmitting) return;

            clearFeedback();

            const name = (document.getElementById('field-name')?.value || '').trim();
            const industry = (document.getElementById('field-industry')?.value || '').trim();
            const companySize = (document.getElementById('field-companySize')?.value || '').trim();
            const foundedYearVal = (document.getElementById('field-foundedYear')?.value || '').trim();
            const taxCode = (document.getElementById('field-taxCode')?.value || '').trim();
            const email = (document.getElementById('field-email')?.value || '').trim();
            const phone = (document.getElementById('field-phone')?.value || '').trim();
            const website = (document.getElementById('field-website')?.value || '').trim();
            const address = (document.getElementById('field-address')?.value || '').trim();
            const description = (document.getElementById('field-description')?.value || '').trim();
            const csrfToken = document.getElementById('field-csrf')?.value || '';

            // Client-side validation
            if (name.length < 2 || name.length > 255) {
                showFeedback('Tên doanh nghiệp phải có từ 2 đến 255 ký tự.');
                return;
            }

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFeedback('Email doanh nghiệp không đúng định dạng.');
                return;
            }

            if (foundedYearVal) {
                const y = parseInt(foundedYearVal, 10);
                const currYear = new Date().getFullYear();
                if (isNaN(y) || y < 1800 || y > currYear + 1) {
                    showFeedback(`Năm thành lập phải là số từ 1800 đến ${currYear}.`);
                    return;
                }
            }

            // Determine logoUrl
            let finalLogoUrl = logoUrlHidden ? logoUrlHidden.value.trim() : null;
            if (isLogoRemoved) {
                finalLogoUrl = null;
            }

            // Loading state
            isSubmitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<span class="ent-spinner" aria-hidden="true"></span> Đang lưu...`;
            }

            try {
                const apiBase = (window.location.pathname.includes('/TalentHub') ? '/TalentHub' : '') + '/api/v1/businesses/me';

                // If user uploaded a new logo file, send it first
                if (pendingLogoDataUrl) {
                    const logoResp = await fetch(apiBase + '/logo', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ dataUrl: pendingLogoDataUrl })
                    });

                    const logoRes = await logoResp.json();
                    if (!logoResp.ok) {
                        const err = logoRes.error?.message || logoRes.message || 'Không thể tải lên logo.';
                        showFeedback(err);
                        return;
                    }

                    finalLogoUrl = logoRes.data?.logoUrl || logoRes.logoUrl;
                }

                const payload = {
                    name,
                    industry: industry || null,
                    companySize: companySize || null,
                    foundedYear: foundedYearVal ? parseInt(foundedYearVal, 10) : null,
                    taxCode: taxCode || null,
                    email: email || null,
                    phone: phone || null,
                    website: website || null,
                    address: address || null,
                    logoUrl: finalLogoUrl || null,
                    description: description || null
                };

                const response = await fetch(apiBase, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (!response.ok) {
                    const errorMsg = result.error?.message || result.message || 'Không thể lưu hồ sơ. Vui lòng kiểm tra lại dữ liệu.';
                    showFeedback(errorMsg);
                    return;
                }

                const updatedData = result.data || result;
                updateDOMProfile(updatedData);

                showToast('Cập nhật hồ sơ doanh nghiệp thành công!', true);
                closeModal(true);
            } catch (err) {
                console.error('Save error:', err);
                showFeedback('Lỗi kết nối máy chủ. Vui lòng thử lại sau.');
            } finally {
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `Lưu thay đổi`;
                }
            }
        });
    }
});
