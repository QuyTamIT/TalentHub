/**
 * TalentHub - Role Selection Page Scripts
 * Handles role card interactions and keyboard navigation for registration.
 */

document.addEventListener('DOMContentLoaded', () => {
    initRoleCards();
});

function initRoleCards() {
    const roleCards = document.querySelectorAll('.role-card');

    roleCards.forEach(card => {
        // Entire card click handler
        card.addEventListener('click', (event) => {
            event.preventDefault();
            selectCard(card);
            const route = card.getAttribute('data-route');
            const roleName = card.getAttribute('data-role-name') || 'này';
            handleRoleNavigation(route, roleName);
        });

        // Keyboard navigation (Enter or Space key)
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectCard(card);
                const route = card.getAttribute('data-route');
                const roleName = card.getAttribute('data-role-name') || 'này';
                handleRoleNavigation(route, roleName);
            }
        });

        // CTA button click handler
        const ctaBtn = card.querySelector('.role-card__cta');
        if (ctaBtn) {
            ctaBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                selectCard(card);
                const route = card.getAttribute('data-route');
                const roleName = card.getAttribute('data-role-name') || 'này';
                handleRoleNavigation(route, roleName);
            });
        }
    });
}

function selectCard(selectedCard) {
    document.querySelectorAll('.role-card').forEach(card => {
        card.classList.remove('is-selected');
    });
    selectedCard.classList.add('is-selected');
}

/**
 * Handles navigation to the selected registration form or displays fallback feedback.
 */
function handleRoleNavigation(route, roleName) {
    if (!route) {
        showRoleToast(`Khu vực ${roleName} đang được phát triển!`);
        return;
    }
    window.location.href = route;
}

/**
 * Displays a non-disruptive feedback toast message.
 */
let toastTimeout = null;

function showRoleToast(message) {
    const toast = document.getElementById('role-toast');
    if (!toast) return;

    const messageEl = toast.querySelector('.role-toast__message');
    if (messageEl) {
        messageEl.textContent = message;
    }

    toast.classList.add('is-visible');

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastTimeout = setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 4000);
}
