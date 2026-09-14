/* Progressive enhancement: forms and links remain usable without JavaScript. */
(() => {
    const workspace = document.querySelector('[data-grading-workspace]');
    if (!workspace) return;
    const links = [...workspace.querySelectorAll('[data-grading-target]')];
    const cards = [...workspace.querySelectorAll('.teacher-grading-card')];
    const select = (id, focus = false) => {
        const card = cards.find(item => item.id === id);
        if (!card) return;
        cards.forEach(item => { item.hidden = item !== card; });
        links.forEach(link => {
            const active = link.dataset.gradingTarget === id;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
        if (focus) card.focus({ preventScroll: true });
    };
    links.forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        select(link.dataset.gradingTarget, true);
        history.replaceState(null, '', link.hash);
    }));
    // Retain the current learner after validation errors or a saved assessment.
    const restored = cards.find(card => '#' + card.id === location.hash);
    const invalid = cards.find(card => card.querySelector('[aria-invalid="true"]'));
    select((restored || invalid || cards[0])?.id);
    workspace.querySelectorAll('.teacher-grading-criteria input[type="number"]').forEach(input => {
        const label = input.closest('label');
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.min = input.min;
        slider.max = input.max;
        slider.step = input.step;
        slider.className = 'teacher-criterion-slider';
        slider.setAttribute('aria-label', label.querySelector('.teacher-grading-field__label').textContent.trim());
        const sync = () => {
            slider.value = input.value === '' ? input.min : input.value;
            slider.setAttribute('aria-valuetext', input.value === '' ? 'Chưa chấm' : `${input.value} / ${input.max}`);
            const percent = (Number(slider.value) - Number(input.min)) / (Number(input.max) - Number(input.min)) * 100;
            slider.style.setProperty('--score-progress', `${percent}%`);
        };
        slider.addEventListener('input', () => {
            input.value = slider.value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        input.addEventListener('input', sync);
        input.insertAdjacentElement('afterend', slider);
        sync();
    });
    workspace.querySelectorAll('.teacher-grading-skills').forEach(fieldset => {
        const details = document.createElement('details');
        details.className = 'teacher-extra-skills';
        const summary = document.createElement('summary');
        summary.textContent = 'Đánh giá kỹ năng bổ sung';
        fieldset.before(details);
        details.append(summary, fieldset);
        // Reveal a field if native validation needs the user's attention.
        details.addEventListener('invalid', () => { details.open = true; }, true);
    });
})();
