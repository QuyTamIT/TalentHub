/**
 * TalentHub - Role Selection Page Scripts
 * Handles role card interactions, keyboard navigation, active state selection,
 * and graceful route fallback notices for pending backend modules.
 * 
 * Note for Junior Developers:
 * - When target modules (/app/learner, /app/teacher, /app/school, /app/enterprise) are ready,
 *   update handleRoleNavigation to navigate directly using window.location.href = route.
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
 * Handles navigation to destination module or displays fallback feedback if module isn't created yet.
 */
function handleRoleNavigation(route, roleName) {
    // Check if target page exists in future implementation
    // Currently, backend modules /app/* are pending future tasks
    showRoleToast(`Khu vực ${roleName} đang được phát triển! (${route})`);
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
