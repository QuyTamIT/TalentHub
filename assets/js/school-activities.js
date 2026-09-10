/**
 * TalentHub — School activity review (app/school/activities.php).
 * - Prevents a no-op submit of "Yêu cầu chỉnh sửa" / "Từ chối" without a reason.
 * - Adds an inline message when reason is required; focuses the textarea.
 * - Clear the inline message on edit.
 */
(() => {
  'use strict';

  const FORMS = 'form[data-activity-review]';

  function findReason(form) {
    return form.querySelector('textarea[name="reason"]');
  }

  function showHint(area, message) {
    let note = area.querySelector('.school-activity-review__field-error');
    if (!note) {
      note = document.createElement('p');
      note.className = 'school-activity-review__field-error';
      note.setAttribute('role', 'alert');
      const actions = area.querySelector('.school-form__actions');
      area.insertBefore(note, actions || null);
    }
    note.textContent = message;
  }

  function clearHint(area) {
    const note = area.querySelector('.school-activity-review__field-error');
    if (note) note.remove();
  }

  function wireForm(form) {
    const reason = findReason(form);
    if (!reason) return;
    const buttons = new Map();
    for (const b of form.querySelectorAll('button[name="action"][value]')) {
      buttons.set(b.value, b);
    }
    let currentDecision = null;
    for (const [value, button] of buttons) {
      button.addEventListener('click', () => { currentDecision = value; });
    }
    reason.addEventListener('input', () => {
      if (reason.value.trim() !== '') clearHint(form);
    });
    form.addEventListener('submit', (event) => {
      if (currentDecision === null) {
        // Fallback: infer from submitter when available (Chromium/Firefox).
        const submitter = (event.submitter && event.submitter.name === 'action') ? event.submitter : null;
        currentDecision = submitter ? String(submitter.value || '') : null;
      }
      if (currentDecision !== 'request_changes' && currentDecision !== 'reject') {
        clearHint(form);
        return;
      }
      if (reason.value.trim() === '') {
        event.preventDefault();
        const text = currentDecision === 'request_changes'
          ? 'Vui lòng nhập lý do khi yêu cầu Giáo viên chỉnh sửa hoạt động.'
          : 'Vui lòng nhập lý do từ chối hoạt động.';
        showHint(form, text);
        reason.focus();
        // Reset so repeated submits re-evaluate the pressed button.
        currentDecision = null;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    for (const form of document.querySelectorAll(FORMS)) wireForm(form);
  });
})();