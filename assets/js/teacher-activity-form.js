document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-activity-form]');
    if (!form) return;

    form.classList.add('is-enhanced');

    // --- Delivery Mode Sync ---
    const deliveryMode = form.querySelector('[data-delivery-mode]');
    const deliveryChanged = form.querySelector('[data-delivery-changed]');
    const locationFields = form.querySelector('[data-location-fields]');
    const onlineFields = form.querySelector('[data-online-fields]');

    const clearFields = (container) => {
        if (!container) return;
        container.querySelectorAll('input, textarea, select').forEach((control) => {
            if (control instanceof HTMLSelectElement) control.selectedIndex = 0;
            else control.value = '';
        });
    };

    const syncDelivery = (clearIrrelevant = false) => {
        if (!(deliveryMode instanceof HTMLSelectElement)) return;
        const mode = deliveryMode.value;
        if (clearIrrelevant) {
            if (mode === 'in_person') clearFields(onlineFields);
            if (mode === 'online') clearFields(locationFields);
        }
        if (locationFields) locationFields.hidden = mode === 'online';
        if (onlineFields) onlineFields.hidden = mode === 'in_person';
    };

    if (deliveryMode) {
        syncDelivery(false);
        deliveryMode.addEventListener('change', () => {
            if (deliveryChanged instanceof HTMLInputElement) deliveryChanged.value = '1';
            syncDelivery(true);
        });
    }

    // --- Fee Mode Sync ---
    const feeModes = [...form.querySelectorAll('[data-fee-mode]')];
    const feeFields = form.querySelector('[data-fee-amount]');
    const feeAmount = form.querySelector('input[name="feeAmount"]');
    const selectedFeeMode = () => feeModes.find((control) => control.checked)?.value ?? 'free';
    const syncFee = (changed = false) => {
        const isPaid = selectedFeeMode() === 'paid';
        if (feeFields) feeFields.hidden = !isPaid;
        if (changed && feeAmount instanceof HTMLInputElement) {
            if (!isPaid) feeAmount.value = '0.00';
            else if (Number(feeAmount.value) <= 0) feeAmount.value = '';
        }
    };
    if (feeModes.length) {
        syncFee(false);
        feeModes.forEach((control) => control.addEventListener('change', () => syncFee(true)));
    }

    // --- Cover Image Uploader & Live Preview ---
    const fileInput = form.querySelector('[data-cover-file-input]');
    const previewImg = document.getElementById('cover-preview-img');
    const previewWrapper = document.getElementById('cover-preview-wrapper');
    const previewBadge = document.getElementById('cover-preview-badge');
    const coverUrlInput = document.getElementById('activity-cover-url');
    const coverAltInput = document.getElementById('activity-cover-alt');
    const titleInput = document.getElementById('activity-title');
    const btnRemoveCover = document.getElementById('btn-remove-cover');
    const fallbackSrc = previewImg?.getAttribute('data-fallback-src') || '';

    if (fileInput instanceof HTMLInputElement) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (!file) return;

            // Validate max 5MB
            if (file.size > 5 * 1024 * 1024) {
                alert('Dung lượng ảnh vượt quá 5MB. Vui lòng chọn ảnh có kích thước nhỏ hơn.');
                fileInput.value = '';
                return;
            }

            // Validate MIME
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Định dạng ảnh không hợp lệ. Vui lòng chọn định dạng JPG, PNG hoặc WebP.');
                fileInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                if (previewImg && e.target?.result) {
                    previewImg.src = String(e.target.result);
                }
                if (previewWrapper) {
                    previewWrapper.classList.add('teacher-cover-preview--has-image');
                }
                if (previewBadge) {
                    previewBadge.textContent = 'Ảnh tải lên từ máy (Chưa lưu)';
                }
                if (btnRemoveCover) {
                    btnRemoveCover.style.display = '';
                }
                // Clear preset URL since user uploaded a custom file
                if (coverUrlInput) {
                    coverUrlInput.value = '';
                }
                // Auto-fill alt text if empty
                if (coverAltInput && !coverAltInput.value.trim() && titleInput) {
                    const titleVal = titleInput.value.trim();
                    coverAltInput.value = 'Ảnh bìa hoạt động ' + (titleVal || 'ngoại khóa');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // --- Remove Cover Button ---
    if (btnRemoveCover) {
        btnRemoveCover.addEventListener('click', () => {
            if (fileInput) fileInput.value = '';
            if (coverUrlInput) coverUrlInput.value = '';
            if (previewImg && fallbackSrc) previewImg.src = fallbackSrc;
            if (previewWrapper) previewWrapper.classList.remove('teacher-cover-preview--has-image');
            if (previewBadge) previewBadge.textContent = 'Ảnh mặc định hệ thống';
            btnRemoveCover.style.display = 'none';
        });
    }

    // --- Preset Gallery Modal ---
    const modal = document.getElementById('teacher-preset-modal');
    const btnOpenModal = document.getElementById('btn-open-preset-modal');
    const closeButtons = modal?.querySelectorAll('[data-close-modal]');
    const presetTabs = modal?.querySelectorAll('[data-preset-tab]');
    const presetCards = modal?.querySelectorAll('.teacher-preset-card');

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    const openModal = () => {
        if (!modal) return;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    if (btnOpenModal) {
        btnOpenModal.addEventListener('click', openModal);
    }

    closeButtons?.forEach((btn) => {
        btn.addEventListener('click', closeModal);
    });

    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });

    // Preset Category Tabs
    presetTabs?.forEach((tab) => {
        tab.addEventListener('click', () => {
            presetTabs.forEach((t) => {
                t.classList.remove('teacher-preset-tab--active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('teacher-preset-tab--active');
            tab.setAttribute('aria-selected', 'true');

            const selectedCat = tab.getAttribute('data-preset-tab');
            presetCards?.forEach((card) => {
                const cardCat = card.getAttribute('data-preset-category');
                if (selectedCat === 'all' || cardCat === selectedCat) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Preset Card Selection
    presetCards?.forEach((card) => {
        card.addEventListener('click', () => {
            const presetUrl = card.getAttribute('data-preset-url') || '';
            const presetWebUrl = card.getAttribute('data-preset-web-url') || presetUrl;
            const presetAlt = card.getAttribute('data-preset-alt') || '';

            if (coverUrlInput) coverUrlInput.value = presetUrl;
            if (previewImg) previewImg.src = presetWebUrl;
            if (previewWrapper) previewWrapper.classList.add('teacher-cover-preview--has-image');
            if (previewBadge) previewBadge.textContent = 'Ảnh mẫu từ thư viện';
            if (btnRemoveCover) btnRemoveCover.style.display = '';
            if (fileInput) fileInput.value = ''; // Reset file input

            if (coverAltInput && (!coverAltInput.value.trim() || coverAltInput.value.startsWith('Ảnh bìa hoạt động'))) {
                coverAltInput.value = presetAlt;
            }

            closeModal();
        });
    });

    // --- Smart Date Helpers ---
    const startAtInput = form.querySelector('input[name="startAt"]');
    const endAtInput = form.querySelector('input[name="endAt"]');
    const regOpenInput = form.querySelector('input[name="registrationOpensAt"]');
    const regCloseInput = form.querySelector('input[name="registrationClosesAt"]');
    const cancelCloseInput = form.querySelector('input[name="cancellationClosesAt"]');
    const hoursInput = form.querySelector('input[name="confirmedHours"]');

    const formatIsoLocal = (date) => {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    if (startAtInput) {
        startAtInput.addEventListener('change', () => {
            const startVal = startAtInput.value;
            if (!startVal) return;
            const startDate = new Date(startVal);
            if (isNaN(startDate.getTime())) return;

            // Auto-adjust end date (+3 hours) if empty or earlier than start
            if (endAtInput && (!endAtInput.value || new Date(endAtInput.value) <= startDate)) {
                const endDate = new Date(startDate.getTime() + 3 * 3600 * 1000);
                endAtInput.value = formatIsoLocal(endDate);
            }

            // Auto-adjust registration closes (-2 hours before start) if empty or after start
            if (regCloseInput && (!regCloseInput.value || new Date(regCloseInput.value) >= startDate)) {
                const regCloseDate = new Date(startDate.getTime() - 2 * 3600 * 1000);
                regCloseInput.value = formatIsoLocal(regCloseDate);
            }

            // Auto-adjust cancellation closes (-24 hours before start) if empty or after start
            if (cancelCloseInput && (!cancelCloseInput.value || new Date(cancelCloseInput.value) > startDate)) {
                const cancelDate = new Date(startDate.getTime() - 24 * 3600 * 1000);
                cancelCloseInput.value = formatIsoLocal(cancelDate);
            }

            // Auto-adjust registration opens (now) if empty
            if (regOpenInput && !regOpenInput.value) {
                regOpenInput.value = formatIsoLocal(new Date());
            }

            // Auto-calculate confirmed hours if empty or 0
            if (hoursInput && endAtInput && endAtInput.value && (!hoursInput.value || Number(hoursInput.value) === 0)) {
                const endDate = new Date(endAtInput.value);
                if (!isNaN(endDate.getTime()) && endDate > startDate) {
                    const diffHours = Math.min(24, Math.round(((endDate.getTime() - startDate.getTime()) / 3600000) * 100) / 100);
                    if (diffHours > 0) hoursInput.value = diffHours.toFixed(2);
                }
            }
        });
    }

    if (endAtInput) {
        endAtInput.addEventListener('change', () => {
            if (hoursInput && startAtInput && startAtInput.value && endAtInput.value) {
                const startDate = new Date(startAtInput.value);
                const endDate = new Date(endAtInput.value);
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime()) && endDate > startDate) {
                    if (!hoursInput.value || Number(hoursInput.value) === 0) {
                        const diffHours = Math.min(24, Math.round(((endDate.getTime() - startDate.getTime()) / 3600000) * 100) / 100);
                        if (diffHours > 0) hoursInput.value = diffHours.toFixed(2);
                    }
                }
            }
        });
    }

    // --- Accessible Invalid Form Element Focus ---
    form.addEventListener('invalid', (event) => {
        const control = event.target;
        if (!(control instanceof HTMLElement)) return;
        window.setTimeout(() => {
            control.scrollIntoView({ behavior: 'smooth', block: 'center' });
            control.focus();
        }, 0);
    }, true);
});

