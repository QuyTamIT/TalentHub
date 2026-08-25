(function initLearnerOnboarding(global) {
    'use strict';

    function safeOnboardingDestination(value) {
        return typeof value === 'string'
            && value.startsWith('/app/learner/')
            && !value.startsWith('//')
            ? value
            : null;
    }

    function containDialogFocus(dialog, event) {
        if (!dialog || event?.key !== 'Tab') return;
        const focusable = Array.from(dialog.querySelectorAll(
            'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ));
        if (focusable.length === 0) {
            event.preventDefault();
            dialog.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = dialog.ownerDocument?.activeElement;
        if (event.shiftKey && (active === first || !focusable.includes(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !focusable.includes(active))) {
            event.preventDefault();
            first.focus();
        }
    }

    function suppressEscape(event) {
        if (event?.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
    }

    function boot(doc = global.document) {
        const root = doc?.querySelector?.('[data-onboarding-dialog]');
        const dialog = root?.querySelector?.('[role="dialog"]');
        if (!dialog) return;

        dialog.addEventListener('keydown', (event) => {
            suppressEscape(event);
            containDialogFocus(dialog, event);
        });
        global.requestAnimationFrame
            ? global.requestAnimationFrame(() => dialog.focus())
            : dialog.focus();
    }

    const exported = { safeOnboardingDestination, containDialogFocus, suppressEscape, boot };
    if (typeof module !== 'undefined' && module.exports) module.exports = exported;
    global.TalentHubLearnerOnboarding = exported;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => boot());
        } else {
            boot();
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
