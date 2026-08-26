document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-activity-form]');
    if (!form) return;

    form.classList.add('is-enhanced');

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

    form.addEventListener('invalid', (event) => {
        const control = event.target;
        if (!(control instanceof HTMLElement)) return;
        const disclosure = control.closest('details');
        if (disclosure instanceof HTMLDetailsElement) disclosure.open = true;
        window.setTimeout(() => control.focus(), 0);
    }, true);
});
