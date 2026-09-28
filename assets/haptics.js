import { WebHaptics } from 'web-haptics';

const haptics = new WebHaptics();

document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-haptic]');
    if (!el) return;
    haptics.trigger(el.dataset.haptic || 'medium');
});
