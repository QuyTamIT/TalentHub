/**
 * TalentHub — School activity review (app/school/activities.php).
 * - Prevents a no-op submit of "Yêu cầu chỉnh sửa" / "Từ chối" without a reason.
 * - Live character counter (0 / 1.000 ký tự).
 * - Adds an inline animated error banner when reason is required; focuses and pulses the textarea.
 * - Clears error on edit.
 */
(() => {
  'use strict';

  const FORMS = 'form[data-activity-review]';

  function findReason(form) {
    return form.querySelector('textarea[name="reason"]');
  }

  function findCounter(form) {
    return form.querySelector('[data-char-counter]');
  }

  function updateCounter(textarea, counter) {
    if (!counter) return;
    const current = textarea.value.length;
    const max = textarea.getAttribute('maxlength') || '1000';
    counter.textContent = `${current.toLocaleString('vi-VN')} / ${Number(max).toLocaleString('vi-VN')} ký tự`;
  }

  function showHint(area, message) {
    let note = area.querySelector('.school-activity-review__field-error');
    if (!note) {
      note = document.createElement('div');
      note.className = 'school-activity-review__field-error';
      note.setAttribute('role', 'alert');
      const actions = area.querySelector('.school-act-actions') || area.querySelector('.school-form__actions');
      if (actions) {
        area.insertBefore(note, actions);
      } else {
        area.appendChild(note);
      }
    }
    note.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span>${message}</span>
    `;
  }

  function clearHint(area) {
    const note = area.querySelector('.school-activity-review__field-error');
    if (note) note.remove();
  }

  function wireForm(form) {
    const reason = findReason(form);
    if (!reason) return;
    const counter = findCounter(form);
    
    // Initial counter setup
    updateCounter(reason, counter);

    const buttons = new Map();
    for (const b of form.querySelectorAll('button[name="action"][value]')) {
      buttons.set(b.value, b);
    }
    let currentDecision = null;
    for (const [value, button] of buttons) {
      button.addEventListener('click', () => {
        currentDecision = value;
        const hiddenAction = form.querySelector('input[type="hidden"][name="action"]');
        if (hiddenAction) {
          hiddenAction.value = value;
        }
      });
    }

    reason.addEventListener('input', () => {
      updateCounter(reason, counter);
      if (reason.value.trim() !== '') {
        clearHint(form);
        reason.style.borderColor = '';
      }
    });

    form.addEventListener('submit', (event) => {
      if (currentDecision === null) {
        // Fallback: infer from submitter when available
        const submitter = (event.submitter && event.submitter.name === 'action') ? event.submitter : null;
        currentDecision = submitter ? String(submitter.value || '') : null;
      }
      if (!currentDecision) {
        const hiddenAction = form.querySelector('input[type="hidden"][name="action"]');
        if (hiddenAction && hiddenAction.value) {
          currentDecision = hiddenAction.value;
        }
      }

      const hiddenAction = form.querySelector('input[type="hidden"][name="action"]');
      if (hiddenAction && currentDecision) {
        hiddenAction.value = currentDecision;
      }

      if (currentDecision !== 'request_changes' && currentDecision !== 'reject') {
        clearHint(form);
        if (currentDecision === 'approve') {
          const approveBtn = buttons.get('approve');
          if (approveBtn) {
            approveBtn.classList.add('is-loading');
            approveBtn.innerHTML = `
              <svg class="school-act-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25" stroke="currentColor"></circle>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor"></path>
              </svg>
              <span>Đang phê duyệt...</span>
            `;
            setTimeout(() => {
              if (approveBtn) approveBtn.disabled = true;
            }, 0);
          }
        }
        return;
      }
      if (reason.value.trim() === '') {
        event.preventDefault();
        const text = currentDecision === 'request_changes'
          ? 'Vui lòng nhập nội dung cần chỉnh sửa để Giảng viên có thể cập nhật hoạt động.'
          : 'Vui lòng nêu rõ lý do từ chối để thông báo đến Giảng viên.';
        showHint(form, text);
        reason.focus();
        reason.style.borderColor = '#EF4444';
        // Reset so repeated submits re-evaluate the pressed button.
        currentDecision = null;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    for (const form of document.querySelectorAll(FORMS)) {
      wireForm(form);
    }
  });
})();