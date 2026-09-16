(function initCredentialManagement(global) {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-credential-management]');
        if (!root) return;

        const tabs = Array.from(root.querySelectorAll('[data-credential-tab]'));
        const panels = Array.from(root.querySelectorAll('[data-credential-panel]'));

        const selectTab = (name, focus = false) => {
            tabs.forEach((tab) => {
                const active = tab.dataset.credentialTab === name;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                if (active && focus) tab.focus();
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.credentialPanel !== name;
            });
            try { global.sessionStorage.setItem('talenthub.credential.tab', name); } catch {}
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => selectTab(tab.dataset.credentialTab || 'award'));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                const direction = event.key === 'ArrowRight' ? 1 : -1;
                const next = (index + direction + tabs.length) % tabs.length;
                selectTab(tabs[next].dataset.credentialTab || 'award', true);
            });
        });

        let savedTab = '';
        try { savedTab = global.sessionStorage.getItem('talenthub.credential.tab') || ''; } catch {}
        if (tabs.some((tab) => tab.dataset.credentialTab === savedTab)) selectTab(savedTab);

        const awardForm = root.querySelector('[data-credential-award-form]');
        if (awardForm) {
            const kind = awardForm.querySelector('[data-credential-kind]');
            const credentialFields = Array.from(awardForm.querySelectorAll('[data-credential-select]'));
            const recipientInputs = Array.from(awardForm.querySelectorAll('input[name="recipientType"]'));
            const recipientFields = Array.from(awardForm.querySelectorAll('[data-recipient-field]'));
            const targetCount = awardForm.querySelector('[data-credential-target-count]');

            const syncCredential = () => {
                credentialFields.forEach((field) => {
                    const active = field.dataset.credentialSelect === kind?.value;
                    field.hidden = !active;
                    const select = field.querySelector('select');
                    if (select) select.disabled = !active;
                });
            };

            const syncRecipient = () => {
                const selected = recipientInputs.find((input) => input.checked)?.value || 'student';
                recipientFields.forEach((field) => {
                    const active = field.dataset.recipientField === selected;
                    field.hidden = !active;
                    const select = field.querySelector('select');
                    if (select) select.disabled = !active;
                });
                const classSelect = awardForm.querySelector('select[name="classId"]');
                const option = classSelect?.selectedOptions?.[0];
                const count = selected === 'class' ? Number.parseInt(option?.dataset?.studentCount || '0', 10) : 1;
                if (targetCount) targetCount.textContent = `${count} sinh viên được chọn`;
            };

            kind?.addEventListener('change', syncCredential);
            recipientInputs.forEach((input) => input.addEventListener('change', syncRecipient));
            awardForm.querySelector('select[name="classId"]')?.addEventListener('change', syncRecipient);
            awardForm.addEventListener('submit', (event) => {
                const type = recipientInputs.find((input) => input.checked)?.value;
                const classOption = awardForm.querySelector('select[name="classId"]')?.selectedOptions?.[0];
                if (type === 'class' && !global.confirm(`Cấp thành tích cho toàn bộ ${classOption?.textContent?.trim() || 'lớp đã chọn'}?`)) {
                    event.preventDefault();
                    return;
                }
                try { global.sessionStorage.setItem('talenthub.credential.tab', 'history'); } catch {}
            });
            syncCredential();
            syncRecipient();
        }

        root.querySelectorAll('[data-award-mode]').forEach((mode) => {
            const form = mode.closest('form');
            const fields = form?.querySelector('[data-auto-fields]');
            const syncMode = () => {
                const automatic = mode.value === 'automatic';
                if (fields) fields.hidden = !automatic;
                fields?.querySelectorAll('select, input').forEach((input) => { input.disabled = !automatic; });
            };
            mode.addEventListener('change', syncMode);
            syncMode();
        });

        root.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!global.confirm(form.dataset.confirm || 'Xác nhận thao tác này?')) event.preventDefault();
            });
        });

        root.querySelectorAll('.credential-form--catalog').forEach((form) => {
            form.addEventListener('submit', () => {
                try { global.sessionStorage.setItem('talenthub.credential.tab', 'catalog'); } catch {}
            });
        });
    });
})(typeof window !== 'undefined' ? window : globalThis);
