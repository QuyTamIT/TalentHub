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
        card.addEventListener('click', () => {
            selectCard(card);
        });
    });
}

function selectCard(selectedCard) {
    document.querySelectorAll('.role-card').forEach(card => {
        card.classList.remove('is-selected');
    });
    selectedCard.classList.add('is-selected');
}
