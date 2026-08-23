/**
 * TalentHub Learner Badges: Filter and dynamic view for badges and progression.
 */
(function () {
    'use strict';

    function initBadges() {
        const filterButtons = document.querySelectorAll('[data-badge-filter]');
        const badgeCards = document.querySelectorAll('[data-badge-card]');
        const emptyState = document.querySelector('[data-badge-empty]');
        const resultStatus = document.querySelector('[data-badge-result-status]');

        if (!filterButtons.length || !badgeCards.length) return;

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                const filter = button.getAttribute('data-badge-filter') || 'all';

                filterButtons.forEach(btn => {
                    btn.setAttribute('aria-pressed', btn === button ? 'true' : 'false');
                });

                let visibleCount = 0;
                badgeCards.forEach(card => {
                    const status = card.getAttribute('data-badge-status');
                    const matches = (filter === 'all') || (filter === status);

                    if (matches) {
                        card.removeAttribute('hidden');
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.setAttribute('hidden', '');
                        card.style.display = 'none';
                    }
                });

                if (emptyState) {
                    if (visibleCount === 0) {
                        emptyState.removeAttribute('hidden');
                    } else {
                        emptyState.setAttribute('hidden', '');
                    }
                }

                if (resultStatus) {
                    resultStatus.textContent = `${visibleCount} huy hiệu phù hợp`;
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBadges);
    } else {
        initBadges();
    }
})();
